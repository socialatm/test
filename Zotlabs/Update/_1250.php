<?php

namespace Zotlabs\Update;

class _1250 {

	function run() {

		dbq("START TRANSACTION");

		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = dbq("ALTER TABLE atoken ADD atoken_guid VARCHAR(255) NOT NULL DEFAULT ''");
			$r2 = dbq("CREATE INDEX \"atoken_guid\" ON atoken (\"atoken_guid\")");
			$r = ($r1 && $r2);
		}
		else {
			$r = dbq("ALTER TABLE `atoken` ADD `atoken_guid` CHAR(191) NOT NULL DEFAULT '' ,
				ADD INDEX `atoken_guid` (`atoken_guid`)");
		}

		if($r) {
			dbq("COMMIT");
			return UPDATE_SUCCESS;
		}

		dbq("ROLLBACK");
		return UPDATE_FAILED;

	}

}
