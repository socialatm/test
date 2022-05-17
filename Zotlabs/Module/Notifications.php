<?php
namespace Zotlabs\Module;

require_once('include/bbcode.php');

class Notifications extends \Zotlabs\Web\Controller {

	function get() {

		if(! local_channel()) {
			return;
		}

		// ajax mark all unseen items read
		if(x($_REQUEST, 'markRead')) {
			switch($_REQUEST['markRead']) {
				case 'dm':
					$r = q("UPDATE item SET item_unseen = 0 WHERE uid = %d AND item_unseen = 1 AND item_private = 2",
						intval(local_channel())
					);
					break;
				case 'network':
					$r = q("UPDATE item SET item_unseen = 0 WHERE uid = %d AND item_unseen = 1 AND item_private IN (0, 1)",
						intval(local_channel())
					);
					break;
				case 'home':
					$r = q("UPDATE item SET item_unseen = 0 WHERE uid = %d AND item_unseen = 1 AND item_wall = 1 AND item_private IN (0, 1)",
						intval(local_channel())
					);
					break;
				case 'all_events':
					$evdays = intval(get_pconfig(local_channel(), 'system', 'evdays', 3));
					$r = q("UPDATE event SET dismissed = 1 WHERE uid = %d AND dismissed = 0 AND dtstart < '%s' AND dtstart > '%s' ",
						intval(local_channel()),
						dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + ' . intval($evdays) . ' days')),
						dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
					);
					break;
				case 'notify':
					$r = q("UPDATE notify SET seen = 1 WHERE seen = 0 AND uid = %d",
						intval(local_channel())
					);
					break;
				case 'pubs':
					unset($_SESSION['static_loadtime']);
					break;
				default:
					break;
			}
			killme();
		}

		// ajax mark all comments of a parent item read
		if(x($_REQUEST, 'markItemRead') && local_channel()) {
			$r = q("UPDATE item SET item_unseen = 0 WHERE  uid = %d AND parent = %d",
				intval(local_channel()),
				intval($_REQUEST['markItemRead'])
			);
			killme();
		}

		nav_set_selected('Notifications');

		$o = '';
		$notif_content = '';
		$notifications_available = false;

		$r = q("select count(*) as total from notify where uid = %d and seen = 0",
			intval(local_channel())
		);
		if($r && intval($r[0]['total']) > 49) {
			$r = q("select * from notify where uid = %d
				and seen = 0 order by created desc limit 50",
				intval(local_channel())
			);
		}
		else {
			$r1 = q("select * from notify where uid = %d
				and seen = 0 order by created desc limit 50",
				intval(local_channel())
			);
			$r2 = q("select * from notify where uid = %d
				and seen = 1 order by created desc limit %d",
				intval(local_channel()),
				intval(50 - intval($r[0]['total']))
			);
			$r = array_merge($r1,$r2);
		}

		if($r) {
			$notifications_available = true;
			foreach ($r as $rr) {
				$x = strip_tags(bbcode($rr['msg']));
				$notif_content .= replace_macros(get_markup_template('notify.tpl'),array(
					'$item_link' => z_root().'/notify/view/'. $rr['id'],
					'$item_image' => $rr['photo'],
					'$item_text' => $x,
					'$item_when' => relative_date($rr['created']),
					'$item_seen' => (($rr['seen']) ? true : false),
					'$new' => t('New')
				));
			}
		}
		else {
			$notif_content = t('No more system notifications.');
		}

		$o .= replace_macros(get_markup_template('notifications.tpl'),array(
			'$notif_header' => t('System Notifications'),
			'$notif_link_mark_seen' => t('Mark all seen'),
			'$notif_content' => $notif_content,
			'$notifications_available' => $notifications_available,
		));

		return $o;
	}

}
