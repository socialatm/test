<?php
namespace Zotlabs\Module; /** @file */

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Activity;
use Zotlabs\Web\Controller;

/**
 *
 * Poke, prod, finger, or otherwise do unspeakable things to somebody - who must be a connection in your address book
 * This function can be invoked with the required arguments (verb and cid and private and possibly parent) silently via ajax or
 * other web request. You must be logged in and connected to a channel.
 * If the required arguments aren't present, we'll display a simple form to choose a recipient and a verb.
 * parent is a special argument which let's you attach this activity as a comment to an existing conversation, which
 * may have started with somebody else poking (etc.) somebody, but this isn't necessary. This can be used in the adult
 * plugin version to have entire conversations where Alice poked Bob, Bob fingered Alice, Alice hugged Bob, etc.
 *
 * private creates a private conversation with the recipient. Otherwise your channel's default post privacy is used.
 *
 */

require_once('include/items.php');


class Poke extends Controller {

	function init() {

		if(! local_channel())
			return;

		if(! Apps::system_app_installed(local_channel(), 'Poke')) {
			return;
		}

		$uid = local_channel();
		$channel = App::get_channel();

		$verb = ((isset($_GET['verb'])) ? notags(trim($_GET['verb'])) : '');

		if(! $verb)
			return;

		$verbs = get_poke_verbs();

		if(! array_key_exists($verb,$verbs))
			return;

		$activity = ACTIVITY_POKE . '#' . urlencode($verbs[$verb][0]);

		$contact_id = intval($_REQUEST['cid']);

		$xchan = trim($_REQUEST['xchan']);

		if(! ($contact_id || $xchan))
			return;

		$parent = ((x($_REQUEST,'parent')) ? intval($_REQUEST['parent']) : 0);

		logger('poke: verb ' . $verb . ' contact ' . $contact_id, LOGGER_DEBUG);


		if($contact_id) {
			$r = q("SELECT * FROM abook left join xchan on xchan_hash = abook_xchan where abook_id = %d and abook_channel = %d LIMIT 1",
				intval($contact_id),
				intval($uid)
			);
		}
		if($xchan) {
			$r = q("SELECT * FROM xchan where xchan_hash like ( '%s' ) LIMIT 1",
				dbesc($xchan . '%')
			);
		}

		if(! $r) {
			logger('poke: no target.');
			return;
		}

		$target = $r[0];
		$parent_item = null;

		if($parent) {
			$r = q("select mid, item_private, owner_xchan, allow_cid, allow_gid, deny_cid, deny_gid
				from item where id = %d and parent = %d and uid = %d limit 1",
				intval($parent),
				intval($parent),
				intval($uid)
			);
			if($r) {
				$parent_item  = $r[0];
				$parent_mid   = $r[0]['mid'];
				$item_private = $r[0]['item_private'];
				$allow_cid    = $r[0]['allow_cid'];
				$allow_gid    = $r[0]['allow_gid'];
				$deny_cid     = $r[0]['deny_cid'];
				$deny_gid     = $r[0]['deny_gid'];
			}
		}
		elseif($contact_id) {

			$item_private = ((x($_GET,'private')) ? intval($_GET['private']) : 0);

			$allow_cid     = (($item_private) ? '<' . $target['abook_xchan']. '>' : $channel['channel_allow_cid']);
			$allow_gid     = (($item_private) ? '' : $channel['channel_allow_gid']);
			$deny_cid      = (($item_private) ? '' : $channel['channel_deny_cid']);
			$deny_gid      = (($item_private) ? '' : $channel['channel_deny_gid']);
		}

		$arr['item_wall']     = 1;
		$arr['owner_xchan']   = (($parent_item) ? $parent_item['owner_xchan'] : $channel['channel_hash']);
		$arr['parent_mid']    = (($parent_mid) ? $parent_mid : '');
		$arr['title']         = '';
		$arr['allow_cid']     = $allow_cid;
		$arr['allow_gid']     = $allow_gid;
		$arr['deny_cid']      = $deny_cid;
		$arr['deny_gid']      = $deny_gid;
		$arr['verb']          = $activity;
		$arr['item_private']  = $item_private;
		$arr['obj_type']      = ACTIVITY_OBJ_NOTE;
		$arr['body']          = '[zrl=' . $channel['xchan_url'] . ']' . $channel['xchan_name'] . '[/zrl]' . ' ' . t($verbs[$verb][0]) . ' ' . '[zrl=' . $target['xchan_url'] . ']' . $target['xchan_name'] . '[/zrl]';
		$arr['item_origin']   = 1;
		$arr['item_unseen']   = 1;
		if(! $parent_item)
			$arr['item_thread_top'] = 1;

		$arr['obj'] = Activity::encode_item($arr);


		post_activity_item($arr);

		return;
	}



	function get() {

		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		if(! Apps::system_app_installed(local_channel(), 'Poke')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Poke');
			return Apps::app_render($papp, 'module');
		}

		nav_set_selected('Poke');

		$name = '';
		$id = '';

		if(isset($_REQUEST['c']) && intval($_REQUEST['c'])) {
			$r = q("select abook_id, xchan_name from abook left join xchan on abook_xchan = xchan_hash
				where abook_id = %d and abook_channel = %d limit 1",
				intval($_REQUEST['c']),
				intval(local_channel())
			);
			if($r) {
				$name = $r[0]['xchan_name'];
				$id = $r[0]['abook_id'];
			}
		}

		$parent = ((x($_REQUEST,'parent')) ? intval($_REQUEST['parent']) : '0');

		$verbs = get_poke_verbs();

		$shortlist = array();
		foreach($verbs as $k => $v)
			if($v[1] !== 'NOTRANSLATION')
				$shortlist[] = array($k,$v[1]);


		$poke_basic = get_config('system','poke_basic');
		if($poke_basic) {
			$title = t('Poke');
			$desc = t('Poke somebody');
		}
		else {
			$title = t('Poke');
			$desc = t('Poke or ping somebody');
		}

		$o = replace_macros(get_markup_template('poke_content.tpl'),array(
			'$title' => $title,
			'$poke_basic' => $poke_basic,
			'$desc' => $desc,
			'$clabel' => t('Recipient'),
			'$choice' => t('Choose action'),
			'$verbs' => $shortlist,
			'$parent' => $parent,
			'$prv_desc' => t('Make this post private'),
			'$private' => array('private', t('Make this post private'), false, ''),
			'$submit' => t('Submit'),
			'$name' => $name,
			'$id' => $id
		));

		return $o;

	}
}
