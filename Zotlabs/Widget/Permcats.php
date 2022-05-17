<?php

/**
 *   * Name: Contact roles
 *   * Description: Display a menu with all defined contact roles and contacts which are assigned to the selected role
 *   * Author: Mario Vavti
 *   * Requires: permcats
 */

namespace Zotlabs\Widget;

use Zotlabs\Lib\Permcat;
use Zotlabs\Access\PermissionLimits;

class Permcats {

	function widget($arr) {
		$pcat = new Permcat(local_channel());
		$pcatlist = $pcat->listing();

		if (!$pcatlist) {
			return;
		}

		$roles = [];
		$active_role = '';

		foreach($pcatlist as $pc) {
			if (!$active_role) {
				$active_role = ((argc() > 1 && $pc['name'] === hex2bin(argv(1))) ? $pc['name'] : '');
			}
			$roles[] = [
				'name' => $pc['localname'],
				'url' => z_root() . '/permcats/' . bin2hex($pc['name']),
				'active' => (argc() > 1 && $pc['name'] === hex2bin(argv(1)))
			];
		}

		if($active_role) {

			$roles[] = [
				'name' => '<i class="fa fa-plus"></i>&nbsp;' . t('Add new role'),
				'url' => z_root() . '/permcats',
				'active' => ''
			];

/* get role members based on permissions
			$test = $pcatlist[$active]['perms'];

			$role_sql = '';
			$count = 0;
			foreach ($test as $t) {
				$checkinherited = PermissionLimits::Get(local_channel(),$t['name']);

				if($checkinherited & PERMS_SPECIFIC) {
					$role_sql .= "( abconfig.k = '" . dbesc($t['name']) . "' AND abconfig.v = '" . intval($t['value']) . "' ) OR ";
					$count++;
				}
			}

			$role_sql = rtrim($role_sql, ' OR ');

			$r = q("SELECT abconfig.xchan, xchan.xchan_name, abook.abook_id FROM abconfig LEFT JOIN xchan on abconfig.xchan = xchan.xchan_hash LEFT JOIN abook ON abconfig.xchan = abook.abook_xchan WHERE xchan.xchan_deleted = 0 and abconfig.chan = %d AND abconfig.cat = 'my_perms' AND ( $role_sql ) GROUP BY abconfig.xchan HAVING count(abconfig.xchan) = %d ORDER BY xchan.xchan_name",
				intval(local_channel()),
				intval($count)
			);
*/

			// get role members based on abook_role

			$r = q("SELECT abook.abook_id, abook.abook_role, xchan.xchan_name, xchan.xchan_addr, xchan.xchan_url, xchan.xchan_photo_s FROM abook
				LEFT JOIN xchan on abook.abook_xchan = xchan.xchan_hash
				WHERE abook.abook_channel = %d AND abook.abook_role = '%s' AND abook_self = 0 AND xchan_deleted = 0
				ORDER BY xchan.xchan_name",
				intval(local_channel()),
				dbesc($active_role)
			);

			$members = [];

			foreach ($r as $rr) {
				$members[] = [
					'name' => $rr['xchan_name'],
					'addr' => (($rr['xchan_addr']) ? $rr['xchan_addr'] : $rr['xchan_url']),
					'url' => z_root() . '/connections#' . $rr['abook_id'],
					'photo' => $rr['xchan_photo_s']
				];
			}
		}

		$tpl = get_markup_template("permcats_widget.tpl");
		$o .= replace_macros($tpl, [
			'$roles_label' => t('Contact roles'),
			'$members_label' => t('Role members'),
			'$roles' => $roles,
			'$members' => $members

		]);

		return $o;

	}
}
