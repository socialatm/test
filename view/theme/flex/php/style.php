<?php

if(! App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		load_pconfig($uid,'redbasic');
	}

	// Load the owners pconfig
	$nav_bg = get_pconfig($uid, 'redbasic', 'nav_bg');
	$nav_icon_colour = get_pconfig($uid, 'redbasic', 'nav_icon_colour');
	$nav_active_icon_colour = get_pconfig($uid, 'redbasic', 'nav_active_icon_colour');
	$banner_colour = get_pconfig($uid,'redbasic','banner_colour');
	$narrow_navbar = get_pconfig($uid,'redbasic','narrow_navbar');
	$link_colour = get_pconfig($uid, 'redbasic', 'link_colour');
	$link_hover_colour = get_pconfig($uid, 'redbasic', 'link_hover_colour');
	$schema = get_pconfig($uid,'redbasic','schema');
	$bgcolour = get_pconfig($uid, 'redbasic', 'background_colour');
	$background_image = get_pconfig($uid, 'redbasic', 'background_image');
	$item_colour = get_pconfig($uid, 'redbasic', 'item_colour');
	$comment_item_colour = get_pconfig($uid, 'redbasic', 'comment_item_colour');
	$item_opacity = get_pconfig($uid, 'redbasic', 'item_opacity');
	$font_size = get_pconfig($uid, 'redbasic', 'font_size');
	$font_colour = get_pconfig($uid, 'redbasic', 'font_colour');
	$radius = get_pconfig($uid, 'redbasic', 'radius');
	$shadow = get_pconfig($uid,'redbasic','photo_shadow');
	$converse_width=get_pconfig($uid,'redbasic','converse_width');
	$top_photo=get_pconfig($uid,'redbasic','top_photo');
	$reply_photo=get_pconfig($uid,'redbasic','reply_photo');
}

// Now load the scheme.  If a value is changed above, we'll keep the settings
// If not, we'll keep those defined by the schema
// Setting $schema to '' wasn't working for some reason, so we'll check it's
// not --- like the mobile theme does instead.

// Allow layouts to over-ride the schema
if (isset($_REQUEST['schema']) && preg_match('/^[\w_-]+$/i', $_REQUEST['schema'])) {
  $schema = $_REQUEST['schema'];
}

if (($schema) && ($schema != '---')) {

	// Check it exists, because this setting gets distributed to clones
	if(file_exists('view/theme/redbasic/schema/' . $schema . '.php')) {
		$schemefile = 'view/theme/redbasic/schema/' . $schema . '.php';
		require_once ($schemefile);
	}

	if(file_exists('view/theme/redbasic/schema/' . $schema . '.css')) {
		$schemecss = file_get_contents('view/theme/redbasic/schema/' . $schema . '.css');
	}

}

// Allow admins to set a default schema for the hub.
// default.php and default.css MUST be symlinks to existing schema files in view/theme/redbasic/schema
if ((!$schema) || ($schema == '---')) {

	if(file_exists('view/theme/redbasic/schema/default.php')) {
		$schemefile = 'view/theme/redbasic/schema/default.php';
		require_once ($schemefile);
	}

	$schemecss = '';
	if(file_exists('view/theme/redbasic/schema/default.css')) {
		$schemecss = file_get_contents('view/theme/redbasic/schema/default.css');
	}

}

//Set some defaults - we have to do this after pulling owner settings, and we have to check for each setting
//individually.  If we don't, we'll have problems if a user has set one, but not all options.
if (! $nav_bg)
	$nav_bg = '#343a40';
if (! $nav_icon_colour)
	$nav_icon_colour = 'rgba(255, 255, 255, 0.55)';
if (! $nav_active_icon_colour)
	$nav_active_icon_colour = 'rgba(255, 255, 255, 0.75)';
if (! $link_colour)
	$link_colour = '#0d6efd';
if (! $link_hover_colour)
	$link_hover_colour = '#0a58ca;';
if (! $banner_colour)
	$banner_colour = '#efefef';
if (! $bgcolour)
	$bgcolour = 'rgb(254,254,254)';
if (! $background_image)
	$background_image ='';
if (! $item_colour)
	$item_colour = '#f6f6f6';
if (! $comment_item_colour)
	$comment_item_colour = 'rgb(255,255,255)';
if (! $item_opacity)
	$item_opacity = '1';
if (! $font_size)
	$font_size = '0.875rem';
if (! $font_colour)
	$font_colour = '#4d4d4d';
if (! $radius)
	$radius = '0.375rem';
if (! $shadow)
	$shadow = '0';
if (! $converse_width)
	$converse_width = '52'; //unit: rem
if(! $top_photo)
	$top_photo = '2.3rem';
if(! $reply_photo)
	$reply_photo = '2.3rem';

// Apply the settings
if(file_exists('view/theme/redbasic/css/style.css')) {

	$x = file_get_contents('view/theme/redbasic/css/style.css');

	if($schema === 'dark' && file_exists('view/theme/redbasic/schema/bootstrap-nightfall.css')) {
		$x .= file_get_contents('view/theme/redbasic/schema/bootstrap-nightfall.css');
	}

	if($narrow_navbar && file_exists('view/theme/redbasic/css/narrow_navbar.css')) {
		$x .= file_get_contents('view/theme/redbasic/css/narrow_navbar.css');
	}

	if($schemecss) {
		$x .= $schemecss;
	}

	$left_aside_width = 21; //unit: rem
	$right_aside_width = 21; //unit: rem

	$main_width = $left_aside_width + $right_aside_width + intval($converse_width);

	// prevent main_width smaller than 768px
	$main_width = (($main_width < 30) ? 30 : $main_width);

	$options = array (
		'$nav_bg' => $nav_bg,
		'$nav_icon_colour' => $nav_icon_colour,
		'$nav_active_icon_colour' => $nav_active_icon_colour,
		'$link_colour' => $link_colour,
		'$link_hover_colour' => $link_hover_colour,
		'$banner_colour' => $banner_colour,
		'$bgcolour' => $bgcolour,
		'$background_image' => $background_image,
		'$item_colour' => $item_colour,
		'$comment_item_colour' => $comment_item_colour,
		'$font_size' => $font_size,
		'$font_colour' => $font_colour,
		'$radius' => $radius,
		'$shadow' => $shadow,
		'$converse_width' => $converse_width,
		'$top_photo' => $top_photo,
		'$reply_photo' => $reply_photo,
		'$main_width' => $main_width,
		'$left_aside_width' => $left_aside_width,
		'$right_aside_width' => $right_aside_width
	);

	echo str_replace(array_keys($options), array_values($options), $x);

}

// Set the schema to the default schema in derived themes. See the documentation for creating derived themes how to override this.

if(local_channel() && App::$channel && App::$channel['channel_theme'] != 'redbasic')
	set_pconfig(local_channel(), 'redbasic', 'schema', '---');
