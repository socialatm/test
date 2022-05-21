<?php /** @file */

/*
 * Features management
 */





function feature_enabled($uid,$feature) {

	$x = get_config('feature_lock',$feature);
	if($x === false) {
		$x = get_pconfig($uid,'feature',$feature);
		if($x === false) {
			$x = get_config('feature',$feature);
			if($x === false)
				$x = get_feature_default($feature);
		}
	}
	$arr = array('uid' => $uid, 'feature' => $feature, 'enabled' => $x);
	call_hooks('feature_enabled',$arr);
	return($arr['enabled']);
}

function get_feature_default($feature) {
	$f = get_features(false);
	foreach($f as $cat) {
		foreach($cat as $feat) {
			if(is_array($feat) && $feat[0] === $feature) {
				return $feat[3];
			}
		}
	}
	return false;
}


function feature_level($feature,$def) {
	$x = get_config('feature_level',$feature);
	if($x !== false)
		return intval($x);
	return $def;
}

function process_module_features_get($uid, $features) {
	unset($features[0]);
	foreach($features as $f) {
		$arr[] = [
			'feature_' . $f[0],
			$f[1],
			((intval(feature_enabled($uid, $f[0]))) ? "1" : ''),
			$f[2],
			[t('Off'),t('On')],
			(($f[4] === false) ? '' : 'disabled'),
			$f[5]
		];
	}
	return $arr;
}

function process_module_features_post($uid, $features, $post_arr) {
	unset($features[0]);
	foreach($features as $f) {
		$k = $f[0];
		if(array_key_exists("feature_$k",$post_arr))
			set_pconfig($uid,'feature',$k, (string) $post_arr["feature_$k"]);
		else
			set_pconfig($uid,'feature', $k, '');
	}
}

function get_features($filtered = true, $level = (-1)) {

	$account = \App::get_account();

	$arr = [

		'calendar' => [

			t('Calendar'),

			[
				'cal_first_day',
				t('Start calendar week on Monday'),
				t('Default is Sunday'),
				false,
				get_config('feature_lock','cal_first_day')
			],

			[
				'event_tz_select',
				t('Event Timezone Selection'),
				t('Allow event creation in timezones other than your own.'),
				false,
				get_config('feature_lock','event_tz_select'),
			]

		],

		'channel_home' => [

			t('Channel Home'),

			[
				'archives',
				t('Search by Date'),
				t('Ability to select posts by date ranges'),
				false,
				get_config('feature_lock','archives')
			],

			[
				'tagadelic',
				t('Tag Cloud'),
				t('Provide a personal tag cloud on your channel page'),
				false,
				get_config('feature_lock','tagadelic'),
			],

			[
				'channel_list_mode',
				t('Use blog/list mode'),
				t('Comments will be displayed separately'),
				false,
				get_config('feature_lock','channel_list_mode'),
			]
		],

		'connections' => [

			t('Connections'),

			[
				'connfilter',
				t('Connection Filtering'),
				t('Filter incoming posts from connections based on keywords/content'),
				false,
				get_config('feature_lock','connfilter')
			]
		],

		'conversation' => [

			t('Conversation'),
			/* disable until we agree on how to implemnt this in zot6/activitypub
			[
				'commtag',
				t('Community Tagging'),
				t('Ability to tag existing posts'),
				false,
				get_config('feature_lock','commtag'),
			],
			*/
			[
				'emojis',
				t('Emoji Reactions'),
				t('Add emoji reaction ability to posts'),
				true,
				get_config('feature_lock','emojis'),
			],

			[
				'dislike',
				t('Dislike Posts'),
				t('Ability to dislike posts/comments'),
				false,
				get_config('feature_lock','dislike'),
			],

			[
				'star_posts',
				t('Star Posts'),
				t('Ability to mark special posts with a star indicator'),
				false,
				get_config('feature_lock','star_posts'),
			],

			[
				'reply_to',
				t('Reply on comment'),
				t('Ability to reply on selected comment'),
				false,
				get_config('feature_lock','reply_to'),
			]

		],

		'directory' => [

			t('Directory'),

			[
				'advanced_dirsearch',
				t('Advanced Directory Search'),
				t('Allows creation of complex directory search queries'),
				false,
				get_config('feature_lock','advanced_dirsearch'),
			]

		],

		'editor' => [

			t('Editor'),

			[
				'categories',
				t('Post Categories'),
				t('Add categories to your posts'),
				false,
				get_config('feature_lock','categories'),
			],

			[
				'large_photos',
				t('Large Photos'),
				t('Include large (1024px) photo thumbnails in posts. If not enabled, use small (640px) photo thumbnails'),
				false,
				get_config('feature_lock','large_photos'),
			],

			[
				'content_encrypt',
				t('Even More Encryption'),
				t('Allow optional encryption of content end-to-end with a shared secret key'),
				false,
				get_config('feature_lock','content_encrypt'),
			],

			[
				'disable_comments',
				t('Disable Comments'),
				t('Provide the option to disable comments for a post'),
				false,
				get_config('feature_lock','disable_comments'),
			],

			[
				'delayed_posting',
				t('Delayed Posting'),
				t('Allow posts to be published at a later date'),
				false,
				get_config('feature_lock','delayed_posting'),
			],

			[
				'content_expire',
				t('Content Expiration'),
				t('Remove posts/comments and/or private messages at a future time'),
				false,
				get_config('feature_lock','content_expire'),
			],

			[
				'suppress_duplicates',
				t('Suppress Duplicate Posts/Comments'),
				t('Prevent posts with identical content to be published with less than two minutes in between submissions.'),
				true,
				get_config('feature_lock','suppress_duplicates'),
			],

			[
				'auto_save_draft',
				t('Auto-save drafts of posts and comments'),
				t('Automatically saves post and comment drafts in local browser storage to help prevent accidental loss of compositions'),
				true,
				get_config('feature_lock','auto_save_draft'),
			]

		],

		'manage' => [

			t('Manage'),

			[
				'nav_channel_select',
				t('Navigation Channel Select'),
				t('Change channels directly from within the navigation dropdown menu'),
				false,
				get_config('feature_lock','nav_channel_select'),
			]

		],

		'network' => [

			t('Network'),

			[
				'events_tab',
				t('Events Filter'),
				t('Ability to display only events'),
				false,
				get_config('feature_lock','events_tab')
			],

			[
				'polls_tab',
				t('Polls Filter'),
				t('Ability to display only polls'),
				false,
				get_config('feature_lock','polls_tab')
			],

			[
				'savedsearch',
				t('Saved Searches'),
				t('Save search terms for re-use'),
				false,
				get_config('feature_lock','savedsearch')
			],

			[
				'filing',
				t('Saved Folders'),
				t('Ability to file posts under folders'),
				false,
				get_config('feature_lock','filing'),
			],

			[
				'order_tab',
				t('Alternate Stream Order'),
				t('Ability to order the stream by last post date, last comment date or unthreaded activities'),
				false,
				get_config('feature_lock','order_tab')
			],

			[
				'name_tab',
				t('Contact Filter'),
				t('Ability to display only posts of a selected contact'),
				false,
				get_config('feature_lock','name_tab')
			],

			[
				'forums_tab',
				t('Forum Filter'),
				t('Ability to display only posts of a specific forum'),
				false,
				get_config('feature_lock','forums_tab')
			],

			[
				'personal_tab',
				t('Personal Posts Filter'),
				t('Ability to display only posts that you\'ve interacted on'),
				false,
				get_config('feature_lock','personal_tab')
			],

			[
				'network_list_mode',
				t('Use blog/list mode'),
				t('Comments will be displayed separately'),
				false,
				get_config('feature_lock','network_list_mode'),
			]

		],

		'photos' => [

			t('Photos'),

			[
				'photo_location',
				t('Photo Location'),
				t('If location data is available on uploaded photos, link this to a map.'),
				false,
				get_config('feature_lock','photo_location'),
			],

			[
				'adult_photo_flagging',
				t('Flag Adult Photos'),
				t('Provide photo edit option to hide inappropriate photos from default album view'),
				false,
				get_config('feature_lock','adult_photo_flagging'),
			]

		],

		'profiles' => [

			t('Profiles'),

			[
				'advanced_profiles',
				t('Advanced Profiles'),
				t('Additional profile sections and selections'),
				false,
				get_config('feature_lock','advanced_profiles')
			],

			[
				'profile_export',
				t('Profile Import/Export'),
				t('Save and load profile details across sites/channels'),
				false,
				get_config('feature_lock','profile_export')
			],

			[
				'multi_profiles',
				t('Multiple Profiles'),
				t('Ability to create multiple profiles'),
				false,
				get_config('feature_lock','multi_profiles')
			]

		]


	];

	$x = [ 'features' => $arr, ];
	call_hooks('get_features',$x);

	$arr = $x['features'];

	// removed any locked features and remove the entire category if this makes it empty

	if($filtered) {
		$narr = [];
		foreach($arr as $k => $x) {
			$narr[$k] = [ $arr[$k][0] ];
			$has_items = false;
			for($y = 0; $y < count($arr[$k]); $y ++) {
				$disabled = false;
				if(is_array($arr[$k][$y])) {
					if($arr[$k][$y][4] !== false) {
						$disabled = true;
					}
					if(! $disabled) {
						$has_items = true;
						$narr[$k][$y] = $arr[$k][$y];
					}
				}
			}
			if(! $has_items) {
				unset($narr[$k]);
			}
		}
	}
	else {
		$narr = $arr;
	}

	return $narr;
}

function get_module_features($module) {
	$features = get_features(false);
	return $features[$module];
}
