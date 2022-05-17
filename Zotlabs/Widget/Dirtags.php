<?php

/**
 *   * Name: Directory tags
 *   * Description: Show directory tags in a cloud
 *   * Requires: directory
 */

namespace Zotlabs\Widget;

class Dirtags {

	function widget($arr) {
		return dir_tagblock(z_root() . '/directory', null);
	}

}
