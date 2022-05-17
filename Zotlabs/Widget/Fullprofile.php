<?php

/**
 *   * Name: Full profile
 *   * Description: Profile card with extended profile info
 *   * Requires: channel, articles, cards, wiki, cloud, photos
 */

namespace Zotlabs\Widget;

class Fullprofile {

	function widget($arr) {

		if(! \App::$profile['profile_uid'])
			return;

		$block = observer_prohibited();

		return profile_sidebar(\App::$profile, $block, true, true);
	}
}
