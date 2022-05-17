<?php

/**
 *   * Name: Find channels
 *   * Description: A simple form to search for channels in the directory
 */

namespace Zotlabs\Widget;

require_once('include/contact_widgets.php');

class Findpeople {
	function widget($arr) {
		return findpeople_widget();
	}
}

