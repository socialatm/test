<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;

class Affinity extends \Zotlabs\Web\Controller {

	function post() {

		if(! local_channel())
			return;

		if(! Apps::system_app_installed(local_channel(),'Affinity Tool'))
			return;

		check_form_security_token_redirectOnErr('affinity', 'affinity');

		$cmax = intval($_POST['affinity_cmax']);
		if($cmax < 0 || $cmax > 99)
			$cmax = 99;

		$cmin = intval($_POST['affinity_cmin']);
		if($cmin < 0 || $cmin > 99)
			$cmin = 0;

		$lock = intval($_POST['affinity_lock']);

		set_pconfig(local_channel(),'affinity','cmin',$cmin);
		set_pconfig(local_channel(),'affinity','cmax',$cmax);
		set_pconfig(local_channel(),'affinity','lock',$lock);

		info( t('Affinity Tool settings updated.') . EOL);

		Libsync::build_sync_packet();

	}


	function get() {

		if(! local_channel())
			return;

		if(! Apps::system_app_installed(local_channel(), 'Affinity Tool')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Affinity Tool');
			return Apps::app_render($papp, 'module');
		}

		$text = t('The numbers below represent the minimum and maximum slider default positions for your network/stream page as a percentage.');

		$content = '<div class="section-content-info-wrapper">' . $text . '</div>';

		$cmax = intval(get_pconfig(local_channel(),'affinity','cmax'));
		$cmax = (($cmax) ? $cmax : 99);
		$content .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'    => array('affinity_cmax', t('Default maximum affinity level'), $cmax, t('0-99 default 99'))
		));

		$cmin = intval(get_pconfig(local_channel(),'affinity','cmin'));
		$cmin = (($cmin) ? $cmin : 0);
		$content .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'    => array('affinity_cmin', t('Default minimum affinity level'), $cmin, t('0-99 - default 0'))
		));

		$lock = intval(get_pconfig(local_channel(),'affinity','lock',1));

		$content .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'    => array('affinity_lock', t('Persistent affinity levels'), $lock, t('If disabled the max and min levels will be reset to default after page reload'), ['No','Yes'])
		));

		$tpl = get_markup_template("settings_addon.tpl");

		$o = replace_macros($tpl, array(
			'$action_url' => 'affinity',
			'$form_security_token' => get_form_security_token("affinity"),
			'$title' => t('Affinity Tool Settings'),
			'$content'  => $content,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));

		return $o;
	}


}
