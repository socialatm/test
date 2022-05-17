<?php

namespace Zotlabs\Update;

class _1248 {

	function run() {

		q("START TRANSACTION");

		// remove possible bogus entries from xconfig
		$r = dbq("DELETE FROM xconfig WHERE xchan = ''");

		// remove gnu social app - it has been moved to addons unmaintained
		$r1 = dbq("DELETE FROM app WHERE app_plugin = 'gnusoc'");

		if($r && $r1) {
			q("COMMIT");
			return UPDATE_SUCCESS;
		}

		q("ROLLBACK");
		return UPDATE_FAILED;

	}

}
