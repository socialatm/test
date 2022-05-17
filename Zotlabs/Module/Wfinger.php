<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\Keyutils;
use Zotlabs\Lib\Libzot;

class Wfinger extends \Zotlabs\Web\Controller {

	function init() {

		session_write_close();

		$result = array();

		$scheme = '';

		if(x($_SERVER,'HTTPS') && $_SERVER['HTTPS'])
			$scheme = 'https';
		elseif(x($_SERVER,'SERVER_PORT') && (intval($_SERVER['SERVER_PORT']) == 443))
			$scheme = 'https';
		elseif(x($_SERVER,'HTTP_X_FORWARDED_PROTO') && ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'))
			$scheme = 'https';

		$zot = intval($_REQUEST['zot']);

		if(($scheme !== 'https') && (! $zot)) {
			header($_SERVER["SERVER_PROTOCOL"] . ' ' . 500 . ' ' . 'Webfinger requires HTTPS');
			killme();
		}


		$resource = $_REQUEST['resource'];
		logger('webfinger: ' . $resource,LOGGER_DEBUG);


		$root_resource  = false;
		$pchan = false;

		if(strcasecmp(rtrim($resource,'/'),z_root()) === 0)
			$root_resource = true;

		$r = null;

		if(($resource) && (! $root_resource)) {

			if(strpos($resource,'acct:') === 0) {
				$channel = str_replace('acct:','',$resource);
				if(substr($channel,0,1) === '@' && strpos(substr($channel,1),'@')) {
					$channel = substr($channel,1);
				}
				if(strpos($channel,'@') !== false) {
					$host = substr($channel,strpos($channel,'@')+1);

					// If the webfinger address points off site, redirect to the correct site

					if(strcasecmp($host,\App::get_hostname())) {
						goaway('https://' . $host . '/.well-known/webfinger?f=&resource=' . $resource . (($zot) ? '&zot=' . $zot : ''));
					}
					$channel = substr($channel,0,strpos($channel,'@'));
				}
			}
			if(strpos($resource,'http') === 0) {
				$channel = str_replace('~','',basename($resource));
			}

			if(substr($channel,0,1) === '[' ) {
				$channel = substr($channel,1);
				$channel = substr($channel,0,-1);
				$pchan = true;
				$r = q("select * from pchan left join xchan on pchan_hash = xchan_hash
					where pchan_guid = '%s' limit 1",
					dbesc($channel)
				);
				if($r) {
					$r = pchan_to_chan($r[0]);
				}
			}
			else {
				$r = channelx_by_nick($channel);
			}
		}

		header('Access-Control-Allow-Origin: *');

		if($root_resource) {
			$result['subject'] = $resource;
			$result['properties'] = [
					'https://w3id.org/security/v1#publicKeyPem' => get_config('system','pubkey')
			];
			$result['links'] = [
				[
					'rel'  => 'http://purl.org/openwebauth/v1',
					'type' => 'application/x-zot+json',
					'href' => z_root() . '/owa',
				],
			];




		}

		if($resource && $r) {

			$h = q("select hubloc_addr from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
				dbesc($r['channel_hash'])
			);

			$result['subject'] = $resource;

			$aliases = array(
				z_root() . (($pchan) ? '/pchan/' : '/channel/') . $r['channel_address'],
				z_root() . '/~' . $r['channel_address'],
				z_root() . '/@' . $r['channel_address']
			);

			if($h) {
				foreach($h as $hh) {
					$aliases[] = 'acct:' . $hh['hubloc_addr'];
				}
			}

			$result['aliases'] = [];

			$result['properties'] = [
					'http://webfinger.net/ns/name'   => $r['channel_name'],
					'http://xmlns.com/foaf/0.1/name' => $r['channel_name'],
					'https://w3id.org/security/v1#publicKeyPem' => $r['xchan_pubkey'],
					'http://purl.org/zot/federation' => 'zot6'
			];

			foreach($aliases as $alias)
				if($alias != $resource)
					$result['aliases'][] = $alias;


			if($pchan) {
				$result['links'] = [

					[
						'rel'  => 'http://webfinger.net/rel/avatar',
						'type' => $r['xchan_photo_mimetype'],
						'href' => $r['xchan_photo_l']
					],

					[
						'rel'  => 'http://webfinger.net/rel/profile-page',
						'href' => $r['xchan_url'],
					],

					[
						'rel'  => 'magic-public-key',
						'href' => 'data:application/magic-public-key,' . Keyutils::salmonKey($r['channel_pubkey']),
					]

				];


			}
			else {

				$result['links'] = [

					[
						'rel'  => 'http://webfinger.net/rel/avatar',
						'type' => $r['xchan_photo_mimetype'],
						'href' => $r['xchan_photo_l']
					],

					[
						'rel'  => 'http://microformats.org/profile/hcard',
						'type' => 'text/html',
						'href' => z_root() . '/hcard/' . $r['channel_address']
					],

					[
						'rel'  => 'http://openid.net/specs/connect/1.0/issuer',
						'href' => z_root()
					],

					[
						'rel'  => 'http://webfinger.net/rel/profile-page',
						'href' => z_root() . '/profile/' . $r['channel_address'],
					],

					[
						'rel'  => 'http://webfinger.net/rel/blog',
						'href' => z_root() . '/channel/' . $r['channel_address'],
					],

					[
						'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
						'template' => z_root() . '/follow?f=&url={uri}',
					],

					[
						'rel'  => 'http://purl.org/zot/protocol/6.0',
						'type' => 'application/x-zot+json',
						'href' => channel_url($r)
					],

					[
						'rel'  => 'http://purl.org/openwebauth/v1',
						'type' => 'application/x-zot+json',
						'href' => z_root() . '/owa',
					],

					[
						'rel'  => 'magic-public-key',
						'href' => 'data:application/magic-public-key,' . Keyutils::salmonKey($r['channel_pubkey']),
					]
				];
			}

			if($zot) {
				// get a zotinfo packet and return it with webfinger
				$result['zot'] = Libzot::zotinfo( [ 'address' => $r['xchan_addr'] ]);
			}
		}

		if(! $result) {
			header($_SERVER["SERVER_PROTOCOL"] . ' ' . 400 . ' ' . 'Bad Request');
			killme();
		}

		$arr = [ 'channel' => $r, 'pchan' => $pchan, 'request' => $_REQUEST, 'result' => $result ];
		call_hooks('webfinger',$arr);

		json_return_and_die($arr['result'],'application/jrd+json');

	}

}
