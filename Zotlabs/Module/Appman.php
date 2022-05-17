<?php /** @file */

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;

class Appman extends \Zotlabs\Web\Controller {

	function post() {

		if(! local_channel())
			return;

		if($_POST['url']) {
			$arr = array(
				'uid' => intval($_REQUEST['uid']),
				'url' => escape_tags($_REQUEST['url']),
				'guid' => escape_tags($_REQUEST['guid']),
				'author' => escape_tags($_REQUEST['author']),
				'addr' => escape_tags($_REQUEST['addr']),
				'name' => escape_tags($_REQUEST['name']),
				'desc' => escape_tags($_REQUEST['desc']),
				'photo' => escape_tags($_REQUEST['photo']),
				'version' => escape_tags($_REQUEST['version']),
				'price' => escape_tags($_REQUEST['price']),
				'page' => escape_tags($_REQUEST['page']),
				'requires' => escape_tags($_REQUEST['requires']),
				'system' => intval($_REQUEST['system']),
				'plugin' => escape_tags($_REQUEST['plugin']),
				'sig' => escape_tags($_REQUEST['sig']),
				'categories' => escape_tags($_REQUEST['categories'])
			);

			$_REQUEST['appid'] = Apps::app_install(local_channel(),$arr);

			if(Apps::app_installed(local_channel(),$arr))
				info( t('App installed.') . EOL);

			goaway(z_root() . '/apps');
			return; //not reached
		}


		$papp = Apps::app_decode($_POST['papp']);

		if(! is_array($papp)) {
			notice( t('Malformed app.') . EOL);
			return;
		}

		if($_POST['install']) {
			Apps::app_install(local_channel(),$papp);
			if(Apps::app_installed(local_channel(),$papp))
				info( t('App installed.') . EOL);

			$sync = q("SELECT * FROM app WHERE app_channel = %d AND app_id = '%s' LIMIT 1",
				intval(local_channel()),
				dbesc($papp['guid'])
			);

			if (!$sync) {
				return;
			}

			if (intval($sync[0]['app_system'])) {
				Libsync::build_sync_packet($uid, ['sysapp' => $sync]);
			}
			else {
				Libsync::build_sync_packet($uid, ['app' => $sync]);
			}

		}

		if($_POST['delete']) {

			// Fetch the app for sync before it is deleted (if it is deletable))
			$sync = q("SELECT * FROM app WHERE app_channel = %d AND app_id = '%s' LIMIT 1",
				intval(local_channel()),
				dbesc($papp['guid'])
			);

			if (!$sync) {
				return;
			}

			Apps::app_destroy(local_channel(), $papp);

			// Now flag it deleted
			$sync[0]['app_deleted'] = 1;

			if (intval($sync[0]['app_system'])) {
				Libsync::build_sync_packet($uid, ['sysapp' => $sync]);
			}
			else {
				Libsync::build_sync_packet($uid, ['app' => $sync]);
			}
		}

		if($_POST['edit']) {
			return;
		}

		if($_POST['feature']) {
			Apps::app_feature(local_channel(), $papp, $_POST['feature']);

			$sync = q("SELECT * FROM app WHERE app_channel = %d AND app_id = '%s' LIMIT 1",
				intval(local_channel()),
				dbesc($papp['guid'])
			);

			if (intval($sync[0]['app_system'])) {
				Libsync::build_sync_packet($uid, ['sysapp' => $sync]);
			}
			else {
				Libsync::build_sync_packet($uid, ['app' => $sync]);
			}
		}

		if($_POST['pin']) {
			Apps::app_feature(local_channel(), $papp, $_POST['pin']);

			$sync = q("SELECT * FROM app WHERE app_channel = %d AND app_id = '%s' LIMIT 1",
				intval(local_channel()),
				dbesc($papp['guid'])
			);

			if (intval($sync[0]['app_system'])) {
				Libsync::build_sync_packet($uid, ['sysapp' => $sync]);
			}
			else {
				Libsync::build_sync_packet($uid, ['app' => $sync]);
			}
		}

		if($_POST['aj']) {
			killme();
		}

		if($_SESSION['return_url'])
			goaway(z_root() . '/' . $_SESSION['return_url']);

		goaway(z_root() . '/apps');


	}


	function get() {

		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		$channel = App::get_channel();

		if(argc() > 3) {
			if(argv(2) === 'moveup') {
				Apps::moveup(local_channel(),argv(1),argv(3));
			}
			if(argv(2) === 'movedown') {
				Apps::movedown(local_channel(),argv(1),argv(3));
			}
			goaway(z_root() . '/apporder');
		}




		$app = null;
		$embed = null;
		if($_REQUEST['appid']) {
			$r = q("select * from app where app_id = '%s' and app_channel = %d limit 1",
				dbesc($_REQUEST['appid']),
				dbesc(local_channel())
			);
			if($r) {
				$app = $r[0];

				$term = q("select * from term where otype = %d and oid = %d and uid = %d",
					intval(TERM_OBJ_APP),
					intval($r[0]['id']),
					intval(local_channel())
				);

				if($term) {
					$app['categories'] = '';
					foreach($term as $t) {
						if($app['categories'])
							$app['categories'] .= ',';
						$app['categories'] .= $t['term'];
					}
				}
			}

			$embed = array('embed', t('Embed code'), Apps::app_encode($app,true),'', 'onclick="this.select();"');

		}

		return replace_macros(get_markup_template('app_create.tpl'), array(

			'$banner' => (($app) ? t('Edit App') : t('Create App')),
			'$app' => $app,
			'$guid' => (($app) ? $app['app_id'] : ''),
			'$author' => (($app) ? $app['app_author'] : $channel['channel_hash']),
			'$addr' => (($app) ? $app['app_addr'] : $channel['xchan_addr']),
			'$name' => array('name', t('Name of app'),(($app) ? $app['app_name'] : ''), t('Required')),
			'$url' => array('url', t('Location (URL) of app'),(($app) ? $app['app_url'] : ''), t('Required')),
	 		'$desc' => array('desc', t('Description'),(($app) ? $app['app_desc'] : ''), ''),
			'$photo' => array('photo', t('Photo icon URL'),(($app) ? $app['app_photo'] : ''), t('80 x 80 pixels - optional')),
			'$categories' => array('categories',t('Categories (optional, comma separated list)'),(($app) ? $app['categories'] : ''),''),
			'$version' => array('version', t('Version ID'),(($app) ? $app['app_version'] : ''), ''),
			'$price' => array('price', t('Price of app'),(($app) ? $app['app_price'] : ''), ''),
			'$page' => array('page', t('Location (URL) to purchase app'),(($app) ? $app['app_page'] : ''), ''),
			'$system' => (($app) ? intval($app['app_system']) : 0),
			'$plugin' => (($app) ? $app['app_plugin'] : ''),
			'$requires' => (($app) ? $app['app_requires'] : ''),
			'$embed' => $embed,
			'$submit' => t('Submit')
		));

	}

}
