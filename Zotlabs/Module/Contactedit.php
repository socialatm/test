<?php

namespace Zotlabs\Module;

/* @file Cobtactedit.php
 * @brief In this file the connection-editor form is generated and evaluated.
 *
 *
 */

use App;
use Sabre\VObject\Reader;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Libsync;
use Zotlabs\Daemon\Master;
use Zotlabs\Web\Controller;
use Zotlabs\Access\Permissions;
use Zotlabs\Access\PermissionLimits;
use Zotlabs\Web\HTTPHeaders;
use Zotlabs\Lib\Permcat;
use Zotlabs\Lib\AccessList;

require_once('include/socgraph.php');
require_once('include/selectors.php');
require_once('include/group.php');
require_once('include/photos.php');

class Contactedit extends Controller {

	/* @brief Initialize the connection-editor
	 *
	 *
	 */

	function init() {

		if (!local_channel())
			return;

		if ((argc() >= 2) && intval(argv(1))) {
			$r = q("SELECT abook.*, xchan.* FROM abook LEFT JOIN xchan ON abook_xchan = xchan_hash
				WHERE abook_channel = %d AND abook_id = %d AND abook_self = 0 AND xchan_deleted = 0",
				intval(local_channel()),
				intval(argv(1))
			);
			if (!$r) {
				json_return_and_die([
					'success' => false,
					'message' => t('Invalid abook_id')
				]);
			}

			App::$poi = $r[0];

		}
	}


	/* @brief Evaluate posted values and set changes
	 *
	 */

	function post() {

		if (!local_channel())
			return;

		$contact_id = intval(argv(1));
		if (!$contact_id)
			return;

		$channel = App::get_channel();

		$contact = App::$poi;

		if (!$contact) {
			notice(t('Could not access contact record.') . EOL);
			killme();
		}

		call_hooks('contact_edit_post', $_REQUEST);

		if (Apps::system_app_installed(local_channel(), 'Privacy Groups')) {
			$pgrp_ids = q("SELECT id FROM pgrp WHERE deleted = 0 AND uid = %d",
				intval(local_channel())
			);

			foreach($pgrp_ids as $pgrp) {
				if (array_key_exists('pgrp_id_' . $pgrp['id'], $_REQUEST)) {
					AccessList::member_add(local_channel(), '', $contact['abook_xchan'], $pgrp['id']);
				}
				else {
					AccessList::member_remove(local_channel(), '', $contact['abook_xchan'], $pgrp['id']);
				}
			}
		}

		$profile_id = ((array_key_exists('profile_assign', $_REQUEST)) ? $_REQUEST['profile_assign'] : $contact['abook_profile']);

		if ($profile_id) {
			$r = q("SELECT profile_guid FROM profile WHERE profile_guid = '%s' AND uid = %d LIMIT 1",
				dbesc($profile_id),
				intval(local_channel())
			);
			if (!count($r)) {
				notice(t('Could not locate selected profile.') . EOL);
				return;
			}
		}

		$abook_incl = ((array_key_exists('abook_incl', $_REQUEST)) ? escape_tags($_REQUEST['abook_incl']) : $contact['abook_incl']);
		$abook_excl = ((array_key_exists('abook_excl', $_REQUEST)) ? escape_tags($_REQUEST['abook_excl']) : $contact['abook_excl']);
		$abook_role = ((array_key_exists('permcat', $_REQUEST)) ? escape_tags($_REQUEST['permcat']) : $contact['abook_role']);

		if (!array_key_exists('closeness', $_REQUEST)) {
			$_REQUEST['closeness'] = 80;
		}

		$closeness = intval($_REQUEST['closeness']);

		if ($closeness < 0 || $closeness > 99) {
			$closeness = 80;
		}

		$new_friend = ((intval($contact['abook_pending'])) ? true : false);

		\Zotlabs\Lib\Permcat::assign($channel, $abook_role, [$contact['abook_xchan']]);

		$abook_pending = (($new_friend) ? 0 : $contact['abook_pending']);

		$r = q("UPDATE abook SET abook_profile = '%s', abook_closeness = %d, abook_pending = %d,
			abook_incl = '%s', abook_excl = '%s'
			where abook_id = %d AND abook_channel = %d",
			dbesc($profile_id),
			intval($closeness),
			intval($abook_pending),
			dbesc($abook_incl),
			dbesc($abook_excl),
			intval($contact_id),
			intval(local_channel())
		);

		$_REQUEST['success'] = false;

		if ($r) {
			$_REQUEST['success'] = true;
		}


		if (!intval($contact['abook_self'])) {
			if ($new_friend) {
				Master::Summon(['Notifier', 'permission_accept', $contact_id]);
			}

			Master::Summon([
				'Notifier',
				(($new_friend) ? 'permission_create' : 'permission_update'),
				$contact_id
			]);
		}

		if ($new_friend) {
			$default_group = $channel['channel_default_group'];
			if ($default_group) {
				$g = AccessList::by_hash(local_channel(), $default_group);
				if ($g) {
					AccessList::member_add(local_channel(), '', $contact['abook_xchan'], $g['id']);
				}
			}

			// Check if settings permit ("post new friend activity" is allowed, and
			// friends in general or this friend in particular aren't hidden)
			// and send out a new friend activity

			$pr = q("select * from profile where uid = %d and is_default = 1 and hide_friends = 0",
				intval($channel['channel_id'])
			);
			if (($pr) && (!intval($contact['abook_hidden'])) && (intval(get_pconfig($channel['channel_id'], 'system', 'post_newfriend')))) {
				$xarr = [];

				$xarr['item_wall']       = 1;
				$xarr['item_origin']     = 1;
				$xarr['item_thread_top'] = 1;
				$xarr['owner_xchan']     = $xarr['author_xchan'] = $channel['channel_hash'];
				$xarr['allow_cid']       = $channel['channel_allow_cid'];
				$xarr['allow_gid']       = $channel['channel_allow_gid'];
				$xarr['deny_cid']        = $channel['channel_deny_cid'];
				$xarr['deny_gid']        = $channel['channel_deny_gid'];
				$xarr['item_private']    = (($xarr['allow_cid'] || $xarr['allow_gid'] || $xarr['deny_cid'] || $xarr['deny_gid']) ? 1 : 0);

				$xarr['body'] = '[zrl=' . $channel['xchan_url'] . ']' . $channel['xchan_name'] . '[/zrl]' . ' ' . t('is now connected to') . ' ' . '[zrl=' . $contact['xchan_url'] . ']' . $contact['xchan_name'] . '[/zrl]';

				$xarr['body'] .= "\n\n\n" . '[zrl=' . $contact['xchan_url'] . '][zmg=80x80]' . $contact['xchan_photo_m'] . '[/zmg][/zrl]';

				post_activity_item($xarr);

			}

			// pull in a bit of content if there is any to pull in
			Master::Summon(['Onepoll', $contact_id]);

		}

		// Refresh the structure in memory with the new data
		$this->init();

		if ($new_friend) {
			$arr = ['channel_id' => local_channel(), 'abook' => App::$poi];
			call_hooks('accept_follow', $arr);
		}

		$this->contactedit_clone();
		$this->get();

		killme();

		return;

	}


	/* @brief Generate content of contact edit page
	 *
	 *
	 */

	function get() {

		if (!local_channel()) {
			killme();
		}

		if (!App::$poi) {
			killme();
		}


		$channel = App::get_channel();
		$contact_id = App::$poi['abook_id'];
		$contact    = App::$poi;
		$section = ((array_key_exists('section', $_REQUEST)) ? $_REQUEST['section'] : 'roles');
		$sub_section = ((array_key_exists('sub_section', $_REQUEST)) ? $_REQUEST['sub_section'] : '');


		if (argc() == 3) {
			$cmd = argv(2);
			$ret = $this->do_action($contact, $cmd);
			$contact = App::$poi;

			$tools_html =  replace_macros(get_markup_template("contact_edit_tools.tpl"), [
				'$tools_label'      => t('Contact Tools'),
				'$tools'            => $this->get_tools($contact),
			]);

			$ret['tools'] = $tools_html;

			json_return_and_die($ret);
		}

		$groups = [];

		if (Apps::system_app_installed(local_channel(), 'Privacy Groups')) {

			$r = q("SELECT * FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
				intval(local_channel())
			);

			$member_of = AccessList::containing(local_channel(), $contact['xchan_hash']);

			if ($r) {
				foreach ($r as $rr) {
					$default_group = false;
					if ($rr['hash'] === $channel['channel_default_group']) {
						$default_group = true;
					}

					$groups[] = [
						'pgrp_id_' . $rr['id'],
						$rr['gname'],
						// if it's a new contact preset the default group if we have one
						(($default_group && $contact['abook_pending']) ? 1 : in_array($rr['id'], $member_of)),
						'',
						[t('No'), t('Yes')]
					];
				}
			}
		}

		$slide = '';

		if (Apps::system_app_installed(local_channel(), 'Affinity Tool')) {

			$labels = [
				t('Me'),
				t('Family'),
				t('Friends'),
				t('Acquaintances'),
				t('All')
			];
			call_hooks('affinity_labels', $labels);
			$label_str = '';

			if ($labels) {
				foreach ($labels as $l) {
					if ($label_str) {
						$label_str .= ", '|'";
						$label_str .= ", '" . $l . "'";
					}
					else
						$label_str .= "'" . $l . "'";
				}
			}

			$slider_tpl = get_markup_template('contact_slider.tpl');

			$slideval = intval($contact['abook_closeness']);

			$slide = replace_macros($slider_tpl, [
				'$min'    => 1,
				'$val'    => $slideval,
				'$labels' => $label_str,
			]);
		}

		$perms        = [];
		$global_perms = Permissions::Perms();
		$existing     = get_all_perms(local_channel(), $contact['abook_xchan'], false);
		$unapproved   = ['pending', t('Approve this contact'), '', t('Accept contact to allow communication'), [t('No'), ('Yes')]];
		$multiprofs   = ((feature_enabled(local_channel(), 'multi_profiles')) ? true : false);

		$theirs = q("select * from abconfig where chan = %d and xchan = '%s' and cat = 'their_perms'",
			intval(local_channel()),
			dbesc($contact['abook_xchan'])
		);

		$their_perms = [];
		if ($theirs) {
			foreach ($theirs as $t) {
				$their_perms[$t['k']] = $t['v'];
			}
		}

		foreach ($global_perms as $k => $v) {
			$thisperm       = $existing[$k];
			$checkinherited = PermissionLimits::Get(local_channel(), $k);
			$perms[]        = ['perms_' . $k, $v, ((array_key_exists($k, $their_perms)) ? intval($their_perms[$k]) : ''), $thisperm, 1, (($checkinherited & PERMS_SPECIFIC) ? '0' : '1'), '', $checkinherited];
		}

		$pcat            = new Permcat(local_channel());
		$pcatlist        = $pcat->listing();
		$default_role    = get_pconfig(local_channel(), 'system', 'default_permcat');
		$current_permcat = (($contact['abook_pending']) ? $default_role : $contact['abook_role']);

		$roles_dict = [];
		foreach ($pcatlist as $role) {
			$roles_dict[$role['name']] = $role['localname'];
		}


		if (!$current_permcat) {
			notice(t('Please select a role for this contact!') . EOL);
			$permcats[] = '';
		}

		if ($pcatlist) {
			foreach ($pcatlist as $pc) {
				$permcats[$pc['name']] = $pc['localname'];
			}
		}

		$locstr = locations_by_netid($contact['xchan_hash']);
		if (!$locstr) {
			$locstr = unpunify($contact['xchan_url']);
		}

		$clone_warn = '';
		$clonable   = in_array($contact['xchan_network'], ['zot6', 'rss']);
		if (!$clonable) {
			$clone_warn = '<strong>';
			$clone_warn .= ((intval($contact['abook_not_here']))
				? t('This contact is unreachable from this location.')
				: t('This contact may be unreachable from other channel locations.')
			);
			$clone_warn .= '</strong><br>' . t('Location independence is not supported by their network.');
		}

		$header_card = '<img src="' . $contact['xchan_photo_s'] . '" class="rounded" style="width: 3rem; height: 3rem;">&nbsp; ' . $contact['xchan_name'];

		$header_html =  replace_macros(get_markup_template("contact_edit_header.tpl"), [
			'$img_src' => $contact['xchan_photo_s'],
			'$name' => $contact['xchan_name'],
			'$addr' => (($contact['xchan_addr']) ? $contact['xchan_addr'] : $contact['xchan_url']),
			'$href' => ((is_matrix_url($contact['xchan_url'])) ? zid($contact['xchan_url']) : $contact['xchan_url']),
			'$link_label' => t('View profile'),
			'$is_group' => $contact['xchan_pubforum'],
			'$group_label' => t('This is a group/forum channel')
		]);

		$tools_html =  replace_macros(get_markup_template("contact_edit_tools.tpl"), [
			'$tools_label'      => t('Contact Tools'),
			'$tools'            => $this->get_tools($contact),
		]);

		$tpl = get_markup_template("contact_edit.tpl");

		$o = replace_macros($tpl, [
			'$permcat'          => ['permcat', t('Select a role for this contact'), $current_permcat, '', $permcats],
			'$permcat_new'      => t('Contact roles'),
			'$permcat_value'    => bin2hex($current_permcat),
//			'$addr'             => unpunify($contact['xchan_addr']),
//			'$primeurl'         => unpunify($contact['xchan_url']),
			'$section'          => $section,
			'$sub_section'      => $sub_section,
			'$groups'           => $groups,
//			'$addr_text'        => t('This contacts\'s primary address is'),
//			'$loc_text'         => t('Available locations:'),
//			'$locstr'           => $locstr,
//			'$unclonable'       => $clone_warn,
			'$lbl_slider'       => t('Slide to adjust your degree of friendship'),
			'$connfilter'       => feature_enabled(local_channel(), 'connfilter'),
			'$connfilter_label' => t('Custom Filter'),
			'$incl'             => ['abook_incl', t('Only import posts with this text'), $contact['abook_incl'], t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')],
			'$excl'             => ['abook_excl', t('Do not import posts with this text'), $contact['abook_excl'], t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')],
			'$slide'            => $slide,
//			'$pending_label'    => t('Contact Pending Approval'),
//			'$is_pending'       => (intval($contact['abook_pending']) ? 1 : ''),
//			'$unapproved'       => $unapproved,
			'$submit'           => ((intval($contact['abook_pending'])) ? t('Approve contact') : t('Submit')),
			'$close'            => (($contact['abook_closeness']) ? $contact['abook_closeness'] : 80),
			'$them'             => t('Their'),
			'$me'               => t('My'),
			'$perms'            => $perms,
//			'$lastupdtext'      => t('Last update:'),
//			'$last_update'      => relative_date($contact['abook_connected']),
			'$profile_select'   => contact_profile_assign($contact['abook_profile']),
			'$multiprofs'       => $multiprofs,
			'$contact_id'       => $contact['abook_id'],
//			'$name'             => $contact['xchan_name'],
			'$roles_label'	    => t('Roles'),
			'$compare_label'    => t('Compare permissions'),
			'$permission_label' => t('Permission'),
			'$pgroups_label'    => t('Privacy groups'),
			'$profiles_label'   => t('Profiles'),
			'$affinity_label'   => t('Affinity'),
			'$filter_label'	    => t('Content filter')
		]);

		$arr = ['contact' => $contact, 'output' => $o];

		call_hooks('contact_edit', $arr);

		if (is_ajax()) {
			json_return_and_die([
				'success' => ((intval($_REQUEST['success'])) ? intval($_REQUEST['success']) : 1),
				'message' => (($_REQUEST['success']) ? t('Contact updated') : t('Contact update failed')),
				'id' => $contact_id,
				'title' => $header_html,
				'role' => ((intval($contact['abook_pending'])) ? '' : $roles_dict[$current_permcat]),
				'body' => $arr['output'],
				'tools' => $tools_html,
				'submit' => ((intval($contact['abook_pending'])) ? t('Approve connection') : t('Submit')),
				'pending' => intval($contact['abook_pending'])
			]);
		}

		return $arr['output'];

	}

	function contactedit_clone() {

		if (!App::$poi)
			return;

		$channel = App::get_channel();

		$clone = App::$poi;

		unset($clone['abook_id']);
		unset($clone['abook_account']);
		unset($clone['abook_channel']);

		$abconfig = load_abconfig($channel['channel_id'], $clone['abook_xchan']);
		if ($abconfig)
			$clone['abconfig'] = $abconfig;

		Libsync::build_sync_packet(0 /* use the current local_channel */, ['abook' => [$clone]]);
	}

	function do_action($contact, $cmd) {
		$ret = [
			'sucess' => false,
			'message' => ''
		];

		if ($cmd === 'resetphoto') {
			q("update xchan set xchan_photo_date = '2001-01-01 00:00:00' where xchan_hash = '%s'",
				dbesc($contact['xchan_hash'])
			);
			$cmd = 'refresh';
		}

		if ($cmd === 'refresh') {
			if ($contact['xchan_network'] === 'zot6') {
				if (Libzot::refresh($contact, App::get_channel())) {
					$ret['success'] = true;
					$ret['message'] = t('Refresh succeeded');
				}
				else {
					$ret['message'] = t('Refresh failed - channel is currently unavailable');
				}
			}
			else {
				// if you are on a different network we'll force a refresh of the connection basic info
				Master::Summon(['Notifier', 'permission_update', $contact['abook_id']]);
				$ret['success'] = true;
				$ret['message'] = t('Refresh succeeded');
			}

			return $ret;
		}

		if ($cmd === 'block') {
			if (abook_toggle_flag($contact, ABOOK_FLAG_BLOCKED)) {
				$this->init(); // refresh data

				$this->contactedit_clone();
				$ret['success'] = true;
				$ret['message'] = t('Block status updated');
			}
			else {
				$ret['success'] = false;
				$ret['message'] = t('Block failed');
			}
			return $ret;
		}

		if ($cmd === 'ignore') {
			if (abook_toggle_flag($contact, ABOOK_FLAG_IGNORED)) {
				$this->init(); // refresh data

				$this->contactedit_clone();
				$ret['success'] = true;
				$ret['message'] = t('Ignore status updated');
			}
			else {
				$ret['success'] = false;
				$ret['message'] = t('Ignore failed');
			}
			return $ret;
		}

		if ($cmd === 'archive') {
			if (abook_toggle_flag($contact, ABOOK_FLAG_ARCHIVED)) {
				$this->init(); // refresh data

				$this->contactedit_clone();
				$ret['success'] = true;
				$ret['message'] = t('Archive status updated');
			}
			else {
				$ret['success'] = false;
				$ret['message'] = t('Archive failed');
			}
			return $ret;
		}

		if ($cmd === 'hide') {
			if (abook_toggle_flag($contact, ABOOK_FLAG_HIDDEN)) {
				$this->init(); // refresh data

				$this->contactedit_clone();
				$ret['success'] = true;
				$ret['message'] = t('Hide status updated');
			}
			else {
				$ret['success'] = false;
				$ret['message'] = t('Hide failed');
			}
			return $ret;
		}

		// We'll prevent somebody from unapproving an already approved contact.
		// Though maybe somebody will want this eventually (??)

		//if ($cmd === 'approve') {
			//if (intval($contact['abook_pending'])) {
				//if (abook_toggle_flag($contact, ABOOK_FLAG_PENDING)) {
					//$this->contactedit_clone();
				//}
				//else
					//notice(t('Unable to set address book parameters.') . EOL);
			//}
			//goaway(z_root() . '/connedit/' . $contact_id);
		//}


		if ($cmd === 'drop') {

			if (contact_remove(local_channel(), $contact['abook_id'])) {

				Master::Summon(['Notifier', 'purge', local_channel(), $contact['xchan_hash']]);
				Libsync::build_sync_packet(0 /* use the current local_channel */,
					['abook' => [
						[
							'abook_xchan'   => $contact['abook_xchan'],
							'entry_deleted' => true
						]
					]
				]);

				$ret['success'] = true;
				$ret['message'] = t('Contact removed');
			}
			else {
				$ret['success'] = false;
				$ret['message'] = t('Delete failed');
			}
			return $ret;
		}
	}

	function get_tools($contact) {
		return [

			'refresh' => [
				'label' => t('Refresh Permissions'),
				'title' => t('Fetch updated permissions'),
			],

			'rephoto' => [
				'label' => t('Refresh Photo'),
				'title' => t('Fetch updated photo'),
			],


			'block' => [
				'label' => (intval($contact['abook_blocked']) ? t('Unblock') : t('Block')),
				'sel'   => (intval($contact['abook_blocked']) ? 'active' : ''),
				'title' => t('Block (or Unblock) all communications with this connection'),
				'info'  => (intval($contact['abook_blocked']) ? t('This connection is blocked!') : ''),
			],

			'ignore' => [
				'label' => (intval($contact['abook_ignored']) ? t('Unignore') : t('Ignore')),
				'sel'   => (intval($contact['abook_ignored']) ? 'active' : ''),
				'title' => t('Ignore (or Unignore) all inbound communications from this connection'),
				'info'  => (intval($contact['abook_ignored']) ? t('This connection is ignored!') : ''),
			],

			'archive' => [
				'label' => (intval($contact['abook_archived']) ? t('Unarchive') : t('Archive')),
				'sel'   => (intval($contact['abook_archived']) ? 'active' : ''),
				'title' => t('Archive (or Unarchive) this connection - mark channel dead but keep content'),
				'info'  => (intval($contact['abook_archived']) ? t('This connection is archived!') : ''),
			],

			'hide' => [
				'label' => (intval($contact['abook_hidden']) ? t('Unhide') : t('Hide')),
				'sel'   => (intval($contact['abook_hidden']) ? 'active' : ''),
				'title' => t('Hide or Unhide this connection from your other connections'),
				'info'  => (intval($contact['abook_hidden']) ? t('This connection is hidden!') : ''),
			],

			'delete' => [
				'label' => t('Delete'),
				'sel'   => '',
				'title' => t('Delete this connection'),
			],

		];
	}

}
