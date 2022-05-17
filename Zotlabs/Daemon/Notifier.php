<?php /** @file */

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Queue;
use Zotlabs\Lib\LDSignatures;

require_once('include/html2plain.php');
require_once('include/conversation.php');
require_once('include/items.php');
require_once('include/bbcode.php');


/*
 * Notifier - message dispatch and preparation for delivery
 *
 * The basic flow is:
 *   Identify the type of message
 *   Collect any information that needs to be sent
 *   Convert it into a suitable generic format for sending
 *   Figure out who the recipients are and if we need to relay
 *       through a conversation owner
 *   Once we know what recipients are involved, collect a list of
 *       destination sites
 *   Build and store a queue item for each unique site and invoke
 *       a delivery process for each site or a small number of sites (1-3)
 *       and add a slight delay between each delivery invocation if desired (usually)
 *
 */

/*
 * The notifier is typically called with:
 *
 *		Zotlabs\Daemon\Master::Summon(array('Notifier', COMMAND, ITEM_ID));
 *
 * where COMMAND is one of the following:
 *
 *	activity		(in diaspora.php, dfrn_confirm.php, profiles.php)
 *	comment-import		(in diaspora.php, items.php)
 *	comment-new		(in item.php)
 *	drop			(in diaspora.php, items.php, photos.php)
 *	edit_post		(in item.php)
 *	event			(in events.php)
 *	expire			(in items.php)
 *	like			(in like.php, poke.php)
 *	tag			(in photos.php, poke.php, tagger.php)
 *	tgroup			(in items.php)
 *	wall-new		(in photos.php, item.php)
 *
 * and ITEM_ID is the id of the item in the database that needs to be sent to others.
 *
 * ZOT
 *       permission_create      abook_id
 *       permission_accept      abook_id
 *       permission_update      abook_id
 *       refresh_all            channel_id
 *       purge                  channel_id            xchan_hash
 *       purge_all              channel_id
 *       expire                 channel_id
 *       relay			item_id (item was relayed to owner, we will deliver it as owner)
 *       single_activity        item_id (deliver to a singleton network from the appropriate clone)
 *       location               channel_id
 *       request                channel_id            xchan_hash             message_id
 *       rating                 xlink_id
 *       keychange              channel_id
 *
 */


class Notifier {

	static public $deliveries = [];
	static public $recipients = [];
	static public $env_recips = [];
	static public $packet_type = 'activity';
	static public $encoding = 'activitystreams';
	static public $encoded_item = null;
	static public $channel = null;
	static public $private = false;
	// $fragment can contain additional info to omit de-duplication in the queueworker.
	// E.g. if an item is updated many times in a row from different sources (multiple vote updates) the
	// update source mid or a timestamp or random string can be added.
	static public $fragment = null;

	static public function run($argc, $argv) {

		if ($argc < 3) {
			return;
		}

		logger('notifier: invoked: ' . print_r($argv, true), LOGGER_DEBUG);

		$cmd = $argv[1];
		$item_id = $argv[2];

		if (!$item_id) {
			return;
		}

		self::$deliveries   = [];
		self::$recipients   = [];
		self::$env_recips   = [];
		self::$packet_type  = 'activity';
		self::$encoding     = 'activitystreams';
		self::$encoded_item = null;
		self::$channel      = null;
		self::$private      = false;
		self::$fragment     = null;

		$sys         = get_sys_channel();
		$normal_mode = true;

		if ($cmd === 'keychange') {
			self::$channel = channelx_by_n($item_id);
			$r             = q("select abook_xchan from abook where abook_channel = %d",
				intval($item_id)
			);
			if ($r) {
				foreach ($r as $rr) {
					self::$recipients[] = $rr['abook_xchan'];
				}
			}
			self::$private      = false;
			self::$packet_type  = 'keychange';
			self::$encoded_item = get_pconfig(self::$channel['channel_id'], 'system', 'keychange');
			self::$encoding     = 'zot';
			$normal_mode        = false;
		}
		elseif (in_array($cmd, ['permission_update', 'permission_accept', 'permission_create'])) {
			// Get the (single) recipient
			$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d and abook_self = 0",
				intval($item_id)
			);
			if ($r) {
				$recip = $r[0];

				// Get the sender
				self::$channel = channelx_by_n($recip['abook_channel']);
				if (self::$channel) {
					$perm_update = ['sender' => self::$channel, 'recipient' => $recip, 'success' => false, 'deliveries' => ''];

					switch ($cmd) {
						case 'permission_create':
							call_hooks('permissions_create', $perm_update);
							break;
						case 'permission_accept':
							call_hooks('permissions_accept', $perm_update);
							break;
						case 'permission_update':
							call_hooks('permissions_update', $perm_update);
							break;
						default:
							break;
					}

					if ($perm_update['success']) {
						if ($perm_update['deliveries']) {
							self::$deliveries[] = $perm_update['deliveries'];
							do_delivery(self::$deliveries);
						}
						return;
					}
					else {
						self::$recipients[] = $recip['abook_xchan'];
						self::$private      = false;
						self::$packet_type  = 'refresh';
						self::$env_recips   = [$recip['xchan_hash']];
					}
				}
			}
		}
		elseif ($cmd === 'refresh_all') {
			logger('notifier: refresh_all: ' . $item_id);

			self::$channel = channelx_by_n($item_id, true);

			$r = q("select abook_xchan from abook where abook_channel = %d",
				intval($item_id)
			);
			if ($r) {
				foreach ($r as $rr) {
					self::$recipients[] = $rr['abook_xchan'];
				}
			}

			// In case we deleted the channel, our abook entry has already vanished.
			// In order to be able to update our clones we need to add ourself here.
			self::$recipients[] = self::$channel['channel_hash'];

			self::$private     = false;
			self::$packet_type = 'refresh';
		}
		elseif ($cmd === 'purge') {
			$xchan = $argv[3];
			logger('notifier: purge: ' . $item_id . ' => ' . $xchan);
			if (!$xchan) {
				return;
			}

			self::$channel     = channelx_by_n($item_id, true);
			self::$recipients  = [$xchan];
			self::$private     = true;
			self::$packet_type = 'purge';
		}
		elseif ($cmd === 'purge_all') {
			logger('notifier: purge_all: ' . $item_id);
			self::$channel     = channelx_by_n($item_id, true);
			self::$recipients  = [];
			self::$private     = false;
			self::$packet_type = 'purge';

			$r = q("select abook_xchan from abook where abook_channel = %d and abook_self = 0",
				intval($item_id)
			);
			if (!$r) {
				return;
			}
			foreach ($r as $rr) {
				self::$recipients[] = $rr['abook_xchan'];
			}
		}
		else {

			// Normal items

			// Fetch the target item

			self::$fragment = $argv[3] ?? '';

			$r = q("SELECT * FROM item WHERE id = %d AND parent != 0",
				intval($item_id)
			);
			if (!$r) {
				return;
			}

			xchan_query($r);
			$r = fetch_post_tags($r);

			$target_item = $r[0];

			if (in_array($target_item['author']['xchan_network'], ['rss', 'anon', 'token'])) {
				logger('notifier: target item author is not a fetchable actor', LOGGER_DEBUG);
				return;
			}

			if (intval($target_item['item_deleted'])) {
				logger('notifier: target item ITEM_DELETED', LOGGER_DEBUG);
			}

			if (!in_array(intval($target_item['item_type']), [ITEM_TYPE_POST])) {
				$hookinfo = [
					'targetitem' => $target_item,
					'deliver'    => false
				];
				if (intval($target_item['item_type'] == ITEM_TYPE_CUSTOM)) {
					call_hooks('customitem_deliver', $hookinfo);
				}

				if (!$hookinfo['deliver']) {
					logger('notifier: target item not forwardable: type ' . $target_item['item_type'], LOGGER_DEBUG);
					return;
				}

				$target_item = $hookinfo['targetitem'];

			}

			// Check for non published items, but allow an exclusion for transmitting hidden file activities

			if (intval($target_item['item_unpublished']) || intval($target_item['item_delayed']) ||
				intval($target_item['item_blocked']) ||
				(intval($target_item['item_hidden']) && ($target_item['obj_type'] !== ACTIVITY_OBJ_FILE))) {
				logger('notifier: target item not published, so not forwardable', LOGGER_DEBUG);
				return;
			}

			// follow/unfollow is for internal use only
			if (in_array($target_item['verb'], [ACTIVITY_FOLLOW, ACTIVITY_UNFOLLOW])) {
				logger('not fowarding follow/unfollow note activity');
				return;
			}

			if (strpos($target_item['postopts'], 'nodeliver') !== false) {
				logger('notifier: target item is undeliverable', LOGGER_DEBUG);
				return;
			}

			$s = q("select * from channel left join xchan on channel_hash = xchan_hash where channel_id = %d limit 1",
				intval($target_item['uid'])
			);
			if ($s) {
				self::$channel = $s[0];
			}

			if (self::$channel['channel_hash'] !== $target_item['author_xchan'] && self::$channel['channel_hash'] !== $target_item['owner_xchan']) {
				logger("notifier: Sending channel " . self::$channel['channel_hash'] . " is not owner {$target_item['owner_xchan']} or author {$target_item['author_xchan']}", LOGGER_NORMAL, LOG_WARNING);
				return;
			}

			if ($target_item['mid'] === $target_item['parent_mid']) {
				$parent_item    = $target_item;
				$top_level_post = true;
			}
			else {
				// fetch the parent item
				$r = q("SELECT * FROM item WHERE id = %d",
					intval($target_item['parent'])
				);

				if (!$r) {
					return;
				}

				if (strpos($r[0]['postopts'], 'nodeliver') !== false) {
					logger('notifier: target item is undeliverable', LOGGER_DEBUG, LOG_NOTICE);
					return;
				}

				xchan_query($r);
				$r = fetch_post_tags($r);

				$parent_item    = $r[0];
				$top_level_post = false;
			}

			// avoid looping of discover items 12/4/2014
			if ($sys && $parent_item['uid'] == $sys['channel_id']) {
				return;
			}

			$m = get_iconfig($target_item, 'activitypub', 'signed_data');
			// Re-use existing signature unless the activity type changed to a Tombstone, which won't verify.
			if ($m && (!intval($target_item['item_deleted']))) {
				self::$encoded_item = json_decode($m, true);
			}
			else {

				self::$encoded_item = array_merge(['@context' => [
					ACTIVITYSTREAMS_JSONLD_REV,
					'https://w3id.org/security/v1',
					z_root() . ZOT_APSCHEMA_REV
				]], Activity::encode_activity($target_item)
				);
				self::$encoded_item['signature'] = LDSignatures::sign(self::$encoded_item, self::$channel);
			}

			logger('target_item: ' . print_r($target_item, true), LOGGER_DEBUG);
			logger('encoded: ' . print_r(self::$encoded_item, true), LOGGER_DEBUG);

			// Send comments to the owner to re-deliver to everybody in the conversation
			// We only do this if the item in question originated on this site. This prevents looping.
			// To clarify, a site accepting a new comment is responsible for sending it to the owner for relay.
			// Relaying should never be initiated on a post that arrived from elsewhere.

			// We should normally be able to rely on ITEM_ORIGIN, but start_delivery_chain() incorrectly set this
			// flag on comments for an extended period. So we'll also call comment_local_origin() which looks at
			// the hostname in the message_id and provides a second (fallback) opinion.

			$relay_to_owner = (!$top_level_post && intval($target_item['item_origin']) && comment_local_origin($target_item));
			$uplink         = false;
			$upstream       = false;

			// $cmd === 'relay' indicates the owner is sending it to the original recipients
			// don't allow the item in the relay command to relay to owner under any circumstances, it will loop

			logger('notifier: relay_to_owner: ' . (($relay_to_owner) ? 'true' : 'false'), LOGGER_DATA, LOG_DEBUG);
			logger('notifier: top_level_post: ' . (($top_level_post) ? 'true' : 'false'), LOGGER_DATA, LOG_DEBUG);

			// tag_deliver'd post which needs to be sent back to the original author

			if (($cmd === 'uplink') && intval($parent_item['item_uplink']) && (!$top_level_post)) {
				logger('notifier: uplink');
				$uplink            = true;
				self::$packet_type = 'response';
			}

			if (($relay_to_owner || $uplink) && ($cmd !== 'relay')) {
				logger('notifier: followup relay', LOGGER_DEBUG);
				$sendto            = (($uplink) ? $parent_item['source_xchan'] : $parent_item['owner_xchan']);
				self::$recipients  = [$sendto];
				self::$private     = true;
				$upstream          = true;
				self::$packet_type = 'response';
			}
			else {
				if ($cmd === 'relay') {
					logger('owner relay (downstream delivery)');
				}
				else {
					logger('normal (downstream) distribution', LOGGER_DEBUG);
				}

				if ($parent_item && $parent_item['item_private'] !== $target_item['item_private']) {
					logger('conversation privacy mismatch - downstream delivery prevented');
					return;
				}

				// if our parent is a tag_delivery recipient, uplink to the original author causing
				// a delivery fork.
				if ($parent_item && intval($parent_item['item_uplink']) && !$top_level_post && $cmd !== 'uplink') {
					// don't uplink a relayed post to the relay owner
					if ($parent_item['source_xchan'] !== $parent_item['owner_xchan']) {
						logger('notifier: uplinking this item');
						Master::Summon(['Notifier', 'uplink', $item_id]);
					}
				}

				self::$private    = false;
				self::$recipients = collect_recipients($parent_item, self::$private);

				// FIXME add any additional recipients such as mentions, etc.

				if ($top_level_post) {
					// remove clones who will receive the post via sync
					self::$recipients = array_values(array_diff(self::$recipients, [$target_item['owner_xchan']]));
				}

				// don't send deletions onward for other people's stuff
				if (intval($target_item['item_deleted']) && (!intval($target_item['item_wall']))) {
					logger('notifier: ignoring delete notification for non-wall item', LOGGER_NORMAL, LOG_NOTICE);
					return;
				}
			}
		}

		// Generic delivery section, we have an encoded item and recipients
		// Now start the delivery process

		logger('encoded item: ' . print_r(self::$encoded_item, true), LOGGER_DATA, LOG_DEBUG);

		stringify_array_elms(self::$recipients);
		if (!self::$recipients) {
			logger('no recipients');
			return;
		}

		// logger('recipients: ' . print_r(self::$recipients,true), LOGGER_NORMAL, LOG_DEBUG);

		if (!count(self::$env_recips)) {
			self::$env_recips = ((self::$private) ? [] : null);
		}

		$recip_list = [];

		$details = dbq("select xchan_hash, xchan_network, xchan_addr, xchan_guid, xchan_guid_sig from xchan where xchan_hash in (" . protect_sprintf(implode(',', self::$recipients)) . ")");

		if ($details) {
			foreach ($details as $d) {
				$recip_list[] = $d['xchan_addr'] . ' (' . $d['xchan_hash'] . ')';
				if (self::$private) {
					self::$env_recips[] = $d['xchan_hash'];
				}
			}
		}

		$narr = [
			'channel'        => self::$channel,
			'upstream'       => $upstream,
			'env_recips'     => self::$env_recips,
			'recipients'     => self::$recipients,
			'target_item'    => $target_item,
			'parent_item'    => $parent_item,
			'top_level_post' => $top_level_post,
			'private'        => self::$private,
			'relay_to_owner' => $relay_to_owner,
			'uplink'         => $uplink,
			'cmd'            => $cmd,
			'single'         => ($cmd === 'single_activity'),
			'normal_mode'    => $normal_mode,
			'packet_type'    => self::$packet_type,
			'queued'         => []
		];

		call_hooks('notifier_process', $narr);
		if ($narr['queued']) {
			foreach ($narr['queued'] as $pq)
				self::$deliveries[] = $pq;
		}

		// notifier_process can alter the recipient list

		self::$recipients = $narr['recipients'];
		self::$env_recips = $narr['env_recips'];

		if (self::$private && !self::$env_recips) {
			// shouldn't happen
			logger('notifier: private message with no envelope recipients.' . print_r($argv, true), LOGGER_NORMAL, LOG_NOTICE);
			return;
		}

		logger('notifier: recipients (may be delivered to more if public): ' . print_r($recip_list, true), LOGGER_DEBUG);


		// Now we have collected recipients (except for external mentions, FIXME)
		// Let's reduce this to a set of hubs; checking that the site is not dead.

		$hubs = dbq("select hubloc.*, site.site_crypto, site.site_flags, site.site_dead from hubloc left join site on site_url = hubloc_url
			where hubloc_hash in (" . protect_sprintf(implode(',', self::$recipients)) . ")
			and hubloc_error = 0 and hubloc_deleted = 0"
		);

		// public posts won't make it to the local public stream unless there's a recipient on this site.
		// This code block sees if it's a public post and localhost is missing, and if so adds an entry for the local sys channel to the $hubs list

		if (!self::$private) {
			$found_localhost = false;
			if ($hubs) {
				foreach ($hubs as $h) {
					if ($h['hubloc_url'] === z_root()) {
						$found_localhost = true;
						break;
					}
				}
			}
			if (!$found_localhost) {
				$localhub = q("select hubloc.*, site.site_crypto, site.site_flags, site.site_dead from hubloc left join site on site_url = hubloc_url
				 	where hubloc_id_url = '%s' and hubloc_error = 0 and hubloc_deleted = 0",
					dbesc(z_root() . '/channel/sys')
				);
				if ($localhub) {
					$hubs = array_merge($localhub, $hubs);
				}
			}
		}

		if (!$hubs) {
			logger('notifier: no hubs', LOGGER_NORMAL, LOG_NOTICE);
			return;
		}

		/**
		 * Reduce the hubs to those that are unique. For zot hubs, we need to verify uniqueness by the sitekey,
		 * since it may have been a re-install which has not yet been detected and pruned.
		 * For other networks which don't have or require sitekeys, we'll have to use the URL
		 */


		$hublist    = []; // this provides an easily printable list for the logs
		$dhubs      = []; // delivery hubs where we store our resulting unique array
		$keys       = []; // array of keys to check uniquness for zot hubs
		$urls       = []; // array of urls to check uniqueness of hubs from other networks
		$hub_env    = []; // per-hub envelope so we don't broadcast the entire envelope to all
		$dead_hosts = []; // known dead hubs - report them as undeliverable

		foreach ($hubs as $hub) {
			if (isset($hub['site_dead']) && intval($hub['site_dead'])) {
				if(!in_array($hub['hubloc_host'], $dead_hosts)) {
					$dead_hosts[] = $hub['hubloc_host'];
				}
				continue;
			}

			if (self::$env_recips) {
				foreach (self::$env_recips as $er) {
					if ($hub['hubloc_hash'] === $er) {
						if (!array_key_exists($hub['hubloc_site_id'], $hub_env)) {
							$hub_env[$hub['hubloc_site_id']] = [];
						}
						if (!in_array($er, $hub_env[$hub['hubloc_site_id']])) {
							$hub_env[$hub['hubloc_site_id']][] = $er;
						}
					}
				}
			}

			if ($hub['hubloc_network'] === 'zot6') {
				if (!in_array($hub['hubloc_sitekey'], $keys)) {
					if ($hub['hubloc_url'] === z_root()) {
						//deliver to local hub first
						array_unshift($hublist, $hub['hubloc_host'] . ' ' . $hub['hubloc_network']);
						array_unshift($dhubs, $hub);
					}
					else {
						$hublist[] = $hub['hubloc_host'] . ' ' . $hub['hubloc_network'];
						$dhubs[]   = $hub;
					}
					$keys[] = $hub['hubloc_sitekey'];
				}
			}
			else {
				if (!in_array($hub['hubloc_url'], $urls)) {
					$hublist[] = $hub['hubloc_host'] . ' ' . $hub['hubloc_network'];
					$dhubs[]   = $hub;
					$urls[]    = $hub['hubloc_url'];
				}
			}
		}

		logger('notifier: will notify/deliver to these hubs: ' . print_r($hublist, true), LOGGER_DEBUG, LOG_DEBUG);

		foreach ($dhubs as $hub) {

			logger('notifier_hub: ' . $hub['hubloc_url'], LOGGER_DEBUG);

			if ($hub['hubloc_network'] !== 'zot6') {
				$narr = [
					'channel'        => self::$channel,
					'upstream'       => $upstream,
					'env_recips'     => self::$env_recips,
					'recipients'     => self::$recipients,
					'target_item'    => $target_item,
					'parent_item'    => $parent_item,
					'hub'            => $hub,
					'top_level_post' => $top_level_post,
					'private'        => self::$private,
					'relay_to_owner' => $relay_to_owner,
					'uplink'         => $uplink,
					'cmd'            => $cmd,
					'single'         => $cmd === 'single_activity',
					'normal_mode'    => $normal_mode,
					'packet_type'    => self::$packet_type,
					'queued'         => []
				];

				call_hooks('notifier_hub', $narr);
				if ($narr['queued']) {
					foreach ($narr['queued'] as $pq)
						self::$deliveries[] = $pq;
				}
				continue;

			}

			// singleton deliveries by definition 'not got zot'.
			// Single deliveries are other federated networks (plugins) and we're essentially
			// delivering only to those that have this site url in their abook_instance
			// and only from within a sync operation. This means if you post from a clone,
			// and a connection is connected to one of your other clones; assuming that hub
			// is running it will receive a sync packet. On receipt of this sync packet it
			// will invoke a delivery to those connections which are connected to just that
			// hub instance.

			if ($cmd === 'single_activity') {
				continue;
			}

			// default: zot protocol

			// Prevent zot6 delivery of group comment boosts, which are not required for conversational platforms.
			// ActivityPub conversational platforms may wish to filter these if they don't want or require them.
			// We will assume here that if $target_item exists and has a verb that it is an actual item structure
			// so we won't need to check the existence of the other item fields prior to evaluation.

			// This shouldn't produce false positives on comment boosts that were generated on other platforms
			// because we won't be delivering them.

			if (isset($target_item) && isset($target_item['verb']) && $target_item['verb'] === 'Announce' && $target_item['author_xchan'] === $target_item['owner_xchan'] && ! intval($target_item['item_thread_top'])) {
				continue;
			}

			$hash = new_uuid();

			$env = (($hub_env && $hub_env[$hub['hubloc_site_id']]) ? $hub_env[$hub['hubloc_site_id']] : '');
			if ((self::$private) && (!$env)) {
				continue;
			}

			$packet = Libzot::build_packet(self::$channel, self::$packet_type, $env, self::$encoded_item, self::$encoding, ((self::$private) ? $hub['hubloc_sitekey'] : null), $hub['site_crypto']);

			Queue::insert(
				[
					'hash'       => $hash,
					'account_id' => self::$channel['channel_account_id'],
					'channel_id' => self::$channel['channel_id'],
					'posturl'    => $hub['hubloc_callback'],
					'notify'     => $packet,
					'msg'        => EMPTY_STR
				]
			);

			// only create delivery reports for normal undeleted items
			if (is_array($target_item) && (!$target_item['item_deleted']) && (!get_config('system', 'disable_dreport'))) {
				q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_name, dreport_result, dreport_time, dreport_xchan, dreport_queue )
					values ( '%s', '%s','%s','%s','%s','%s','%s','%s' ) ",
					dbesc($target_item['mid']),
					dbesc($hub['hubloc_host']),
					dbesc($hub['hubloc_host']),
					dbesc($hub['hubloc_host']),
					dbesc('queued'),
					dbesc(datetime_convert()),
					dbesc(self::$channel['channel_hash']),
					dbesc($hash)
				);
			}

			self::$deliveries[] = $hash;

		}

		if ($normal_mode) {
			// This wastes a process if there are no delivery hooks configured, so check this before launching the new process
			$x = q("select * from hook where hook = 'notifier_normal'");
			if ($x) {
				Master::Summon(['Deliver_hooks', $target_item['id'], self::$fragment]);
			}
		}

		if (self::$deliveries) {
			do_delivery(self::$deliveries);
		}

		if ($dead_hosts && is_array($target_item) && (!$target_item['item_deleted']) && (!get_config('system', 'disable_dreport'))) {
			foreach ($dead_hosts as $deceased_host) {
				$r = q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_name, dreport_result, dreport_time, dreport_xchan, dreport_queue )
					values ( '%s', '%s','%s','%s','%s','%s','%s','%s' ) ",
					dbesc($target_item['mid']),
					dbesc($deceased_host),
					dbesc($deceased_host),
					dbesc($deceased_host),
					dbesc('undeliverable/unresponsive site'),
					dbesc(datetime_convert()),
					dbesc(self::$channel['channel_hash']),
					dbesc(new_uuid())
				);
			}
		}

		call_hooks('notifier_end', $target_item);

		logger('notifer: complete.');

		return;

	}
}

