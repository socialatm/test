<?php
namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Zotfeed extends Controller {

	function post() {

	}

	function get() {

		$outbox = new Outbox();
		return $outbox->init();

	}

}



