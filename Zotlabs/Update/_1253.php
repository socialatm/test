<?php

namespace Zotlabs\Update;

class _1253 {

	function run() {

		dbq("START TRANSACTION");

		$r = dbq("DELETE FROM app WHERE app_name IN ('Wiki', 'Cards', 'Articles') AND app_plugin = ''");

		if($r) {
			dbq("COMMIT");
			return UPDATE_SUCCESS;
		}

		dbq("ROLLBACK");
		return UPDATE_FAILED;

	}

}
