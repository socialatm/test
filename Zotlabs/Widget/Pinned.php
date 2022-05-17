<?php
namespace Zotlabs\Widget;

/**
 *   * Name: Pinned items
 *   * Description: Display pinned items
 *   * Author: Max Kostikov
 *   * Requires: disabled_for_pdledit_gui
 */


class Pinned {

	private $allowed_types = 0;
	private $uid = 0;


	/*
	 * @brief Displays pinned items
	 *
	 * @param $uid
	 * @param $types
	 * @return array of results: 'html' string, 'ids' array
	 *
	 */
	function widget($uid, $types) {

		$ret = [ 'html' => EMPTY_STR, 'ids' => [] ];

		$this->uid = intval($uid);
		if(! $this->uid)
			return $ret;

		$this->allowed_types = get_config('system', 'pin_types', [ ITEM_TYPE_POST ]);

		$items = $this->list($types);

		if(empty($items))
			return $ret;

		$ret['ids'] = array_column($items, 'id');

		$observer = \App::get_observer();

		foreach($items as $item) {

			$midb64 = gen_link_id($item['mid']);

			if(isset($observer['xchan_hash']) && in_array($observer['xchan_hash'], get_pconfig($item['uid'], 'pinned_hide', $midb64, [])))
				continue;

			$author = channelx_by_hash($item['author_xchan']);
			$owner = channelx_by_hash($item['owner_xchan']);

			$profile_avatar = $author['xchan_photo_m'];
			$profile_link = chanlink_hash($item['author_xchan']);
			$profile_name = $author['xchan_name'];

			$commentable = ($item['item_nocomment'] == 0 && $item['comments_closed'] == NULL_DATE ? true : false);

			$location = format_location($item);
			$isevent = false;
			$attend = null;
			$canvote = false;

			$conv_responses = [];

			if($item['obj_type'] === ACTIVITY_OBJ_EVENT) {
				$conv_responses['attendyes'] = [ 'title' => t('Attending','title') ];
				$conv_responses['attendno'] = [ 'title' => t('Not attending','title') ];
				$conv_responses['attendmaybe'] = [ 'title' => t('Might attend','title') ];
				if($commentable && $observer) {
					$attend = [ t('I will attend'), t('I will not attend'), t('I might attend') ];
					$isevent = true;
				}
			}

			$consensus = (intval($item['item_consensus']) ? true : false);
			if($consensus) {
				$conv_responses['agree'] = [ 'title' => t('Agree','title') ];
				$conv_responses['disagree'] = [ 'title' => t('Disagree','title') ];
				$conv_responses['abstain'] = [ 'title' => t('Abstain','title') ];
				if($commentable && $observer) {
					$conlabels = [ t('I agree'), t('I disagree'), t('I abstain') ];
					$canvote = true;
				}
			}

			$this->activity($item, $conv_responses);

			$verified = (intval($item['item_verified']) ? t('Message signature validated') : '');
			$forged = ((! intval($item['item_verified']) && $item['sig']) ? t('Message signature incorrect') : '');

			$shareable = ((local_channel() && \App::$profile_uid == local_channel() && $item['item_private'] != 1) ? true : false);
			if ($shareable) {
				// This actually turns out not to be possible in some protocol stacks without opening up hundreds of new issues.
				// Will allow it only for uri resolvable sources.
				if(strpos($item['mid'],'http') === 0) {
					$share = []; // Isn't yet ready for primetime
					//$share = [ t('Repeat This'), t('repeat') ];
				}
				$embed = [ t('Share This'), t('share') ];
			}

			$is_new = boolval(strcmp(datetime_convert('UTC','UTC',$item['created']),datetime_convert('UTC','UTC','now - 12 hours')) > 0);

			$body = prepare_body($item,true);

			$str = [
				'item_type'	 => intval($item['item_type']),
				'body'		 => $body['html'],
				'tags'		 => $body['tags'],
				'categories'	 => $body['categories'],
				'mentions'	 => $body['mentions'],
				'attachments'	 => $body['attachments'],
				'folders'	 => $body['folders'],
				'text'		 => strip_tags($body['html']),
				'id'		 => $item['id'],
				'mids'		 => json_encode([ $midb64 ]),
				'isevent'	 => $isevent,
				'attend'	 => $attend,
				'consensus'	 => $consensus,
				'conlabels'	 => ($canvote ? $conlabels : []),
				'canvote'	 => $canvote,
				'linktitle' 	 => sprintf( t('View %s\'s profile - %s'), $profile_name, ($author['xchan_addr'] ? $author['xchan_addr'] : $author['xchan_url']) ),
				'olinktitle' 	 => sprintf( t('View %s\'s profile - %s'), $owner['xchan_name'], ($owner['xchan_addr'] ? $owner['xchan_addr'] : $owner['xchan_url']) ),
				'profile_url' 	 => $profile_link,
				'name'		 => $profile_name,
				'thumb'		 => $profile_avatar,
				'via'		 => t('via'),
				'title'		 => $item['title'],
				'title_tosource' => get_pconfig($item['uid'],'system','title_tosource'),
				'ago'		 => relative_date($item['created']),
				'app'		 => $item['app'],
				'str_app'	 => sprintf( t('from %s'), $item['app'] ),
				'isotime'	 => datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'c'),
				'localtime'	 => datetime_convert('UTC', date_default_timezone_get(), $item['created'], 'r'),
				'editedtime'	 => (($item['edited'] != $item['created']) ? sprintf( t('last edited: %s'), datetime_convert('UTC', date_default_timezone_get(), $item['edited'], 'r') ) : ''),
				'expiretime'	 => ($item['expires'] > NULL_DATE ? sprintf( t('Expires: %s'), datetime_convert('UTC', date_default_timezone_get(), $item['expires'], 'r') ) : ''),
				'verified'	 => $verified,
				'forged'	 => $forged,
				'location'	 => $location,
				'divider'	 => get_pconfig($item['uid'],'system','item_divider'),
				'attend_title' => t('Attendance Options'),
				'vote_title' => t('Voting Options'),
				'is_new'	 => $is_new,
				'owner_url'	 => ($owner['xchan_addr'] != $author['xchan_addr'] ? chanlink_hash($owner['xchan_hash']) : ''),
				'owner_photo'=> $owner['xchan_photo_m'],
				'owner_name' => $owner['xchan_name'],
				'photo'		 => $body['photo'],
				'event'		 => $body['event'],
				'has_tags'	 => (($body['tags'] || $body['categories'] || $body['mentions'] || $body['attachments'] || $body['folders']) ? true : false),
				// Item toolbar buttons
				'share'		 => (isset($share) && count($share) ? $share : false),
				'embed'		 => (isset($embed) && count($embed) ? $embed : false),
				'plink'		 => get_plink($item),
				'pinned'	 => t('Pinned post'),
				'pinme'		 => (isset($observer['xchan_hash']) && $observer['xchan_hash'] == $owner['xchan_hash'] ? t('Unpin from the top') : ''),
				'hide'		 => (! $is_new && isset($observer['xchan_hash']) && $observer['xchan_hash'] != $owner['xchan_hash'] ? t("Don't show") : ''),
				// end toolbar buttons
				'modal_dismiss' => t('Close'),
				'responses'	 => $conv_responses
			];

			$tpl = get_markup_template('pinned_item.tpl');
			$ret['html'] .= replace_macros($tpl, $str);
		}

		return $ret;
	}


	/*
	 * @brief List pinned items depend on type
	 *
	 * @param $types
	 * @return array of pinned items
	 *
	 */
	private function list($types) {

		if(empty($types) || (! is_array($types)))
			return [];

		$item_types = array_intersect($this->allowed_types, $types);
		if(empty($item_types))
			return [];

		$mids_list = [];

		foreach($item_types as $type) {

			$mids = get_pconfig($this->uid, 'pinned', $type, []);
			foreach($mids as $mid) {
				if(!empty($mid))
					$mids_list[] = unpack_link_id($mid);
			}
		}
		if(empty($mids_list))
			return [];

		$r = q("SELECT * FROM item WHERE mid IN ( '%s' ) AND uid = %d AND id = parent AND item_private = 0 ORDER BY created DESC",
			dbesc(implode(",", $mids_list)),
			intval($this->uid)
		);
		if($r)
			return $r;

		return [];
	}


	/*
	 * @brief List activities on item
	 *
	 * @param array $item
	 * @param array $conv_responses
	 * @return array
	 *
	 */
	private function activity($item, &$conv_responses) {

		foreach(array_keys($conv_responses) as $verb) {

			switch($verb) {
				case 'like':
					$v = ACTIVITY_LIKE;
					break;
				case 'dislike':
					$v = ACTIVITY_DISLIKE;
					break;
				case 'agree':
					$v = ACTIVITY_AGREE;
					break;
				case 'disagree':
					$v = ACTIVITY_DISAGREE;
					break;
				case 'abstain':
					$v = ACTIVITY_ABSTAIN;
					break;
				case 'attendyes':
					$v = ACTIVITY_ATTEND;
					break;
				case 'attendno':
					$v = ACTIVITY_ATTENDNO;
					break;
				case 'attendmaybe':
					$v = ACTIVITY_ATTENDMAYBE;
					break;
				default:
					break;
			}

			$r = q("SELECT * FROM item WHERE parent = %d AND id <> parent AND verb = '%s' AND item_deleted = 0",
				intval($item['id']),
				dbesc($v)
			);
			if(! $r) {
				unset($conv_responses[$verb]);
				continue;
			}

			$conv_responses[$verb]['count'] = count($r);
			$conv_responses[$verb]['button'] = get_response_button_text($verb, $conv_responses[$verb]['count']);

			foreach($r as $rr) {

				$author = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
					dbesc($rr['author_xchan'])
				);
				$name = (($author && $author[0]['xchan_name']) ? $author[0]['xchan_name'] : t('Unknown'));
				$conv_responses[$verb]['list'][] = (($rr['author_xchan'] && $author && $author[0]['xchan_photo_s']) ?
					'<a class="dropdown-item" href="' . chanlink_hash($rr['author_xchan']) . '">' . '<img class="menu-img-1" src="' . zid($author[0]['xchan_photo_s']) . '" alt="' . urlencode($name) . '" /> ' . $name . '</a>' :
					'<a class="dropdown-item" href="#" class="disabled">' . $name . '</a>'
				);
			}
		}

		$conv_responses['count'] = count($conv_responses);
	}
}
