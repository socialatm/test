<?php

/**
 *   * Name: Categories
 *   * Description: Display a menu with links to categories
 *   * Requires: channel, articles, cards, cloud
 */

namespace Zotlabs\Widget;

use App;
use Zotlabs\Lib\Apps;

require_once('include/contact_widgets.php');

class Categories {

	function widget($arr) {

		$files = ((array_key_exists('files',$arr) && $arr['files']) ? true : false);

		if(!isset(App::$profile['profile_uid']) || !perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_stream')) {
			return '';
		}

		$cat = ((x($_REQUEST, 'cat')) ? htmlspecialchars($_REQUEST['cat'], ENT_COMPAT, 'UTF-8') : '');
		$srchurl = App::$query_string;
		$srchurl = rtrim(preg_replace('/cat\=[^\&].*?(\&|$)/is', '', $srchurl), '&');
		$srchurl = str_replace(['?f=','&f=', '/?'], ['', '', ''], $srchurl);

		if($files) {
			return filecategories_widget($srchurl, $cat);
		}

		return categories_widget($srchurl, $cat);

	}
}
