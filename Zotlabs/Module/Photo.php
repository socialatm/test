<?php


namespace Zotlabs\Module;

use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\Config;


require_once('include/security.php');
require_once('include/attach.php');
require_once('include/photo/photo_driver.php');

class Photo extends \Zotlabs\Web\Controller {

	function init() {

		if (ActivityStreams::is_as_request()) {

			$sigdata = HTTPSig::verify(EMPTY_STR);
			if ($sigdata['portable_id'] && $sigdata['header_valid']) {
				$portable_id = $sigdata['portable_id'];
				if (! check_channelallowed($portable_id)) {
					http_status_exit(403, 'Permission denied');
				}
				if (! check_siteallowed($sigdata['signer'])) {
					http_status_exit(403, 'Permission denied');
				}
				observer_auth($portable_id);
			}
			elseif (Config::get('system','require_authenticated_fetch',false)) {
				http_status_exit(403,'Permission denied');
			}

			$observer_xchan = get_observer_hash();
			$allowed = false;

			$bear = Activity::token_from_request();
			if ($bear) {
				logger('bear: ' . $bear, LOGGER_DEBUG);
			}

			$r = q("select * from item where resource_type = 'photo' and resource_id = '%s' limit 1",
				dbesc(argv(1))
			);
			if ($r) {
				$allowed = attach_can_view($r[0]['uid'],$observer_xchan,argv(1)/*,$bear*/);
			}
			if (! $allowed) {
				http_status_exit(404,'Permission denied.');
			}
			$channel = channelx_by_n($r[0]['uid']);

			$obj = json_decode($r[0]['obj'],true);

			as_return_and_die($obj,$channel);

		}

		$streaming = null;
		$channel = null;
		$person = 0;
		$renew = false;

		switch(argc()) {
			case 4:
				$person = argv(3);
				$res    = argv(2);
				$type   = argv(1);
				break;
			case 2:
				$photo = argv(1);
				break;
			case 1:
			default:
				killme();
				// NOTREACHED
		}

		$cache_mode = [ 'on' => false, 'age' => 86400, 'exp' => true, 'leak' => false ];
		call_hooks('cache_mode_hook', $cache_mode);

		$observer_xchan = get_observer_hash();
		$cachecontrol = ', no-cache';

		if(isset($type)) {

			/**
			 * Profile photos - Access controls on default profile photos are not honoured since they need to be exchanged with remote sites.
			 *
			 */

			$default = get_default_profile_photo();

			if($type === 'profile') {
				switch($res) {
					case 'm':
						$resolution = 5;
						$default = get_default_profile_photo(80);
						break;
					case 's':
						$resolution = 6;
						$default = get_default_profile_photo(48);
						break;
					case 'l':
					default:
						$resolution = 4;
						break;
				}
			}

			$uid = $person;

			$data = '';

			if ($uid > 0) {
				$r = q("SELECT * FROM photo WHERE imgscale = %d AND uid = %d AND photo_usage = %d LIMIT 1",
					intval($resolution),
					intval($uid),
					intval(PHOTO_PROFILE)
				);
				if($r) {
				    $modified = strtotime($r[0]['edited'] . "Z");
				    $mimetype = $r[0]['mimetype'];
				    if(intval($r[0]['os_storage']))
				        $data = file_get_contents(dbunescbin($r[0]['content']));
				    else
				        $data = dbunescbin($r[0]['content']);
				}

				if(! $data) {
					$d = [ 'imgscale' => $resolution, 'channel_id' => $uid, 'default' => $default, 'data'  => '', 'mimetype' => '' ];
					call_hooks('get_profile_photo',$d);

					$resolution = $d['imgscale'];
					$uid        = $d['channel_id'];
					$default    = $d['default'];
					$data       = $d['data'];
					$mimetype   = $d['mimetype'];
					$modified   = 0;
				}
			}

			if(! $data) {
			    $x = z_fetch_url(z_root() . '/' . $default, true, 0, [ 'novalidate' => true ]);
			    $data = ($x['success'] ? $x['body'] : EMPTY_STR);
			    $mimetype = 'image/png';
			    $modified = filemtime($default);
			}

			$cachecontrol .= ', must-revalidate';
		}
		else {

			/**
			 * Other photos
			 */

			/* Check for a cookie to indicate display pixel density, in order to detect high-resolution
			   displays. This procedure was derived from the "Retina Images" by Jeremey Worboys,
			   used in accordance with the Creative Commons Attribution 3.0 Unported License.
			   Project link: https://github.com/Retina-Images/Retina-Images
			   License link: http://creativecommons.org/licenses/by/3.0/
			*/

			// @FIXME It seems this part doesn't work because we are not setting such cookie
			$cookie_value = false;
			if (isset($_COOKIE['devicePixelRatio'])) {
			  $cookie_value = intval($_COOKIE['devicePixelRatio']);
			}
			else {
			  // Force revalidation of cache on next request
			  // $prvcachecontrol = 'no-cache';
			  $status = 'no cookie';
			}

			$resolution = 0;

			if(strpos($photo,'.') !== false)
				$photo = substr($photo,0,strpos($photo,'.'));

			if(substr($photo,-2,1) == '-') {
				$resolution = intval(substr($photo,-1,1));
				$photo = substr($photo,0,-2);
				// If viewing on a high-res screen, attempt to serve a higher resolution image:
				if ($resolution == 2 && ($cookie_value > 1))
				    $resolution = 1;
			}

			$r = q("SELECT * FROM photo WHERE resource_id = '%s' AND imgscale = %d LIMIT 1",
				dbesc($photo),
				intval($resolution)
			);
			if($r) {
				$allowed = (-1);
				$filename = $r[0]['filename'];
				$u = intval($r[0]['photo_usage']);
				if($u) {
					$allowed = 1;
					if($u === PHOTO_COVER)
						if($resolution < PHOTO_RES_COVER_1200)
							$allowed = (-1);
					if($u === PHOTO_PROFILE)
						if(! in_array($resolution,[4,5,6]))
							$allowed = (-1);
					if($u === PHOTO_CACHE) {
						// Validate cache
						if($cache_mode['on']) {
							$cache = [ 'status' => false, 'item' => $r[0] ];
							call_hooks('cache_url_hook', $cache);
							if(! $cache['status']) {
								$url = html_entity_decode($cache['item']['display_path'], ENT_QUOTES);
								// SSLify if needed
								if(strpos(z_root(),'https:') !== false && strpos($url,'https:') === false)
									$url = z_root() . '/sslify/' . $filename . '?f=&url=' . urlencode($url);
								goaway($url);
							}
							$cachecontrol = '';
						}
					}
				}

				if($allowed === (-1))
					$allowed = attach_can_view($r[0]['uid'],$observer_xchan,$photo);

				$channel = channelx_by_n($r[0]['uid']);

				// Now we'll see if we can access the photo
				$e = q("SELECT * FROM photo WHERE resource_id = '%s' AND imgscale = %d LIMIT 1",
					dbesc($photo),
					intval($resolution)
				);

				$exists = (($e) ? true : false);

				if($exists && $allowed) {
					$expires = strtotime($e[0]['expires'] . 'Z');
					$data = dbunescbin($e[0]['content']);
					$filesize = $e[0]['filesize'];
					$mimetype = $e[0]['mimetype'];
					$modified = strtotime($e[0]['edited'] . 'Z');

					if(intval($e[0]['os_storage']))
						$streaming = $data;

					if($e[0]['allow_cid'] != '' || $e[0]['allow_gid'] != '' || $e[0]['deny_gid'] != '' || $e[0]['deny_gid'] != '')
						$prvcachecontrol = 'no-store, no-cache, must-revalidate';
				}
				else {
					if(! $allowed) {
						http_status_exit(403,'forbidden');
					}
					if(! $exists) {
						http_status_exit(404,'not found');
					}

				}
			}
			else
				http_status_exit(404,'not found');
		}

 		if(! $data)
 			killme();

 		$etag = '"' . md5($data . $modified) . '"';

 		if($modified == 0)
 		    $modified = time();

		header_remove('Pragma');

		if((isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) || (!isset($_SERVER['HTTP_IF_NONE_MATCH']) && isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] === gmdate("D, d M Y H:i:s", $modified) . " GMT")) {
			header_remove('Expires');
			header_remove('Cache-Control');
			header_remove('Set-Cookie');
			http_status_exit(304,'not modified');
		}

		if(isset($res) && intval($res) && $res < 500) {
			$ph = photo_factory($data, $mimetype);
			if($ph->is_valid()) {
				$ph->scaleImageSquare($res);
				$data = $ph->imageString();
				$mimetype = $ph->getType();
			}
		}

		if(isset($prvcachecontrol)) {

			// it is a private photo that they have no permission to view.
			// tell the browser not to cache it, in case they authenticate
			// and subsequently have permission to see it

			header("Cache-Control: " . $prvcachecontrol);

		}
		else {
			// The photo cache default is 1 day to provide a privacy trade-off,
			// as somebody reducing photo permissions on a photo that is already
			// "in the wild" won't be able to stop the photo from being viewed
			// for this amount amount of time once it is in the browser cache.
			// The privacy expectations of your site members and their perception
			// of privacy where it affects the entire project may be affected.
			// This has performance considerations but we highly recommend you
			// leave it alone.

			$maxage = $cache_mode['age'];

			if($cache_mode['exp'] || (! isset($expires)) || (isset($expires) && $expires - 60 < time()))
				$expires = time() + $maxage;
			else
				$maxage = $expires - time();

		 	header("Expires: " . gmdate("D, d M Y H:i:s", $expires) . " GMT");

			// set CDN/Infrastructure caching much lower than maxage
			// in the event that infrastructure caching is present.
			$smaxage = intval($maxage/12);

			header("Cache-Control: s-maxage=" . $smaxage . ", max-age=" . $maxage . $cachecontrol);

		}

		header("Content-type: " . $mimetype);
		header("Last-Modified: " . gmdate("D, d M Y H:i:s", $modified) . " GMT");
		header("ETag: " . $etag);
		header("Content-Length: " . (isset($filesize) ? $filesize : strlen($data)));

		// If it's a file resource, stream it.
		if($streaming) {
			if(strpos($streaming,'store') !== false)
				$istream = fopen($streaming,'rb');
			else
				$istream = fopen('store/' . $channel['channel_address'] . '/' . $streaming,'rb');
			$ostream = fopen('php://output','wb');
			if($istream && $ostream) {
				pipe_streams($istream,$ostream);
				fclose($istream);
				fclose($ostream);
			}
		}
		else {
			echo $data;
		}

		killme();
	}

}
