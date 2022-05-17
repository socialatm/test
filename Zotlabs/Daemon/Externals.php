<?php /** @file */

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\ASCollection;

require_once('include/channel.php');


class Externals {

	static public function run($argc, $argv) {

		logger('externals: start');

		$importer = get_sys_channel();
		$total    = 0;
		$attempts = 0;

		logger('externals: startup', LOGGER_DEBUG);

		// pull in some public posts

		while ($total == 0 && $attempts < 3) {
			$arr = ['url' => ''];
			call_hooks('externals_url_select', $arr);

			if ($arr['url']) {
				$url = $arr['url'];
			}
			else {
				$networks = ['zot6'];

				if (plugin_is_installed('pubcrawl')) {
					$networks[] = 'activitypub';
				}

				stringify_array_elms($networks);
				$networks_str = implode(',', $networks);

				$randfunc = db_getfunc('RAND');

				// fixme this query does not deal with directory realms.
				//$r = q("select site_url, site_pull from site where site_url != '%s'
						//and site_flags != %d and site_type = %d
						//and site_dead = 0 and site_project like '%s' and site_version > '5.3.1' order by $randfunc limit 1",
					//dbesc(z_root()),
					//intval(DIRECTORY_MODE_STANDALONE),
					//intval(SITE_TYPE_ZOT),
					//dbesc('hubzilla%')
				//);

				$r = q("SELECT * FROM hubloc
					LEFT JOIN abook ON abook_xchan = hubloc_hash
					LEFT JOIN site ON site_url = hubloc_url WHERE
					hubloc_network IN ( $networks_str ) AND
					abook_xchan IS NULL AND
					hubloc_url != '%s' AND
					hubloc_updated > '%s' AND
					hubloc_primary = 1 AND hubloc_deleted = 0 AND
					site_dead = 0
					ORDER BY $randfunc LIMIT 1",
					dbesc(z_root()),
					datetime_convert('UTC', 'UTC', 'now - 30 days')
				);

				$contact = $r[0];

				if ($contact) {
					$url = $contact['hubloc_id_url'];
				}
			}

			if (!$url) {
				continue;
			}

			$blacklisted = false;

			if (!check_siteallowed($contact['hubloc_url'])) {
				logger('blacklisted site: ' . $url);
				$blacklisted = true;
			}

			$attempts++;

			// make sure we can eventually break out if somebody blacklists all known sites

			if ($blacklisted) {
				if ($attempts > 5)
					break;
				$attempts--;
				continue;
			}

			$cl = Activity::get_actor_collections($contact['hubloc_hash']);
			if(empty($cl)) {
				$cl = get_xconfig($contact['hubloc_hash'], 'activitypub', 'collections');
			}

			if (is_array($cl) && array_key_exists('outbox', $cl)) {
				$url = $cl['outbox'];
			}
			else {
				$url = str_replace('/channel/', '/outbox/', $contact['hubloc_id_url']);
				if ($url) {
					$url .= '?top=1';
				}
			}

			if ($url) {
				logger('fetching outbox: ' . $url);

				$obj      = new ASCollection($url, $importer, 0, 10);
				$messages = $obj->get();

				if ($messages) {
					foreach ($messages as $message) {
						if (is_string($message)) {
							$message = Activity::fetch($message, $importer);
						}

						if ($message['type'] !== 'Create') {
							continue;
						}

						if ($contact['hubloc_network'] === 'zot6') {
							// make sure we only fetch top level items
							if (isset($message['object']['inReplyTo'])) {
								continue;
							}

							$obj_id = isset($message['object']['id']) ?? $message['object'];

							Libzot::fetch_conversation($importer, $obj_id);
							$total++;
							continue;
						}

						$AS = new ActivityStreams($message);
						if ($AS->is_valid() && is_array($AS->obj)) {
							$item = Activity::decode_note($AS);
							Activity::store($importer, $contact['abook_xchan'], $AS, $item);
							$total++;
						}
					}
				}
				logger('fetched messages count: ' . $total);
			}
		}
		return;
	}
}
