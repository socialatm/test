<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libsync;

require_once('include/socgraph.php');
require_once('include/selectors.php');
require_once('include/photos.php');

class Defperms extends Controller {

	/* @brief Initialize the connection-editor
	 *
	 *
	 */

	function init() {

		if(! local_channel())
			return;

		//if(! Apps::system_app_installed(local_channel(), 'Default Permissions'))
		//	return;

		$r = q("SELECT abook.*, xchan.*
			FROM abook left join xchan on abook_xchan = xchan_hash
			WHERE abook_self = 1 and abook_channel = %d LIMIT 1",
			intval(local_channel())
		);
		if($r) {
			App::$poi = $r[0];
		}

		$channel = App::get_channel();
		if($channel)
			head_set_icon($channel['xchan_photo_s']);
	}


	/* @brief Evaluate posted values and set changes
	 *
	 */

	function post() {

		if(! local_channel())
			return;

		//if(! Apps::system_app_installed(local_channel(), 'Default Permissions'))
		//	return;

		$contact_id = intval(argv(1));
		if(! $contact_id)
			return;

		$channel = App::get_channel();

		$orig_record = q("SELECT * FROM abook WHERE abook_id = %d AND abook_channel = %d LIMIT 1",
			intval($contact_id),
			intval(local_channel())
		);

		if(! $orig_record) {
			notice( t('Could not access contact record.') . EOL);
			goaway(z_root() . '/connections');
			return; // NOTREACHED
		}


		if(intval($orig_record[0]['abook_self'])) {
			$autoperms = intval($_POST['autoperms']);
			$is_self = true;
		}
		else {
			$autoperms = null;
			$is_self = false;
		}


		$all_perms = \Zotlabs\Access\Permissions::Perms();

		if($all_perms) {
			foreach($all_perms as $perm => $desc) {

				$checkinherited = \Zotlabs\Access\PermissionLimits::Get(local_channel(),$perm);
				$inherited = (($checkinherited & PERMS_SPECIFIC) ? false : true);

				if(array_key_exists('perms_' . $perm, $_POST)) {
					set_abconfig($channel['channel_id'],$orig_record[0]['abook_xchan'],'my_perms',$perm,
						intval($_POST['perms_' . $perm]));
					if($autoperms) {
						set_pconfig($channel['channel_id'],'autoperms',$perm,intval($_POST['perms_' . $perm]));
					}
				}
				else {
					set_abconfig($channel['channel_id'],$orig_record[0]['abook_xchan'],'my_perms',$perm,0);
					if($autoperms) {
						set_pconfig($channel['channel_id'],'autoperms',$perm,0);
					}
				}
			}
		}

		if(! is_null($autoperms))
			set_pconfig($channel['channel_id'],'system','autoperms',$autoperms);


		notice( t('Settings updated.') . EOL);


		// Refresh the structure in memory with the new data

		$r = q("SELECT abook.*, xchan.*
			FROM abook left join xchan on abook_xchan = xchan_hash
			WHERE abook_channel = %d and abook_id = %d LIMIT 1",
			intval(local_channel()),
			intval($contact_id)
		);
		if($r) {
			App::$poi = $r[0];
		}


		$this->defperms_clone($a);

		goaway(z_root() . '/defperms');

		return;

	}

	/* @brief Clone connection
	 *
	 *
	 */

	function defperms_clone(&$a) {

			if(! App::$poi)
				return;

			$channel = App::get_channel();

			$r = q("SELECT abook.*, xchan.*
				FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_channel = %d and abook_id = %d LIMIT 1",
				intval(local_channel()),
				intval(App::$poi['abook_id'])
			);
			if($r) {
				App::$poi = array_shift($r);
			}

			$clone = App::$poi;

			unset($clone['abook_id']);
			unset($clone['abook_account']);
			unset($clone['abook_channel']);

			$abconfig = load_abconfig($channel['channel_id'],$clone['abook_xchan']);
			if($abconfig)
				$clone['abconfig'] = $abconfig;

			Libsync::build_sync_packet(0 /* use the current local_channel */, array('abook' => array($clone)));
	}

	/* @brief Generate content of connection default permissions page
	 *
	 *
	 */

	function get() {

		$sort_type = 0;
		$o = '';

		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return login();
		}

		//~ if(! Apps::system_app_installed(local_channel(), 'Default Permissions')) {
			//~ //Do not display any associated widgets at this point
			//~ App::$pdl = '';
			//~ $papp = Apps::get_papp('Default Permissions');
			//~ return Apps::app_render($papp, 'module');
		//~ }

		$section = ((array_key_exists('section',$_REQUEST)) ? $_REQUEST['section'] : '');
		$channel = App::get_channel();

		$yes_no = array(t('No'),t('Yes'));

		$connect_perms = \Zotlabs\Access\Permissions::connect_perms(local_channel());

		$o .= "<script>function connectDefaultShare() {
		\$('.abook-edit-me').each(function() {
			if(! $(this).is(':disabled'))
				$(this).prop('checked', false);
		});\n\n";
		foreach($connect_perms['perms'] as $p => $v) {
			if($v) {
				$o .= "\$('#me_id_perms_" . $p . "').prop('checked', true); \n";
			}
		}
		$o .= " }\n</script>\n";

		if(App::$poi) {

			$sections = [];

			$self = false;

			$tpl = get_markup_template('defperms.tpl');


			$perms = array();
			$channel = App::get_channel();

			$contact = App::$poi;

			$global_perms = \Zotlabs\Access\Permissions::Perms();

			$hidden_perms = [];

			foreach($global_perms as $k => $v) {
				$thisperm = get_abconfig(local_channel(),$contact['abook_xchan'],'my_perms',$k);

				$checkinherited = \Zotlabs\Access\PermissionLimits::Get(local_channel(),$k);

				$inherited = (($checkinherited & PERMS_SPECIFIC) ? false : true);

				$perms[] = [ 'perms_' . $k, $v, intval($thisperm), '', $yes_no, (($inherited) ? ' disabled="disabled" ' : '') ];
				if($inherited) {
					$hidden_perms[] = [ 'perms_' . $k, intval($thisperm) ];
				}
			}

			$pcat = new \Zotlabs\Lib\Permcat(local_channel());
			$pcatlist = $pcat->listing();
			$permcats = [];
			if($pcatlist) {
				foreach($pcatlist as $pc) {
					$permcats[$pc['name']] = $pc['localname'];
				}
			}

			$o .= replace_macros($tpl, [
				'$header'         => t('Connection Default Permissions'),
				'$autoperms'      => array('autoperms',t('Apply these permissions automatically'), ((get_pconfig(local_channel(),'system','autoperms')) ? 1 : 0), t('If enabled, connection requests will be approved without your interaction'), $yes_no),
				'$permcat'        => [ 'permcat', t('Permission role'), '', '<span class="loading invisible">' . t('Loading') . '<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span></span>',$permcats ],
				'$permcat_new'    => t('Add permission role'),
				'$permcat_enable' => Apps::system_app_installed(local_channel(), 'Permission Categories'),
				'$section'        => $section,
				'$sections'       => $sections,
				'$autolbl'        => t('The permissions indicated on this page will be applied to all new connections.'),
				'$autoapprove'    => t('Automatic approval settings'),
				'$inherited'      => t('inherited'),
				'$submit'         => t('Submit'),
				'$me'             => t('My Settings'),
				'$perms'          => $perms,
				'$hidden_perms'   => $hidden_perms,
				'$permlbl'        => t('Individual Permissions'),
				'$permnote_self'  => t('Some individual permissions may have been preset or locked based on your channel type and privacy settings.'),
				'$contact_id'     => $contact['abook_id'],
				'$name'           => $contact['xchan_name'],
			]);

			$arr = array('contact' => $contact,'output' => $o);

			call_hooks('contact_edit', $arr);

			return $arr['output'];

		}
	}
}
