<?php
namespace Zotlabs\Module;


use App;
use Zotlabs\Web\Controller;

require_once('include/conversation.php');
require_once('include/bbcode.php');
require_once('include/datetime.php');
require_once('include/event.php');
require_once('include/items.php');
require_once('include/html2plain.php');


class Cal extends Controller {

	function init() {
		if(observer_prohibited()) {
			return;
		}

		if(argc() > 1) {
			$nick = argv(1);

			profile_load($nick);

			$channelx = channelx_by_nick($nick);

			if(! $channelx) {
				notice( t('Channel not found.') . EOL);
				return;
			}

			App::$data['channel'] = $channelx;

			$observer = App::get_observer();
			App::$data['observer'] = $observer;

			head_set_icon(App::$data['channel']['xchan_photo_s']);

			App::$page['htmlhead'] = "<script> var profile_uid = " . ((App::$data['channel']) ? App::$data['channel']['channel_id'] : 0) . "; </script>" ;

		}

		return;
	}



	function get() {

		if(observer_prohibited()) {
			return;
		}

		$channel = App::$data['channel'];

		// since we don't currently have an event permission - use the stream permission

		if(! perm_is_allowed($channel['channel_id'], get_observer_hash(), 'view_stream')) {
			notice( t('Permissions denied.') . EOL);
			return;
		}

		nav_set_selected('Calendar');

		head_add_css('/library/fullcalendar/packages/core/main.min.css');
		head_add_css('/library/fullcalendar/packages/daygrid/main.min.css');
		head_add_css('cdav_calendar.css');

		head_add_js('/library/fullcalendar/packages/core/main.min.js');
		head_add_js('/library/fullcalendar/packages/daygrid/main.min.js');

		$sql_extra = permissions_sql($channel['channel_id'], get_observer_hash(), 'event');

		if(! perm_is_allowed($channel['channel_id'], get_observer_hash(), 'view_contacts') || App::$profile['hide_friends'])
			$sql_extra .= " and etype != 'birthday' ";

		$first_day = feature_enabled($channel['channel_id'], 'cal_first_day');
		$first_day = (($first_day) ? $first_day : 0);

		$start = '';
		$finish = '';

		if (argv(2) === 'json') {
			if (x($_GET,'start'))	$start = $_GET['start'];
			if (x($_GET,'end'))	$finish = $_GET['end'];
		}

		$start  = datetime_convert('UTC','UTC',$start);
		$finish = datetime_convert('UTC','UTC',$finish);
		$adjust_start = datetime_convert('UTC', date_default_timezone_get(), $start);
		$adjust_finish = datetime_convert('UTC', date_default_timezone_get(), $finish);

		if (x($_GET, 'id')) {
			$r = q("SELECT event.*, item.plink, item.item_flags, item.author_xchan, item.owner_xchan, item.id as item_id
                                from event left join item on item.resource_id = event.event_hash
				where item.resource_type = 'event' and event.uid = %d and event.id = %d $sql_extra limit 1",
				intval($channel['channel_id']),
				intval($_GET['id'])
			);
		}
		else {
			// fixed an issue with "nofinish" events not showing up in the calendar.
			// There's still an issue if the finish date crosses the end of month.
			// Noting this for now - it will need to be fixed here and in Friendica.
			// Ultimately the finish date shouldn't be involved in the query.
			$r = q("SELECT event.*, item.plink, item.item_flags, item.author_xchan, item.owner_xchan, item.id as item_id
				from event left join item on event.event_hash = item.resource_id
				where item.resource_type = 'event' and event.uid = %d and event.uid = item.uid
				AND (( event.adjust = 0 AND ( event.dtend >= '%s' or event.nofinish = 1 ) AND event.dtstart <= '%s' )
				OR (  event.adjust = 1 AND ( event.dtend >= '%s' or event.nofinish = 1 ) AND event.dtstart <= '%s' ))
				$sql_extra",
				intval($channel['channel_id']),
				dbesc($start),
				dbesc($finish),
				dbesc($adjust_start),
				dbesc($adjust_finish)
			);
		}

		if($r) {
			xchan_query($r);
			$r = fetch_post_tags($r,true);
			$r = sort_by_date($r);
		}

		$events = [];

		if($r) {

			foreach($r as $rr) {

				$start = (($rr['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtstart'], 'c') : datetime_convert('UTC', 'UTC', $rr['dtstart'], 'c'));
				if ($rr['nofinish']){
					$end = null;
				} else {
					$end = (($rr['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtend'], 'c') : datetime_convert('UTC', 'UTC', $rr['dtend'], 'c'));
				}

				$html = '';
				if (x($_GET,'id')) {
					$rr['timezone'] = $tz;
					$html = format_event_html($rr);
				}

				$tz = get_iconfig($rr, 'event', 'timezone');
				if(! $tz)
					$tz = 'UTC';

				$events[] = array(
					'calendar_id' => 'channel_calendar',
					'rw' => true,
					'id'=>$rr['id'],
					'uri' => $rr['event_hash'],
					'timezone' => $tz,
					'start'=> $start,
					'end' => $end,
					'drop' => false,
					'allDay' => (($rr['adjust']) ? 0 : 1),
					'title' => html_entity_decode($rr['summary'], ENT_COMPAT, 'UTF-8'),
					'editable' => false,
					'item' => $rr,
					'plink' => [$rr['plink'], t('Link to source')],
					'description' => html_entity_decode($rr['description'], ENT_COMPAT, 'UTF-8'),
					'location' => html_entity_decode($rr['location'], ENT_COMPAT, 'UTF-8'),
					'allow_cid' => expand_acl($rr['allow_cid']),
					'allow_gid' => expand_acl($rr['allow_gid']),
					'deny_cid' => expand_acl($rr['deny_cid']),
					'deny_gid' => expand_acl($rr['deny_gid']),
					'html' => $html
				);
			}
		}

		if (argv(2) === 'json') {
			echo json_encode($events);
			killme();
		}

		if (x($_GET,'id')) {
			$o = replace_macros(get_markup_template("cal_event.tpl"), [
				'$events' => $events
			]);
			echo $o;
			killme();
		}

		$nick = $channel['channel_address'];

		$sources = '{
			id: \'channel_calendar\',
			url: \'/cal/' . $nick . '/json/\',
			color: \'#3a87ad\'
		}';

		$o = replace_macros(get_markup_template("cal_calendar.tpl"), [
			'$sources' => $sources,
			'$lang' => App::$language,
			'$timezone' => date_default_timezone_get(),
			'$first_day' => $first_day,
			'$prev'	=> t('Previous'),
			'$next'	=> t('Next'),
			'$today' => t('Today'),
			'$title' => '',
			'$dtstart' => '',
			'$dtend' => '',
			'$nick' => $nick
		]);

		return $o;

	}

}
