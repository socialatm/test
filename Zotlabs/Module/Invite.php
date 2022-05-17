<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

/**
 * module: invitexv2.php
 *
 * send email invitations to join social network
 *
 */


class Invite extends Controller {

	/**
	 * While coding this, I want to introduce a system of qualified messages and notifications.
	 * Each message consists of a 3 letter prefix, a 4 digit number and a one letter suffix (PREnnnnS).
	 * The spirit about is not from me, but many decades used by IBM inc. in devel with best success.
	 *
	 * The system prefix, used uppercase as system message id, lowercase as css and js prefix (classes, ids etc).
	 * Usually not used as self::MYP, but placed in the code dominant enough for easy to find.
	 *
	 * Concrete here:
	 * The prefix indicates Z for the Zlabs(core), A for Account stuff, I for Invite.
	 * The numbers scope will be 00xx within/for templates, 01xx for get, 02xx for post functions.
	 * Message qualification ends with a uppercase suffix, where
	 * 	I=Info(only),
	 *	W=Warning(more then info and less then error),
	 *	E=Error,
	 *	F=Fatal(for unexpected errors).
	 * Btw, in case of using fail2ban, a scan of messages going to log is very much more with ease,
	 * esspecially in multi language driven systems where messages vary.
	 *
	 * @author Hilmar Runge
	 * @version 2.0.0
	 * @since 2020-01-20
	 *
	 */

	const MYP = 	'ZAI';
	const VERSION =	'2.0.0';

	function post() {

		// zai02

		if (! local_channel()) {
			notice( 'ZAI0201E,' .t('Permission denied.') . EOL);
			return;
		}

		if (! Apps::system_app_installed(local_channel(), 'Invite')) {
			notice( 'ZAI0202E,' . t('Invite App') . ' (' . t('Not Installed') . ')' . EOL);
			return;
		}

		check_form_security_token_redirectOnErr('/', 'send_invite');

		$ok = $ko = 0;
		$feedbk = '';
		$isajax = is_ajax();
		$eol 	= $isajax ? "\n" : EOL;
		$policy  = intval(get_config('system','register_policy'));
		if ($policy == REGISTER_CLOSED) {
			notice( 'ZAI0212E,' . t('Register is closed') . ')' . EOL);
			return;
		}
		if ($policy == REGISTER_OPEN)
			$flags = 0;
		elseif ($policy == REGISTER_APPROVE)
			$flags = ACCOUNT_PENDING;
		$flags  = ($flags | intval(get_config('system','verify_email')));

		// how many max recipients in one mail submit
		$maxto = get_config('system','invitation_max_recipients', 'na');
		If (is_site_admin()) {
			// set, if admin is operator, default to 12
			if ($maxto === 'na') set_config('system','invitation_max_recipients', 12);
		}
		$maxto = ($maxto === 'na') ? 12 : $maxto;

		// language code current for the invitation
		$lcc  = x($_POST['zailcc'])  && preg_match('/[a-z\-]{2,5}/', $_POST['zailcc'])
				? $_POST['zailcc']
				: '';

		// expiration duration amount quantity, in case of doubts defaults 2
		$durn = x($_POST['zaiexpiren']) && preg_match('/[0-9]{1,2}/', $_POST['zaiexpiren'])
				? trim(intval($_POST['zaiexpiren']))
				: '2';
		!$durn ? $durn = 2 : '';

		// expiration duration unit 1st letter (day, weeks, months, years), defaults days
		$durq = x($_POST['zaiexpire']) && preg_match('/[ihd]{1,1}/', $_POST['zaiexpire'])
				? $_POST['zaiexpire']
				: 'd';

		$dur = self::calcdue($durn.$durq);
		$due = t('Note, the invitation code is valid up to') . ' ' . $dur['due'];

		if ($isajax) {
			$feedbk .= 'ZAI0207I ' . $due . $eol;
		}

		// take the received email addresses and discart duplicates
		$recips  = array_filter( array_unique( preg_replace('/^\s*$/', '',
			((x($_POST,'zaito')) ? explode( "\n",$_POST['zaito']) : array() ) )));

		$havto = count($recips);

		if ( $havto > $maxto) {
			$feedbk .= 'ZAI0210E ' . sprintf( t('Too many recipients for one invitation (max %d)'), $maxto) . $eol;
			$ko++;

		} elseif ( $havto == 0 ) {
			$feedbk .= 'ZAI0211E ' . t('No recipients for this invitation') . $eol;
			$ko++;

		} else {

			// each email address
			foreach($recips as $n => $recip) {

				// if empty ignore
				$recip = $recips[$n] = trim($recip);
				if(! $recip) continue;

				// see if we have an email address who@domain.tld
				//if (!preg_match('/^.{2,64}\@[a-z0-9.-]{2,32}\.[a-z]{2,12}$/', $recip)) {
					//$feedbk .= 'ZAI0203E ' . ($n+1) . ': ' . sprintf( t('(%s) : Not a valid email address'), $recip) . $eol;
					//$ko++;
					//continue;
				//}
				if(! validate_email($recip)) {
					$feedbk .= 'ZAI0204E ' . ($n+1) . ': ' . sprintf( t('(%s) : Not a real email address'), $recip) . $eol;
					$ko++;
					continue;
				}

				// do we accept the email (not black listed)
				if(! allowed_email($recip)) {
					$feedbk .= 'ZAI0205E ' . ($n+1) . ': ' . sprintf( t('(%s) : Not allowed email address'), $recip) . $eol;
					$ko++;
					continue;
				}

				// is the email address just in use for account or registered before
				$r = q("SELECT account_email       AS em FROM account  WHERE account_email = '%s'"
					  . " UNION "
					  ."SELECT reg_email           AS em FROM register WHERE reg_vital = 1 AND reg_email = '%s' LIMIT 1;",
						dbesc($recip),
						dbesc($recip)
				);
				if($r && $r[0]['em'] == $recip) {
					$feedbk .= 'ZAI0206E ' . ($n+1) . ': ' . sprintf( t('(%s) : email address already in use'), $recip) . $eol;
					$ko++;
					continue;
				}

				if ($isajax) {
					// seems we have an email address acceptable
					$feedbk .= 'ZAI0209I ' . ($n+1) . ': ' . sprintf( t('(%s) : Accepted email address'), $recip) . $eol;
				}
			}
		}

		if ($isajax) {
			// we are not silent on the ajax road
			echo json_encode(array('feedbk' => $feedbk, 'due' => $due));

			// that mission is complete
			killme();
			exit;
		}

		//	Total ?todo notice( t('Invitation limit exceeded. Please contact your site administrator.') . EOL);

		// any errors up to now in fg?


		// down from here, only on the main road (no more ajax)

		// tell if sth is to tell
		$feedbk ? notice($feedbk) . $eol : '';

		if ($ko > 0) return;

		// the personal mailtext
		$mailtext = ((x($_POST,'zaitxt'))    ? notags(trim($_POST['zaitxt']))    : '');

		// to log in db
		$reonar = json_decode( ((x($_POST,'zaireon'))  ? notags(trim($_POST['zaireon']))    : ''), TRUE, 8) ;

		// me, the invitor
		$account = App::get_account();
		$reonar['from'] = $account['account_email'];
		$reonar['date'] = datetime_convert();
		$reonar['fromip'] = $_SERVER['REMOTE_ADDR'];

		// who is the invitor on
		$inby = local_channel();

		$ok = $ko = 0;

		// send the mail(s)
		foreach($recips as $n => $recip) {

			$reonar['due'] = $due;
			$reonar['to']  = $recip;
			$reonar['txtpersonal'] = $mailtext;

			// generate an invide code to store and pm
			$invite_code = autoname(8) . rand(1000,9999);

			// again the final localized templates  $reonar['subject'] $reonar['lang'] $reonar['tpl']

			// save current operators lc and take the desired to mail
			push_lang($reonar['lang']);
			// resolve
			$tx = replace_macros(get_intltext_template('invite.'.$reonar['tpl'].'.tpl'),
				array(
					'$projectname'		=> t('$Projectname'),
					'$invite_code'		=> $invite_code,
					'$invite_where' 	=> z_root() . '/register',
					'$invite_whereami'	=> $reonar['whereami'],
					'$invite_whoami'	=> z_root() . '/channel/' . $reonar['whoami'],
					'$invite_anywhere'	=> z_root() . '/pubsites'
				)
			);
			// restore lc to operator
			pop_lang();

			$reonar['txttemplate'] = $tx;

			// pm
			$zem = z_mail(
				[
				'toEmail'        => $recip,
				'fromName'       => ' ',
				'fromEmail'      => $reonar['from'],
				'messageSubject' => $reonar['subject'],
				'textVersion'    => ($mailtext ? $mailtext . "\n\n" : '') . $tx . "\n" . $due,
				]
			);

			if(!$zem) {

				$ko++;
				$msg = 'ZAI0208E,' . sprintf( t('%s : Message delivery failed.'), $recip);

			} else {

				$ok++;
				$msg = 'ZAI0208I ' . sprintf( t('To %s : Message delivery success.'), $recip);

				// if verify_email is the rule, email becomes a dId2 - NO
				// $did2 = ($flags & ACCOUNT_UNVERIFIED) == ACCOUNT_UNVERIFIED ? $recip : '';

				// always enforce verify email with invitations, thus email becomes a dId2
				$did2 = $recip;
				$flags |= ACCOUNT_UNVERIFIED;

				// defaults vital, reg_pass
				$r = q("INSERT INTO register ("
				. "reg_flags,reg_didx,reg_did2,reg_hash,reg_created,reg_startup,reg_expires,reg_email,reg_byc,reg_uid,reg_atip,reg_lang,reg_stuff)"
				. " VALUES ( %d, 'i', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, '%s', '%s', '%s') ",
					intval($flags),
					dbesc($did2),
					dbesc($invite_code),
					dbesc(datetime_convert()),
					dbesc(datetime_convert()),
					dbesc($dur['due']),
					dbesc($recip),
					intval($inby),
					intval($account['account_id']),
					dbesc($reonar['fromip']),
					dbesc($reonar['lang']),
					dbesc(json_encode( array('reon' => $reonar) ))
				);
			}
			$msg .= ' (a' . $account['account_id'] . ', c' . $inby . ', from:' . $reonar['from'] . ')';
			zar_log( $msg);
		}

		$ok + $ko > 0
		? notice( 'ZAI0212I ' . sprintf( t('%1$d mail(s) sent, %2$d mail error(s)'), $ok, $ko) . EOL)
		: '';
		//logger( print_r( $reonar, true) );

		return;
	}


	function get() {

		// zai1

		if(! local_channel()) {
			notice( 'ZAI0101E,' . t('Permission denied.') . EOL);
			return;
		}

		if(! Apps::system_app_installed(local_channel(), 'Invite')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Invite');
			return Apps::app_render($papp, 'module');
		}

		if (! (get_config('system','invitation_also') || get_config('system','invitation_only')) ) {
			$o = 'ZAI0103E,' . t('Invites not proposed by configuration') . '. ';
			$o .= t('Contact the site admin');
			return $o;
		}

		// invitation_by_user may still not configured, the default 'na' will tell this
		// if configured, 0 disables invitations by users, other numbers are how many invites a user may propagate
		$invuser = get_config('system','invitation_by_user', 'na');

		// if the mortal user drives the invitation
		If (! is_site_admin()) {

			// when not configured, 4 is the default
			$invuser = ($invuser === 'na') ? 4 : $invuser;

			// a config value 0 disables invitation by users
			if (!$invuser) {
				$o = 'ZAI0104E, ' . t('Invites by users not enabled') . '. ';
				return $o;
			}

			if ($ihave >= $invuser) {
				notice( 'ZAI0105W,' . t('You have no more invitations available') . EOL);
				return '';
			}

		} else {
			// general deity admin invite limit infinite (theoretical)
			if ($invuser === 'na') set_config('system','invitation_by_user', 4);
			// for display only
			$invuser = '∞';
		}

		// xchan record of the page observer
		// while quoting matters the user, the sending is associated with a channel (of the user)
		// also the admin may and should decide, which channel will told to the public
		$ob = App::get_observer();
		if(! $ob)
			return 'ZAI0109F,' . t('Not on xchan') . EOL;
		$whereami = $ob['xchan_addr'];
		$channel  = App::get_channel();
		$whoami = $channel['channel_address'];

		// to pass also to post()
		$tao = 'tao.zai.whereami = ' . "'" . $whereami . "';\n"
			 . 'tao.zai.whoami = ' 	 . "'" . $whoami . "';\n";

		// expirations, duration interval
		$dur = self::calcdue();
		$tao .= 'tao.zai.expire = { durn: ' . $dur['durn']
			  					. ', durq: ' . "'" . $dur['durq']  . "'"
			  					. ', due: '  . "'" . $dur['due']   . "' };\n";

		// to easy redisplay the empty form
		nav_set_selected('Invite');

		// inform about the count of invitations we have at all
		$r = q("SELECT count(reg_id) as ct FROM register WHERE reg_vital = 1");		// where not admin TODO
		$wehave = ($r ? $r[0]['ct'] : 0);

		// invites max for all users except admins
		$invmaxau = intval(get_config('system','invitations_max_users'));
		if(! $invmaxau) {
			$invmaxau = 50;
			if (is_site_admin()) {
				set_config('system','invitations_max_users',intval($invmaxau));
			}
		}

		if ($wehave > $invmaxau) {
			if (! is_site_admin()) {
				$feedbk .= 'ZAI0200E,' . t('All users invitation limit exceeded.') . $eol;
			}
		}

		// let see how many invites currently used by the user
		$r = q("SELECT count(reg_id) AS n FROM register WHERE reg_vital = 1 AND reg_byc = %d",
			 intval(local_channel()));
		$ihave = $r ? $r[0]['n'] : 0;

		$tpl = get_markup_template('invite.tpl');

		$inv_rabots = array(
 					'i'	=> t('Minute(s)'),
 					'h' => t('Hour(s)')  ,
 					'd' => t('Day(s)')
		);
		$inv_expire = replace_macros(get_markup_template('field_duration.qmc.tpl'),
			 array(
			 	'label'  	=> t('Invitation expires after'),
			 	'qmc'	 	=> 'zai',
				'qmcid'		=> 'ZAI0014I',
			 	'field' => 	array(
			 		'name'  => 'expire',
			 		'title' => t('duration up from now'),
			 		'value' => ($invexpire_n ? $invexpire_n : 2),
			 		'min'  => '1',
			 		'max'  => '99',
			 		'size' => '2',
					'default' => ($invexpire_u ? $invexpire_u : 'd')
			 	),
			 	'rabot'	=> 	$inv_rabots
			 )
		);

		// let generate an invite code that here and never will be applied (only to fill displayed template)
		// real invite codes become generated for each recipient when we store the new invitation(s)
		// $invite_code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8) . rand(1000,9999);
		// let take one descriptive for template (as said is never used)
		$invite_code = 'INVITATE2020';

		// and all the localized templates belonging to invite
		$tpls	= glob('view/*/invite.*.tpl');

		$tpla=$tplx=$tplxs=array();
		foreach ($tpls as $tpli) {
			list( $nop, $l, $t ) = explode( '/', $tpli);
			if ( preg_match('/\.subject/', $t) =='1' ) {
				// indicate a subject tpl exists
				$t=str_replace(array('invite.', '.subject', '.tpl'), '', $t);
				$tplxs[$l][$t]=true;
				continue;
			}
			// collect unique template names cross all languages and
			// tpla[language][]=template those available in each language
			$tplx[] = $tpla[$l][] = str_replace( array('invite.', '.tpl'), '', $t);
		}

		$langs = array_keys($tpla);
		asort($langs);

		// Use the current language if we have a template for it. Otherwise fall back to 'en'.
		$lccmy	= ((in_array(App::$language, $langs)) ? App::$language : 'en');

		$tplx = array_unique($tplx);
		asort($tplx);

		// prepare current language and the default standard template (causual) for js
		// With and in js, I use a var 'tao' as a shortcut for top array object
		// and also qualify the object with the prefix zai = tao.zai as my var used outsite functions
		// can be unique within the overall included spaghette whirls
		// one can say Im too lazy to write prototypes and just I can agree.
		// tao simply applies the fact of using the same var as object and/or array in ja.
		$tao.='tao.zai.lccmy = ' . "'" . $lccmy . "';\n" . 'tao.zai.itpl = ' . "'" . 'casual' . "';\n";

		$lcclane=$tx=$tplin='';
		//$lccsym='<span class="fa zai_fa zai_lccsym"></span>';	// alt 
		$tplsym='<span class="fa zai_fa"></span>';

		// I will uncomment for js console debug
		// $tao.='tao.zai.debug = ' . "'" . json_encode($tplxs) . "';\n";

		// running thru the localized templates (subjects and textmsgs) and bring them to tao
		// lcc LanguageCountryCode,
		// lcc2 is a 2 character and lcc5 a 5 character LanguageCountryCode
		foreach($tpla as $l => $tn) {

			// restyle lc to iso getttext format to avoid errors in js, hilite the current
			$lcc = str_replace('-', '_', $l);
			$hi = ($l == $lccmy) ? ' zai_hi' : '';
			$lcc2 = strlen($l) == 2 ? ' zai_lcc2' : '';
			$lcc5 = strlen($l) == 5 ? ' zai_lcc5' : '';
			$lccg = ' zai_lccg' . substr( $l, 0, 2 );
			$lcclane
			.= 	'<span class="fa zai_fa zai_lccsym' . $lcc2 . $lcc5 . $lccg . '"></span>'
			.	'<a href="javascript:;" class="zai_lcc' . $lcc2 . $lcc5 . $lccg . $hi . '">' . $lcc . '</a>';
			//  textmsg
			$tao .= 'tao.zai.t.' . $lcc . ' = {};' . "\n";
			//  subject
			$tao .= 'tao.zai.s.' . $lcc . ' = {};' . "\n";

			// resolve localized templates and take intented lc for
			foreach($tn as $t1) {

				// save current lc and take the desired
				push_lang($l);

				// resolve
				$tx = replace_macros(get_intltext_template('invite.'.$t1.'.tpl'),
					array(
						'$projectname'		=> t('$Projectname'),
						'$invite_code'		=> $invite_code,
						'$invite_where' 	=> z_root() . '/register',
						'$invite_whereami'	=> $whereami,
						'$invite_whoami'	=> z_root() . '/channel/' . $whoami,
						'$invite_anywhere'	=> z_root() . '/pubsites'
					)
				);

				// a default subject if no associated exists
				$ts=t('Invitation');
				if ( $tplxs[$l][$t1] )
					$ts = replace_macros(get_intltext_template('invite.'.$t1.'.subject.tpl'),
					array(
						'$projectname'		=> t('$Projectname'),
						'$invite_loc'		=> get_config('system','sitename')
					)
				);

				// restore lc to current foreground
				pop_lang();

				// bring to tao as js like it
				$tao .= 'tao.zai.t.' . $lcc . '.' . $t1 . " = '" . rawurlencode($tx) . "';\n";
				$tao .= 'tao.zai.s.' . $lcc . '.' . $t1 . " = '" . rawurlencode($ts) . "';\n";
			}
		}

		// hilite the current defauls just from the beginning
		foreach ($tplx as $t1) {
			$hi = ($t1 == 'casual') ? ' zai_hi' : '';
			$tplin .= $tplsym.'<a href="javascript:;" id="zai-' . $t1
					. '" class="invites'.$hi.'">' . $t1 . '</a>';
		}

		// fill the form for foreground
		$o = replace_macros($tpl, array(
			'$form_security_token' => get_form_security_token("send_invite"),
			'$zai'	=> strtolower(self::MYP),
			'$tao'	=> $tao,
			'$invite' => t('Send invitations'),
			'$ihave'  => 'ZAI0106I, ' . t('Invitations I am using') . ': ' . $ihave . ' / ' . $invuser,
			'$wehave' => 'ZAI0107I, ' . t('Invitations we are using') . ': ' . $wehave . ' / ' . $invmaxau,
			'$n10' => 'ZAI0010I',	'$m10' => t('§ Note, the email(s) sent will be recorded in the system logs'),
			'$n11' => 'ZAI0011I',	'$m11' => t('Enter email addresses, one per line:'),
			'$n12' => 'ZAI0012I',	'$m12' => t('Your message:'),
			'$n13' => 'ZAI0013I',	'$m13' => t('Invite template'),
			'$inv_expire' => $inv_expire,
			'$subject_label' => t('Subject:'),
			'$subject'	=> t('Invitation'),
			'$lcclane'		=> $lcclane,
			'$tplin'	=> $tplin,
			'$standard_message' => '',
			'$personal_message' => '',
			'$personal_pointer' => t('Here you may enter personal notes to the recipient(s)'),
			'$due' => t('Note, the invitation code is valid up to') . ' ' . $dur['due'],
			'$submit' => t('Submit')
		));

		return $o;
	}

	function calcdue($duri=false) {
		// expirations, duration interval
		if ($duri===false)
			$duri = get_config('system','register_expire', '2d');
		if ( preg_match( '/^[0-9]{1,2}[ihdwmy]{1}$/', $duri ) ) {
			$durq = substr($duri, -1);
			$durn = substr($duri, 0, -1);
			$due  = date('Y-m-d H:i:s', strtotime('+' . $durn . ' '
				  . str_replace( array(':i',':h',':d',':w',':m',':y'),
			 				 	 array('minutes', 'hours', 'days', 'weeks', 'months', 'years'),
			 		 (':'.$durq))
				));
			return array( 'durn' => $durn, 'durq' => $durq, 'due' => $due);
		}
		return false;
	}
}

