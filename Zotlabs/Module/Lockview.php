<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\AccessList;
use Zotlabs\Web\Controller;

require_once('include/security.php');

class Lockview extends Controller {

	function get() {

		$atokens           = [];
		$atoken_xchans     = [];
		$access_list       = [];
		$guest_access_list = [];

		if (local_channel()) {
			$at = q("select * from atoken where atoken_uid = %d",
				intval(local_channel())
			);
			if ($at) {
				foreach ($at as $t) {
					$atoken_xchan    = atoken_xchan($t);
					$atokens[]       = array_merge($t, $atoken_xchan);
					$atoken_xchans[] = $atoken_xchan['xchan_hash'];
				}
			}
		}

		$type = ((argc() > 1) ? argv(1) : 0);
		if (is_numeric($type)) {
			$item_id = intval($type);
			$type    = 'item';
		}
		else {
			$item_id = ((argc() > 2) ? intval(argv(2)) : 0);
		}

		if (!$item_id)
			killme();

		if (!in_array($type, ['item', 'photo', 'attach', 'menu_item', 'chatroom']))
			killme();

		// we have different naming in in menu_item table and chatroom table
		switch ($type) {
			case 'menu_item':
				$id = 'mitem_id';
				break;
			case 'chatroom':
				$id = 'cr_id';
				break;
			default:
				$id = 'id';
				break;
		}

		$r = q("SELECT * FROM %s WHERE $id = %d LIMIT 1",
			dbesc($type),
			intval($item_id)
		);

		if (!$r)
			killme();

		$item = $r[0];
		$uid  = null;
		$url  = '';

		switch ($type) {
			case 'menu_item':
				$uid = $item['mitem_channel_id'];
				break;
			case 'chatroom':
				$uid     = $item['cr_uid'];
				$channel = channelx_by_n($uid);
				$url     = z_root() . '/chat/' . $channel['channel_address'] . '/' . $item['cr_id'];
				break;
			case 'item':
				$uid = $item['uid'];
				$url = $item['plink'];
				break;
			case 'photo':
				$uid     = $item['uid'];
				$channel = channelx_by_n($uid);
				$url     = z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $item['resource_id'];
				break;
			case 'attach':
				$uid     = $item['uid'];
				$channel = channelx_by_n($uid);
				$url     = z_root() . '/cloud/' . $channel['channel_address'] . '/' . $item['display_path'];
				break;
			default:
				break;
		}

		if (intval($uid) !== local_channel()) {
			echo '<div class="dropdown-item-text">' . t('Remote privacy information not available') . '</div>';
			killme();
		}

		if (intval($item['item_private']) && (!strlen($item['allow_cid'])) && (!strlen($item['allow_gid']))
			&& (!strlen($item['deny_cid'])) && (!strlen($item['deny_gid']))) {

			// if the post is private, but public_policy is blank ("visible to the internet"), and there aren't any
			// specific recipients, we're the recipient of a post with "bcc" or targeted recipients; so we'll just show it
			// as unknown specific recipients. The sender will have the visibility list and will fall through to the
			// next section.

			echo '<div class="dropdown-item-text">' . translate_scope((!$item['public_policy']) ? 'specific' : $item['public_policy']) . '</div>';
			killme();
		}

		$allowed_users  = expand_acl($item['allow_cid']);
		$allowed_groups = expand_acl($item['allow_gid']);
		$deny_users     = expand_acl($item['deny_cid']);
		$deny_groups    = expand_acl($item['deny_gid']);

		stringify_array_elms($allowed_groups, true);
		stringify_array_elms($allowed_users, true);
		stringify_array_elms($deny_groups, true);
		stringify_array_elms($deny_users, true);

		$allowed_xchans = [];

		$profile_groups = [];
		if ($allowed_groups) {
			foreach ($allowed_groups as $g) {
				if (substr($g, 0, 4) === '\'vp.') {
					$profile_groups[] = '\'' . substr($g, 4);
				}
			}
		}

		if ($profile_groups) {
			$r = q("SELECT id, profile_name FROM profile WHERE profile_guid IN ( " . implode(', ', $profile_groups) . " )");
			if ($r) {
				foreach ($r as $rr) {
					$pgrp_members   = AccessList::profile_members_xchan($uid, $rr['id']);
					$allowed_xchans = array_merge($allowed_xchans, $pgrp_members);
					$access_list[] = '<div class="dropdown-item-text" title="' . t('Profile', 'acl') . '">' . $rr['profile_name'] . '</div>';
				}
			}
		}

		if ($allowed_groups) {
			$r = q("SELECT id, gname FROM pgrp WHERE hash IN ( " . implode(', ', $allowed_groups) . " )");
			if ($r) {
				foreach ($r as $rr) {
					$pgrp_members   = AccessList::members_xchan($uid, $rr['id']);
					$allowed_xchans = array_merge($allowed_xchans, $pgrp_members);
					$access_list[] = '<div class="dropdown-item-text" title="' . t('Privacy group') . '">' . $rr['gname'] . '</div>';
				}
			}
		}

		if ($allowed_users) {
			$r = q("SELECT xchan_name, xchan_hash FROM xchan WHERE xchan_hash IN ( " . implode(', ', $allowed_users) . " )");
			if ($r) {
				foreach ($r as $rr) {
					$allowed_xchans[] = $rr['xchan_hash'];
					if (!in_array($rr['xchan_hash'], $atoken_xchans)) {
						$access_list[] = '<div class="dropdown-item-text">' . $rr['xchan_name'] . '</div>';
					}
				}
			}
		}

		$profile_groups = [];
		if ($deny_groups) {
			foreach ($deny_groups as $g) {
				if (substr($g, 0, 4) === '\'vp.') {
					$profile_groups[] = '\'' . substr($g, 4);
				}
			}
		}

		if ($profile_groups) {
			$r = q("SELECT profile_name FROM profile WHERE profile_guid IN ( " . implode(', ', $profile_groups) . " )");
			if ($r) {
				foreach ($r as $rr) {
					$access_list[] = '<div class="dropdown-item-text" title="' . t('Profile', 'acl') . '"><strike>' . $rr['profile_name'] . '</strike></b></div>';
				}
			}
		}

		if ($deny_groups) {
			$r = q("SELECT gname FROM pgrp WHERE hash IN ( " . implode(', ', $deny_groups) . " )");
			if ($r) {
				foreach ($r as $rr) {
					$access_list[] = '<div class="dropdown-item-text" title="' . t('Privacy group') . '"><strike>' . $rr['gname'] . '</strike></b></div>';
				}
			}
		}

		if ($deny_users) {
			$r = q("SELECT xchan_name FROM xchan WHERE xchan_hash IN ( " . implode(', ', $deny_users) . " )");
			if ($r) {
				foreach ($r as $rr) {
					$access_list[] = '<div class="dropdown-item-text"><strike>' . $rr['xchan_name'] . '</strike></div>';
				}
			}
		}

		if ($atokens && $allowed_xchans && $url) {

			$guest_access_list = [];

			$allowed_xchans = array_unique($allowed_xchans);
			foreach ($atokens as $atoken) {
				if (in_array($atoken['xchan_hash'], $allowed_xchans)) {
					$guest_access_list[] = '<div class="dropdown-item d-flex justify-content-between cursor-pointer" title="' . sprintf(t('Click to copy link to this ressource for guest %s to clipboard'), $atoken['xchan_name']) . '" data-token="' . $url . '?zat=' . $atoken['atoken_token'] . '" onclick="navigator.clipboard.writeText(this.dataset.token); $.jGrowl(\'' . t('Link copied') . '\', { sticky: false, theme: \'info\', life: 1000 });"><span>' . $atoken['xchan_name'] . '</span><i class="fa fa-copy p-1"></i></div>';
				}
			}
		}

		$access_list_header = '';
		if ($access_list) {
			$access_list_header = '<div class="dropdown-header text-uppercase h6">' . t('Access') . '</div>';
		}

		$guest_access_list_header = '';
		if ($guest_access_list) {
			$guest_access_list_header = '<div class="dropdown-header text-uppercase h6">' . t('Guest access') . '</div>';
		}

		$divider = '';
		if ($access_list && $guest_access_list) {
				$divider = '<div class="dropdown-divider"></div>';
		}

		echo $access_list_header . implode($access_list) . $divider . $guest_access_list_header . implode($guest_access_list);
		killme();

	}

}
