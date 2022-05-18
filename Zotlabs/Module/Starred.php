<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Libsync;

class Starred extends \Zotlabs\Web\Controller {

	function init() {

		$starred = 0;

		if(! local_channel())
			killme();
		if(argc() > 1)
			$message_id = intval(argv(1));
		if(! $message_id)
			killme();

		$sys = get_sys_channel();

		$r = q("SELECT * FROM item WHERE (uid = %d OR uid = %d) AND id = %d
			and item_type in (0,6,7) and item_deleted = 0 and item_unpublished = 0
			and item_delayed = 0 and item_pending_remove = 0 and item_blocked = 0 LIMIT 1",
			intval(local_channel()),
			intval($sys['channel_id']),
			intval($message_id)
		);

        if ($r) {
            if ($r[0]['uid'] === $sys['channel_id']) {
                $r = [ copy_of_pubitem(App::get_channel(), $r[0]['mid']) ];
            }
        }

		if(!$r)
			killme();

		// reset $message_id to the fetched copy of message if applicable
		$message_id = $r[0]['id'];

		$item_starred = (intval($r[0]['item_starred']) ? 0 : 1);

		$r = q("UPDATE item SET item_starred = %d WHERE uid = %d and id = %d",
			intval($item_starred),
			intval(local_channel()),
			intval($message_id)
		);

		$r = q("select * from item where id = %d",
				intval($message_id)
		);
		if($r) {
			xchan_query($r);
			$sync_item = fetch_post_tags($r);
			Libsync::build_sync_packet(local_channel(),[
				'item' => [
					encode_item($sync_item[0],true)
				]
			]);
		}

		header('Content-type: application/json');
		echo json_encode(array('result' => $item_starred));
		killme();
	}

}
