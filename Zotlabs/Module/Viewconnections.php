<?php
namespace Zotlabs\Module;

require_once('include/selectors.php');

class Viewconnections extends \Zotlabs\Web\Controller {

	function init() {

		if(observer_prohibited()) {
			return;
		}

		if(argc() > 1) {
			profile_load(argv(1));
		}

	}

	function get() {

		if(observer_prohibited()) {
			notice( t('Public access denied.') . EOL);
			return;
		}

		if(((! count(\App::$profile)) || (\App::$profile['hide_friends']))) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		if(! perm_is_allowed(\App::$profile['uid'], get_observer_hash(),'view_contacts')) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		if(! $_REQUEST['aj'])
			$_SESSION['return_url'] = \App::$query_string;

		$o = '';
		$is_owner = ((local_channel() && local_channel() == \App::$profile['uid']) ? true : false);

		$abook_flags = " and abook_pending = 0 and abook_self = 0 and abook_blocked = 0 and abook_ignored = 0 ";
		$sql_extra = '';

		if(! $is_owner) {
			$abook_flags .= " and abook_hidden = 0 ";
			$sql_extra = " and xchan_hidden = 0 ";
		}

		$r = q("SELECT count(*) as total FROM abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d $abook_flags and xchan_orphan = 0 and xchan_deleted = 0 $sql_extra ",
			intval(\App::$profile['uid'])
		);
		if($r) {
			\App::set_pager_total($r[0]['total']);
		}

		$r = q("SELECT * FROM abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d $abook_flags and xchan_orphan = 0 and xchan_deleted = 0 $sql_extra order by xchan_name LIMIT %d OFFSET %d ",
			intval(\App::$profile['uid']),
			intval(\App::$pager['itemspage']),
			intval(\App::$pager['start'])
		);

		if((! $r) && (! $_REQUEST['aj'])) {
			info( t('No connections.') . EOL );
			return $o;
		}

		$contacts = array();

		foreach($r as $rr) {

			$oneway = false;
			if(! intval(get_abconfig(\App::$profile['uid'],$rr['xchan_hash'],'their_perms','post_comments'))) {
				$oneway = true;
			}

			$perminfo=[];
			$perminfo['connpermcount']=0;
			$perminfo['connperms']=t('Accepts').': ';
			if(intval(get_abconfig(\App::$profile['uid'],$rr['xchan_hash'],'their_perms','post_comments'))) {
				$perminfo['connpermcount']++;
				$perminfo['connperms'] .= t('Comments');
			}
			if(intval(get_abconfig(\App::$profile['uid'],$rr['xchan_hash'],'their_perms','send_stream'))) {
				$perminfo['connpermcount']++;
				$perminfo['connperms'] = ($perminfo['connperms']) ? $perminfo['connperms'] . ', ' : $perminfo['connperms'] ;
				$perminfo['connperms'] .= t('Stream items');
			}
			if(intval(get_abconfig(\App::$profile['uid'],$rr['xchan_hash'],'their_perms','post_wall'))) {
				$perminfo['connpermcount']++;
				$perminfo['connperms'] = ($perminfo['connperms']) ? $perminfo['connperms'] . ', ' : $perminfo['connperms'] ;
				$perminfo['connperms'] .= t('Wall posts');
			}

			if ($perminfo['connpermcount'] == 0) {
				$perminfo['connperms'] .= t('Nothing');
			}

			$url = chanlink_hash($rr['xchan_hash']);
			if($url) {
				$contacts[] = array(
					'id' => $rr['abook_id'],
					'archived' => (intval($rr['abook_archived']) ? true : false),
					'img_hover' => sprintf( t('Visit %s\'s profile [%s]'), $rr['xchan_name'], $rr['xchan_url']),
					'thumb' => $rr['xchan_photo_m'],
					'name' => substr($rr['xchan_name'],0,20),
					'username' => $rr['xchan_addr'],
					'link' => $url,
					'sparkle' => '',
					'itemurl' => $rr['url'],
					'network' => '',
					'perminfo' => (($is_owner) ? $perminfo : (($perminfo['connpermcount'] === 0) ? $perminfo : [])),
					'oneway' => $oneway
				);
			}
		}

		if($_REQUEST['aj']) {
			if($contacts) {
				$o .= replace_macros(get_markup_template('viewcontactsajax.tpl'),array(
					'$contacts' => $contacts
				));
			}
			else {
				$o .= '<div id="content-complete"></div>';
			}
			echo $o;
			killme();
		}
		else {
			$o .= "<script> var page_query = '" . escape_tags(urlencode($_GET['q'])) . "'; var extra_args = '" . extra_query_args() . "' ; </script>";
			$tpl = get_markup_template("viewcontact_template.tpl");
			$o .= replace_macros($tpl, array(
				'$title' => t('View Connections'),
				'$contacts' => $contacts,
	//			'$paginate' => paginate($a),
			));
		}

	    if(! $contacts)
	        $o .= '<div id="content-complete"></div>';

		return $o;
	}

}
