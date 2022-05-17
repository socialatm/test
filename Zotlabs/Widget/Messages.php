<?php

/**
 *   * Name: HQ Messages
 *   * Description: Quick access to messages, direct messages, starred messages (if enabled) and notifications
 *   * Author: Mario Vavti
 *   * Requires: hq
 */


namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\IConfig;

class Messages {

	public static function widget($arr) {
		if (!local_channel())
			return EMPTY_STR;

		$page = self::get_messages_page([]);

		$_SESSION['messages_loadtime'] = datetime_convert();

		$tpl = get_markup_template('messages_widget.tpl');
		$o = replace_macros($tpl, [
			'$entries' => $page['entries'] ?? [],
			'$offset' => $page['offset'] ?? 0,
			'$feature_star' => feature_enabled(local_channel(), 'star_posts'),
			'$strings' => [
				'messages_title' => t('Public and restricted messages'),
				'direct_messages_title' => t('Direct messages'),
				'starred_messages_title' => t('Starred messages'),
				'notice_messages_title' => t('Notices'),
				'loading' => t('Loading'),
				'empty' => t('No messages')
			]
		]);

		return $o;
	}

	public static function get_messages_page($options) {
		if (!local_channel())
			return;

		if (isset($options['offset']) && $options['offset'] == -1) {
			return;
		}

		if (isset($options['type']) && $options['type'] == 'notification') {
			return self::get_notices_page($options);
		}

		$channel = App::get_channel();
		$item_normal = item_normal();
		$entries = [];
		$limit = 30;
		$dummy_order_sql = '';

		$offset = 0;
		if ($options['offset']) {
			$offset = intval($options['offset']);
		}

		$loadtime = (($offset) ? $_SESSION['messages_loadtime'] : datetime_convert());

		switch($options['type']) {
			case 'direct':
				$type_sql = ' AND item_private = 2 ';
				// $dummy_order_sql has no other meaning but to trick
				// some mysql backends into using the right index.
				$dummy_order_sql = ', received DESC ';
				break;
			case 'starred':
				$type_sql = ' AND item_starred = 1 ';
				break;
			default:
				$type_sql = ' AND item_private IN (0, 1) ';
		}

		$items = q("SELECT * FROM item WHERE uid = %d
			AND created <= '%s'
			$type_sql
			AND item_thread_top = 1
			$item_normal
			ORDER BY created DESC $dummy_order_sql
			LIMIT $limit OFFSET $offset",
			intval(local_channel()),
			dbescdate($loadtime)
		);

		xchan_query($items, false);

		$i = 0;
		$entries = [];

		foreach($items as $item) {

			$info = '';
			if ($options['type'] == 'direct') {
				$info .= self::get_dm_recipients($channel, $item);
			}

			if($item['owner_xchan'] !== $item['author_xchan']) {
				$info .= t('via') . ' ' . $item['owner']['xchan_name'];
			}

			$summary = $item['title'];
			if (!$summary) {
				$summary = $item['summary'];
			}

			if (!$summary) {
				$summary = html2plain(bbcode($item['body'], ['drop_media' => true]), 75, true);
				if ($summary) {
					$summary = htmlentities($summary, ENT_QUOTES, 'UTF-8', false);
				}
			}

			if (!$summary) {
				$summary = '...';
			}
			else {
				$summary = substr_words($summary, 68);
			}

			switch(intval($item['item_private'])) {
				case 1:
					$icon = '<i class="fa fa-lock"></i>';
					break;
				case 2:
					$icon = '<i class="fa fa-envelope-o"></i>';
					break;
				default:
					$icon = '';
			}

			$entries[$i]['author_name'] = $item['author']['xchan_name'];
			$entries[$i]['author_addr'] = (($item['author']['xchan_addr']) ? $item['author']['xchan_addr'] : $item['author']['xchan_url']);
			$entries[$i]['info'] = $info;
			$entries[$i]['created'] = datetime_convert('UTC', date_default_timezone_get(), $item['created']);
			$entries[$i]['summary'] = $summary;
			$entries[$i]['b64mid'] = gen_link_id($item['mid']);
			$entries[$i]['href'] = z_root() . '/hq/' . gen_link_id($item['mid']);
			$entries[$i]['icon'] = $icon;

			$i++;
		}

		$result = [
			'offset' => ((count($entries) < $limit) ? -1 : intval($offset + $limit)),
			'entries' => $entries
		];

		return $result;
	}

	public static function get_dm_recipients($channel, $item) {

		if($channel['channel_hash'] === $item['owner']['xchan_hash']) {
			// we are the owner, get the recipients from the item
			$recips = expand_acl($item['allow_cid']);
			if (is_array($recips)) {
				array_unshift($recips, $item['owner']['xchan_hash']);
				$column = 'xchan_hash';
			}
		}
		else {
			$recips = IConfig::Get($item, 'activitypub', 'recips');
			if (isset($recips['to']) && is_array($recips['to'])) {
				$recips = $recips['to'];
				array_unshift($recips, $item['owner']['xchan_url']);
				$column = 'xchan_url';
			}
			else {
				$hookinfo = [
					'item' => $item,
					'recips' => null,
					'column' => ''
				];

				call_hooks('direct_message_recipients', $hookinfo);

				$recips = $hookinfo['recips'];
				$column = $hookinfo['column'];
			}
		}

		if(is_array($recips)) {
			stringify_array_elms($recips, true);

			$query_str = implode(',', $recips);
			$xchans = dbq("SELECT DISTINCT xchan_name FROM xchan WHERE $column IN ($query_str) AND xchan_deleted = 0");
			foreach($xchans as $xchan) {
				$recipients .= $xchan['xchan_name'] . ', ';
			}
		}

		return trim($recipients, ', ');
	}

	public static function get_notices_page($options) {

		if (!local_channel())
			return;

		$limit  = 30;

		$offset = 0;
		if ($options['offset']) {
			$offset = intval($options['offset']);
		}

		$notices = q("SELECT * FROM notify WHERE uid = %d
			ORDER BY created DESC LIMIT $limit OFFSET $offset",
			intval(local_channel())
		);

		$i = 0;
		$entries = [];

		foreach($notices as $notice) {

			$summary = trim(strip_tags(bbcode($notice['msg'])));

			if(strpos($summary, $notice['xname']) === 0) {
				$summary = substr($summary, strlen($notice['xname']) + 1);
			}

			$entries[$i]['author_name'] = $notice['xname'];
			$entries[$i]['author_addr'] = $notice['url'];
			$entries[$i]['info'] = '';
			$entries[$i]['created'] = datetime_convert('UTC', date_default_timezone_get(), $notice['created']);
			$entries[$i]['summary'] = $summary;
			$entries[$i]['b64mid'] = (($notice['ntype'] & NOTIFY_INTRO) ? '' : basename($notice['link']));
			$entries[$i]['href'] = (($notice['ntype'] & NOTIFY_INTRO) ? $notice['link'] : z_root() . '/hq/' . basename($notice['link']));
			$entries[$i]['icon'] = (($notice['ntype'] & NOTIFY_INTRO) ? '<i class="fa fa-user-plus"></i>' : '');

			$i++;
		}

		$result = [
			'offset' => ((count($entries) < $limit) ? -1 : intval($offset + $limit)),
			'entries' => $entries
		];

		return $result;
	}
}
