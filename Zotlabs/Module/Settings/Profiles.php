<?php

namespace Zotlabs\Module\Settings;

use Zotlabs\Lib\Libsync;

require_once('include/selectors.php');

class Profiles {

	function post() {

		$module = substr(strrchr(strtolower(static::class), '\\'), 1);

		check_form_security_token_redirectOnErr('/settings/' . $module, 'settings_' . $module);

		$features = get_module_features($module);

		process_module_features_post(local_channel(), $features, $_POST);

		$profile_assign  = ((x($_POST,'profile_assign')) ? notags(trim($_POST['profile_assign'])) : '');
		set_pconfig(local_channel(),'system','profile_assign',$profile_assign);

		Libsync::build_sync_packet();

		if(isset($_POST['rpath']) && is_local_url($_POST['rpath']))
			goaway($_POST['rpath']);

		return;
	}

	function get() {

		$module = substr(strrchr(strtolower(static::class), '\\'), 1);

		$features = get_module_features($module);
		$rpath = (($_GET['rpath']) ? $_GET['rpath'] : '');

		$extra_settings_html = '';
		if(feature_enabled(local_channel(),'multi_profiles'))
			$extra_settings_html = contact_profile_assign(get_pconfig(local_channel(),'system','profile_assign',''), t('Default profile for new contacts'));

		$tpl = get_markup_template("settings_module.tpl");

		$o .= replace_macros($tpl, array(
			'$rpath' => escape_url($rpath),
			'$action_url' => 'settings/' . $module,
			'$form_security_token' => get_form_security_token('settings_' . $module),
			'$title' => t('Profiles Settings'),
			'$features' => process_module_features_get(local_channel(), $features),
			'$extra_settings_html' => $extra_settings_html,
			'$submit' => t('Submit')
		));

		return $o;
	}

}
