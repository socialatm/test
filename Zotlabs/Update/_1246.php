<?php

namespace Zotlabs\Update;

class _1246 {

	function run() {

		q("START TRANSACTION");

		$r1 = dbq("UPDATE xchan SET xchan_deleted = 2 WHERE xchan_network = 'zot' AND xchan_deleted = 0");
		$r2 = dbq("UPDATE hubloc SET hubloc_deleted = 2 WHERE hubloc_network = 'zot' AND hubloc_deleted = 0");

		if($r1 && $r2) {
			q("COMMIT");
			return UPDATE_SUCCESS;
		}

		q("ROLLBACK");
		return UPDATE_FAILED;

	}

}
