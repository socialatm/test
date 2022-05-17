<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Render\Comanche;
use Zotlabs\Lib\Libsync;

class Pdledit_gui extends Controller {

	function post() {

		if (!local_channel()) {
			return;
		}

		if (!$_REQUEST['module']) {
			return;
		}

		$module = $_REQUEST['module'];

		$ret = [
			'success' => false,
			'module' => $module
		];

		if ($_REQUEST['reset']) {
			del_pconfig(local_channel(), 'system', 'mod_' . $module . '.pdl');
			Libsync::build_sync_packet();
			$ret['success'] = true;
			json_return_and_die($ret);
		}

		if ($_REQUEST['save']) {
			if (!$_REQUEST['data']) {
				return $ret;
			}

			$data = json_decode($_REQUEST['data'],true);
			$stored_pdl_result = self::get_pdl($module);
			$pdl = $stored_pdl_result['pdl'];

			foreach ($data as $region => $entries) {
				$region_pdl = '';
				foreach ($entries as $entry) {
					$region_pdl .= base64_decode($entry) . "\r\n";
				}
				$pdl = preg_replace('/\[region=' . $region . '\](.*?)\[\/region\]/ism', '[region=' . $region . ']' . "\r\n" . $region_pdl . "\r\n" . '[/region]', $pdl);
			}

			set_pconfig(local_channel(), 'system', 'mod_' . $module . '.pdl', escape_tags($pdl));
			Libsync::build_sync_packet();

			$ret['success'] = true;
			json_return_and_die($ret);
		}

		if ($_REQUEST['save_src']) {
			set_pconfig(local_channel(), 'system', 'mod_' . $module . '.pdl', escape_tags($_REQUEST['src']));
			Libsync::build_sync_packet();

			$ret['success'] = true;
			json_return_and_die($ret);
		}

		if ($_REQUEST['save_template']) {
			if (!$_REQUEST['data']) {
				return $ret;
			}

			$template = $_REQUEST['data'][0]['value'];
			$pdl_result = self::get_pdl($module);
			$stored_template = self::get_template($pdl_result['pdl']);

			if ($template === $stored_template) {
				$ret['success'] = true;
				return $ret;
			}

			$cnt = preg_match("/\[template\](.*?)\[\/template\]/ism", $pdl_result['pdl'], $matches);
			if ($cnt) {
				$pdl = str_replace('[template]' . $stored_template . '[/template]', '[template]' . $template . '[/template]', $pdl_result['pdl']);
			}
			else {
				$pdl = '[template]' . $template . '[/template]' . "\r\n";
				$pdl .= $pdl_result['pdl'];
			}

			set_pconfig(local_channel(), 'system', 'mod_' . $module . '.pdl', escape_tags($pdl));
			Libsync::build_sync_packet();

			$ret['success'] = true;
			json_return_and_die($ret);
		}

	}

	function get() {

		if(! local_channel()) {
			return EMPTY_STR;
		}

		$module = argv(1);

		if (!$module) {
			goaway(z_root() . '/pdledit_gui/hq');
		}

		$pdl_result = self::get_pdl($module);

		$pdl = $pdl_result['pdl'];
		$modified = $pdl_result['modified'];

		if(!$pdl) {
			return t('Layout not found');
		}

		$template = self::get_template($pdl);

		$template_info = self::get_template_info($template);

		if(empty($template_info['contentregion'])) {
			return t('This template does not support pdledi_gui (no content regions defined)');
		}

		App::$page['template'] = $template;

		$regions = self::get_regions($pdl);

		foreach ($regions as $k => $v) {
			$region_str = '';
			if (is_array($v)) {
				ksort($v);
				foreach ($v as $entry) {
					// Get the info from the file and replace entry if we get anything useful
					$widget_info = get_widget_info($entry['name']);
					$entry['name'] = (($widget_info['name']) ? $widget_info['name'] : $entry['name']);
					$entry['desc'] = (($widget_info['description']) ? $widget_info['description'] : $entry['desc']);

					$region_str .= replace_macros(get_markup_template('pdledit_gui_item.tpl'), [
						'$entry' => $entry
					]);
				}
			}
			App::$layout['region_' . $k] = $region_str;
		}

		$templates = self::get_templates();
		$templates_html = replace_macros(get_markup_template('pdledit_gui_templates.tpl'), [
			'$templates' => $templates,
			'$active' => $template
		]);

		$items_html = '';

		//$items_html .= replace_macros(get_markup_template('pdledit_gui_item.tpl'), [
			//'$entry' => [
				//'type' => 'content',
				//'name' => t('Main page content'),
				//'src' => base64_encode('$content')
			//],
			//'$disable_controls' => true
		//]);

		foreach (self::get_widgets($module) as $entry) {
			$items_html .= replace_macros(get_markup_template('pdledit_gui_item.tpl'), [
				'$entry' => $entry,
				'$disable_controls' => true
			]);
		}

		foreach (self::get_menus() as $entry) {
			$items_html .= replace_macros(get_markup_template('pdledit_gui_item.tpl'), [
				'$entry' => $entry,
				'$disable_controls' => true
			]);
		}

		foreach (self::get_blocks() as $entry) {
			$items_html .= replace_macros(get_markup_template('pdledit_gui_item.tpl'), [
				'$entry' => $entry,
				'$disable_controls' => true
			]);
		}

		App::$layout['region_content'] .= replace_macros(get_markup_template('pdledit_gui.tpl'), [
			'$content_regions' => $template_info['contentregion'],
			'$page_src' => base64_encode($pdl),
			'$templates' => base64_encode($templates_html),
			'$modules' => base64_encode(self::get_modules()),
			'$items' => base64_encode($items_html),
			'$module_modified' => $modified,
			'$module' => $module
		]);

	}

	function get_templates() {
		$ret = [];

		$files = glob('view/php/*.php');
		if($files) {
			foreach($files as $f) {
				$name = basename($f, '.php');
				$x = get_template_info($name);
				if(!empty($x['contentregion'])) {
					$ret[] = [
						'name' => $name,
						'desc' => $x['description']
					];
				}
			}
		}

		return $ret;
	}

	function get_modules() {
		$ret = '';

		$files = glob('Zotlabs/Module/*.php');
		if($files) {
			foreach($files as $f) {
				$name = lcfirst(basename($f,'.php'));

				if ($name === 'admin' && !is_site_admin()) {
					continue;
				}

				$x = theme_include('mod_' . $name . '.pdl');
				if($x) {
					$ret .= '<div class="mb-2"><a href="pdledit_gui/' . $name . '">' . $name . '</a></div>';
				}
			}
		}

		return $ret;
	}

	function get_widgets($module) {
		$ret = [];

		$checkpaths = [
			'Zotlabs/Widget/*.php'
		];

		foreach ($checkpaths as $path) {
			$files = glob($path);
			if($files) {
				foreach($files as $f) {
					$name = lcfirst(basename($f, '.php'));

					$widget_info = get_widget_info($name);
					if ($widget_info['requires'] && strpos($widget_info['requires'], 'admin') !== false && !is_site_admin()) {
						continue;
					}

					if ($widget_info['requires'] && strpos($widget_info['requires'], $module) === false) {
						continue;
					}

					$ret[] = [
						'type' => 'widget',
						'name' => $widget_info['name'] ?? $name,
						'desc' => $widget_info['description'] ?? '',
						'src' => base64_encode('[widget=' . $name . '][/widget]')
					];
				}
			}
		}

		return $ret;
	}

	function get_menus() {
		$ret = [];

		$r = q("select * from menu where menu_channel_id = %d and menu_flags = 0",
			intval(local_channel())
		);

		foreach ($r as $rr) {
			$name = $rr['menu_name'];
			$desc = $rr['menu_desc'];
			$ret[] = [
				'type' => 'menu',
				'name' => $name,
				'desc' => $desc,
				'src' => base64_encode('[menu]' . $name . '[/menu]')
			];
		}

		return $ret;
	}

	function get_blocks() {
		$ret = [];

		$r = q("select v, title, summary from item join iconfig on iconfig.iid = item.id and item.uid = %d
			and iconfig.cat = 'system' and iconfig.k = 'BUILDBLOCK'",
			intval(local_channel())
		);

		foreach ($r as $rr) {
			$name = $rr['v'];
			$desc = (($rr['title']) ? $rr['title'] : $rr['summary']);
			$ret[] = [
				'type' => 'block',
				'name' => $name,
				'desc' => $desc,
				'src' => base64_encode('[block]' . $name . '[/block]')
			];
		}

		return $ret;
	}

	function get_template($pdl) {
		$ret = 'default';

		$cnt = preg_match("/\[template\](.*?)\[\/template\]/ism", $pdl, $matches);
		if($cnt && isset($matches[1])) {
			$ret = trim($matches[1]);
		}

		return $ret;
	}

	function get_regions($pdl) {
		$ret = [];
		$supported_regions = ['aside', 'content', 'right_aside'];

		$cnt = preg_match_all("/\[region=(.*?)\](.*?)\[\/region\]/ism", $pdl, $matches, PREG_SET_ORDER);
		if($cnt) {
			foreach($matches as $mtch) {
				if (!in_array($mtch[1], $supported_regions)) {
					continue;
				}
				$ret[$mtch[1]] = self::parse_region($mtch[2]);
			}
		}

		return $ret;
	}

	function parse_region($pdl) {
		$ret = [];

		$cnt = preg_match_all('/\$content\b/ism', $pdl, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
		if($cnt) {
			foreach($matches as $mtch) {
				$offset = intval($mtch[0][1]);
				$name = trim($mtch[0][0]);
				//$src = base64url_encode(preg_replace(['/\s*\[/', '/\]\s*/'], ['[', ']'], $mtch[0][0]));
				$src = base64_encode($mtch[0][0]);
				$ret[$offset] = [
					'type' => 'content',
					'name' => t('Main page content'),
					'desc' => t('The main page content can not be edited!'),
					'src' => $src
				];
			}
		}

		$cnt = preg_match_all("/\[menu\](.*?)\[\/menu\]/ism", $pdl, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
		if($cnt) {
			foreach($matches as $mtch) {
				$offset = intval($mtch[1][1]);
				$name = trim($mtch[1][0]);
				//$src = base64url_encode(preg_replace(['/\s*\[/', '/\]\s*/'], ['[', ']'], $mtch[0][0]));
				$src = base64_encode($mtch[0][0]);

				$ret[$offset] = [
					'type' => 'menu',
					'name' => $name,
					'desc' => '',
					'src' => $src
				];
			}
		}

		// menu class e.g. [menu=horizontal]my_menu[/menu] or [menu=tabbed]my_menu[/menu]
		// allows different menu renderings to be applied

		//$cnt = preg_match_all("/\[menu=(.*?)\](.*?)\[\/menu\]/ism", $s, $matches, PREG_SET_ORDER);
		//if($cnt) {
			//foreach($matches as $mtch) {
				//$s = str_replace($mtch[0],$this->menu(trim($mtch[2]),$mtch[1]),$s);
			//}
		//}


		$cnt = preg_match_all("/\[block\](.*?)\[\/block\]/ism", $pdl, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
		if($cnt) {
			foreach($matches as $mtch) {
				$offset = intval($mtch[1][1]);
				$name = trim($mtch[1][0]);
				//$src = base64url_encode(preg_replace(['/\s*\[/', '/\]\s*/'], ['[', ']'], $mtch[0][0]));
				$src = base64_encode($mtch[0][0]);
				$ret[$offset] = [
					'type' => 'block',
					'name' => $name,
					'desc' => '',
					'src' => $src
				];
			}
		}

		//$cnt = preg_match_all("/\[block=(.*?)\](.*?)\[\/block\]/ism", $s, $matches, PREG_SET_ORDER);
		//if($cnt) {
			//foreach($matches as $mtch) {
				//$s = str_replace($mtch[0],$this->block(trim($mtch[2]),trim($mtch[1])),$s);
			//}
		//}

		//$cnt = preg_match_all("/\[js\](.*?)\[\/js\]/ism", $s, $matches, PREG_SET_ORDER);
		//if($cnt) {
			//foreach($matches as $mtch) {
				//$s = str_replace($mtch[0],$this->js(trim($mtch[1])),$s);
			//}
		//}

		//$cnt = preg_match_all("/\[css\](.*?)\[\/css\]/ism", $s, $matches, PREG_SET_ORDER);
		//if($cnt) {
			//foreach($matches as $mtch) {
				//$s = str_replace($mtch[0],$this->css(trim($mtch[1])),$s);
			//}
		//}

		// need to modify this to accept parameters

		$cnt = preg_match_all("/\[widget=(.*?)\](.*?)\[\/widget\]/ism", $pdl, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);

		if($cnt) {
			foreach($matches as $mtch) {
				$offset = intval($mtch[1][1]);
				$name = trim($mtch[1][0]);
				//$src = base64url_encode(preg_replace(['/\s*\[/', '/\]\s*/'], ['[', ']'], $mtch[0][0]));
				$src = base64_encode($mtch[0][0]);
				$ret[$offset] = [
					'type' => 'widget',
					'name' => $name,
					'desc' => '',
					'src' => $src
				];
			}
		}

		return $ret;

	}


	/**
	 * @brief Parse template comment in search of template info.
	 *
	 * like
	 * \code
	 *   * Name: MyWidget
	 *   * Description: A widget
	 *   * Version: 1.2.3
	 *   * Author: John <profile url>
	 *   * Author: Jane <email>
	 *   * ContentRegionID: some_id
	 *   * ContentRegionID: some_other_id
	 *   *
	 *\endcode
	 * @param string $template the name of the template
	 * @return array with the information
	 */
	function get_template_info($template){
		$m = [];
		$info = [
			'name' => $template,
			'description' => '',
			'author' => [],
			'maintainer' => [],
			'version' => '',
			'contentregion' => []
		];

		$checkpaths = [
			'view/php/' . $template . '.php',
		];

		$template_found = false;

		foreach ($checkpaths as $path) {
			if (is_file($path)) {
				$template_found = true;
				$f = file_get_contents($path);
				break;
			}
		}

		if(!($template_found && $f))
			return $info;

		$f = escape_tags($f);
		$r = preg_match('|/\*.*\*/|msU', $f, $m);

		if ($r) {
			$ll = explode("\n", $m[0]);
			foreach($ll as $l) {
				$l = trim($l, "\t\n\r */");
				if ($l != ''){
					list($k, $v) = array_map('trim', explode(':', $l, 2));
					$k = strtolower($k);
					if (in_array($k, ['author', 'maintainer'])){
						$r = preg_match('|([^<]+)<([^>]+)>|', $v, $m);
						if ($r) {
							$info[$k][] = array('name' => $m[1], 'link' => $m[2]);
						} else {
							$info[$k][] = array('name' => $v);
						}
					}
					elseif (in_array($k, ['contentregion'])){
						$info[$k][] = array_map('trim', explode(',', $v));
					}
					else {
						$info[$k] = $v;
					}
				}
			}
		}

		return $info;
	}

	function get_pdl($module) {
		$ret = [
			'pdl' => null,
			'modified' => true
		];

		$pdl_path = 'mod_' . $module . '.pdl';

		$ret['pdl'] = get_pconfig(local_channel(), 'system', $pdl_path);

		if(!$ret['pdl']) {
			$pdl_path = theme_include($pdl_path);
			if ($pdl_path) {
				$ret['pdl'] = file_get_contents($pdl_path);
				$ret['modified'] = false;
			}
		}

		return $ret;
	}
}
