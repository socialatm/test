<?php

/**
 *   * Name: App store menu
 *   * Description: Shows a menu with links to installed and available apps
 *   * Requires: apps
 */

namespace Zotlabs\Widget;


class Appstore {

	function widget($arr) {
		return replace_macros(get_markup_template('appstore.tpl'), [
			'$title' => t('App Collections'),
			'$options' => [
				[z_root() . '/apps',           t('Installed apps'), ((argc() == 1 && argv(0) === 'apps') ? 1 : 0)],
				[z_root() . '/apps/available', t('Available Apps'), ((argc() > 1 && argv(1) === 'available') ? 1 : 0)]
			]
		]);
	}
}
