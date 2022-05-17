<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\Keyutils;

require_once('include/crypto.php');


class Xrd extends \Zotlabs\Web\Controller {

	function init() {

		$uri = urldecode(notags(trim($_GET['uri'])));
		$subject = $uri;
		logger('xrd: ' . $uri,LOGGER_DEBUG);

		$resource = $uri;

		if(substr($uri,0,4) === 'http') {
			$uri = str_replace('~','',$uri);
			$name = basename($uri);
		}
		else {
			$local = str_replace('acct:', '', $uri);
			if(substr($local,0,2) == '//')
				$local = substr($local,2);

			$name = substr($local,0,strpos($local,'@'));
		}

		$r = channelx_by_nick($name);

		if(! $r)
			killme();

		$salmon_key = Keyutils::salmonKey($r['channel_pubkey']);

		header('Access-Control-Allow-Origin: *');
		header("Content-type: application/xrd+xml");


		$aliases = array('acct:' . channel_reddress($r), z_root() . '/channel/' . $r['channel_address'], z_root() . '/~' . $r['channel_address']);

		for($x = 0; $x < count($aliases); $x ++) {
			if($aliases[$x] === $resource)
				unset($aliases[$x]);
		}

		$o = replace_macros(get_markup_template('xrd_person.tpl'), array(
			'$nick'        => $r['channel_address'],
			'$accturi'     => $resource,
			'$subject'     => $subject,
			'$aliases'     => $aliases,
			'$channel_url' => z_root() . '/channel/'       . $r['channel_address'],
			'$profile_url' => z_root() . '/channel/'       . $r['channel_address'],
			'$hcard_url'   => z_root() . '/hcard/'         . $r['channel_address'],
			'$atom'        => z_root() . '/ofeed/'         . $r['channel_address'],
			'$zot_post'    => z_root() . '/post/'          . $r['channel_address'],
			'$poco_url'    => z_root() . '/poco/'          . $r['channel_address'],
			'$photo'       => z_root() . '/photo/profile/l/' . $r['channel_id'],
			'$modexp'      => 'data:application/magic-public-key,'  . $salmon_key,
			'$subscribe'   => z_root() . '/follow?f=&amp;url={uri}',
		));


		$arr = array('user' => $r, 'xml' => $o);
		call_hooks('personal_xrd', $arr);

		echo $arr['xml'];
		killme();

	}

}
