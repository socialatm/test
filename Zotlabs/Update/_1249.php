<?php

namespace Zotlabs\Update;

class _1249 {

	function run() {

		dbq("START TRANSACTION");

		if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
			$r1 = dbq("ALTER TABLE abook ADD abook_role TEXT NOT NULL DEFAULT ''");
			$r2 = dbq("CREATE INDEX \"abook_role\" ON abook (\"abook_role\")");
			$r = ($r1 && $r2);
		}
		else {
			$r = dbq("ALTER TABLE `abook` ADD `abook_role` CHAR(191) NOT NULL DEFAULT '' ,
				ADD INDEX `abook_role` (`abook_role`)");
		}

		if($r) {
			dbq("COMMIT");
			return UPDATE_SUCCESS;
		}

		dbq("ROLLBACK");
		return UPDATE_FAILED;

	}

}
