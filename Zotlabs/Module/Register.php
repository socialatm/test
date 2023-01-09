<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;

require_once('include/security.php');
require_once('include/channel.php');

class Register extends Controller {

	const MYP = 'ZAR';		// ZAR0x
	const VERSION =	'2.0.0';

	function init() {

		// ZAR0

		$result = null;
		$cmd = ((argc() > 1) ? argv(1) : '');

		// Provide a stored request for somebody desiring a connection
		// when they first need to register someplace. Once they've
		// created a channel, we'll try to revive the connection request
		// and process it.

		if(isset($_REQUEST['connect']))
			$_SESSION['connect'] = $_REQUEST['connect'];

		switch($cmd) {
			case 'invite_check.json':
				$result = check_account_invite($_REQUEST['invite_code']);
				break;
			case 'email_check.json':
				$result = check_account_email($_REQUEST['email']);
				break;
			case 'password_check.json':
				$result = check_account_password($_REQUEST['password1']);
				break;
			default:
				break;
		}
		if($result) {
			json_return_and_die($result);
		}
	}

	function post() {

		check_form_security_token_redirectOnErr('/register', 'register');

		/**
		 * [hilmar:]
		 * It may happen, the posted form arrives in a strange fashion. With the control of the duty hours
		 * for registration, the input form was disabled at html. While receiving posted data, checks are
		 * required if all is on the right road (most posts are not accepted during off duty).
		 */


		$act          = q("SELECT COUNT(*) AS act FROM account")[0]['act'];
		$is247        = false;
		$ip           = $_SERVER['REMOTE_ADDR'];
		$sameip       = intval(get_config('system','register_sameip', 3));
		$arr          = $_POST;
		$invite_code  = ((x($arr,'invite_code'))   ? notags(trim($arr['invite_code']))   : '');
		$name         = '';
		$nick         = '';
		$email        = ((x($arr,'email'))         ? notags(punify(trim($arr['email']))) : '');
		$password     = ((x($arr,'password'))      ? trim($arr['password'])              : '');
		$password2    = ((x($arr,'password2'))      ? trim($arr['password2'])            : '');
		$register_msg = ((x($arr,'register_msg'))   ? notags(trim($arr['register_msg']))   : '');
		$reonar       = [];
		$auto_create  = get_config('system','auto_channel_create', 1);
		$duty         = zar_register_dutystate();

		if (!get_config('system', 'register_duty_jso')) {
			// if not yet configured default to true
			$duty = array( 'isduty' => true, 'atfrm' => '', 'nowfmt' => '');
		}

		if($auto_create) {
			$name = escape_tags(trim($arr['name']));

			$name_error = validate_channelname($name);
			if($name_error) {
				notice($name_error . EOL);
				return;
			}

			$nick = mb_strtolower(escape_tags(trim($arr['nickname'])));
			if(!$nick) {
				notice(t('Nickname is required.'));
				return;
			}

			if($nick === 'sys') {
				notice(t('Reserved nickname. Please choose another.') . EOL);
				return;
			}

			if(check_webbie([$nick]) !== $nick) {
				notice(t('Nickname has unsupported characters or is already being used on this site.') . EOL);
				return;
			}
		}

		$email_verify = get_config('system', 'verify_email');
		if ($email_verify && !$email) {
			notice(t('Email address required') . EOL);
			return;
		}

		if ($email) {
			$email_result = check_account_email($email);
			if ($email_result['error']) {
				if ($email_result['email_unverified']) {
					goaway(z_root() . '/regate/' . bin2hex($email) . 'e');
				}
				return;
			}
		}

		// case when an invited prepares the own account by supply own pw, accept tos, prepage channel (if auto)
		if ($email && $invite_code) {
			if ( preg_match('/^[a-z0-9]{12,12}$/', $invite_code ) ) {
				$is247 = true;
			}
		}

		if ($act > 0 && !$is247 && !$duty['isduty']) {
			// normally (except very 1st timr after install), that should never arrive here (ie js hack or sth like)
			// log suitable for f2b also
			$logmsg = 'Unexpected registration request off duty';
			notice($logmsg);
			zar_log('ZAR0230S ' . $logmsg);
			return;
		}

		if ($sameip) {
			$f = q("SELECT COUNT(reg_atip) AS atip FROM register WHERE reg_vital = 1 AND reg_atip = '%s' ",
				dbesc($ip)
			);
			if ($f && $f[0]['atip'] >= $sameip) {
				$logmsg = 'ZAR0239S Exceeding same ip register request of ' . $sameip;
				notice('Registrations from same IP exceeded.');
				zar_log($logmsg);
				return;
			}
		}

		if (!$password) {
			notice(t('No password provided') . EOL);
			return;
		}

		if ($password !== $password2) {
			notice(t('Passwords do not match') . EOL);
			return;
		}

		$password_result = check_account_password($password);
		if(!empty($password_result['error'])) {
			$msg = $password_result['message'];
			notice($msg);
			zar_log($msg);
			return;
		}

		$salt = random_string(32);
		$password = $salt . ',' . hash('whirlpool', $salt . $password);

		// accept tos
		if(! x($_POST,'tos')) {
			// msg!
			notice(t('Terms of Service not accepted') . EOL);
			return;
		}

		$policy  = get_config('system','register_policy');
		$invonly = get_config('system','invitation_only');
		$invalso = get_config('system','invitation_also');

		switch($policy) {

			case REGISTER_OPEN:
				$flags = ACCOUNT_OK;
				break;

			case REGISTER_APPROVE:
				$flags = ACCOUNT_PENDING;
				break;

			default:
			case REGISTER_CLOSED:
				if(! is_site_admin()) {
					notice( t('Permission denied.') . EOL );
					return;
				}
				$flags = ACCOUNT_BLOCKED;
				break;
		}

		if($email_verify && ($policy == REGISTER_OPEN || $policy == REGISTER_APPROVE))
			$flags = ($flags | ACCOUNT_UNVERIFIED);

		// $arr has $_POST;
		$arr['account_flags'] = $flags;
		$now = datetime_convert();
		$well = false;

		// s3
		if ($invite_code) {

			if ($invonly || $invalso) {

				$reg = q("SELECT * from register WHERE reg_vital = 1 AND reg_didx = 'i' AND reg_hash = '%s'",
					dbesc($invite_code)
				);

				if ($reg && count($reg) == 1) {
					$reg = $reg[0];
					if ($reg['reg_email'] == ($email)) {

						if ($reg['reg_startup'] <= $now && $reg['reg_expires'] >= $now) {

							if ($auto_create) {
								$reonar['chan.name'] = $name;
								$reonar['chan.did1'] = $nick;
							}

							q("UPDATE register set reg_pass = '%s', reg_stuff = '%s' WHERE reg_id = '%s'",
								dbesc($password),
								dbesc(json_encode($reonar)),
								intval($reg['reg_id'])
							);

							$msg = t('Invitation code succesfully applied');
							zar_log('ZAR0237I ' . $msg . ', ' . $email);
							// msg!
							info($msg . EOL);


							// the invitecode has verified us and we have all the info we need
							// take the shortcut.

							$_SESSION['zar']['invite_in_progress'] = true;

							$mod = new Regate();
							$_REQUEST['form_security_token'] = get_form_security_token("regate");
							App::$argc = 2;
							App::$argv[0] = 'regate';
							App::$argv[1] = bin2hex($reg['reg_did2']) . 'i';
							return $mod->post();

						} else {
							// msg!
							notice(t('Invitation not in time or too late') . EOL);
							return;
						}

					} else {
						// no match email adr
						$msg = t('Invitation email failed');
						zar_log('ZAR0235S ' . $msg);
						notice($msg . EOL);
						return;
					}

				} else {
					// no match invitecode
					$msg = t('Invitation code failed') ;
					zar_log('ZAR0234S ' . $msg);
					notice( $msg . EOL);
					return;
				}

			} else {
				notice(t('Invitations are not available') . EOL);
				return;
			}

		}
		else {
			if (!$invonly) {
				$well = true;
			}
			else {
				$msg = t('Registration on this hub is by invitation only') . EOL;
				notice($msg);
				zar_log('ZAR0233E ' . $msg);
				return;
			}
		}

		// check max daily registrations after we have dealt with the invitecode
		if (self::check_reg_limits()['is']) {
			notice('Max registrations per day exceeded.');
			return;
		}

		if ($well) {

			if($policy == REGISTER_OPEN || $policy == REGISTER_APPROVE ) {

				$cfgdelay = get_config('system', 'register_delay', '0i');
				$reg_delayed = calculate_adue( $cfgdelay );
				$regdelay = (($reg_delayed) ? datetime_convert(date_default_timezone_get(), 'UTC', $reg_delayed['due']) : $now);

				$cfgexpire = get_config('system', 'register_expire', '3d');
				$reg_expires = calculate_adue( $cfgexpire );
				$regexpire = (($reg_expires) ? datetime_convert(date_default_timezone_get(), 'UTC', $reg_expires['due']) : datetime_convert('UTC', 'UTC', 'now + 99 years'));

				// handle an email request that will be verified or an ivitation associated with an email address
				if ($email > '' && $email_verify) {
					// enforce in case of icdone
					$flags |= ACCOUNT_UNVERIFIED;
					$empin = $pass2 = random_string(24);
					$did2  = $email;
					$didx  = 'e';

					push_lang(($reg['lang']) ? $reg['lang'] : App::$language);
					$reonar['from'] = get_config('system', 'from_email');
					$reonar['to'] = $email;
					$reonar['subject'] = sprintf( t('Registration confirmation for %s'), get_config('system','sitename'));
					$reonar['txttemplate']= replace_macros(get_intltext_template('register_verify_member.tpl'),
						[
						'$sitename'  => get_config('system','sitename'),
						'$siteurl'   => z_root(),
						'$email'     => $email,
						'$timeframe' => [$regdelay, $regexpire],
						'$mail'      => bin2hex($email) . 'e',
						'$ko'        => bin2hex(substr($empin,0,4)),
						'$hash'      => $empin
				 		]
					);
					pop_lang();
					zar_reg_mail($reonar);

				} else {
					// that is an anonymous request without email or with email not to verify
					$acpin = $pass2 = rand(100000,999999);
					$did2 = rand(10,99);
					$didx = 'a';
					// enforce delayed verify
					$flags = ($flags | ACCOUNT_UNVERIFIED);
					if ($email) {
						$reonar['email.untrust'] = $email;
						$reonar['email.comment'] = 'received, but no need for';
					}
				}

				if ($auto_create) {
					$reonar['chan.name'] = $name;
					$reonar['chan.did1'] = $nick;
				}

				if ($policy == REGISTER_APPROVE) {
					$reonar['msg'] = $register_msg;
				}

				$reg = q("INSERT INTO register ("
					. "reg_flags,reg_didx,reg_did2,reg_hash,reg_created,reg_startup,reg_expires,"
					. "reg_email,reg_pass,reg_lang,reg_atip,reg_stuff)"
					. " VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s') ",
					intval($flags),
					dbesc($didx),
					dbesc($did2),
					dbesc($pass2),
					dbesc($now),
					dbesc($regdelay),
					dbesc($regexpire),
					dbesc($email),
					dbesc($password),
					dbesc(App::$language),
					dbesc($ip),
					dbesc(json_encode($reonar))
				);

				if ($didx == 'a') {

					$lid = q("SELECT reg_id FROM register WHERE reg_vital = 1 AND reg_did2 = '%s' AND reg_pass = '%s' ",
						dbesc($did2),
						dbesc($password)
					);

					if ($lid && count($lid) == 1 ) {

						$didnew = ( $lid[0]['reg_id'] . $did2 )
						 		. ( substr( base_convert( md5( $lid[0]['reg_id'] . $did2 ), 16, 10 ),-2 ) );

						$reg = q("UPDATE register SET reg_did2 = CONCAT('d','%s') WHERE reg_id = %d ",
							dbesc($didnew), intval($lid[0]['reg_id'])
						);

						zar_log( 'ZAR0239A ' . t('New register request') . ' d' . $didnew . ', '
							.  $regdelay . ' - ' . $regexpire);

						if($reg_delayed) {
							// this could be removed to make registration harder
							$_SESSION['zar']['id'] = 'd' . $didnew;
							$_SESSION['zar']['pin'] = $pass2;
							$_SESSION['zar']['delayed'] = true;
							$_SESSION['zar']['regdelay'] = datetime_convert('UTC', 'UTC', $regdelay, 'c');
							$_SESSION['zar']['regexpire'] = datetime_convert('UTC', 'UTC', $regexpire, 'c');
						}
						else {
							$_SESSION['zar']['pin'] = $pass2;
						}

						goaway(z_root() . '/regate/' . bin2hex('d' . $didnew) . 'a' );
					}
					else {
						$msg = t('Error creating dId A');
						notice( $msg );
						zar_log( 'ZAR0239D,' . $msg . ' ' . $did2);
					}
				}
				goaway(z_root() . '/regate/' . bin2hex($email) . $didx );
			}
		}
	}

	function get() {

		$registration_is = '';
		$other_sites = '';

		if(intval(get_config('system','register_policy')) === REGISTER_CLOSED) {
			if(intval(get_config('system','directory_mode')) === DIRECTORY_MODE_STANDALONE) {
				notice(t('Registration on this hub is disabled.')  . EOL);
				return;
			}

			$mod = new Pubsites();
			return $mod->get();
		}

		if(intval(get_config('system','register_policy')) == REGISTER_APPROVE) {
			$registration_is = t('Registration on this hub is by approval only.');
			$other_sites = '<a href="pubsites">' . t('Register at another affiliated hub in case when prefered') . '</a>';
		}

		$duty = zar_register_dutystate();

		if (!get_config('system', 'register_duty_jso')) {
			// if not yet configured default to true
			$duty = array( 'isduty' => true, 'atfrm' => '', 'nowfmt' => '');
		}

		$invitations = false;
		if(intval(get_config('system','invitation_only'))) {
			$invitations = true;
			$registration_is = t('Registration on this hub is by invitation only.');
			$other_sites = '<a href="pubsites">' . t('Register at another affiliated hub') . '</a>';
		} elseif (intval(get_config('system','invitation_also'))) {
			$invitations = true;
		}

		$opal = self::check_reg_limits();
		if ( $opal['is'])
			 $duty['atform'] = 'disabled';

		// Configurable terms of service link
		$tosurl = get_config('system','tos_url');
		if(! $tosurl)
			$tosurl = z_root() . '/help/TermsOfService';

		$toslink = '<a href="' . $tosurl . '" target="_blank">' . t('Terms of Service') . '</a>';

		// Configurable whether to restrict age or not - default is based on international legal requirements
		// This can be relaxed if you are on a restricted server that does not share with public servers

		if(get_config('system','no_age_restriction')) {
			$label_tos = sprintf( t('I accept the %s for this website'), $toslink);
		}
		else {
			$age = get_config('system','minimum_age');
			if(!$age) {
				$age = 18;
			}
			$label_tos = sprintf( t('I am over %s years of age and accept the %s for this website'), $age, $toslink);
		}

		$enable_tos = 1 - intval(get_config('system','no_termsofservice'));

		$auto_create  = get_config('system', 'auto_channel_create', 1);
		$email_verify = get_config('system','verify_email');

		$emailval = ((x($_REQUEST,'email')) ? strip_tags(trim($_REQUEST['email'])) : "");
		$email = ['email',
			t('Your email address'),
			$emailval,
			(($email_verify) ? t('Required') : t('Optional')),
			(($email_verify) ? '*' : ''),
			$duty['atform']
		];

		$password     = array('password', t('Choose a password'), '', '', '', $duty['atform']);
		$password2    = array('password2', t('Please re-enter your password'), '', '', '', $duty['atform']);

		$invite_code  = array('invite_code', t('Please enter your invitation code'), ((x($_REQUEST,'invite_code')) ? strip_tags(trim($_REQUEST['invite_code'])) : ""));

		$name = array('name', t('Your name'), ((x($_REQUEST,'name')) ? $_REQUEST['name'] : ''), t('Real name is preferred'), '', '', $duty['atform']);
		$nickhub = '@' . str_replace(array('http://','https://','/'), '', get_config('system','baseurl'));
		$nickname = array('nickname', t('Choose a short nickname'),	((x($_REQUEST,'nickname')) ? $_REQUEST['nickname'] : ''), t('Your nickname will be used to create an easy to remember channel address'), '', '', $duty['atform']);

		$tos = array('tos', $label_tos, ((x($_REQUEST,'tos')) ? $_REQUEST['tos'] : ''), '', [t('No'),t('Yes')], $duty['atform']);

		$register_msg = ['register_msg', t('Why do you want to join this hub?'), ((x($_REQUEST,'register_msg')) ? $_REQUEST['register_msg'] : ''), t('This will help to review your registration')];

		require_once('include/bbcode.php');

		$o = replace_macros(get_markup_template('register.tpl'), array(
			'$form_security_token' => get_form_security_token("register"),
			'$title'        => t('Registration'),
			'$reg_is'       => $registration_is,
			'$register_msg' => $register_msg,
			'$registertext' => bbcode(get_config('system','register_text')),
			'$other_sites'  => $other_sites,
			'$msg'          => $opal['msg'],
			'$invitations'  => $invitations,
			'$invite_code'  => $invite_code,
			'$haveivc'		=> t('I have an invite code'),
			'$now'			=> $duty['nowfmt'],
			'$atform'		=> $duty['atform'],
			'$auto_create'  => $auto_create,
			'$name'         => $name,
			'$nickname'     => $nickname,
			'$enable_tos'	=> $enable_tos,
			'$tos'          => $tos,
			'$email'        => $email,
			'$validate'		=> $validate,
			'$validate_link'=> $validate_link,
			'$validate_here'=> $validate_here,
			'$pass1'        => $password,
			'$pass2'        => $password2,
			'$submit'       => t('Register'),
			'$nickhub'      => $nickhub

		));
		return $o;
	}

	function check_reg_limits() {
		// check against register, account
		$rear = array( 'is' => false, 'rn' => 0, 'an' => 0, 'msg' => '' );

		$max_dailies = intval(get_config('system', 'max_daily_registrations', 50));

		if ($max_dailies) {

			$r = q("SELECT COUNT(reg_id) AS nr FROM register WHERE reg_vital = 1 AND reg_created > %s - INTERVAL %s",
				db_utcnow(), db_quoteinterval('1 day')
			);

			$rear['is'] = ( $r && $r[0]['nr'] >= $max_dailies ) ? true : false;
			$rear['rn'] = $r[0]['nr'];

			if (!$rear['is']) {
				$r = q("SELECT COUNT(account_id) AS nr FROM account WHERE account_created > %s - INTERVAL %s",
					db_utcnow(), db_quoteinterval('1 day')
				);

				$rear['is'] = ( $r && ($r[0]['nr'] + $rear['rn']) >= $max_dailies ) ? true : false;
				$rear['ra'] = $r[0]['nr'];
			}

			if ( $rear['is']) {
				$rear['msg'] = t('This site has exceeded the number of allowed daily account registrations.');
				zar_log('ZAR0333W ' . $rear['msg']);
				$rear['is'] = true;
			}
		}
		return $rear;
	}
}
