<?php

/**
 *   * Name: Profile
 *   * Description: Your profile card
 *   * Requires: channel, articles, cards, wiki, cloud, photos
 */


namespace Zotlabs\Widget;

use App;

class Profile {
	function widget($args) {
		if(!App::$profile['profile_uid']) {
			return;
		}

		$block = observer_prohibited();

		return profile_sidebar(App::$profile, $block, true, false);
	}
}
