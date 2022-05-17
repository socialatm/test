<?php /** @file */

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Libsync;

class Cron {

	static public function run($argc, $argv) {

		$maxsysload = intval(get_config('system', 'maxloadavg'));
		if ($maxsysload < 1)
			$maxsysload = 50;
		if (function_exists('sys_getloadavg')) {
			$load = sys_getloadavg();
			if (intval($load[0]) > $maxsysload) {
				logger('system: load ' . $load . ' too high. Cron deferred to next scheduled run.');
				return;
			}
		}

		// Check for a lockfile.  If it exists, but is over an hour old, it's stale.  Ignore it.
		$lockfile = 'store/[data]/cron';
		if ((file_exists($lockfile)) && (filemtime($lockfile) > (time() - 3600))
			&& (!get_config('system', 'override_cron_lockfile'))) {
			logger("cron: Already running");
			return;
		}

		// Create a lockfile.  Needs two vars, but $x doesn't need to contain anything.
		$x = '';
		file_put_contents($lockfile, $x);

		logger('cron: start');

		// run queue delivery process in the background

		Master::Summon(array('Queue'));
		Master::Summon(array('Poller'));

		/**
		 * Chatpresence: if somebody hasn't pinged recently, they've most likely left the page
		 * and shouldn't count as online anymore. We allow an expection for bots.
		 */
		q("delete from chatpresence where cp_last < %s - INTERVAL %s and cp_client != 'auto' ",
			db_utcnow(),
			db_quoteinterval('3 MINUTE')
		);

		require_once('include/account.php');
		remove_expired_registrations();

		$interval = get_config('system', 'delivery_interval', 3);

		// expire any expired items

		$r = q("select id,item_wall from item where expires > '2001-01-01 00:00:00' and expires < %s
			and item_deleted = 0 ",
			db_utcnow()
		);
		if ($r) {
			require_once('include/items.php');
			foreach ($r as $rr) {
				drop_item($rr['id'], false, (($rr['item_wall']) ? DROPITEM_PHASE1 : DROPITEM_NORMAL));
				if ($rr['item_wall']) {
					// The notifier isn't normally invoked unless item_drop is interactive.
					Master::Summon(['Notifier', 'drop', $rr['id']]);
					if ($interval)
						@time_sleep_until(microtime(true) + (float)$interval);
				}
			}
		}


		// delete expired access tokens

		$r = q("select atoken_id from atoken where atoken_expires > '%s' and atoken_expires < %s",
			dbesc(NULL_DATE),
			db_utcnow()
		);
		if ($r) {
			require_once('include/security.php');
			foreach ($r as $rr) {
				atoken_delete($rr['atoken_id']);
			}
		}

		// Ensure that every channel pings a directory server once a month. This way we can discover
		// channels and sites that quietly vanished and prevent the directory from accumulating stale
		// or dead entries.

		$r = q("select channel_id from channel where channel_dirdate < %s - INTERVAL %s and channel_removed = 0",
			db_utcnow(),
			db_quoteinterval('30 DAY')
		);
		if ($r) {
			foreach ($r as $rr) {
				Master::Summon(array('Directory', $rr['channel_id'], 'force'));
				if ($interval)
					@time_sleep_until(microtime(true) + (float)$interval);
			}
		}

		// Clean expired photos from cache

		$r = q("SELECT DISTINCT xchan, content FROM photo WHERE photo_usage = %d AND expires < %s - INTERVAL %s",
			intval(PHOTO_CACHE),
			db_utcnow(),
			db_quoteinterval(get_config('system', 'active_expire_days', '30') . ' DAY')
		);
		if ($r) {
			q("DELETE FROM photo WHERE photo_usage = %d AND expires < %s - INTERVAL %s",
				intval(PHOTO_CACHE),
				db_utcnow(),
				db_quoteinterval(get_config('system', 'active_expire_days', '30') . ' DAY')
			);
			foreach ($r as $rr) {
				$file = dbunescbin($rr['content']);
				if (is_file($file)) {
					@unlink($file);
					@rmdir(dirname($file));
					logger('info: deleted cached photo file ' . $file, LOGGER_DEBUG);
				}
			}
		}

		// publish any applicable items that were set to be published in the future
		// (time travel posts). Restrict to items that have come of age in the last
		// couple of days to limit the query to something reasonable.

		$r = q("select id from item where item_delayed = 1 and created <= %s  and created > '%s' ",
			db_utcnow(),
			dbesc(datetime_convert('UTC', 'UTC', 'now - 2 days'))
		);
		if ($r) {
			foreach ($r as $rr) {
				$x = q("update item set item_delayed = 0 where id = %d",
					intval($rr['id'])
				);
				if ($x) {
					$z = q("select * from item where id = %d",
						intval($rr['id'])
					);
					if ($z) {
						xchan_query($z);
						$sync_item = fetch_post_tags($z);
						Libsync::build_sync_packet($sync_item[0]['uid'],
							[
								'item' => [encode_item($sync_item[0], true)]
							]
						);
					}
					Master::Summon(array('Notifier', 'wall-new', $rr['id']));
					if ($interval)
						@time_sleep_until(microtime(true) + (float)$interval);
				}
			}
		}

		require_once('include/attach.php');
		attach_upgrade();

		// once daily run birthday_updates and then expire in background

		// FIXME: add birthday updates, both locally and for xprof for use
		// by directory servers

		$d1 = intval(get_config('system', 'last_expire_day'));
		$d2 = intval(datetime_convert('UTC', 'UTC', 'now', 'd'));

		// Allow somebody to staggger daily activities if they have more than one site on their server,
		// or if it happens at an inconvenient (busy) hour.

		$h1 = intval(get_config('system', 'cron_hour'));
		$h2 = intval(datetime_convert('UTC', 'UTC', 'now', 'G'));


		if (($d2 != $d1) && ($h1 == $h2)) {
			Master::Summon(array('Cron_daily'));
		}

		// update any photos which didn't get imported properly
		// This should be rare

		$r = q("select xchan_photo_l, xchan_hash from xchan where xchan_photo_l != '' and xchan_photo_m = ''
			and xchan_photo_date < %s - INTERVAL %s",
			db_utcnow(),
			db_quoteinterval('1 DAY')
		);
		if ($r) {
			require_once('include/photo/photo_driver.php');
			foreach ($r as $rr) {
				$photos = import_xchan_photo($rr['xchan_photo_l'], $rr['xchan_hash'], false, true);
				q("update xchan set xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s'
					where xchan_hash = '%s'",
					dbesc($photos[0]),
					dbesc($photos[1]),
					dbesc($photos[2]),
					dbesc($photos[3]),
					dbesc($rr['xchan_hash'])
				);
			}
		}


		// pull in some public posts

		$disable_discover_tab = get_config('system', 'disable_discover_tab') || get_config('system', 'disable_discover_tab') === false;
		if (!$disable_discover_tab)
			Master::Summon(['Externals']);

		$restart = false;

		if (($argc > 1) && ($argv[1] == 'restart')) {
			$restart    = true;
			$generation = intval($argv[2]);
			if (!$generation)
				return;
		}

		reload_plugins();

		// TODO check to see if there are any cronhooks before wasting a process

		if (!$restart)
			Master::Summon(array('Cronhooks'));

		set_config('system', 'lastcron', datetime_convert());

		//All done - clear the lockfile
		@unlink($lockfile);

		return;
	}
}
