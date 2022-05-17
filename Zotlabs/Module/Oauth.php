<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Oauth extends Controller {


	function post() {

		if(! local_channel())
			return;


		if(! Apps::system_app_installed(local_channel(), 'OAuth Apps Manager'))
			return;

		if(x($_POST,'remove')){
			check_form_security_token_redirectOnErr('/oauth', 'oauth');

			$key = $_POST['remove'];
			q("DELETE FROM tokens WHERE id='%s' AND uid=%d",
				dbesc($key),
				local_channel());
			goaway(z_root()."/oauth");
			return;
		}

		if((argc() > 1) && (argv(1) === 'edit' || argv(1) === 'add') && x($_POST,'submit')) {

			check_form_security_token_redirectOnErr('oauth', 'oauth');

			$name   	= ((x($_POST,'name')) ? escape_tags($_POST['name']) : '');
			$key		= ((x($_POST,'key')) ? escape_tags($_POST['key']) : '');
			$secret		= ((x($_POST,'secret')) ? escape_tags($_POST['secret']) : '');
			$redirect	= ((x($_POST,'redirect')) ? escape_tags($_POST['redirect']) : '');
			$icon		= ((x($_POST,'icon')) ? escape_tags($_POST['icon']) : '');
			$oauth2		= ((x($_POST,'oauth2')) ? intval($_POST['oauth2']) : 0);
			$ok = true;
			if($name == '') {
				$ok = false;
				notice( t('Name is required') . EOL);
			}
			if($key == '' || $secret == '') {
				$ok = false;
				notice( t('Key and Secret are required') . EOL);
			}

			if($ok) {
				if ($_POST['submit']==t("Update")){
					$r = q("UPDATE clients SET
								client_id='%s',
								pw='%s',
								clname='%s',
								redirect_uri='%s',
								icon='%s',
								uid=%d
							WHERE client_id='%s'",
							dbesc($key),
							dbesc($secret),
							dbesc($name),
							dbesc($redirect),
							dbesc($icon),
							intval(local_channel()),
							dbesc($key));
				} else {
					$r = q("INSERT INTO clients (client_id, pw, clname, redirect_uri, icon, uid)
						VALUES ('%s','%s','%s','%s','%s',%d)",
						dbesc($key),
						dbesc($secret),
						dbesc($name),
						dbesc($redirect),
						dbesc($icon),
						intval(local_channel())
					);
					$r = q("INSERT INTO xperm (xp_client, xp_channel, xp_perm) VALUES ('%s', %d, '%s') ",
						dbesc($key),
						intval(local_channel()),
						dbesc('all')
					);
				}
			}
			goaway(z_root()."/oauth");
			return;
		}
	}

	function get() {

		if(! local_channel())
			return;

		if(! Apps::system_app_installed(local_channel(), 'OAuth Apps Manager')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('OAuth Apps Manager');
			return Apps::app_render($papp, 'module');
		}


		if((argc() > 1) && (argv(1) === 'add')) {
			$tpl = get_markup_template("oauth_edit.tpl");
			$o .= replace_macros($tpl, array(
				'$form_security_token' => get_form_security_token("oauth"),
				'$title'	=> t('Add application'),
				'$submit'	=> t('Submit'),
				'$cancel'	=> t('Cancel'),
				'$name'		=> array('name', t('Name'), '', t('Name of application')),
				'$key'		=> array('key', t('Consumer Key'), random_string(16), t('Automatically generated - change if desired. Max length 20')),
				'$secret'	=> array('secret', t('Consumer Secret'), random_string(16), t('Automatically generated - change if desired. Max length 20')),
				'$redirect'	=> array('redirect', t('Redirect'), '', t('Redirect URI - leave blank unless your application specifically requires this')),
				'$icon'		=> array('icon', t('Icon url'), '', t('Optional')),
			));
			return $o;
		}

		if((argc() > 2) && (argv(1) === 'edit')) {
			$r = q("SELECT * FROM clients WHERE client_id='%s' AND uid=%d",
					dbesc(argv(2)),
					local_channel());

			if (!count($r)){
				notice(t('Application not found.'));
				return;
			}
			$app = $r[0];

			$tpl = get_markup_template("oauth_edit.tpl");
			$o .= replace_macros($tpl, array(
				'$form_security_token' => get_form_security_token("oauth"),
				'$title'	=> t('Add application'),
				'$submit'	=> t('Update'),
				'$cancel'	=> t('Cancel'),
				'$name'		=> array('name', t('Name'), $app['clname'] , ''),
				'$key'		=> array('key', t('Consumer Key'), $app['client_id'], ''),
				'$secret'	=> array('secret', t('Consumer Secret'), $app['pw'], ''),
				'$redirect'	=> array('redirect', t('Redirect'), $app['redirect_uri'], ''),
				'$icon'		=> array('icon', t('Icon url'), $app['icon'], ''),
			));
			return $o;
		}

		if((argc() > 2) && (argv(1) === 'delete')) {
			check_form_security_token_redirectOnErr('/oauth', 'oauth', 't');

			$r = q("DELETE FROM clients WHERE client_id='%s' AND uid=%d",
					dbesc(argv(2)),
					local_channel());
			goaway(z_root()."/oauth");
			return;
		}


		$r = q("SELECT clients.*, tokens.id as oauth_token, (clients.uid=%d) AS my
				FROM clients
				LEFT JOIN tokens ON clients.client_id=tokens.client_id
				WHERE clients.uid IN (%d,0)",
				local_channel(),
				local_channel());


		$tpl = get_markup_template("oauth.tpl");
		$o .= replace_macros($tpl, array(
			'$form_security_token' => get_form_security_token("oauth"),
			'$baseurl'	=> z_root(),
			'$title'	=> t('Connected OAuth Apps'),
			'$add'		=> t('Add application'),
			'$edit'		=> t('Edit'),
			'$delete'		=> t('Delete'),
			'$consumerkey' => t('Client key starts with'),
			'$noname'	=> t('No name'),
			'$remove'	=> t('Remove authorization'),
			'$apps'		=> $r,
		));
		return $o;

	}

}
