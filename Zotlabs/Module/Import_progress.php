<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\PConfig;
use Zotlabs\Daemon\Master;

class Import_progress extends \Zotlabs\Web\Controller {

	function post() {

		if(! local_channel())
			return;

	}

	function get() {

		if(! local_channel()) {
			return;
		}

		nav_set_selected('Channel Import');

		// items
		$c = PConfig::Get(local_channel(), 'import', 'content_progress');

		if ($c) {
			$total_cpages = floor(intval($c['items_total']) / intval($c['items_page']));
			if(!$total_cpages) {
				$total_cpages = 1; // because of floor
			}

			$cpage = $c['last_page'] + 1; // because page count start at 0

			$cprogress = intval(floor((intval($cpage) * 100) / $total_cpages));
			$ccompleted_str = t('Item sync completed!');

			if(argv(1) === 'resume_itemsync' && $cprogress < 100) {
				Master::Summon($c['next_cmd']);
				goaway('/import_progress');
			}
		}
		else {
			$cprogress = 'waiting to start...';

			if (PConfig::Get(local_channel(), 'import', 'content_completed')) {
				// There was nothing todo. Fake 100% and mention that there were no files found
				$cprogress = 100;
			}

			$ccompleted_str = t('Item sync completed but no items were found!');

			if(argv(1) === 'resume_itemsync') {
				Master::Summon(["Content_importer","0","0001-01-01 00:00:00","2021-10-02 19:49:14","ct5","https%3A%2F%2Fhub.somaton.com"]);
				goaway('/import_progress');
			}
		}

		$cprogress_str = ((intval($cprogress)) ? $cprogress . '%' : $cprogress);

		// files
		$f = PConfig::Get(local_channel(), 'import', 'files_progress');

		if ($f) {
			$total_fpages = floor(intval($f['files_total']) / intval($f['files_page']));
			if(!$total_fpages) {
				$total_fpages = 1;
			}

			$fpage = $f['last_page'] + 1;

			$fprogress = intval(floor((intval($fpage) * 100) / $total_fpages));
			$fcompleted_str = t('File sync completed!');

			if(argv(1) === 'resume_filesync' && $fprogress < 100) {
				Master::Summon($f['next_cmd']);
				goaway('/import_progress');
			}


		}
		else {
			$fprogress = 'waiting to start...';

			if (PConfig::Get(local_channel(), 'import', 'files_completed')) {
				// There was nothing todo. Fake 100% and mention that there were no files found
				$fprogress = 100;
			}

			$fcompleted_str = t('File sync completed but no files were found!');
		}

		$fprogress_str = ((intval($fprogress)) ? $fprogress . '%' : $fprogress);

		if(is_ajax()) {
			$ret = [
				'cprogress' => $cprogress,
				'fprogress' => $fprogress
			];

			json_return_and_die($ret);
		}

		$o = replace_macros(get_markup_template("import_progress.tpl"), [
			'$chtitle_str' => t('Channel clone status'),
			'$ctitle_str' => t('Item sync status'),
			'$ftitle_str' => t('File sync status'),
			'$cprogress_str' => $cprogress_str,
			'$cprogress' => intval($cprogress),
			'$fprogress_str' => $fprogress_str,
			'$fprogress' => intval($fprogress),
			'$fcompleted_str' => $fcompleted_str,
			'$ccompleted_str' => $ccompleted_str,
			'$chcompleted_str' => t('Channel cloning completed!'),
			'$resume_str' => t('Resume'),
			'$resume_helper_str' => t('Only resume if sync stalled!')
		]);

		return $o;
	}

}
