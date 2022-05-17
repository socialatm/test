<?php

namespace Zotlabs\Update;

class _1247 {

	function run() {

		q("START TRANSACTION");

		$r = dbq("DELETE FROM updates WHERE ud_addr = '' OR ud_hash = '' OR ud_guid = ''");

		if($r) {
			q("COMMIT");
			return UPDATE_SUCCESS;
		}

		q("ROLLBACK");
		return UPDATE_FAILED;

	}

}
