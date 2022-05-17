<?php

namespace Zotlabs\Module;

/* @file connedit.php
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
require_once('include/photos.php');

class Connedit extends Controller {

	/* @brief Initialize the connection-editor
	 *
	 *
	 */

	function init() {

		if (!local_channel())
			return;

		if ((argc() >= 2) && intval(argv(1))) {
			$r = q("SELECT abook.*, xchan.*
				FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_channel = %d and abook_id = %d and abook_self = 0 and xchan_deleted = 0 LIMIT 1",
				intval(local_channel()),
				intval(argv(1))
			);
			if ($r) {
				App::$poi = $r[0];
			}
		}

		$channel = App::get_channel();
		if ($channel) {
			head_set_icon($channel['xchan_photo_s']);
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

		$orig_record = q("SELECT * FROM abook WHERE abook_id = %d AND abook_channel = %d AND abook_self = 0 LIMIT 1",
			intval($contact_id),
			intval(local_channel())
		);

		if (!$orig_record) {
			notice(t('Could not access contact record.') . EOL);
			goaway(z_root() . '/connections');
			return; // NOTREACHED
		}

		call_hooks('contact_edit_post', $_POST);

		$vc               = get_abconfig(local_channel(), $orig_record['abook_xchan'], 'system', 'vcard');
		$vcard            = (($vc) ? Reader::read($vc) : null);
		$serialised_vcard = update_vcard($_REQUEST, $vcard);
		if ($serialised_vcard)
			set_abconfig(local_channel(), $orig_record[0]['abook_xchan'], 'system', 'vcard', $serialised_vcard);

		$profile_id = ((array_key_exists('profile_assign', $_POST)) ? $_POST['profile_assign'] : $orig_record[0]['abook_profile']);

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

		$abook_incl = ((array_key_exists('abook_incl', $_POST)) ? escape_tags($_POST['abook_incl']) : $orig_record[0]['abook_incl']);
		$abook_excl = ((array_key_exists('abook_excl', $_POST)) ? escape_tags($_POST['abook_excl']) : $orig_record[0]['abook_excl']);
		$abook_role = ((array_key_exists('permcat', $_POST)) ? escape_tags($_POST['permcat']) : $orig_record[0]['abook_role']);

		if (!array_key_exists('closeness', $_POST)) {
			$_POST['closeness'] = 80;
		}
		$closeness = intval($_POST['closeness']);
		if ($closeness < 0 || $closeness > 99) {
			$closeness = 80;
		}

		$new_friend = ((intval($orig_record[0]['abook_pending'])) ? true : false);

/*
		$perms      = [];
		$permcats   = new Permcat(local_channel());
		$role_perms = $permcats->fetch($abook_role);
		$all_perms  = Permissions::Perms();

		// if we got a valid role use the role (default behaviour because a role is mandatory since version 7.0)
		if (!isset($role_perms['error'])) {
			$perms = $role_perms['raw_perms'];
			if (intval($orig_record[0]['abook_pending']))
				$new_friend = true;
		}

		// approve shortcut (no role provided)
		if (!$perms && intval($orig_record[0]['abook_pending'])) {
			$connect_perms = Permissions::connect_perms(local_channel());
			$perms         = $connect_perms['perms'];
			// set the role from $connect_perms
			$abook_role = $connect_perms['role'];
			$new_friend = true;
		}

		if ($all_perms && $perms) {
			foreach ($all_perms as $perm => $desc) {
				if (array_key_exists($perm, $perms)) {
					set_abconfig($channel['channel_id'], $orig_record[0]['abook_xchan'], 'my_perms', $perm, intval($perms[$perm]));
				}
				else {
					set_abconfig($channel['channel_id'], $orig_record[0]['abook_xchan'], 'my_perms', $perm, 0);
				}
			}
		}
*/

		\Zotlabs\Lib\Permcat::assign($channel, $abook_role, [$orig_record[0]['abook_xchan']]);

		$abook_pending = (($new_friend) ? 0 : $orig_record[0]['abook_pending']);

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

		if ($r)
			info(t('Connection updated.') . EOL);
		else
			notice(t('Failed to update connection record.') . EOL);

		if (!intval(App::$poi['abook_self'])) {
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
				if ($g)
					AccessList::member_add(local_channel(), '', App::$poi['abook_xchan'], $g['id']);
			}

			// Check if settings permit ("post new friend activity" is allowed, and
			// friends in general or this friend in particular aren't hidden)
			// and send out a new friend activity

			$pr = q("select * from profile where uid = %d and is_default = 1 and hide_friends = 0",
				intval($channel['channel_id'])
			);
			if (($pr) && (!intval($orig_record[0]['abook_hidden'])) && (intval(get_pconfig($channel['channel_id'], 'system', 'post_newfriend')))) {
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

				$xarr['body'] = '[zrl=' . $channel['xchan_url'] . ']' . $channel['xchan_name'] . '[/zrl]' . ' ' . t('is now connected to') . ' ' . '[zrl=' . App::$poi['xchan_url'] . ']' . App::$poi['xchan_name'] . '[/zrl]';

				$xarr['body'] .= "\n\n\n" . '[zrl=' . App::$poi['xchan_url'] . '][zmg=80x80]' . App::$poi['xchan_photo_m'] . '[/zmg][/zrl]';

				post_activity_item($xarr);

			}

			// pull in a bit of content if there is any to pull in
			Master::Summon(['Onepoll', $contact_id]);

		}

		// Refresh the structure in memory with the new data

		$r = q("SELECT abook.*, xchan.*
			FROM abook left join xchan on abook_xchan = xchan_hash
			WHERE abook_channel = %d and abook_id = %d LIMIT 1",
			intval(local_channel()),
			intval($contact_id)
		);
		if ($r) {
			App::$poi = $r[0];
		}

		if ($new_friend) {
			$arr = ['channel_id' => local_channel(), 'abook' => App::$poi];
			call_hooks('accept_follow', $arr);
		}

		$this->connedit_clone();

		if (($_REQUEST['pending']) && (!$_REQUEST['done']))
			goaway(z_root() . '/connections/ifpending');

		return;

	}

	/* @brief Clone connection
	 *
	 *
	 */

	function connedit_clone() {

		if (!App::$poi)
			return;

		$channel = App::get_channel();

		$r = q("SELECT abook.*, xchan.*
				FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_channel = %d and abook_id = %d LIMIT 1",
			intval(local_channel()),
			intval(App::$poi['abook_id'])
		);
		if ($r) {
			App::$poi = $r[0];
		}

		$clone = App::$poi;

		unset($clone['abook_id']);
		unset($clone['abook_account']);
		unset($clone['abook_channel']);

		$abconfig = load_abconfig($channel['channel_id'], $clone['abook_xchan']);
		if ($abconfig)
			$clone['abconfig'] = $abconfig;

		Libsync::build_sync_packet(0 /* use the current local_channel */, ['abook' => [$clone]]);
	}

	/* @brief Generate content of connection edit page
	 *
	 *
	 */

	function get() {

		$o = '';

		if (!local_channel()) {
			notice(t('Permission denied.') . EOL);
			return login();
		}

		$section = ((array_key_exists('section', $_REQUEST)) ? $_REQUEST['section'] : '');

		if (argc() == 3) {

			$contact_id = intval(argv(1));
			if (!$contact_id)
				return;

			$cmd = argv(2);

			$orig_record = q("SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_id = %d AND abook_channel = %d AND abook_self = 0 and xchan_deleted = 0 LIMIT 1",
				intval($contact_id),
				intval(local_channel())
			);

			if (!count($orig_record)) {
				notice(t('Could not access address book record.') . EOL);
				goaway(z_root() . '/connections');
			}

			if ($cmd === 'update') {
				// pull feed and consume it, which should subscribe to the hub.
				Master::Summon(['Poller', $contact_id]);
				goaway(z_root() . '/connedit/' . $contact_id);

			}

			if ($cmd === 'fetchvc') {
				$url     = str_replace('/channel/', '/profile/', $orig_record[0]['xchan_url']) . '/vcard';
				$recurse = 0;
				$x       = z_fetch_url(zid($url), false, $recurse, ['session' => true]);
				if ($x['success']) {
					$h      = new HTTPHeaders($x['header']);
					$fields = $h->fetch();
					if ($fields) {
						foreach ($fields as $y) {
							if (array_key_exists('content-type', $y)) {
								$type = explode(';', trim($y['content-type']));
								if ($type && $type[0] === 'text/vcard' && $x['body']) {
									$vc    = Reader::read($x['body']);
									$vcard = $vc->serialize();
									if ($vcard) {
										set_abconfig(local_channel(), $orig_record[0]['abook_xchan'], 'system', 'vcard', $vcard);
										$this->connedit_clone();
									}
								}
							}
						}
					}
				}
				goaway(z_root() . '/connedit/' . $contact_id);
			}


			if ($cmd === 'resetphoto') {
				q("update xchan set xchan_photo_date = '2001-01-01 00:00:00' where xchan_hash = '%s'",
					dbesc($orig_record[0]['xchan_hash'])
				);
				$cmd = 'refresh';
			}

			if ($cmd === 'refresh') {
				if ($orig_record[0]['xchan_network'] === 'zot6') {
					if (!Libzot::refresh($orig_record[0], App::get_channel()))
						notice(t('Refresh failed - channel is currently unavailable.'));
				}
				else {
					// if you are on a different network we'll force a refresh of the connection basic info
					Master::Summon(['Notifier', 'permission_update', $contact_id]);
				}
				goaway(z_root() . '/connedit/' . $contact_id);
			}

			if ($cmd === 'block') {
				if (abook_toggle_flag($orig_record[0], ABOOK_FLAG_BLOCKED)) {
					$this->connedit_clone();
				}
				else
					notice(t('Unable to set address book parameters.') . EOL);
				goaway(z_root() . '/connedit/' . $contact_id);
			}

			if ($cmd === 'ignore') {
				if (abook_toggle_flag($orig_record[0], ABOOK_FLAG_IGNORED)) {
					$this->connedit_clone();
				}
				else
					notice(t('Unable to set address book parameters.') . EOL);
				goaway(z_root() . '/connedit/' . $contact_id);
			}

			if ($cmd === 'archive') {
				if (abook_toggle_flag($orig_record[0], ABOOK_FLAG_ARCHIVED)) {
					$this->connedit_clone();
				}
				else
					notice(t('Unable to set address book parameters.') . EOL);
				goaway(z_root() . '/connedit/' . $contact_id);
			}

			if ($cmd === 'hide') {
				if (abook_toggle_flag($orig_record[0], ABOOK_FLAG_HIDDEN)) {
					$this->connedit_clone();
				}
				else
					notice(t('Unable to set address book parameters.') . EOL);
				goaway(z_root() . '/connedit/' . $contact_id);
			}

			// We'll prevent somebody from unapproving an already approved contact.
			// Though maybe somebody will want this eventually (??)

			if ($cmd === 'approve') {
				if (intval($orig_record[0]['abook_pending'])) {
					if (abook_toggle_flag($orig_record[0], ABOOK_FLAG_PENDING)) {
						$this->connedit_clone();
					}
					else
						notice(t('Unable to set address book parameters.') . EOL);
				}
				goaway(z_root() . '/connedit/' . $contact_id);
			}


			if ($cmd === 'drop') {

				contact_remove(local_channel(), $orig_record[0]['abook_id']);

				Master::Summon(['Notifier', 'purge', local_channel(), $orig_record[0]['xchan_hash']]);

				Libsync::build_sync_packet(0 /* use the current local_channel */,
					['abook' => [[
									 'abook_xchan'   => $orig_record[0]['abook_xchan'],
									 'entry_deleted' => true]]
					]
				);

				info(t('Connection has been removed.') . EOL);
				if (x($_SESSION, 'return_url'))
					goaway(z_root() . '/' . $_SESSION['return_url']);
				goaway(z_root() . '/contacts');

			}
		}

		if (App::$poi) {

			$abook_prev = 0;
			$abook_next = 0;
			$contact_id = App::$poi['abook_id'];
			$contact    = App::$poi;

			$cn = q("SELECT abook_id, xchan_name from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d and abook_self = 0 and xchan_deleted = 0 order by xchan_name",
				intval(local_channel())
			);

			if ($cn) {
				$pntotal = count($cn);

				for ($x = 0; $x < $pntotal; $x++) {
					if ($cn[$x]['abook_id'] == $contact_id) {
						if ($x === 0)
							$abook_prev = 0;
						else
							$abook_prev = $cn[$x - 1]['abook_id'];
						if ($x === $pntotal)
							$abook_next = 0;
						else
							$abook_next = $cn[$x + 1]['abook_id'];
					}
				}
			}

			$tools = [

				'view' => [
					'label' => t('View Profile'),
					'url'   => chanlink_cid($contact['abook_id']),
					'sel'   => '',
					'title' => sprintf(t('View %s\'s profile'), $contact['xchan_name']),
				],

				'refresh' => [
					'label' => t('Refresh Permissions'),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/refresh',
					'sel'   => '',
					'title' => t('Fetch updated permissions'),
				],

				'rephoto' => [
					'label' => t('Refresh Photo'),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/resetphoto',
					'sel'   => '',
					'title' => t('Fetch updated photo'),
				],

				'recent' => [
					'label' => t('Recent Activity'),
					'url'   => z_root() . '/network/?f=&cid=' . $contact['abook_id'],
					'sel'   => '',
					'title' => t('View recent posts and comments'),
				],

				'block' => [
					'label' => (intval($contact['abook_blocked']) ? t('Unblock') : t('Block')),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/block',
					'sel'   => (intval($contact['abook_blocked']) ? 'active' : ''),
					'title' => t('Block (or Unblock) all communications with this connection'),
					'info'  => (intval($contact['abook_blocked']) ? t('This connection is blocked!') : ''),
				],

				'ignore' => [
					'label' => (intval($contact['abook_ignored']) ? t('Unignore') : t('Ignore')),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/ignore',
					'sel'   => (intval($contact['abook_ignored']) ? 'active' : ''),
					'title' => t('Ignore (or Unignore) all inbound communications from this connection'),
					'info'  => (intval($contact['abook_ignored']) ? t('This connection is ignored!') : ''),
				],

				'archive' => [
					'label' => (intval($contact['abook_archived']) ? t('Unarchive') : t('Archive')),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/archive',
					'sel'   => (intval($contact['abook_archived']) ? 'active' : ''),
					'title' => t('Archive (or Unarchive) this connection - mark channel dead but keep content'),
					'info'  => (intval($contact['abook_archived']) ? t('This connection is archived!') : ''),
				],

				'hide' => [
					'label' => (intval($contact['abook_hidden']) ? t('Unhide') : t('Hide')),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/hide',
					'sel'   => (intval($contact['abook_hidden']) ? 'active' : ''),
					'title' => t('Hide or Unhide this connection from your other connections'),
					'info'  => (intval($contact['abook_hidden']) ? t('This connection is hidden!') : ''),
				],

				'delete' => [
					'label' => t('Delete'),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/drop',
					'sel'   => '',
					'title' => t('Delete this connection'),
				],

			];

			if ($contact['xchan_network'] === 'zot6') {
				$tools['fetchvc'] = [
					'label' => t('Fetch Vcard'),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/fetchvc',
					'sel'   => '',
					'title' => t('Fetch electronic calling card for this connection')
				];
			}


			$sections = [];

			$vc = get_abconfig(local_channel(), $contact['abook_xchan'], 'system', 'vcard');

			$vctmp = (($vc) ? Reader::read($vc) : null);
			$vcard = (($vctmp) ? get_vcard_array($vctmp, $contact['abook_id']) : []);
			if (!$vcard['fn'])
				$vcard['fn'] = $contact['xchan_name'];

			$tpl = get_markup_template("abook_edit.tpl");

			if (Apps::system_app_installed(local_channel(), 'Affinity Tool')) {

				$sections['affinity'] = [
					'label' => t('Affinity'),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/?f=&section=affinity',
					'sel'   => '',
					'title' => t('Open Set Affinity section by default'),
				];

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

			if (feature_enabled(local_channel(), 'connfilter')) {
				$sections['filter'] = [
					'label' => t('Filter'),
					'url'   => z_root() . '/connedit/' . $contact['abook_id'] . '/?f=&section=filter',
					'sel'   => '',
					'title' => t('Open Custom Filter section by default'),
				];
			}

			$perms        = [];
			$global_perms = Permissions::Perms();
			$existing     = get_all_perms(local_channel(), $contact['abook_xchan'], false);
			$unapproved   = ['pending', t('Approve this contact'), '', t('Accept contact to allow communication'), [t('No'), ('Yes')]];
			$multiprofs   = ((feature_enabled(local_channel(), 'multi_profiles')) ? true : false);

			if ($slide && !$multiprofs)
				$affinity = t('Set Affinity');

			if (!$slide && $multiprofs)
				$affinity = t('Set Profile');

			if ($slide && $multiprofs)
				$affinity = t('Set Affinity & Profile');

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

			$o .= replace_macros($tpl, [
				'$header'           => sprintf(t('Contact: %s'), $contact['xchan_name']),
				'$permcat'          => ['permcat', t('Contact role'), $current_permcat, '', $permcats],
				'$permcat_new'      => t('Manage contact roles'),
				'$permcat_value'    => bin2hex($current_permcat),
				'$addr'             => unpunify($contact['xchan_addr']),
				'$primeurl'         => unpunify($contact['xchan_url']),
				'$section'          => $section,
				'$sections'         => $sections,
				'$vcard'            => $vcard,
				'$addr_text'        => t('This contacts\'s primary address is'),
				'$loc_text'         => t('Available locations:'),
				'$locstr'           => $locstr,
				'$unclonable'       => $clone_warn,
				'$notself'          => '1',
				'$self'             => '',
				'$autolbl'          => t('The permissions indicated on this page will be applied to all new connections.'),
				'$tools_label'      => t('Contact Tools'),
				'$tools'            => $tools,
				'$lbl_slider'       => t('Slide to adjust your degree of friendship'),
				'$connfilter'       => feature_enabled(local_channel(), 'connfilter'),
				'$connfilter_label' => t('Custom Filter'),
				'$incl'             => ['abook_incl', t('Only import posts with this text'), $contact['abook_incl'], t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')],
				'$excl'             => ['abook_excl', t('Do not import posts with this text'), $contact['abook_excl'], t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')],
				'$slide'            => $slide,
				'$affinity'         => $affinity,
				'$pending_label'    => t('Contact Pending Approval'),
				'$is_pending'       => (intval($contact['abook_pending']) ? 1 : ''),
				'$unapproved'       => $unapproved,
				'$inherited'        => t('inherited'),
				'$submit'           => ((intval($contact['abook_pending'])) ? t('Approve contact') : t('Submit')),
				'$lbl_vis2'         => sprintf(t('Please choose the profile you would like to display to %s when viewing your profile securely.'), $contact['xchan_name']),
				'$close'            => (($contact['abook_closeness']) ? $contact['abook_closeness'] : 80),
				'$them'             => t('Their'),
				'$me'               => t('My'),
				'$perms'            => $perms,
				'$permlbl'          => t('Individual Permissions'),
				'$permnote'         => t('Some permissions may be inherited from your channel\'s <a href="settings"><strong>privacy settings</strong></a>, which have higher priority than individual settings. You can <strong>not</strong> change those settings here.'),
				'$permnote_self'    => t('Some permissions may be inherited from your channel\'s <a href="settings"><strong>privacy settings</strong></a>, which have higher priority than individual settings. You can change those settings here but they wont have any impact unless the inherited setting changes.'),
				'$lastupdtext'      => t('Last update:'),
				'$last_update'      => relative_date($contact['abook_connected']),
				'$profile_select'   => contact_profile_assign($contact['abook_profile']),
				'$multiprofs'       => $multiprofs,
				'$contact_id'       => $contact['abook_id'],
				'$name'             => $contact['xchan_name'],
				'$abook_prev'       => $abook_prev,
				'$abook_next'       => $abook_next,
				'$vcard_label'      => t('Details'),
				'$name_label'       => t('Name'),
				'$org_label'        => t('Organisation'),
				'$title_label'      => t('Title'),
				'$tel_label'        => t('Phone'),
				'$email_label'      => t('Email'),
				'$impp_label'       => t('Instant messenger'),
				'$url_label'        => t('Website'),
				'$adr_label'        => t('Address'),
				'$note_label'       => t('Note'),
				'$mobile'           => t('Mobile'),
				'$home'             => t('Home'),
				'$work'             => t('Work'),
				'$other'            => t('Other'),
				'$add_card'         => t('Add Contact'),
				'$add_field'        => t('Add Field'),
				'$create'           => t('Create'),
				'$update'           => t('Update'),
				'$delete'           => t('Delete'),
				'$cancel'           => t('Cancel'),
				'$po_box'           => t('P.O. Box'),
				'$extra'            => t('Additional'),
				'$street'           => t('Street'),
				'$locality'         => t('Locality'),
				'$region'           => t('Region'),
				'$zip_code'         => t('ZIP Code'),
				'$country'          => t('Country')
			]);

			$arr = ['contact' => $contact, 'output' => $o];

			call_hooks('contact_edit', $arr);

			return $arr['output'];

		}
	}
}
