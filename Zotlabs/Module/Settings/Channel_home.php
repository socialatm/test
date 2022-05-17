<?php

namespace Zotlabs\Module\Settings;

use Zotlabs\Lib\Libsync;

require_once('include/menu.php');

class Channel_home {

	function post() {

		$module = substr(strrchr(strtolower(static::class), '\\'), 1);

		check_form_security_token_redirectOnErr('/settings/' . $module, 'settings_' . $module);

		$features = get_module_features($module);

		process_module_features_post(local_channel(), $features, $_POST);

		$channel_divmore_height = ((x($_POST,'channel_divmore_height')) ? intval($_POST['channel_divmore_height']) : 400);
		if($channel_divmore_height < 50)
			$channel_divmore_height = 50;
		set_pconfig(local_channel(),'system','channel_divmore_height', $channel_divmore_height);

		$channel_menu = ((x($_POST['channel_menu'])) ? htmlspecialchars_decode(trim($_POST['channel_menu']),ENT_QUOTES) : '');
		set_pconfig(local_channel(),'system','channel_menu',$channel_menu);

		Libsync::build_sync_packet();

		if(isset($_POST['rpath']) && is_local_url($_POST['rpath']))
			goaway($_POST['rpath']);

		return;
	}

	function get() {

		$module = substr(strrchr(strtolower(static::class), '\\'), 1);

		$features = get_module_features($module);
		$rpath = (($_GET['rpath']) ? $_GET['rpath'] : '');

		$channel_divmore_height = [
			'channel_divmore_height',
			t('Max height of content (in pixels)'),
			((get_pconfig(local_channel(),'system','channel_divmore_height')) ? get_pconfig(local_channel(),'system','channel_divmore_height') : 400),
			t('Click to expand content exceeding this height')
		];

		$menus = menu_list(local_channel());
		if($menus) {
			$current = get_pconfig(local_channel(),'system','channel_menu');
			$menu[] = '';
			foreach($menus as $m) {
				$menu[$m['menu_name']] = htmlspecialchars($m['menu_name'],ENT_COMPAT,'UTF-8');
			}

			$menu_select = [
				'channel_menu',
				t('Personal menu to display in your channel pages'),
				$current,
				'',
				$menu
			];
		}

		$extra_settings_html = replace_macros(get_markup_template('field_input.tpl'),
			[
				'$field' => $channel_divmore_height
			]
		);

		if($menu) {
			$extra_settings_html .= replace_macros(get_markup_template('field_select.tpl'),
				[
					'$field' => $menu_select
				]
			);
		}

		$tpl = get_markup_template("settings_module.tpl");

		$o .= replace_macros($tpl, array(
			'$rpath' => escape_url($rpath),
			'$action_url' => 'settings/' . $module,
			'$form_security_token' => get_form_security_token('settings_' . $module),
			'$title' => t('Channel Home Settings'),
			'$features'  => process_module_features_get(local_channel(), $features),
			'$extra_settings_html' => $extra_settings_html,
			'$submit'    => t('Submit')
		));

		return $o;
	}

}
