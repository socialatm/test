<?php
namespace Zotlabs\Module;


class Sslify extends \Zotlabs\Web\Controller {

	function init() {
		$x = z_fetch_url($_REQUEST['url']);
		if($x['success']) {
			$h = explode("\n",$x['header']);
			foreach ($h as $l) {
				if (strpos($l, ':') === false) {
					continue;
				}

				list($k,$v) = array_map("trim", explode(":", trim($l), 2));
				$hdrs[strtolower($k)] = $v;
			}

			if (array_key_exists('content-type', $hdrs))
				header('Content-Type: ' . $hdrs['content-type']);
			if (array_key_exists('last-modified', $hdrs))
				header('Last-Modified: ' . $hdrs['last-modified']);
			if (array_key_exists('cache-control', $hdrs))
				header('Cache-Control: ' . $hdrs['cache-control']);
			if (array_key_exists('expires', $hdrs))
				header('Expires: ' . $hdrs['expires']);


			echo $x['body'];
			killme();
		}
		killme();
	}
}
