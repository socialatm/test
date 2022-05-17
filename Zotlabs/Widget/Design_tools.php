<?php

/**
 *   * Name: Design tools
 *   * Description: Links to useful tools for webpages
 *   * Requires: webpages
 */

namespace Zotlabs\Widget;

class Design_tools {

	function widget($arr) {

		if(perm_is_allowed(\App::$profile['profile_uid'],get_observer_hash(),'write_pages') || (\App::$is_sys && is_site_admin()))
			return design_tools();

		return EMPTY_STR;
	}
}
