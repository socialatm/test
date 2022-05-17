<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;

class Randprof extends \Zotlabs\Web\Controller {

	function init() {
		if(local_channel()) {
			if(! Apps::system_app_installed(local_channel(), 'Random Channel'))
				return;
		}

		$x = random_profile();
		if($x)
			goaway(chanlink_hash($x));

		/** FIXME this doesn't work at the moment as a fallback */
		goaway(z_root() . '/profile');
	}

	function get() {
		if(local_channel()) {
			if(! Apps::system_app_installed(local_channel(), 'Random Channel')) {
				//Do not display any associated widgets at this point
				App::$pdl = '';
				$papp = Apps::get_papp('Random Channel');
				return Apps::app_render($papp, 'module');
			}
		}

	}

}
