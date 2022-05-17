<?php

/**
 *   * Name: New member
 *   * Description: Display useful links for new members to help them get started
 */

namespace Zotlabs\Widget;

class Newmember {

	function widget($arr) {

		if(! local_channel())
			return EMPTY_STR;

		$c = \App::get_channel();
		if(! $c)
			return EMPTY_STR;

		$a = \App::get_account();
		if(! $a)
			return EMPTY_STR;

		if($a['account_created'] > datetime_convert('','','now - 60 days')) {
			$enabled = get_pconfig(local_channel(), 'system', 'start_menu', 1);
		}
		else {
			$enabled = get_pconfig(local_channel(), 'system', 'start_menu', 0);
		}

		if(! $enabled)
			return EMPTY_STR;

		$options = [
			t('Profile Creation'),
			[
				'profile_photo' => t('Upload profile photo'),
				'cover_photo'   => t('Upload cover photo'),
				'profiles'      => t('Edit your profile'),
			],

			t('Find and Connect with others'),
			[
				'directory'              => t('View the directory'),
				'directory?f=&suggest=1' => t('View friend suggestions'),
				'connections'            => t('Manage your connections'),
			],

			t('Communicate'),
			[
				'channel/' . $c['channel_address']       => t('View your channel homepage'),
				'network'       => t('View your network stream'),
			],

			t('Miscellaneous'),
			[
				'settings'      => t('Settings'),
				'help'    	=> t('Documentation'),
			],

			t('Missing Features?'),
			[
				'apps' => t('Pin apps to navigation bar'),
				'apps/available' => t('Install more apps')
			]
		];

		$site_firehose = ((intval(get_config('system','site_firehose',0))) ? true : false);
		$net_firehose  = ((get_config('system','disable_discover_tab',1)) ? false : true);


		// hack to put this in the correct spot of the array

		if($site_firehose || $net_firehose) {
			$options[5]['pubstream'] = t('View public stream');
		}

		$o = replace_macros(get_markup_template('new_member.tpl'), [
			'$title' => t('New Member Links'),
			'$options' => $options

		]);

		return $o;

	}

}



