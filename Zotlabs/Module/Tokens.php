<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\AccessList;
use Zotlabs\Lib\Permcat;
use Zotlabs\Lib\Libsync;

require_once('include/security.php');

class Tokens extends Controller {

	function post() {

		if(! local_channel())
			return;

		$channel = App::get_channel();

		if(! Apps::system_app_installed($channel['channel_id'], 'Guest Access'))
			return;

		check_form_security_token_redirectOnErr('tokens', 'tokens');

		if(isset($_POST['delete'])) {
			$r = q("select * from atoken where atoken_id = %d and atoken_uid = %d",
				intval($_POST['atoken_id']),
				intval(local_channel())
			);

			if (!$r) {
				return;
			}

			$atoken = $r[0];
			$atoken_xchan = substr($channel['channel_hash'], 0, 16) . '.' . $atoken['atoken_guid'];

			$atoken['deleted'] = true;

			$r = q("SELECT abook.*, xchan.*
				FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
				intval($channel['channel_id']),
				dbesc($atoken_xchan)
			);

			if (!$r) {
				return;
			}

			$clone = $r[0];

			unset($clone['abook_id']);
			unset($clone['abook_account']);
			unset($clone['abook_channel']);
			$clone['deleted'] = true;

			$abconfig = load_abconfig($channel['channel_id'],$clone['abook_xchan']);
			if ($abconfig) {
				$clone['abconfig'] = $abconfig;
			}

			atoken_delete($atoken['atoken_id']);
			Libsync::build_sync_packet($channel['channel_id'], [ 'abook' => [ $clone ], 'atoken' => [ $atoken ] ], true);

			return;
		}

		$token_errs = 0;
		if(array_key_exists('token',$_POST)) {
			$atoken_id = (($_POST['atoken_id']) ? intval($_POST['atoken_id']) : 0);

			if (! $atoken_id) {
				$atoken_guid = new_uuid();
			}

			$name = trim(escape_tags($_POST['name']));
			$token = trim($_POST['token']);
			if((! $name) || (! $token))
					$token_errs ++;
			if(trim($_POST['expires']))
				$expires = datetime_convert(date_default_timezone_get(),'UTC',$_POST['expires']);
			else
				$expires = NULL_DATE;
			$max_atokens = service_class_fetch($channel['channel_id'],'access_tokens');
			if($max_atokens) {
				$r = q("select count(atoken_id) as total where atoken_uid = %d",
					intval($channel['channel_id'])
				);
				if($r && intval($r[0]['total']) >= $max_tokens) {
					notice( sprintf( t('This channel is limited to %d tokens'), $max_tokens) . EOL);
					return;
				}
			}
		}
		if($token_errs) {
			notice( t('Name and Password are required.') . EOL);
			return;
		}

		$old_atok = q("select * from atoken where atoken_uid = %d and atoken_name = '%s'",
			intval($channel['channel_id']),
			dbesc($name)
		);

		if ($old_atok) {
			$old_atok = $old_atok[0];
			$old_xchan = atoken_xchan($old_atok);
		}

		if($atoken_id) {
			$r = q("update atoken set atoken_name = '%s', atoken_token = '%s', atoken_expires = '%s'
				where atoken_id = %d and atoken_uid = %d",
				dbesc($name),
				dbesc($token),
				dbesc($expires),
				intval($atoken_id),
				intval($channel['channel_id'])
			);
		}
		else {
			$r = q("insert into atoken (atoken_guid, atoken_aid, atoken_uid, atoken_name, atoken_token, atoken_expires )
				values ('%s', %d, %d, '%s', '%s', '%s' ) ",
				dbesc($atoken_guid),
				intval($channel['channel_account_id']),
				intval($channel['channel_id']),
				dbesc($name),
				dbesc($token),
				dbesc($expires)
			);
		}

		$atok = q("select * from atoken where atoken_uid = %d and atoken_name = '%s'",
			intval($channel['channel_id']),
			dbesc($name)
		);

		if ($atok) {
			$xchan = atoken_xchan($atok[0]);
			atoken_create_xchan($xchan);
			$atoken_xchan = $xchan['xchan_hash'];
			if ($old_atok && $old_xchan) {
				$r = q("update xchan set xchan_name = '%s' where xchan_hash = '%s'",
					dbesc($xchan['xchan_name']),
					dbesc($old_xchan['xchan_hash'])
				);
			}
		}


		if (! $atoken_id) {

			// If this is a new token, create a new abook record

			$closeness = get_pconfig($channel['channel_id'], 'system', 'new_abook_closeness',80);
			$profile_assign = get_pconfig($channel['channel_id'], 'system', 'profile_assign', '');

			$r = abook_store_lowlevel(
				[
					'abook_account'   => $channel['channel_account_id'],
					'abook_channel'   => $channel['channel_id'],
					'abook_closeness' => intval($closeness),
					'abook_xchan'     => $atoken_xchan,
					'abook_profile'   => $profile_assign,
					'abook_feed'      => 0,
					'abook_created'   => datetime_convert(),
					'abook_updated'   => datetime_convert(),
					'abook_instance'  => z_root(),
				]
			);

			if (! $r) {
				logger('abook creation failed');
			}

			/** If there is a default group for this channel, add this connection to it */
			if ($channel['channel_default_group']) {
				$g = AccessList::by_hash($channel['channel_id'], $channel['channel_default_group']);
				if ($g) {
					AccessList::member_add($channel['channel_id'], '', $atoken_xchan,$g['id']);
				}
			}
		}

		$role = ((array_key_exists('permcat', $_POST)) ? escape_tags($_POST['permcat']) : '');
		\Zotlabs\Lib\Permcat::assign($channel, $role, [$atoken_xchan]);

		$r = q("SELECT abook.*, xchan.*
			FROM abook left join xchan on abook_xchan = xchan_hash
			WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
			intval($channel['chnnel_id']),
			dbesc($atoken_xchan)
		);

		if (! $r) {
			return;
		}

		$clone = $r[0];

		unset($clone['abook_id']);
		unset($clone['abook_account']);
		unset($clone['abook_channel']);

		$abconfig = load_abconfig($channel['channel_id'],$clone['abook_xchan']);
		if ($abconfig) {
			$clone['abconfig'] = $abconfig;
		}

		Libsync::build_sync_packet($channel['channel_id'], [ 'abook' => [ $clone ], 'atoken' => $atok ], true);

		info( t('Token saved.') . EOL);
		return;
	}


	function get() {

		if(! local_channel())
			return;

		if(! Apps::system_app_installed(local_channel(), 'Guest Access')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Guest Access');
			return Apps::app_render($papp, 'module');
		}

		nav_set_selected('Guest Access');

		$channel = App::get_channel();

		$atoken = null;
		$atoken_xchan = '';
		$atoken_abook = [];

		if(argc() > 1) {
			$id = argv(1);

			$atoken = q("select * from atoken where atoken_id = %d and atoken_uid = %d",
				intval($id),
				intval(local_channel())
			);

			if($atoken) {
				$atoken = $atoken[0];
				$atoken_xchan = substr($channel['channel_hash'],0,16) . '.' . $atoken['atoken_guid'];

				$atoken_abook = q("select * from abook where abook_channel = %d and abook_xchan = '%s'",
					intval(local_channel()),
					dbesc($atoken_xchan)
				);

				$atoken_abook = $atoken_abook[0];
			}
		}

		$desc = t('Use this form to create temporary access identifiers to share things with non-members. These identities may be used in privacy groups and visitors may login using these credentials to access private content.');

		$pcat            = new Permcat(local_channel());
		$pcatlist        = $pcat->listing();
		$default_role    = get_pconfig(local_channel(), 'system', 'default_permcat');
		$current_permcat = (($atoken_abook) ? $atoken_abook['abook_role'] : $default_role);

		$roles_dict = [];
		foreach ($pcatlist as $role) {
			$roles_dict[$role['name']] = $role['localname'];
		}

		if (!$current_permcat) {
			notice(t('Please select a role for this guest!') . EOL);
			$permcats[] = '';
		}

		if ($pcatlist) {
			foreach ($pcatlist as $pc) {
				$permcats[$pc['name']] = $pc['localname'];
			}
		}

		$tpl = get_markup_template("tokens.tpl");
		$o .= replace_macros($tpl, array(
			'$form_security_token' => get_form_security_token('tokens'),
			'$permcat' => ['permcat', t('Select a role for this guest'), $current_permcat, '', $permcats],
			'$title' => t('Guest Access'),
			'$desc' => $desc,
			'$atoken' => $atoken,
			'$name' => array('name', t('Login Name') . ' <span class="required">*</span>', (($atoken) ? $atoken['atoken_name'] : ''),''),
			'$token'=> array('token', t('Login Password') . ' <span class="required">*</span>',(($atoken) ? $atoken['atoken_token'] : new_token()), ''),
			'$expires'=> array('expires', t('Expires (yyyy-mm-dd)'), (($atoken['atoken_expires'] && $atoken['atoken_expires'] > NULL_DATE) ? datetime_convert('UTC',date_default_timezone_get(),$atoken['atoken_expires']) : ''), ''),
			'$submit' => t('Submit'),
			'$delete' => t('Delete')
		));
		return $o;
	}

}
