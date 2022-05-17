<?php

namespace Zotlabs\Module\Settings;

use Zotlabs\Lib\Libsync;

class Network {

	function post() {

		$module = substr(strrchr(strtolower(static::class), '\\'), 1);

		check_form_security_token_redirectOnErr('/settings/' . $module, 'settings_' . $module);
	
		$features = get_module_features($module);

		process_module_features_post(local_channel(), $features, $_POST);

		$network_divmore_height = ((x($_POST,'network_divmore_height')) ? intval($_POST['network_divmore_height']) : 400);
		if($network_divmore_height < 50)
			$network_divmore_height = 50;

		set_pconfig(local_channel(),'system','network_divmore_height', $network_divmore_height);

		Libsync::build_sync_packet();

		if(isset($_POST['rpath']) && is_local_url($_POST['rpath']))
			goaway($_POST['rpath']);

		return;
	}

	function get() {

		$module = substr(strrchr(strtolower(static::class), '\\'), 1);

		$features = get_module_features($module);
		$rpath = (($_GET['rpath']) ? $_GET['rpath'] : '');

		$network_divmore_height = [
			'network_divmore_height',
			t('Max height of content (in pixels)'),
			((get_pconfig(local_channel(),'system','network_divmore_height')) ? get_pconfig(local_channel(),'system','network_divmore_height') : 400),
			t('Click to expand content exceeding this height')
		];

		$extra_settings_html = replace_macros(get_markup_template('field_input.tpl'),
			[
				'$field' => $network_divmore_height
			]
		);

		$tpl = get_markup_template("settings_module.tpl");

		$o .= replace_macros($tpl, array(
			'$rpath' => escape_url($rpath),
			'$action_url' => 'settings/' . $module,
			'$form_security_token' => get_form_security_token('settings_' . $module),
			'$title' => t('Stream Settings'),
			'$features' => process_module_features_get(local_channel(), $features),
			'$extra_settings_html' => $extra_settings_html,
			'$submit' => t('Submit')
		));

		return $o;
	}

}
