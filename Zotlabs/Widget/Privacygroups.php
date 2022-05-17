<?php

/**
 *   * Name: Privacy Groups
 *   * Description: Display a menu with links to existing privacy groups
 *   * Requires: group
 */


namespace Zotlabs\Widget;

use Zotlabs\Lib\AccessList;

class Privacygroups {

	function widget($arr) {

		$o = '';

		$groups = q("SELECT id, gname FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
			intval(local_channel())
		);

		if (!$groups) {
			return $o;
		}

		$menu_items = [];
		$z_root = z_root();
		$active = argv(1) ?? '';

		foreach($groups as $group) {
			$menu_items[] = [
				'href' => $z_root . '/group/' . $group['id'],
				'label' => $group['gname'],
				'title' => '',
				'active' => ($active === $group['id']),
				'count' => count(AccessList::members(local_channel(), $group['id']))
			];
		}

		if ($active) {
			$menu_items[] = [
				'href' => $z_root . '/group',
				'label' => '<i class="fa fa-plus"></i> &nbsp;' . t('Add new group'),
				'title' => '',
				'active' => '',
				'count' => ''
			];
		}

		$tpl = get_markup_template("widget_menu_count.tpl");
		$o .= replace_macros($tpl, [
			'$title' => t('Privacy groups'),
			'$menu_items' => $menu_items,

		]);

		return $o;

	}
}
