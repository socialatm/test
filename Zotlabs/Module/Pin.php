<?php
namespace Zotlabs\Module;

/*
 * Pinned post processing
 */

use App;
use Zotlabs\Lib\Libsync;

class Pin extends \Zotlabs\Web\Controller {


	function init() {

		if(argc() !== 2)
			http_status_exit(400, 'Bad request');
	}


	function post() {

		$item_id = intval($_POST['id']);

		if ($item_id <= 0)
			http_status_exit(404, 'Not found');

		$observer = \App::get_observer();
		if(! $observer)
			http_status_exit(403, 'Forbidden');

		$r = q("SELECT * FROM item WHERE id = %d AND id = parent AND item_private = 0 LIMIT 1",
			$item_id
		);
		if(! $r) {
			notice(t('Unable to locate original post.'));
			http_status_exit(404, 'Not found');
		}

		$midb64 = gen_link_id($r[0]['mid']);
		$pinned = (in_array($midb64, get_pconfig($r[0]['uid'], 'pinned', $r[0]['item_type'], [])) ? true : false);

		switch(argv(1)) {

			case 'pin':
				if(! local_channel() || (local_channel() != $r[0]['uid'] && local_channel() != is_site_admin()))
					http_status_exit(403, 'Forbidden');
				// Currently allow only one pinned item for each type
				set_pconfig($r[0]['uid'], 'pinned', $r[0]['item_type'], ($pinned ? [] : [ $midb64 ]));
				if($pinned)
					del_pconfig($r[0]['uid'], 'pinned_hide', $midb64);
				break;

			case 'hide':
				if($pinned) {
					$hidden = get_pconfig($r[0]['uid'], 'pinned_hide', $midb64, []);
					if(! in_array($observer['xchan_hash'], $hidden)) {
						$hidden[] = $observer['xchan_hash'];
						set_pconfig($r[0]['uid'], 'pinned_hide', $midb64, $hidden);
					}
				}
				break;

			default:
				http_status_exit(404, 'Not found');
		}

		Libsync::build_sync_packet($r[0]['uid'], [ 'config' ]);
	}
}
