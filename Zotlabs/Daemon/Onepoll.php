<?php /** @file */

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\ASCollection;
use Zotlabs\Lib\Libzot;

require_once('include/socgraph.php');
require_once('include/feedutils.php');

class Onepoll {

	static public function run($argc, $argv) {

		logger('onepoll: start');

		if (($argc > 1) && (intval($argv[1])))
			$contact_id = intval($argv[1]);

		if (!$contact_id) {
			logger('onepoll: no contact');
			return;
		}

		$sql_extra = '';
		$allow_feeds = get_config('system', 'feed_contacts');
		if(!$allow_feeds) {
			$sql_extra = ' and abook_feed = 0 ';
		}

		$contacts = q("SELECT abook.*, xchan.*, account.*
			FROM abook LEFT JOIN account on abook_account = account_id left join xchan on xchan_hash = abook_xchan
			where abook_id = %d $sql_extra
			and abook_pending = 0 and abook_archived = 0 and abook_blocked = 0 and abook_ignored = 0
			AND (( account_flags = %d ) OR ( account_flags = %d )) limit 1",
			intval($contact_id),
			intval(ACCOUNT_OK),
			intval(ACCOUNT_UNVERIFIED)
		);

		if (!$contacts) {
			logger('onepoll: abook_id not found: ' . $contact_id);
			return;
		}

		$contact      = $contacts[0];
		$importer_uid = $contact['abook_channel'];

		$importer = channelx_by_n($importer_uid);

		if (!$importer)
			return;

		logger("onepoll: poll: ({$contact['id']}) IMPORTER: {$importer['xchan_name']}, CONTACT: {$contact['xchan_name']}");

		$last_update = ((($contact['abook_updated'] === $contact['abook_created']) || ($contact['abook_updated'] <= NULL_DATE))
			? datetime_convert('UTC', 'UTC', 'now - 7 days')
			: datetime_convert('UTC', 'UTC', $contact['abook_updated'] . ' - 2 days')
		);

		if ($contact['xchan_network'] === 'rss') {
			logger('onepoll: processing feed ' . $contact['xchan_name'], LOGGER_DEBUG);
			$alive = handle_feed($importer['channel_id'], $contact_id, $contact['xchan_hash']);
			if ($alive) {
				q("update abook set abook_connected = '%s' where abook_id = %d",
					dbesc(datetime_convert()),
					intval($contact['abook_id'])
				);
			}
			return;
		}

		if ($contact['xchan_network'] !== 'zot6')
			return;

		// update permissions

		$x = Libzot::refresh($contact, $importer);

		$responded = false;
		$updated   = datetime_convert();
		$connected = datetime_convert();
		if (!$x) {
			// mark for death by not updating abook_connected, this is caught in include/poller.php
			q("update abook set abook_updated = '%s' where abook_id = %d",
				dbesc($updated),
				intval($contact['abook_id'])
			);
		}
		else {
			q("update abook set abook_updated = '%s', abook_connected = '%s' where abook_id = %d",
				dbesc($updated),
				dbesc($connected),
				intval($contact['abook_id'])
			);
			$responded = true;
		}

		if (!$responded)
			return;

		$fetch_feed = true;

		// They haven't given us permission to see their stream
		$can_view_stream = intval(get_abconfig($importer_uid, $contact['abook_xchan'], 'their_perms', 'view_stream'));

		if (!$can_view_stream) {
			$fetch_feed = false;
		}

		// we haven't given them permission to send us their stream
		$can_send_stream = intval(get_abconfig($importer_uid, $contact['abook_xchan'], 'my_perms', 'send_stream'));

		if (!$can_send_stream) {
			$fetch_feed = false;
		}

		if ($fetch_feed) {

			$max = intval(get_config('system', 'max_imported_posts', 30));

			if (intval($max)) {
				$cl = Activity::get_actor_collections($contact['abook_xchan']);
				if(empty($cl)) {
					$cl = get_xconfig($contact['abook_xchan'], 'activitypub', 'collections');
				}

				if (is_array($cl) && array_key_exists('outbox', $cl)) {
					$url = $cl['outbox'];
				}
				else {
					$url = str_replace('/poco/', '/outbox/', $contact['xchan_connurl']);
				}

				if ($url) {
					logger('fetching outbox');
					$url = $url . '?date_begin=' .  urlencode($last_update);

					if($contact['xchan_network'] === 'zot6') {
						$url = $url . '&top=1';
					}

					$obj      = new ASCollection($url, $importer, 0, $max);
					$messages = $obj->get();

					if ($messages) {
						foreach ($messages as $message) {
							if (is_string($message)) {
								$message = Activity::fetch($message, $importer);
							}

							if ($contact['xchan_network'] === 'zot6') {
								// make sure we only fetch top level items
								if ($message['type'] === 'Create' && !isset($message['object']['inReplyTo'])) {
									Libzot::fetch_conversation($importer, $message['object']['id']);
								}
								continue;
							}

							$AS = new ActivityStreams($message);
							if ($AS->is_valid() && is_array($AS->obj)) {
								$item = Activity::decode_note($AS);
								Activity::store($importer, $contact['abook_xchan'], $AS, $item);
							}
						}
					}
				}
			}
		}

		// update the poco details for this connection
		$r = q("SELECT xlink_id from xlink where xlink_xchan = '%s' and xlink_updated > %s - INTERVAL %s and xlink_static = 0 limit 1",
			intval($contact['xchan_hash']),
			db_utcnow(),
			db_quoteinterval('1 DAY')
		);

		if (!$r) {
			poco_load($contact['xchan_hash'], $contact['xchan_connurl']);
		}

		return;
	}
}
