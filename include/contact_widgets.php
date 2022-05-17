<?php /** @file */

use Zotlabs\Lib\Cache;

function findpeople_widget() {

	if(get_config('system','invitation_only')) {
		$x = get_pconfig(local_channel(),'system','invites_remaining');
		if($x || is_site_admin()) {
			App::$page['aside'] .= '<div class="side-link" id="side-invite-remain">'
			. sprintf( tt('%d invitation available','%d invitations available',$x), $x)
			. '</div>';
		}
	}

	$advanced_search = ((local_channel() && feature_enabled(local_channel(),'advanced_dirsearch')) ? t('Advanced') : false);

	return replace_macros(get_markup_template('peoplefind.tpl'),array(
		'$findpeople' => t('Find Channels'),
		'$desc' => t('Enter name or interest'),
		'$label' => t('Connect/Follow'),
		'$hint' => t('Examples: Robert Morgenstein, Fishing'),
		'$findthem' => t('Find'),
		'$suggest' => t('Channel Suggestions'),
		'$similar' => '', /// @FIXME fixme and uncomment when mod/match working // t('Similar Interests'),
		'$random' => t('Random Profile'),
		'$inv' => t('Invite Friends'),
		'$advanced_search' => $advanced_search,
		'$advanced_hint' => "\r\n" . t('Advanced example: name=fred and country=iceland'),
		'$loggedin' => local_channel()
	));

}


function fileas_widget($baseurl,$selected = '') {

	if(! local_channel())
		return '';

	$terms = array();
	$r = q("select distinct(term) from term where uid = %d and ttype = %d order by term asc",
		intval(local_channel()),
		intval(TERM_FILE)
	);
	if(! $r)
		return;

	foreach($r as $rr)
		$terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

	return replace_macros(get_markup_template('fileas_widget.tpl'),array(
		'$title' => t('Saved Folders'),
		'$desc' => '',
		'$sel_all' => (($selected == '') ? 'selected' : ''),
		'$all' => t('Everything'),
		'$terms' => $terms,
		'$base' => $baseurl,
	));
}


function categories_widget($baseurl,$selected = '') {

	if(! feature_enabled(App::$profile['profile_uid'],'categories'))
		return '';

	require_once('include/security.php');

	$sql_extra = item_permissions_sql(App::$profile['profile_uid']);

	$item_normal = item_normal();

	$key = __FUNCTION__ . "-" . App::$profile['profile_uid'];

	$content = Cache::get($key, '5 MINUTE');
	if (! $content) {

		$content = Cache::get($key, '1 MONTH');

		$arr = [
			"SELECT distinct(term.term) FROM term JOIN item ON term.oid = item.id
			WHERE item.uid = %d
			AND term.uid = item.uid
			AND term.ttype = %d
			AND term.otype = %d
			AND item.owner_xchan = '%s'
			AND item.item_wall = 1
			AND item.verb != '%s'
			$item_normal
			$sql_extra
			ORDER BY term.term ASC",
			intval(App::$profile['profile_uid']),
			intval(TERM_CATEGORY),
			intval(TERM_OBJ_POST),
			dbesc(App::$profile['channel_hash']),
			dbesc(ACTIVITY_UPDATE)
		];

		\Zotlabs\Daemon\Master::Summon([ 'Cache_query', $key, base64_encode(json_encode($arr)) ]);
	}

	if (!$content) {
		return EMPTY_STR;
	}

	$r = unserialize($content);

	$terms = [];
	if($r && count($r)) {

		foreach($r as $rr)
			$terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

		return replace_macros(get_markup_template('categories_widget.tpl'),array(
			'$title' => t('Categories'),
			'$desc' => '',
			'$sel_all' => (($selected == '') ? 'selected' : ''),
			'$all' => t('Everything'),
			'$terms' => $terms,
			'$base' => $baseurl,
		));
	}
	return '';
}


function cardcategories_widget($baseurl,$selected = '') {

	if(! feature_enabled(App::$profile['profile_uid'],'categories'))
		return '';

	$sql_extra = item_permissions_sql(App::$profile['profile_uid']);

	$item_normal = "and item.item_hidden = 0 and item.item_type = 6 and item.item_deleted = 0
		and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_pending_remove = 0
		and item.item_blocked = 0 ";

	$terms = array();
	$r = q("select distinct(term.term)
				from term join item on term.oid = item.id
				where item.uid = %d
				and term.uid = item.uid
				and term.ttype = %d
				and term.otype = %d
				and item.owner_xchan = '%s'
				$item_normal
				$sql_extra
				order by term.term asc",
			intval(App::$profile['profile_uid']),
			intval(TERM_CATEGORY),
			intval(TERM_OBJ_POST),
			dbesc(App::$profile['channel_hash'])
	);
	if($r && count($r)) {
		foreach($r as $rr)
			$terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

		return replace_macros(get_markup_template('categories_widget.tpl'),array(
			'$title' => t('Categories'),
			'$desc' => '',
			'$sel_all' => (($selected == '') ? 'selected' : ''),
			'$all' => t('Everything'),
			'$terms' => $terms,
			'$base' => $baseurl,
		));
	}

	return '';
}


function articlecategories_widget($baseurl,$selected = '') {

	if(! feature_enabled(App::$profile['profile_uid'],'categories'))
		return '';

	$sql_extra = item_permissions_sql(App::$profile['profile_uid']);

	$item_normal = "and item.item_hidden = 0 and item.item_type = 7 and item.item_deleted = 0
		and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_pending_remove = 0
		and item.item_blocked = 0 ";

	$terms = array();
	$r = q("select distinct(term.term)
				from term join item on term.oid = item.id
				where item.uid = %d
				and term.uid = item.uid
				and term.ttype = %d
				and term.otype = %d
				and item.owner_xchan = '%s'
				$item_normal
				$sql_extra
				order by term.term asc",
			intval(App::$profile['profile_uid']),
			intval(TERM_CATEGORY),
			intval(TERM_OBJ_POST),
			dbesc(App::$profile['channel_hash'])
	);
	if($r && count($r)) {
		foreach($r as $rr)
			$terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

		return replace_macros(get_markup_template('categories_widget.tpl'),array(
			'$title' => t('Categories'),
			'$desc' => '',
			'$sel_all' => (($selected == '') ? 'selected' : ''),
			'$all' => t('Everything'),
			'$terms' => $terms,
			'$base' => $baseurl,
		));
	}

	return '';
}

function filecategories_widget($baseurl,$selected = '') {

	$perms = permissions_sql(App::$profile['profile_uid']);

	$terms = array();
	$r = q("select distinct(term.term)
			from term join attach on term.oid = attach.id
			where attach.uid = %d
			and term.uid = attach.uid
			and term.ttype = %d
			and term.otype = %d
			$perms
			order by term.term asc",
			intval(App::$profile['profile_uid']),
			intval(TERM_CATEGORY),
			intval(TERM_OBJ_FILE)
	);

	if($r && count($r)) {
		foreach($r as $rr)
			$terms[] = array('name' => $rr['term'], 'selected' => (($selected == $rr['term']) ? 'selected' : ''));

		return replace_macros(get_markup_template('categories_widget.tpl'),array(
			'$title' => t('Categories'),
			'$desc' => '',
			'$sel_all' => (($selected == '') ? 'selected' : ''),
			'$all' => t('Everything'),
			'$terms' => $terms,
			'$base' => $baseurl,
		));
	}

	return '';
}


function common_friends_visitor_widget($profile_uid,$cnt = 36) {

	if(local_channel() == $profile_uid)
		return;

	$observer_hash = get_observer_hash();

	if((! $observer_hash) || (! perm_is_allowed($profile_uid,$observer_hash,'view_contacts')))
		return;

	require_once('include/socgraph.php');

	$t = count_common_friends($profile_uid,$observer_hash);

	if(! $t)
		return;

	$r = common_friends($profile_uid,$observer_hash,0,$cnt,true);

	return replace_macros(get_markup_template('remote_friends_common.tpl'), [
		'$desc'     => t('Common Connections'),
		'$base'     => z_root(),
		'$uid'      => $profile_uid,
		'$linkmore' => (($t > $cnt) ? 'true' : ''),
		'$more'     => sprintf( t('View all %d common connections'), $t),
		'$items'    => $r,
	]);

};
