<?php

/**
 *   * Name: Chatroom members
 *   * Description: A widget that shows members of a chatroom
 *   * Requires: chat
 */

namespace Zotlabs\Widget;

class Chatroom_members {

	// The actual contents are filled in via AJAX

	function widget() {
		return replace_macros(get_markup_template('chatroom_members.tpl'), array(
			'$header' => t('Chat Members')
		));
	}

}
