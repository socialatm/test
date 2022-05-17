<?php

namespace Zotlabs\Lib;

use App;

/**
 * @brief Class for handling channel specific configurations.
 *
 * <b>PConfig</b> is used for channel specific configurations and takes a
 * <i>channel_id</i> as identifier. It stores for example which features are
 * enabled per channel. The storage is of size MEDIUMTEXT.
 *
 * @code{.php}$var = Zotlabs\Lib\PConfig::Get('uid', 'category', 'key');
 * // with default value for non existent key
 * $var = Zotlabs\Lib\PConfig::Get('uid', 'category', 'unsetkey', 'defaultvalue');@endcode
 *
 * The old (deprecated?) way to access a PConfig value is:
 * @code{.php}$var = get_pconfig(local_channel(), 'category', 'key');@endcode
 */
class PConfig {

	/**
	 * @brief Loads all configuration values of a channel into a cached storage.
	 *
	 * All configuration values of the given channel are stored in global cache
	 * which is available under the global variable App::$config[$uid].
	 *
	 * @param string $uid
	 *  The channel_id
	 * @return void|false Nothing or false if $uid is null or false
	 */
	static public function Load($uid) {
		if(is_null($uid) || $uid === false)
			return false;

		if(! is_array(App::$config)) {
			btlogger('App::$config not an array');
		}

		if(! array_key_exists($uid, App::$config)) {
			App::$config[$uid] = array();
		}

		if(! is_array(App::$config[$uid])) {
			btlogger('App::$config[$uid] not an array: ' . $uid);
		}

		$r = q("SELECT * FROM pconfig WHERE uid = %d",
			intval($uid)
		);

		if($r) {
			foreach($r as $rr) {
				$k = $rr['k'];
				$c = $rr['cat'];
				if(! array_key_exists($c, App::$config[$uid])) {
					App::$config[$uid][$c] = array();
					App::$config[$uid][$c]['config_loaded'] = true;
				}
				App::$config[$uid][$c][$k] = $rr['v'];
				App::$config[$uid][$c]['pcfgud:'.$k] = $rr['updated'];
			}
		}
	}

	/**
	 * @brief Get a particular channel's config variable given the category name
	 * ($family) and a key.
	 *
	 * Get a particular channel's config value from the given category ($family)
	 * and the $key from a cached storage in App::$config[$uid].
	 *
	 * Returns false if not set.
	 *
	 * @param string $uid
	 *  The channel_id
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to query
	 * @param mixed $default (optional, default false)
	 *  Default value to return if key does not exist
	 * @return mixed Stored value or false if it does not exist
	 */
	static public function Get($uid, $family, $key, $default = false) {

		if(is_null($uid) || $uid === false)
			return $default;

		if(! array_key_exists($uid, App::$config))
			self::Load($uid);

		if((! array_key_exists($family, App::$config[$uid])) || (! array_key_exists($key, App::$config[$uid][$family])))
			return $default;

		return ((! is_array(App::$config[$uid][$family][$key])) && (preg_match('|^a:[0-9]+:{.*}$|s', App::$config[$uid][$family][$key]))
			? unserialize(App::$config[$uid][$family][$key])
			: App::$config[$uid][$family][$key]
		);
	}

	/**
	 * @brief Sets a configuration value for a channel.
	 *
	 * Stores a config value ($value) in the category ($family) under the key ($key)
	 * for the channel_id $uid.
	 *
	 * @param string $uid
	 *  The channel_id
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to set
	 * @param string $value
	 *  The value to store
	 * @param string $updated (optional)
	 *  The datetime to store
	 * @return mixed Stored $value or false
	 */
	static public function Set($uid, $family, $key, $value, $updated = NULL) {

		// this catches subtle errors where this function has been called
		// with local_channel() when not logged in (which returns false)
		// and throws an error in array_key_exists below.
		// we provide a function backtrace in the logs so that we can find
		// and fix the calling function.

		if(is_null($uid) || $uid === false) {
			btlogger('UID is FALSE!', LOGGER_NORMAL, LOG_ERR);
			return;
		}

		// manage array value
		$dbvalue = ((is_array($value))  ? serialize($value) : $value);
		$dbvalue = ((is_bool($dbvalue)) ? intval($dbvalue)  : $dbvalue);
		$new = false;
		$update = false;

		$now = datetime_convert();
		if (! $updated) {
			//Sometimes things happen fast... very fast.
			//To make sure legitimate updates aren't rejected
			//because not enough time has passed.  We say our updates
			//happened just a short time in the past rather than right now.
			$updated = datetime_convert('UTC','UTC','-2 seconds');
		}

		$hash = gen_link_id($family.':'.$key);

		if (self::Get($uid, 'hz_delpconfig', $hash) !== false) {
			if (self::Get($uid, 'hz_delpconfig', $hash) > $now) {
				logger('Refusing to update pconfig with outdated info (Item deleted more recently).', LOGGER_NORMAL, LOG_ERR);
				return self::Get($uid,$family,$key);
			} else {
				self::Delete($uid, 'hz_delpconfig', $hash);
			}
		}

		if(self::Get($uid, $family, $key) === false) {
			if(! array_key_exists($uid, App::$config))
				App::$config[$uid] = array();
			if(! array_key_exists($family, App::$config[$uid]))
				App::$config[$uid][$family] = array();

			$ret = q("INSERT INTO pconfig ( uid, cat, k, v, updated ) VALUES ( %d, '%s', '%s', '%s', '%s' ) ",
				intval($uid),
				dbesc($family),
				dbesc($key),
				dbesc($dbvalue),
				dbesc($updated)
			);

			// There is a possible race condition if another process happens
			// to insert something after this thread has Loaded and now.  We should
			// at least make a note of it if it happens.

			if (!$ret) {
				logger("Error: Insert to pconfig failed.",LOGGER_NORMAL, LOG_ERR);
			}

			$new = true;
			App::$config[$uid][$family]['pcfgud:'.$key] = $updated;

		}
		else {
			$update = (App::$config[$uid][$family]['pcfgud:'.$key] < $now);

			if ($update) {

				// @NOTE There is still a possible race condition under limited circumstances
				// where a value will be updated by another thread with more current data than
				// we have.  At this point there is no easy way to test for it, so we update
				// and hope for the best.

				$ret = q("UPDATE pconfig SET v = '%s', updated = '%s' WHERE uid = %d and cat = '%s' AND k = '%s' ",
					dbesc($dbvalue),
					dbesc($updated),
					intval($uid),
					dbesc($family),
					dbesc($key)
				);

				App::$config[$uid][$family]['pcfgud:'.$key] = $updated;

			} else {
				logger('Refusing to update pconfig with outdated info.', LOGGER_NORMAL, LOG_ERR);
				return self::Get($uid, $family, $key);
			}
		}


		// keep a separate copy for all variables which were
		// set in the life of this page. We need this to
		// synchronise channel clones.

		if(! array_key_exists('transient', App::$config[$uid]))
			App::$config[$uid]['transient'] = array();
		if(! array_key_exists($family, App::$config[$uid]['transient']))
			App::$config[$uid]['transient'][$family] = array();

		App::$config[$uid][$family][$key] = $value;

		if ($new || $update) {
			App::$config[$uid]['transient'][$family][$key] = $value;
			App::$config[$uid]['transient'][$family]['pcfgud:'.$key] = $updated;
		}

		if($ret)
			return $value;

		return $ret;
	}


	/**
	 * @brief Deletes the given key from the channel's configuration.
	 *
	 * Removes the configured value from the stored cache in App::$config[$uid]
	 * and removes it from the database.
	 *
	 * @param string $uid
	 *  The channel_id
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to delete
	 * @param string $updated (optional)
	 *  The datetime to store
	 * @return boolean
	 */
	static public function Delete($uid, $family, $key, $updated = NULL) {

		if(is_null($uid) || $uid === false)
			return false;

		$updated = ($updated) ? $updated : datetime_convert('UTC','UTC','-2 seconds');
		$now = datetime_convert();
		$newer = (App::$config[$uid][$family]['pcfgud:'.$key] < $now);

		if (! $newer) {
			logger('Refusing to delete pconfig with outdated delete request.', LOGGER_NORMAL, LOG_ERR);
			return false;
		}

		$ret = false;

		if (isset(App::$config[$uid][$family][$key])) {
			unset(App::$config[$uid][$family][$key]);
		}

		if (isset(App::$config[$uid][$family]['pcfgud:'.$key])) {
			unset(App::$config[$uid][$family]['pcfgud:'.$key]);
		}

		$ret = q("DELETE FROM pconfig WHERE uid = %d AND cat = '%s' AND k = '%s'",
			intval($uid),
			dbesc($family),
			dbesc($key)
		);

		// Synchronize delete with clones.

		if ($family !== 'hz_delpconfig') {
			$hash = gen_link_id($family.':'.$key);
			set_pconfig($uid, 'hz_delpconfig', $hash, $updated);
		}

		return $ret;
	}

}
