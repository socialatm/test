<?php

namespace Zotlabs\Module\Settings;

use Zotlabs\Lib\Libsync;

class Display {

		/*
		 * DISPLAY SETTINGS
		 */

	function post() {
		check_form_security_token_redirectOnErr('/settings/display', 'settings_display');

		$themespec = explode(':', \App::$channel['channel_theme']);
		$existing_theme  = $themespec[0];
		$existing_schema = $themespec[1];

		$theme = ((x($_POST,'theme')) ? notags(trim($_POST['theme']))  : $existing_theme);

		if(! $theme)
			$theme = 'redbasic';


		$preload_images    = ((x($_POST,'preload_images')) ? intval($_POST['preload_images'])  : 0);
		$nosmile           = ((x($_POST,'nosmile')) ? intval($_POST['nosmile'])  : 0);
		$title_tosource    = ((x($_POST,'title_tosource')) ? intval($_POST['title_tosource'])  : 0);
		$start_menu        = ((x($_POST,'start_menu')) ? intval($_POST['start_menu']) : 0);

		$browser_update   = ((x($_POST,'browser_update')) ? intval($_POST['browser_update']) : 0);
		$browser_update   = $browser_update * 1000;
		if($browser_update < 10000)
			$browser_update = 10000;

		$itemspage   = ((x($_POST,'itemspage')) ? intval($_POST['itemspage']) : 10);
		if($itemspage > 30)
			$itemspage = 30;


		set_pconfig(local_channel(),'system','preload_images',$preload_images);
		set_pconfig(local_channel(),'system','update_interval', $browser_update);
		set_pconfig(local_channel(),'system','itemspage', $itemspage);
		set_pconfig(local_channel(),'system','no_smilies',1-intval($nosmile));
		set_pconfig(local_channel(),'system','title_tosource',$title_tosource);
		set_pconfig(local_channel(),'system','start_menu', $start_menu);

		$newschema = '';
		if($theme){
			// call theme_post only if theme has not been changed
			if( ($themeconfigfile = $this->get_theme_config_file($theme)) != null){
				require_once($themeconfigfile);
				if(class_exists('\\Zotlabs\\Theme\\' . ucfirst($theme) . 'Config')) {
					$clsname = '\\Zotlabs\\Theme\\' . ucfirst($theme) . 'Config';
					$theme_config = new $clsname();
					$schemas = $theme_config->get_schemas();
					if(array_key_exists($_POST['schema'],$schemas))
						$newschema = $_POST['schema'];
					if($newschema === '---')
						$newschema = '';
					$theme_config->post();
				}
			}
		}

		logger('theme: ' . $theme . (($newschema) ? ':' . $newschema : ''));

		$_SESSION['theme'] = $theme . (($newschema) ? ':' . $newschema : '');

		$r = q("UPDATE channel SET channel_theme = '%s' WHERE channel_id = %d",
				dbesc($theme . (($newschema) ? ':' . $newschema : '')),
				intval(local_channel())
		);

		call_hooks('display_settings_post', $_POST);
		Libsync::build_sync_packet();
		goaway(z_root() . '/settings/display' );
		return; // NOTREACHED
	}


	function get() {

		$yes_no = array(t('No'),t('Yes'));

		$default_theme = get_config('system','theme');
		if(! $default_theme)
			$default_theme = 'redbasic';

		$themespec = explode(':', \App::$channel['channel_theme']);
		$existing_theme  = $themespec[0] ?? '';
		$existing_schema = $themespec[1] ?? '';

		$theme = (($existing_theme) ? $existing_theme : $default_theme);

		$allowed_themes_str = get_config('system','allowed_themes');
		$allowed_themes_raw = explode(',',$allowed_themes_str);
		$allowed_themes = array();
		if(count($allowed_themes_raw))
			foreach($allowed_themes_raw as $x)
				if(strlen(trim($x)) && is_dir("view/theme/$x"))
					$allowed_themes[] = trim($x);


		$themes = array();
		$files = glob('view/theme/*');
		if($allowed_themes) {
			foreach($allowed_themes as $th) {
				$f = $th;

				$info = get_theme_info($th);
				$compatible = check_plugin_versions($info);
				if(! $compatible) {
					$themes[$f] = sprintf(t('%s - (Incompatible)'), $f);
					continue;
				}

				$is_experimental = file_exists('view/theme/' . $th . '/experimental');
				$unsupported = file_exists('view/theme/' . $th . '/unsupported');
				$is_library = file_exists('view/theme/'. $th . '/library');

				if (!$is_experimental or ($is_experimental && (get_config('experimentals','exp_themes')==1 or get_config('experimentals','exp_themes')===false))){
					$theme_name = (($is_experimental) ?  sprintf(t('%s - (Experimental)'), $f) : $f);
					if (! $is_library) {
						$themes[$f] = $theme_name;
					}
				}
			}
		}

		$theme_selected = ((array_key_exists('theme',$_SESSION) && $_SESSION['theme']) ? $_SESSION['theme'] : $theme);

		if (strpos($theme_selected, ':')) {
			$theme_selected = explode(':', $theme_selected)[0];
		}

		$account = \App::get_account();

		if($account['account_created'] > datetime_convert('','','now - 60 days')) {
			$start_menu = get_pconfig(local_channel(), 'system', 'start_menu', 1);
		}
		else {
			$start_menu = get_pconfig(local_channel(), 'system', 'start_menu', 0);
		}

		$preload_images = get_pconfig(local_channel(),'system','preload_images');
		$preload_images = (($preload_images===false)? '0': $preload_images); // default if not set: 0

		$browser_update = intval(get_pconfig(local_channel(), 'system','update_interval'));
		$browser_update = (($browser_update == 0) ? 80 : $browser_update / 1000); // default if not set: 40 seconds

		$itemspage = intval(get_pconfig(local_channel(), 'system','itemspage'));
		$itemspage = (($itemspage > 0 && $itemspage <= 30) ? $itemspage : 10); // default if not set: 10 items

		$nosmile = get_pconfig(local_channel(),'system','no_smilies');
		$nosmile = (($nosmile===false)? '0': $nosmile); // default if not set: 0

		$title_tosource = get_pconfig(local_channel(),'system','title_tosource');
		$title_tosource = (($title_tosource===false)? '0': $title_tosource); // default if not set: 0

		$theme_config = "";
		if(($themeconfigfile = $this->get_theme_config_file($theme)) != null){
			require_once($themeconfigfile);
			if(class_exists('\\Zotlabs\\Theme\\' . ucfirst($theme) . 'Config')) {
				$clsname = '\\Zotlabs\\Theme\\' . ucfirst($theme) . 'Config';
				$thm_config = new $clsname();
				$schemas = $thm_config->get_schemas();
			}
		}

		// logger('schemas: ' . print_r($schemas,true));

		$tpl = get_markup_template("settings_display.tpl");
		$o = replace_macros($tpl, array(
			'$ptitle' 	=> t('Display Settings'),
			'$d_tset'       => t('Theme Settings'),
			'$d_ctset'      => t('Custom Theme Settings'),
			'$d_cset'       => t('Content Settings'),
			'$form_security_token' => get_form_security_token("settings_display"),
			'$submit' 	=> t('Submit'),
			'$baseurl' => z_root(),
			'$uid' => local_channel(),

			'$theme'	=> (($themes) ? array('theme', t('Display Theme:'), $theme_selected, '', $themes, 'preview') : false),
			'$schema'   => array('schema', t('Select scheme'), $existing_schema, '' , $schemas),

			'$preload_images' => array('preload_images', t("Preload images before rendering the page"), $preload_images, t("The subjective page load time will be longer but the page will be ready when displayed"), $yes_no),
			'$ajaxint'   => array('browser_update',  t("Update browser every xx seconds"), $browser_update, t('Minimum of 10 seconds, no maximum')),
			'$itemspage'   => array('itemspage',  t("Maximum number of conversations to load at any time:"), $itemspage, t('Maximum of 30 items')),
			'$nosmile'	=> array('nosmile', t("Show emoticons (smilies) as images"), 1-intval($nosmile), '', $yes_no),
			'$title_tosource'	=> array('title_tosource', t("Link post titles to source"), $title_tosource, '', $yes_no),
			'$theme_config' => $theme_config,
			'$start_menu' => ['start_menu', t('New Member Links'), $start_menu, t('Display new member quick links menu'), $yes_no]
		));

		call_hooks('display_settings',$o);
		return $o;
	}


	function get_theme_config_file($theme){

		$base_theme = \App::$theme_info['extends'] ?? '';

		if ($theme && file_exists("view/theme/$theme/php/config.php")){
			return "view/theme/$theme/php/config.php";
		}
		if ($base_theme && file_exists("view/theme/$base_theme/php/config.php")){
			return "view/theme/$base_theme/php/config.php";
		}
		return null;
	}
}
