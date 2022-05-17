<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;

require_once('include/socgraph.php');
require_once('include/contact_widgets.php');

class Suggest extends \Zotlabs\Web\Controller {

	function init() {
		if(! local_channel())
			return;

		if(! Apps::system_app_installed(local_channel(), 'Suggest Channels'))
			return;

		if(x($_GET,'ignore')) {
			q("insert into xign ( uid, xchan ) values ( %d, '%s' ) ",
				intval(local_channel()),
				dbesc($_GET['ignore'])
			);
		}

	}


	function get() {

		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		if(! Apps::system_app_installed(local_channel(), 'Suggest Channels')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Suggest Channels');
			return Apps::app_render($papp, 'module');
		}

		$o = '';

		nav_set_selected('Suggest Channels');

		$_SESSION['return_url'] = z_root() . '/' . \App::$cmd;

		$r = suggestion_query(local_channel(),get_observer_hash());

		if(! $r) {
			info( t('No suggestions available. If this is a new site, please try again in 24 hours.'));
			return;
		}

		$arr = array();

		foreach($r as $rr) {

			$connlnk = z_root() . '/follow?f=&url=' . $rr['xchan_addr'];

			$arr[] = array(
				'url' => chanlink_url($rr['xchan_url']),
				'common' => $rr['total'],
				'profile' => $rr['xchan_url'],
				'name' => $rr['xchan_name'],
				'photo' => $rr['xchan_photo_m'],
				'ignlnk' => z_root() . '/suggest?ignore=' . $rr['xchan_hash'],
				'conntxt' => t('Connect'),
				'connlnk' => $connlnk,
				'ignore' => t('Ignore/Hide')
			);
		}


		$o = replace_macros(get_markup_template('suggest_page.tpl'),array(
			'$title' => t('Channel Suggestions'),
			'$entries' => $arr
		));

		return $o;

	}

}
