<?php

/**
 *   * Name: Photo albums
 *   * Description: Displays a menu with links to existing photo albums
 *   * Requires: photos
 */

namespace Zotlabs\Widget;

require_once('include/photos.php');

class Photo_albums {

	function widget($arr) {

		if (!(isset(\App::$profile['profile_uid']) && \App::$profile['profile_uid'])) {
			return '';
		}

		$channelx = channelx_by_n(\App::$profile['profile_uid']);

		if((! $channelx) || (! perm_is_allowed(\App::$profile['profile_uid'], get_observer_hash(), 'view_storage')))
			return '';

		$sortkey = ((array_key_exists('sortkey',$arr)) ? $arr['sortkey'] : 'display_path');
		$direction = ((array_key_exists('direction',$arr)) ? $arr['direction'] : 'asc');

		return photos_album_widget($channelx, \App::get_observer(),$sortkey,$direction);
	}
}

