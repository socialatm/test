<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Web\Controller;

/** @file */

require_once('include/contact_widgets.php');
require_once('include/items.php');
require_once("include/bbcode.php");
require_once('include/security.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');


class Profile extends Controller {

	function init() {

		if (argc() > 1)
			$which = argv(1);
		else {
			notice(t('Requested profile is not available.') . EOL);
			App::$error = 404;
			return;
		}

		if (ActivityStreams::is_as_request()) {
			$channel = channelx_by_nick($which);
			if (!$channel) {
				http_status_exit(404, 'Not found');
			}

			$p = Activity::encode_person($channel, true);
			as_return_and_die(['type' => 'Profile', 'describes' => $p], $channel);
		}

		$profile = '';

		if ((local_channel()) && (argc() > 2) && (argv(2) === 'view')) {
			$channel = App::get_channel();
			$which   = $channel['channel_address'];
			$profile = argv(1);

			$r = q("select profile_guid from profile where id = %d and uid = %d limit 1",
				intval($profile),
				intval(local_channel())
			);

			if (!$r)
				$profile = '';
			$profile = $r[0]['profile_guid'];
		}

		head_add_link([
			'rel'   => 'alternate',
			'type'  => 'application/atom+xml',
			'title' => t('Posts and comments'),
			'href'  => z_root() . '/feed/' . $which
		]);

		head_add_link([
			'rel'   => 'alternate',
			'type'  => 'application/atom+xml',
			'title' => t('Only posts'),
			'href'  => z_root() . '/feed/' . $which . '?f=&top=1'
		]);


		if (!$profile) {
			$x = q("select channel_id as profile_uid from channel where channel_address = '%s' limit 1",
				dbesc(argv(1))
			);
			if ($x) {
				App::$profile = $x[0];
			}
		}

		profile_load($which, $profile);

	}

	function get() {

		if (observer_prohibited(true)) {
			return login();
		}

		nav_set_selected('Profile');

		$groups = [];
		$o      = '';

		if (!(perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_profile'))) {
			notice(t('Permission denied.') . EOL);
			return;
		}


		if (argc() > 2 && argv(2) === 'vcard') {
			header('Content-type: text/vcard');
			header('content-disposition: attachment; filename="' . t('vcard') . '-' . App::$profile['channel_address'] . '.vcf"');
			echo App::$profile['profile_vcard'];
			killme();
		}

		$is_owner = ((local_channel()) && (local_channel() == App::$profile['profile_uid']) ? true : false);

		if ((isset(App::$profile['hidewall']) && App::$profile['hidewall']) && (!$is_owner) && (!remote_channel())) {
			notice(t('Permission denied.') . EOL);
			return;
		}

		head_add_link([
			'rel'   => 'alternate',
			'type'  => 'application/json+oembed',
			'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
			'title' => 'oembed'
		]);

		$o .= advanced_profile();
		call_hooks('profile_advanced', $o);
		return $o;

	}

}
