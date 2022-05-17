<?php

namespace Zotlabs\Module;

use App;
use ZipArchive;
use Zotlabs\Web\Controller;

require_once('include/import.php');

/**
 * @brief Module for importing items.
 *
 * Import existing posts and content from an export file.
 */
class Import_items extends Controller {

	function post() {

		if (!local_channel())
			return;

		check_form_security_token_redirectOnErr('/import_items', 'import_items');

		$data = null;

		$src      = $_FILES['filename']['tmp_name'];
		$filename = basename($_FILES['filename']['name']);
		$filesize = intval($_FILES['filename']['size']);
		$filetype = $_FILES['filename']['type'];

		$channel = App::get_channel();

		if ($src) {

			if ($filetype === 'application/zip') {
				$zip = new ZipArchive;

				$r = $zip->open($src);
				if ($r === true) {
					for ($i = 0; $i < $zip->count(); $i++) {
						$data = $zip->getFromIndex($i);
						self::import($channel, $data);
					}
					$zip->close();
					unlink($src);
					return;
				}

				notice(t('Not a zip file or zip file corrupted.') . EOL);
				unlink($src);
				return;
			}

			// This is OS specific and could also fail if your tmpdir isn't very large
			// mostly used for Diaspora which exports gzipped files.

			//if(strpos($filename,'.gz')){
			//@rename($src,$src . '.gz');
			//@system('gunzip ' . escapeshellarg($src . '.gz'));
			//}

			if ($filesize) {
				$data = @file_get_contents($src);
				self::import($channel, $data);
			}
			unlink($src);
			return;
		}
		/*
				if(! $src) {

					$old_address = ((x($_REQUEST,'old_address')) ? $_REQUEST['old_address'] : '');

					if(! $old_address) {
						logger('Nothing to import.');
						notice( t('Nothing to import.') . EOL);
						return;
					}

					$email    = ((x($_REQUEST,'email'))    ? $_REQUEST['email']    : '');
					$password = ((x($_REQUEST,'password')) ? $_REQUEST['password'] : '');

					$year = ((x($_REQUEST,'year'))    ? $_REQUEST['year']    : '');

					$channelname = substr($old_address,0,strpos($old_address,'@'));
					$servername  = substr($old_address,strpos($old_address,'@')+1);

					$scheme = 'https://';
					$api_path = '/api/red/channel/export/items?f=&channel=' . $channelname . '&year=' . intval($year);
					$binary = false;
					$redirects = 0;
					$opts = array('http_auth' => $email . ':' . $password);
					$url = $scheme . $servername . $api_path;
					$ret = z_fetch_url($url, $binary, $redirects, $opts);
					if(! $ret['success'])
						$ret = z_fetch_url('http://' . $servername . $api_path, $binary, $redirects, $opts);
					if($ret['success'])
						$data = $ret['body'];
					else
						notice( t('Unable to download data from old server') . EOL);
				}
		*/

	}


	/**
	 * @brief Generate item import page.
	 *
	 * @return string with parsed HTML.
	 */
	function get() {

		if (!local_channel()) {
			notice(t('Permission denied') . EOL);
			return login();
		}

		$o = replace_macros(get_markup_template('item_import.tpl'), [
			'$title'               => t('Import Items'),
			'$desc'                => t('Use this form to import existing posts and content from an export file.'),
			'$label_filename'      => t('File to Upload'),
			'$form_security_token' => get_form_security_token('import_items'),
			'$submit'              => t('Submit')
		]);

		return $o;
	}


	public static function import($channel, $data) {

		if (!$data) {
			logger('Empty file.');
			notice(t('Imported file is empty.') . EOL);
			return;
		}

		$data = json_decode($data, true);
		//logger('import: data: ' . print_r($data,true));
		//print_r($data);

		if (!is_array($data)) {
			return;
		}

		//if (array_key_exists('compatibility', $data) && array_key_exists('database', $data['compatibility'])) {
			//$v1 = substr($data['compatibility']['database'], -4);
			//$v2 = substr(DB_UPDATE_VERSION, -4);
			//if ($v2 > $v1) {
				//$t = sprintf(t('Warning: Database versions differ by %1$d updates.'), $v2 - $v1);
				//notice($t . EOL);
			//}
		//}

		if (array_key_exists('item', $data) && is_array($data['item'])) {
			import_items($channel, $data['item'], false, ((array_key_exists('relocate', $data)) ? $data['relocate'] : null));
			info(t('Content import completed') . EOL);
		}

		if (array_key_exists('chatroom', $data) && is_array($data['chatroom'])) {
			import_chatrooms($channel, $data['chatroom']);
			info(t('Chatroom import completed') . EOL);

		}

		if (array_key_exists('event', $data) && is_array($data['event'])) {
			import_events($channel, $data['event']);
			info(t('Channel calendar import 1/2 completed') . EOL);

		}

		if (array_key_exists('event_item', $data) && is_array($data['event_item'])) {
			import_items($channel, $data['event_item'], false, ((array_key_exists('relocate', $data)) ? $data['relocate'] : null));
			info(t('Channel calendar import 2/2 completed') . EOL);
		}

		if (array_key_exists('menu', $data) && is_array($data['menu'])) {
			import_menus($channel, $data['menu']);
			info(t('Menu import completed') . EOL);
		}

		if (array_key_exists('wiki', $data) && is_array($data['wiki'])) {
			import_items($channel, $data['wiki'], false, ((array_key_exists('relocate', $data)) ? $data['relocate'] : null));
			info(t('Wiki import completed') . EOL);
		}

		if (array_key_exists('webpages', $data) && is_array($data['webpages'])) {
			import_items($channel, $data['webpages'], false, ((array_key_exists('relocate', $data)) ? $data['relocate'] : null));
			info(t('Webpages import completed') . EOL);
		}

	}

}
