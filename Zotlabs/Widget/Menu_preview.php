<?php

/**
 *   * Name: Menu preview
 *   * Description: Shows a preview of the current menu
 *   * Requires: mitem
 */

namespace Zotlabs\Widget;

require_once('include/menu.php');

class Menu_preview {

	function widget($arr) {
		if(! \App::$data['menu_item'])
			return;

		return menu_render(\App::$data['menu_item']);
	}

}
