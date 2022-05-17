<?php

namespace Zotlabs\Daemon;

use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\PConfig;


require_once('include/cli_startup.php');
require_once('include/attach.php');
require_once('include/import.php');

class Content_importer {

	static public function run($argc,$argv) {
		cli_startup();

		$page = $argv[1];
		$since = $argv[2];
		$until = $argv[3];
		$channel_address = $argv[4];
		$hz_server = urldecode($argv[5]);

		$m = parse_url($hz_server);

		$channel = channelx_by_nick($channel_address);
		if(! $channel) {
			logger('channel not found');
			return;
		}

		$headers = [
			'X-API-Token'      => random_string(),
			'X-API-Request'    => $hz_server . '/api/z/1.0/item/export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
			'Host'             => $m['host'],
			'(request-target)' => 'get /api/z/1.0/item/export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
		];

		$headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'], channel_url($channel),true,'sha512');

		$x = z_fetch_url($hz_server . '/api/z/1.0/item/export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page,false,$redirects,[ 'headers' => $headers ]);

		// logger('item fetch: ' . print_r($x,true));

		if(! $x['success']) {
			logger('no API response',LOGGER_DEBUG);
			killme();
		}

		$j = json_decode($x['body'],true);

		if(! is_array($j['item']) || ! count($j['item'])) {
			PConfig::Set($channel['channel_id'], 'import', 'content_completed', 1);
			return;
		}

		$saved_notification_flags = notifications_off($channel['channel_id']);

		import_items($channel,$j['item'],false,((array_key_exists('relocate',$j)) ? $j['relocate'] : null));

		notifications_on($channel['channel_id'], $saved_notification_flags);

		PConfig::Set($channel['channel_id'], 'import', 'content_progress', [
			'items_total' => $j['items_total'],
			'items_page' => $j['items_page'],
			'items_current_page' => count($j['item']),
			'last_page' => $page,
			'next_cmd' => ['Content_importer', sprintf('%d',$page + 1), $since, $until, $channel['channel_address'], urlencode($hz_server)]
		]);

		$page++;

		Master::Summon([ 'Content_importer', sprintf('%d',$page), $since, $until, $channel['channel_address'], urlencode($hz_server) ]);

		return;
	}
}
