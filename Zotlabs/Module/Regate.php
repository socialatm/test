<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Connect;
use Zotlabs\Daemon\Master;

require_once('include/security.php');

/**
 *
 * @version 2.0.0
 * @author  hilmar runge
 * @since 	2020-03-03
 * Check verification pin
 * input field email address
 * input field pin (told during register)
 * check duty
 * check startup and expire
 * compare email address
 * check pin
 * limited tries to enter the correct pin/pass 2 handle via f2b
 * on success create account and update register
 *
 */

	define ( 'REGISTER_AGREED', 0x0020 );
	define ( 'REGISTER_DENIED', 0x0040 );

class Regate extends \Zotlabs\Web\Controller {

	const MYP = 	'ZAR';		//ZAR1x
	const VERSION =	'2.0.0';


	function post() {

		check_form_security_token_redirectOnErr('/', 'regate');

		if ( argc() > 1 ) {
			$did2 = hex2bin( substr( argv(1), 0, -1) );
			$didx = substr( argv(1), -1 );
		}

		$msg = '';
		$nextpage = '';

		if ($did2) {

			$nowhhmm 	= date('Hi');
			$day 	= date('N');
			$now 	= datetime_convert();
			$ip 	= $_SERVER['REMOTE_ADDR'];

			$isduty = zar_register_dutystate();

			if (!$_SESSION['zar']['invite_in_progress'] && ($isduty['isduty'] !== false && $isduty['isduty'] != 1)) {
					// normally, that should never happen here
					// log suitable for fail2ban also
					$logmsg = 'ZAR1230S Unexpected registration verification request for '
							. get_config('system','sitename') . ' arrived from § ' . $ip . ' §';
					zar_log($logmsg);
					goaway(z_root());
			}

			// do we have a valid dId2 ?
			if (($didx == 'a' && substr( $did2 , -2) == substr( base_convert( md5( substr( $did2, 1, -2) ),16 ,10), -2)) || ($didx == 'e') || ($didx == 'i')) {
				// check startup and expiration via [=[register
				$r = q("SELECT * FROM register WHERE reg_vital = 1 AND reg_did2 = '%s' ORDER BY reg_created DESC ",
					dbesc($did2)
				);
				if ($r && count($r)) {
					$r = $r[0];
					// check timeframe
					if ($r['reg_startup'] <= $now && $r['reg_expires'] >= $now) {
						if (isset($_POST['resend']) && $didx == 'e') {
							$re = q("SELECT * FROM register WHERE reg_vital = 1 AND reg_didx = 'e' AND reg_did2 = '%s' ORDER BY reg_created DESC ", dbesc($r['reg_did2']) );
							if ($re) {
								$re = $re[0];
								$reonar = json_decode($re['reg_stuff'], true);
								if ($reonar) {
									$reonar['subject'] = 'Re,Fwd,' . $reonar['subject'];
									$zm = zar_reg_mail($reonar);
									$msg = (($zm) ? t('Email resent') : t('Email resend failed'));
									zar_log((($zm) ? 'ZAR1238I' : 'ZAR1238E') . ' ' . $msg . ' ' . $r['reg_did2']);
									info($msg);
									return;
								}
							}
						}

						// check hash
						if ( $didx == 'a' )
							$acpin = (preg_match('/^[0-9]{6,6}$/', $_POST['acpin']) ? $_POST['acpin'] : false);
						elseif ( $didx == 'e' )
							$acpin = (preg_match('/^[0-9a-f]{24,24}$/', $_POST['acpin']) ? $_POST['acpin'] : false);
						elseif ( $didx == 'i' )
							$acpin = $r['reg_hash'];
						else
							$acpin = false;

						if ( $acpin && ($r['reg_hash'] == $acpin )) {

							$flags = $r['reg_flags'];
							if (($flags & ACCOUNT_UNVERIFIED) == ACCOUNT_UNVERIFIED) {

								// verification success
								$msg_code = 'ZAR1237I';
								$msg = t('Verification successful');
								$reonar = json_decode( $r['reg_stuff'], true);
								$reonar['valid'] = $now . ',' . $ip . ' ' . $did2 . ' ' . $msg_code . ' ' . $msg;

								// clear flag
								$flags &= $flags ^ ACCOUNT_UNVERIFIED;

								// are we invited by the admin?
								$isa = get_account_by_id($r['reg_uid']);
								$isa = ($isa && ($isa['account_roles'] && ACCOUNT_ROLE_ADMIN));

								// approve contra invite by admin
								if ($isa && get_config('system','register_policy') == REGISTER_APPROVE) {
									$flags &= $flags ^ ACCOUNT_PENDING;
								}

								// sth todo?
								$vital = $flags == 0 ? 0 : 1;

								// set flag
								$flags |= REGISTER_AGREED;
								zar_log($msg . ' ' . $did2 . ':flags' . $flags . ',rid' . $r['reg_id']);

								q("START TRANSACTION");

								$qu = q("UPDATE register SET reg_stuff = '%s', reg_vital = %d, reg_flags = %d "
										." WHERE reg_id = %d ",
									dbesc(json_encode($reonar)),
									intval($vital),
									intval($flags),
									intval($r['reg_id'])
								);

								if (($flags & ACCOUNT_PENDING ) == ACCOUNT_PENDING) {
									$nextpage = 'regate/' . bin2hex($did2) . $didx;
									q("COMMIT");
								}
								elseif (($flags ^ REGISTER_AGREED) == 0) {

									$cra = create_account_from_register([ 'reg_id' => $r['reg_id'] ]);

									if ($cra['success']) {

										q("COMMIT");
										$msg = t('Account successfull created');
										// zar_log($msg . ':' . print_r($cra, true));
										zar_log('ZAR1238I ' . $msg . ' ' . $cra['account']['account_email']
													 . ' ' . $cra['account']['account_language']);

										authenticate_success($cra['account'],null,true,false,true);

										$nextpage = 'new_channel';

										$auto_create  = get_config('system', 'auto_channel_create', 1);

										if($auto_create) {

											$new_channel = ['success' => false];

											// We do not reserve a channel_address before the registration is verified
											// and possibly approved by the admin.
											// If the provided channel_address has been claimed meanwhile,
											// we will proceed to /new_channel.

											if(isset($reonar['chan.did1']) && check_webbie([$reonar['chan.did1']])) {

												// prepare channel creation
												if($reonar['chan.name'])
													set_aconfig($cra['account']['account_id'], 'register', 'channel_name', $reonar['chan.name']);

												if($reonar['chan.did1'])
													set_aconfig($cra['account']['account_id'], 'register', 'channel_address', $reonar['chan.did1']);

												$permissions_role  = get_config('system','default_permissions_role');
												if($permissions_role)
													set_aconfig($cra['account']['account_id'], 'register', 'permissions_role', $permissions_role);

												// create channel
												$new_channel = auto_channel_create($cra['account']['account_id']);

												if($new_channel['success']) {

													$channel_id = $new_channel['channel']['channel_id'];

													// If we have an inviter, connect.
													if ($didx === 'i' && intval($r['reg_byc'])) {
														$invite_channel = channelx_by_n($r['reg_byc']);
														if ($invite_channel) {
															$f = Connect::connect($new_channel['channel'], $invite_channel['xchan_addr']);
															if ($f['success']) {
																$can_view_stream = intval(get_abconfig($channel_id, $f['abook']['abook_xchan'], 'their_perms', 'view_stream'));
																// If we can view their stream, pull in some posts
																if ($can_view_stream) {
																	Master::Summon(['Onepoll', $f['abook']['abook_id']]);
																}
															}
														}
													}

													change_channel($channel_id);
													$nextpage = 'profiles/' . $channel_id;
													$msg_code = 'ZAR1239I';
													$msg = t('Channel successfull created') . ' ' . $did2;
												}
											}

											if(!$new_channel['success']) {
												$msg_code = 'ZAR1239E';
												$msg = t('Automatic channel creation failed. Please create a channel.') . ' ' . $did2;
												$nextpage = 'new_channel?name=' . $reonar['chan.name'];
											}

											zar_log($msg_code . ' ' . $msg . ' ' . $reonar['chan.did1'] . ' (' . $reonar['chan.name'] . ')');

										}
										unset($_SESSION['login_return_url']);
									}
									else {
										q("ROLLBACK");
										$msg_code = 'ZAR1238E';
										$msg = t('Account creation error');
										zar_log($msg_code . ' ' . $msg . ': ' . print_r($cra, true));
									}
								}
								else {
									// new flags implemented and not recognized or sth like
									zar_log('ZAR1237D unexpected,' . $flags);
								}
							}
							else {
								// nothing to confirm
								$msg_code = 'ZAR1236E';
								$msg = t('Verify failed');
							}
						}
						else {
							$msg_code = 'ZAR1235E';
							$msg = t('Token verification failed');
						}
					}
					else {
						$msg_code = 'ZAR1234W';
						$msg = t('Request not inside time frame');
						//info($r[0]['reg_startup'] . EOL . $r[0]['reg_expire'] );
					}
				}
				else {
					$msg_code = 'ZAR1232E';
					$msg = t('Identity unknown');
					zar_log($msg_code . ' ' . $msg . ':' . $did2 . $didx);
				}
			}
			else {
				$msg_code = 'ZAR1231E';
				$msg = t('dId2 mistaken');
				zar_log($msg_code . ' ' . $msg);

			}

		}

		if ($msg > '') info($msg);
		goaway( z_root() . '/' . $nextpage );
	}


	function get() {

		if (argc() == 1) {
			if(isset($_GET['reg_id'])) {
				if ( preg_match('/^.{2,64}\@[a-z0-9.-]{4,32}\.[a-z]{2,12}$/', $_GET['reg_id'] ) ) {
					// dId2 E email
					goaway(z_root() . '/regate/' . bin2hex($_GET['reg_id']) . 'e' );
				}
				if ( preg_match('/^d{1,1}[0-9]{5,10}$/', $_GET['reg_id'] ) ) {
					// dId2 A artifical & anonymous
					goaway(z_root() . '/regate/' . bin2hex($_GET['reg_id']) . 'a' );
				}
				notice(t('Identity unknown') . EOL);
			}

			$o = replace_macros(get_markup_template('plain.tpl'), [
				'$title'	=> t('Your Registration ID'),
				'$now'		=> '<form action="regate" method="get"><input type="text" name="reg_id" class="form-control form-group"><button class="btn btn-primary float-right">Submit</button></form>'
			]);

			return $o;
		}

		$isduty = zar_register_dutystate();
		$nowfmt = $isduty['nowfmt'];
		$atform = $isduty['atform'];

		if ($_SESSION['zar']['delayed']) {
			$o = replace_macros(get_markup_template('regate_pre.tpl'), [
				'$title'      => t('Registration verification'),
				'$now'        => $nowfmt,
				'$id'         => $_SESSION['zar']['id'],
				'$pin'        => $_SESSION['zar']['pin'],
				'$regdelay'   => $_SESSION['zar']['regdelay'],
				'$regexpire'  => $_SESSION['zar']['regexpire'],
				'$strings' => [
					t('Hold on, you can start verification in'),
					t('Please remember your verification token for ID'),
					'',
					t('Token validity')
				]
			]);
			unset($_SESSION['zar']['delayed']);
			return $o;
		}

		if (argc() < 2)
			return;

		$did2   = hex2bin( substr( argv(1), 0, -1) );
		$didx   = substr( argv(1), -1 );
		$deny   = argc() > 2 ? argv(2) : '';
		$deny   = preg_match('/^[0-9a-f]{8,8}$/', $deny) ? hex2bin($deny) : false;
		$now    = datetime_convert();
		$ip     = $_SERVER['REMOTE_ADDR'];

		$pin    = '';

		if(isset($_SESSION['zar']['pin'])) {
			$pin = $_SESSION['zar']['pin'];
			unset($_SESSION['zar']['pin']);
		}

		// do we have a valid dId2 ?
		if (($didx == 'a' && substr( $did2 , -2) == substr( base_convert( md5( substr( $did2, 1, -2) ),16 ,10), -2)) || ($didx == 'e') || ($didx == 'i')) {

			$r = q("SELECT * FROM register WHERE reg_vital = 1 AND reg_didx = '%s' AND reg_did2 = '%s' ORDER BY reg_created DESC",
				dbesc($didx),
				dbesc($did2)
			);

			if ($r && count($r) && $r[0]['reg_flags'] &= (ACCOUNT_UNVERIFIED | ACCOUNT_PENDING)) {
				$r = $r[0];

				// provide a button in case
				$resend = (($r['reg_didx'] == 'e') ? t('Resend email') : '');

				// is still only instance admins intervention required?
				if ($r['reg_flags'] == ACCOUNT_PENDING) {
					$o = replace_macros(get_markup_template('regate_post.tpl'), [
						'$title' => t('Registration status'),
						'$id'    => $did2,
						'$strings' => [
							t('Verification successful!'),
							t('Your login ID is'),
							t('After your account has been approved by our administrator you will be able to login with your login ID and your provided password.')
						]
					]);
				}
				else {

					if ($deny) {

						if (substr($r['reg_hash'],0,4) == $deny) {
							zar_log('ZAR1134S email verfication denied ' . $did2);

							$o = replace_macros(get_markup_template('plain.tpl'), [
								'$title' => t('Registration request revoked'),
								'$infos' => t('Sorry for any inconvience. Thank you for your response.')
							]);

							$reonar = json_decode( $r['reg_stuff'], true);
							$reonar['deny'] = $now . ',' . $ip . ' ' . $did2 . ' ' . $msg;
							$flags  = ( $r['reg_flags'] &= ( $r['reg_flags'] ^ ACCOUNT_UNVERIFIED) )
									| ( $r['reg_flags'] |= REGISTER_DENIED);
							$rd = q("UPDATE register SET reg_stuff='%s', reg_vital=0, reg_flags=%d  WHERE reg_id = %d ",
								dbesc(json_encode($reonar)),
								intval($flags),
								intval($r['reg_id'])
							);
						}
						else {
							zar_log('ZAR1135E not awaited url parameter received');
							goaway(z_root);
						}
					}
					else {

						if ( $r['reg_startup'] <= $now && $r['reg_expires'] >= $now) {
							$o = replace_macros(get_markup_template('regate.tpl'), [
							'$form_security_token' => get_form_security_token("regate"),
							'$title'  => t('Registration verification'),
							'$desc'   => t('Please enter your verification token for ID'),
							'$email_extra' => (($didx === 'e') ? t('Please check your email!') : ''),
							'$id'     => $did2,
							// we might consider to not provide $pin if a registration delay is configured
							// and the pin turns out to be readable by bots
							'$pin'    => $pin,
							'$did2'   => bin2hex($did2) . $didx,
							'$now'    => $nowfmt,
							'$atform' => $atform,
							'$resend' => $resend,
							'$submit' => t('Submit'),
							'$acpin'  => [ 'acpin', t('Verification token'),'','' ]
							]);
						}
						else {
							//  expired ?
							if ( $now > $r['reg_expires'] ) {
								$rd = q("UPDATE register SET reg_vital = 0 WHERE reg_id = %d ",
									intval($r['reg_id'])
								);

								$o = replace_macros(get_markup_template('plain.tpl'), [
									'$infos'	=> t('ID expired'),
								]);

								return $o;
							}

							$email_extra = (($didx === 'e') ? t('Please check your email!') : '');

							$o = replace_macros(get_markup_template('regate_pre.tpl'), [
								'$title'     => t('Registration verification'),
								'$now'       => $nowfmt,
								'$id'        => $did2,
								'$countdown' => datetime_convert('UTC', 'UTC', $r['reg_startup'], 'c'),
								'$strings'   => [
									t('Hold on, you can start verification in'),
									t('You will require the verification token for ID'),
									$email_extra
								]
							]);
						}
					}
				}
			}
			else {
				$msg = t('Unknown or expired ID');
				zar_log('ZAR1132E ' . $msg . ':' . $did2 . ',' . $didx);
				$o = replace_macros(get_markup_template('plain.tpl'), [
					'$title'	=> $title,
					'$now'		=> $nowfmt,
					'$infos'	=> $msg
				]);
			}

		}
		else {
			$msg = 'ZAR1131E ' . t('dId2 malformed');
			// $log = ' from § ' . $ip . ' §' . ' (' . dbesc($did2) . ')';
			zar_log($msg);
			$o = replace_macros(get_markup_template('plain.tpl'), [
				'$title'	=> $title,
				'$now'		=> $nowfmt,
				'$infos'	=> $msg
			]);
		}

		return $o;
	}
}

