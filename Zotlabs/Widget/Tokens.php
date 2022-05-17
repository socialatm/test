<?php

/**
 *   * Name: Tokens
 *   * Description: Display a menu with links to existing guest access tokens
 *   * Requires: tokens
 */

namespace Zotlabs\Widget;

class Tokens {

	function widget($arr) {

		$o = '';

		$tokens = q("SELECT atoken_id, atoken_name FROM atoken WHERE atoken_uid = %d",
			intval(local_channel())
		);

		if (!$tokens) {
			return $o;
		}

		$menu_items = [];
		$z_root = z_root();
		$active = argv(1) ?? '';

		foreach($tokens as $token) {
			$menu_items[] = [
				'href' => $z_root . '/tokens/' . $token['atoken_id'],
				'label' => $token['atoken_name'],
				'title' => '',
				'active' => ($active === $token['atoken_id'])
			];
		}

		if ($active) {
			$menu_items[] = [
				'href' => $z_root . '/tokens',
				'label' => '<i class="fa fa-plus"></i> &nbsp;' . t('Add new guest'),
				'title' => '',
				'active' => ''
			];
		}

		$tpl = get_markup_template("widget_menu.tpl");
		$o .= replace_macros($tpl, [
			'$title' => t('Guest access'),
			'$menu_items' => $menu_items,

		]);

		return $o;

	}
}
