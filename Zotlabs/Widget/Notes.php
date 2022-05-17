<?php

/**
 *   * Name: Notes
 *   * Description: A simple notes widget, requires the Notes App
 *   * Author: Mike Macgirvin
 *   * Author: Mario Vavti
 *   * Maintainer: Mario Vavti
 */

namespace Zotlabs\Widget;

use Zotlabs\Lib\Apps;

class Notes {

	function widget($arr) {
		if(! local_channel())
			return EMPTY_STR;

		if(! Apps::system_app_installed(local_channel(), 'Notes'))
			return EMPTY_STR;

		$text = get_pconfig(local_channel(),'notes','text');

		$tpl = get_markup_template('notes.tpl');

		$o = replace_macros($tpl, array(
			'$text' => $text,
			'$html' => bbcode($text),
			'$app' => ((isset($arr['app'])) ? true : false),
			'$hidden' => ((isset($arr['hidden'])) ? true : false),
			'$strings' => [
				'title' => t('Notes'),
				'read' => t('Read mode'),
				'edit' => t('Edit mode'),
				'editing' => t('Editing'),
				'saving' => t('Saving'),
				'saved' => t('Saved'),
				'dots' => '<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span>'
			]
		));

		return $o;
	}
}
