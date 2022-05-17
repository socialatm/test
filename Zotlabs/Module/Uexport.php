<?php
namespace Zotlabs\Module;

use App;
use ZipArchive;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Uexport extends Controller {

	function init() {
		if(! local_channel()) {
			return;
		}

		if(! Apps::system_app_installed(local_channel(), 'Channel Export')) {
			return;
		}

		if(argc() > 1) {

			$zap_compat = (($_REQUEST['zap_compat']) ? intval($_REQUEST['zap_compat']) : false);
			$channel = App::get_channel();
			$year = null;
			$month = null;

			if(argc() > 1 && intval(argv(1)) > 1900) {
				$year = intval(argv(1));
			}

			if(argc() > 2 && intval(argv(2)) > 0 && intval(argv(2)) <= 12) {
				$month = intval(argv(2));
			}

			$sections = [];
			$section = '';
			if(argc() > 1 && ctype_lower(argv(1))) {
				$section = argv(1);
			}

			switch ($section) {
				case 'channel':
					$sections = get_default_export_sections();
					break;
				case 'chatrooms':
					$sections = ['chatrooms'];
					break;
				case 'events':
					$sections = ['events'];
					break;
				case 'webpages':
					$sections = ['webpages'];
					break;
				case 'wikis':
					$sections = ['wikis'];
					break;
				case 'custom':
				default:
					$custom_sections = ['channel', 'connections', 'config', 'apps', 'chatrooms', 'events', 'webpages', 'wikis'];
					$raw_sections = (($_REQUEST['sections']) ? explode(',', $_REQUEST['sections']) : '');
					if ($raw_sections) {
						foreach ($raw_sections as $raw_section) {
							if(in_array($raw_section, $custom_sections)) {
								$sections[] = $raw_section;
							}
						}
					}
			}

			if ($sections) {

				$export = json_encode(identity_basic_export(local_channel(), $sections, $zap_compat));

				header('Content-Type: application/json');
				header('Content-Disposition: attachment; filename="' . $channel['channel_address'] . '-' . implode('-', $sections) . '.json"');
				header('Content-Length: ' . strlen($export));

				echo $export;

				killme();
			}
			elseif ($year && !$month) {
				$zip_dir = 'store/[data]/' . $channel['channel_address'] . '/tmp';
				if (!is_dir($zip_dir))
					mkdir($zip_dir, STORAGE_DEFAULT_PERMISSIONS, true);

				$zip_file = $channel['channel_address'] . '-' . $year . '.zip';
				$zip_path = $zip_dir . '/' . $zip_file;
				$zip_content_available = false;
				$zip = new ZipArchive();

				if ($zip->open($zip_path, ZipArchive::CREATE) === true) {
					$month = 1;
					while ($month <= 12) {
						$name = $channel['channel_address'] . '-' . $year . '-' . $month . '.json';
						$content = conv_item_export_year(local_channel(), $year, $month, $zap_compat);
						if(isset($content['item'])) {
							$zip_content_available = true;
							$zip->addFromString($name, json_encode($content));
						}
						$month++;
					}
					$zip->setCompressionName($zip_path, ZipArchive::CM_STORE);
					$zip->close();
				}
				if (!$zip_content_available) {
					unlink($zip_path);
					notice(t('No content available for year') . ' ' . $year . EOL);
					goaway('/uexport');
				}

				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename="' . $zip_file . '"');
				header('Content-Length: ' . filesize($zip_path));

				$istream = fopen($zip_path, 'rb');
				$ostream = fopen('php://output', 'wb');
				if ($istream && $ostream) {
					pipe_streams($istream, $ostream);
					fclose($istream);
					fclose($ostream);
				}

				unlink($zip_path);
				killme();
			}
			elseif ($year && $month) {
				$export = json_encode(conv_item_export_year(local_channel(), $year, $month, $zap_compat));

				header('Content-Type: application/json');
				header('Content-Disposition: attachment; filename="' . $channel['channel_address'] . '-' . $year . '-' . $month . '.json"');
				header('Content-Length: ' . strlen($export));

				echo $export;

				killme();
			}
			else {
				killme();
			}
		}
	}

	function get() {

		if(! local_channel()) {
			return;
		}

		if(! Apps::system_app_installed(local_channel(), 'Channel Export')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Channel Export');
			return Apps::app_render($papp, 'module');
		}

		$account = App::get_account();
		$year_start = datetime_convert('UTC', date_default_timezone_get(), $account['account_created'], 'Y');
		$year_end = datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y');
		$years = [];

		while ($year_start <= $year_end) {
			$years[] = $year_start;
			$year_start++;
		}

		$item_import_url = '/import_items';
		$channel_import_url = '/import';

		$o = replace_macros(get_markup_template('uexport.tpl'), array(
			'$title' => t('Export Channel'),

			'$channel_title' => t('Export channel'),
			'$channel_info' => t('This will export your identity and social graph into a file which can be used to import your channel to a new hub.'),

			'$years' => $years,
			'$content_title' => t('Export content'),
			'$content_info' => t('This will export your posts, direct messages, articles and cards per month stored into a zip file per year. Months with no posts will be dismissed.'),

			'$wikis_title' => t('Export wikis'),
			'$wikis_info' => t('This will export your wikis and wiki pages.'),

			'$webpages_title' => t('Export webpages'),
			'$webpages_info' => t('This will export your webpages and menus.'),

			'$events_title' => t('Export channel calendar'),
			'$events_info' => t('This will export your channel calendar events and associated items. CalDAV calendars are not included.'),

			'$chatrooms_title' => t('Export chatrooms'),
			'$chatrooms_info' => t('This will export your chatrooms. Chat history is dismissed.'),

			'$items_extra_info' => sprintf( t('This export can be imported or restored by visiting <a href="%1$s">%2$s</a> on any site containing your channel.'), $item_import_url, $item_import_url),
		));
		return $o;
	}




}
