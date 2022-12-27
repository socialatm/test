<?php

namespace Zotlabs\Module\Admin;

use Zotlabs\Lib\Queue as LibQueue;

class Queue {

	function get() {

		$o = '';

		$expert = $_REQUEST['expert'] ?? false;

		if(isset($_REQUEST['drophub'])) {
			hubloc_mark_as_down($_REQUEST['drophub']);
			LibQueue::remove_by_posturl($_REQUEST['drophub']);
		}

		if(isset($_REQUEST['emptyhub'])) {
			LibQueue::remove_by_posturl($_REQUEST['emptyhub']);
		}

		if(isset($_REQUEST['deliverhub'])) {

			$hubq = q("SELECT * FROM outq WHERE outq_posturl = '%s'",
				dbesc($_REQUEST['deliverhub'])
			);

			foreach ($hubq as $q) {
				LibQueue::deliver($q, true);
			}
		}

		$r = dbq("select count(outq_posturl) as total, max(outq_priority) as priority, outq_posturl from outq
			where outq_delivered = 0 group by outq_posturl order by total desc");

		for($x = 0; $x < count($r); $x ++) {
			$r[$x]['eurl'] = urlencode($r[$x]['outq_posturl']);
		}

		$o = replace_macros(get_markup_template('admin_queue.tpl'), array(
			'$banner' => t('Queue Statistics'),
			'$numentries' => t('Total Entries'),
			'$priority' => t('Priority'),
			'$desturl' => t('Destination URL'),
			'$nukehub' => t('Mark hub permanently offline'),
			'$deliverhub' => t('Retry delivery to this hub'),
			'$empty' => t('Empty queue for this hub'),
			'$lastconn' => t('Last known contact'),
			'$hasentries' => ((count($r)) ? true : false),
			'$entries' => $r,
			'$expert' => $expert
		));
		return $o;
	}
}
