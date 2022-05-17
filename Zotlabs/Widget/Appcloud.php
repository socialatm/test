<?php

/**
 *   * Name: App cloud
 *   * Description: Shows a cloud with various app categories
 *   * Requires: apps
 */

namespace Zotlabs\Widget;

class Appcloud {

	function widget($arr) {
		if(! local_channel())
			return '';
		return app_tagblock(z_root() . '/apps');
	}
}

