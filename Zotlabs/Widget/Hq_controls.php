<?php

/**
 *   * Name: HQ Controls
 *   * Description: Control buttons for the HQ module
 *   * Author: Mario Vavti
 *   * Requires: hq
 */

namespace Zotlabs\Widget;

use Zotlabs\Lib\Apps;


class Hq_controls {

	function widget($options) {

		if (! local_channel())
			return;

		$entries['toggle_editor'] = [
			'label' => t('Toggle post editor'),
			'href' => '#',
			'class' => 'btn jot-toggle',
			'type' => 'button',
			'icon' => 'pencil',
			'extra' => 'data-toggle="button"'
		];

		if(Apps::system_app_installed(local_channel(), 'Notes')) {
			$entries['toggle_notes'] = [
				'label' => t('Toggle personal notes'),
				'href' => '#',
				'class' => 'btn notes-toggle',
				'type' => 'button',
				'icon' => 'sticky-note-o',
				'extra' => 'data-toggle="button"'
			];
		}

		$entries['toggle_channel_activities'] = [
			'label' => t('Channel activities'),
			'href' => '#',
			'class' => 'btn channel-activities-toggle d-none',
			'type' => 'button',
			'icon' => 'user-circle-o',
			'extra' => 'data-toggle="button"'
		];

		return replace_macros(get_markup_template('hq_controls.tpl'),
			[
				'$entries' => $entries,
				'$wrapper_class' => $options['wrapper_class'],
				'$entry_class' => $options['entry_class']
			]
		);
	}
}
