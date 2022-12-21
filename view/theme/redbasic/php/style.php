<?php

if(! App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) { load_pconfig($uid,'redbasic'); }

	// Load the owners pconfig
	$schema = get_pconfig($uid,'redbasic','schema');
}

// Now load the scheme.  If a value is changed above, we'll keep the settings
// If not, we'll keep those defined by the schema
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

// Apply the settings

if(file_exists('view/theme/redbasic/css/style.css')) {

	$x = file_get_contents('view/theme/redbasic/css/style.css');

	if($schema === 'dark' && file_exists('view/theme/redbasic/schema/bootstrap-nightfall.css')) {
		$x .= file_get_contents('view/theme/redbasic/schema/bootstrap-nightfall.css');
	}

	if($schemecss) { $x .= $schemecss; }

	$options = array (
		
	);

	echo str_replace(array_keys($options), array_values($options), $x);

}

// Set the schema to the default schema in derived themes. 
// See the documentation for creating derived themes how to override this.

if(local_channel() && App::$channel && App::$channel['channel_theme'] != 'redbasic')
	set_pconfig(local_channel(), 'redbasic', 'schema', '---');
