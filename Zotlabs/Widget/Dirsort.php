<?php

/**
 *   * Name: Dirsort
 *   * Description: Various options to provide different vies of the directory
 *   * Requires: directory
 */

namespace Zotlabs\Widget;

use Zotlabs\Lib\Libzotdir;

class Dirsort {
	function widget($arr) {
		return Libzotdir::dir_sort_links();
	}
}
