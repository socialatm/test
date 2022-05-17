<?php
/**
 * @file include/account.php
 * @brief Somme account related functions.
 */

use Zotlabs\Lib\Crypto;

require_once('include/config.php');
require_once('include/network.php');
require_once('include/plugin.php');
require_once('include/text.php');
require_once('include/language.php');
require_once('include/datetime.php');
require_once('include/crypto.php');
require_once('include/channel.php');


function get_account_by_id($account_id) {
	$r = q("select * from account where account_id = %d",
		intval($account_id)
	);
	return (($r) ? $r[0] : false);
}

function check_account_email($email) {

	$email = punify($email);
	$result = array('error' => false, 'message' => '');

	// Caution: empty email isn't counted as an error in this function.
	// Check for empty value separately.

	if(! strlen($email))
		return $result;

	if(! validate_email($email)) {
		$result['message'] = t('The provided email address is not valid');
	}
	elseif(! allowed_email($email)) {
		$result['message'] = t('The provided email domain is not among those allowed on this site');
	}
	else {
		$account = q("select account_email from account where account_email = '%s' limit 1",
			dbesc($email)
		);
		if ($account) {
			$result['message'] = t('The provided email address is already registered at this site');
		}

		$register = q("select reg_did2 from register where reg_vital = 1 and reg_did2 = '%s' and reg_didx = 'e' limit 1",
			dbesc($email)
		);
		if ($register) {
			$result['message'] = t('There is a pending registration for this address - click "Register" to continue verification');
			$result['email_unverified'] = true;
		}
	}

	if($result['message'])
		$result['error'] = true;

	$arr = array('email' => $email, 'result' => $result);
	call_hooks('check_account_email', $arr);

	return $arr['result'];
}

function check_account_password($password) {
	$result = array('error' => false, 'message' => '');

	// The only validation we perform by default is pure Javascript to
	// check minimum length and that both entered passwords match.
	// Use hooked functions to perform complexity requirement checks.

	$arr = array('password' => $password, 'result' => $result);
	call_hooks('check_account_password', $arr);

	return $arr['result'];
}

function check_account_invite($invite_code) {
	$result = array('error' => false, 'message' => '');

	// [hilmar ->
	$using_invites = (get_config('system','invitation_only')
				   || get_config('system','invitation_also'));

	if($using_invites) {

		if(! $invite_code) {

			$result['message']
			.= 'ZAR0510E,' . t('An invitation is required.') . EOL;

		} else {

			// check if invite code exists
			$r = q("SELECT * FROM register WHERE reg_hash = '%s' AND reg_vital = 1 LIMIT 1",
			 	dbesc($invite_code));
			if(! $r) {
				$result['message']
				.= 'ZAR0511E,' . t('Invitation could not be verified.') . EOL;
			}
		}
	}
	// <- hilmar]

	if(strlen($result['message']))
		$result['error'] = true;

	$arr = array('invite_code' => $invite_code, 'result' => $result);
	call_hooks('check_account_invite', $arr);

	return $arr['result'];
}

function check_account_admin($arr) {
	if(is_site_admin())
		return true;
	$admin_email = trim(get_config('system','admin_email'));
	if(strlen($admin_email) && $admin_email === trim($arr['email']))
		return true;
	return false;
}

function account_total() {
	$r = q("select account_id from account where true");
	if(is_array($r))
		return count($r);
	return false;
}

// legacy
function account_store_lowlevel_IS_OBSOLETE($arr) {

    $store = [
        'account_parent'           => ((array_key_exists('account_parent',$arr))           ? $arr['account_parent']           : '0'),
        'account_default_channel'  => ((array_key_exists('account_default_channel',$arr))  ? $arr['account_default_channel']  : '0'),
        'account_salt'             => ((array_key_exists('account_salt',$arr))             ? $arr['account_salt']             : ''),
        'account_password'         => ((array_key_exists('account_password',$arr))         ? $arr['account_password']         : ''),
        'account_email'            => ((array_key_exists('account_email',$arr))            ? $arr['account_email']            : ''),
        'account_external'         => ((array_key_exists('account_external',$arr))         ? $arr['account_external']         : ''),
        'account_language'         => ((array_key_exists('account_language',$arr))         ? $arr['account_language']         : 'en'),
        'account_created'          => ((array_key_exists('account_created',$arr))          ? $arr['account_created']          : '0001-01-01 00:00:00'),
        'account_lastlog'          => ((array_key_exists('account_lastlog',$arr))          ? $arr['account_lastlog']          : '0001-01-01 00:00:00'),
        'account_flags'            => ((array_key_exists('account_flags',$arr))            ? $arr['account_flags']            : '0'),
        'account_roles'            => ((array_key_exists('account_roles',$arr))            ? $arr['account_roles']            : '0'),
        'account_reset'            => ((array_key_exists('account_reset',$arr))            ? $arr['account_reset']            : ''),
        'account_expires'          => ((array_key_exists('account_expires',$arr))          ? $arr['account_expires']          : '0001-01-01 00:00:00'),
        'account_expire_notified'  => ((array_key_exists('account_expire_notified',$arr))  ? $arr['account_expire_notified']  : '0001-01-01 00:00:00'),
        'account_service_class'    => ((array_key_exists('account_service_class',$arr))    ? $arr['account_service_class']    : ''),
        'account_level'            => '5',
        'account_password_changed' => ((array_key_exists('account_password_changed',$arr)) ? $arr['account_password_changed'] : '0001-01-01 00:00:00')
	];

	// never ever is this a create table but a pdo insert into account
	// strange function placement in text.php (obscure by design :-)
	return create_table_from_array('account',$store);
	// the TODO may be to adjust others using create_table_from_array():
	// channel.php
	// connections.php
	// event.php
	// hubloc.php
	// import.php
}



// legacy
function create_account_IS_OBSOLETE($arr) {

	// Required: { email, password }

	$result = array('success' => false, 'email' => '', 'password' => '', 'message' => '');

	$invite_code = ((x($arr,'invite_code'))   ? notags(trim($arr['invite_code']))  : '');
	$email       = ((x($arr,'email'))         ? notags(punify(trim($arr['email']))) : '');
	$password    = ((x($arr,'password'))      ? trim($arr['password'])             : '');
	$parent      = ((x($arr,'parent'))        ? intval($arr['parent'])             : 0 );
	$flags       = ((x($arr,'account_flags')) ? intval($arr['account_flags'])      : ACCOUNT_OK);
	$roles       = ((x($arr,'account_roles')) ? intval($arr['account_roles'])      : 0 );
	$expires     = ((x($arr,'expires'))       ? intval($arr['expires'])            : NULL_DATE);

	$default_service_class = get_config('system','default_service_class');

	if($default_service_class === false)
		$default_service_class = '';

	if((! x($email)) || (! x($password))) {
		$result['message'] = t('Please enter the required information.');
		return $result;
	}

	// prevent form hackery

	if($roles & ACCOUNT_ROLE_ADMIN) {
		$admin_result = check_account_admin($arr);
		if(! $admin_result) {
			$roles = 0;
		}
	}

	// allow the admin_email account to be admin, but only if it's the first account.

	$c = account_total();
	if (($c === 0) && (check_account_admin($arr)))
		$roles |= ACCOUNT_ROLE_ADMIN;

	// Ensure that there is a host keypair.

	if ((! get_config('system', 'pubkey')) && (! get_config('system', 'prvkey'))) {
		$hostkey = Crypto::new_keypair(4096);
		set_config('system', 'pubkey', $hostkey['pubkey']);
		set_config('system', 'prvkey', $hostkey['prvkey']);
	}

	$invite_result = check_account_invite($invite_code);
	if($invite_result['error']) {
		$result['message'] = $invite_result['message'];
		return $result;
	}

	$email_result = check_account_email($email);

	if($email_result['error']) {
		$result['message'] = $email_result['message'];
		return $result;
	}

	$password_result = check_account_password($password);

	if($password_result['error']) {
		$result['message'] = $password_result['message'];
		return $result;
	}

	$salt = random_string(32);
	$password_encoded = hash('whirlpool', $salt . $password);

	$r = account_store_lowlevel(
		[
			'account_parent'        => intval($parent),
			'account_salt'          => $salt,
			'account_password'      => $password_encoded,
			'account_email'         => $email,
			'account_language'      => get_best_language(),
			'account_created'       => datetime_convert(),
			'account_flags'         => intval($flags),
			'account_roles'         => intval($roles),
			'account_level'         => 5,
			'account_expires'       => $expires,
			'account_service_class' => $default_service_class
		]
	);
	if(! $r) {
		logger('create_account: DB INSERT failed.');
		$result['message'] = t('Failed to store account information.');
		return($result);
	}

	$r = q("select * from account where account_email = '%s' and account_password = '%s' limit 1",
		dbesc($email),
		dbesc($password_encoded)
	);
	if($r && count($r)) {
		$result['account'] = $r[0];
	}
	else {
		logger('create_account: could not retrieve newly created account');
	}

	// Set the parent record to the current record_id if no parent was provided

	if(! $parent) {
		$r = q("update account set account_parent = %d where account_id = %d",
			intval($result['account']['account_id']),
			intval($result['account']['account_id'])
		);
		if(! $r) {
			logger('create_account: failed to set parent');
		}
		$result['account']['parent'] = $result['account']['account_id'];
	}

	$result['success']  = true;
	$result['email']    = $email;
	$result['password'] = $password;

	call_hooks('register_account',$result);

	return $result;
}

/**
 * create_account_from_register
 * @author hilmar runge
 * @since  2020-02-20
 *
 * Account creation only happens via table register.
 * This function creates the account when all conditions are solved.
 *
 */
function create_account_from_register($arr) {

	$result = array('success' => false, 'message' => 'rid:' . $arr['reg_id']);
	$now = datetime_convert();

	// reg_flags 0x0020 = REGISTER_AGREED = register request verified by user @ regate
	$register = q("SELECT * FROM register WHERE reg_id = %d AND (reg_flags & 31) = 0 "
				. " AND reg_startup < '%s' AND reg_expires > '%s' ",
				intval($arr['reg_id']),
				dbesc($now),
				dbesc($now)
	);

	if ( ! $register ) return $result;

	// account
	$expires = NULL_DATE;

	$default_service_class = get_config('system','default_service_class');
	if($default_service_class === false)
		$default_service_class = '';

	$roles = 0;
	// prevent form hackery
	if($roles & ACCOUNT_ROLE_ADMIN) {
		$admin_result = check_account_admin($arr);
		if(! $admin_result) {
			$roles = 0;
		}
	}

	// any accounts available ?
	$isa = q("SELECT COUNT(*) AS isa FROM account");
	if ($isa && $isa[0]['isa'] == 0) {
		$roles = ACCOUNT_ROLE_ADMIN;
	}

	$password_parts = explode(',', $register[0]['reg_pass']);
	$salt = $password_parts[0];
	$password_encoded = $password_parts[1];

	$ri = q(
		"INSERT INTO account ("
		. " account_parent, account_salt, account_password, account_email, "
		. " account_language, account_created, account_flags, account_roles, account_level, "
		. " account_expires, account_service_class) VALUES( "
		. " %d, '%s', '%s', '%s', '%s', '%s', %d, %d, %d, '%s', '%s' ) ",
			intval($parent),
			dbesc($salt),
			dbesc($password_encoded),
			dbesc($register[0]['reg_did2']),
			dbesc($register[0]['reg_lang']),
			dbesc($now),
			intval($register[0]['reg_flags'] & 31),			// off REGISTER_AGREE at ACCOUNT
			intval($roles),
			intval(5),
			dbesc($expires),
			dbesc($default_service_class)
	);

	if(! $ri) {
		logger('create_account: DB INSERT failed.');
		$result['message'] = 'ZAR ' . t('Failed to store account information.');
		return($result);
	}

	$r = q("SELECT * FROM account WHERE account_email = '%s' AND account_password = '%s' LIMIT 1",
		dbesc($register[0]['reg_did2']),
		dbesc($password_encoded)
	);
	if($r && count($r)) {
		$result['account'] = $r[0];
	}
	else {
		logger('create_account: could not retrieve newly created account');
	}

	// Set the parent record to the current record_id if no parent was provided

	if(! $parent) {
		$r = q("UPDATE account SET account_parent = %d WHERE account_id = %d",
			intval($result['account']['account_id']),
			intval($result['account']['account_id'])
		);
		if(! $r) {
			logger('create_account: failed to set parent');
		}
		$result['account']['parent'] = $result['account']['account_id'];
	}

	$result['success']  = true;

	//call_hooks('register_account',$result);

	return $result;
}

/**
 *	@brief as far to see, email validation for register account verification
 *	@param array (account)
 *	@param array ('resend' => true, 'email' = > email)
 *
 */

function verify_email_address($arr) {

		// $hash = random_string(24);

		// [hilmar ->
		$reg = q("SELECT * FROM register WHERE reg_vital = 1 AND reg_email = 's%' ",
				dbesc($arr['email'])
			);
		if ( ! $reg)
			return false;

	push_lang(($reg[0]['email']) ? $reg[0]['email'] : 'en');

	$email_msg = replace_macros(get_intltext_template('register_verify_member.tpl'),
		[
			'$sitename' => get_config('system','sitename'),
			'$siteurl'  => z_root(),
			'$email'    => $arr['email'],
			'$uid'      => 1,
			'$hash'     => $hash,
			'$details'  => ''
	 	]
	);

	$res = z_mail(
		[
		'toEmail' => $arr['email'],
		'messageSubject' => sprintf( t('Registration confirmation for %s'), get_config('system','sitename')),
		'textVersion' => $email_msg,
		]
	);

	pop_lang();

	if(! $res)
		logger('send_reg_approval_email: failed to account_id: ' . $arr['account']['account_id']);

	return $res;
}

function verify_email_addressNOP($arr) {

	if(array_key_exists('resend',$arr)) {
		$a = q("select * from account where account_email = '%s' limit 1",
		    dbesc($arr['email'])
		);
		if(! ($a && ($a[0]['account_flags'] & ACCOUNT_UNVERIFIED))) {
			return false;
		}
		$account = $a[0];
		// [hilmar ->
		$v = q("SELECT * FROM register WHERE reg_uid = %d AND reg_vital = 1 "
									. " AND reg_pass = 'verify' LIMIT 1",
			intval($account['account_id'])
		);
		// <- hilmar]
		if($v) {
			$hash = $v[0]['reg_hash'];
		}
		else {
			return false;
		}
	}
	else {
		$hash = random_string(24);

		// [hilmar ->
		q("INSERT INTO register ( reg_hash, reg_created, reg_uid, reg_pass, reg_lang, reg_stuff ) "
			." VALUES ( '%s', '%s', %d, '%s', '%s', '' ) ",
			dbesc($hash),
			dbesc(datetime_convert()),
			intval($arr['account']['account_id']),
			dbesc('verify'),
			dbesc($arr['account']['account_language'])
		);
		// <- hilmar]
		$account = $arr['account'];
	}

	push_lang(($account['account_language']) ? $account['account_language'] : 'en');

	$email_msg = replace_macros(get_intltext_template('register_verify_member.tpl'),
		[
			'$sitename' => get_config('system','sitename'),
			'$siteurl'  => z_root(),
			'$email'    => $arr['email'],
			'$uid'      => $account['account_id'],
			'$hash'     => $hash,
			'$details'  => ''
	 	]
	);

	$res = z_mail(
		[
		'toEmail' => $arr['email'],
		'messageSubject' => sprintf( t('Registration confirmation for %s'), get_config('system','sitename')),
		'textVersion' => $email_msg,
		]
	);

	pop_lang();

	if(! $res)
		logger('send_reg_approval_email: failed to account_id: ' . $arr['account']['account_id']);

	return $res;
}




function send_reg_approval_email($arr) {

	$r = q("select * from account where (account_roles & %d) >= 4096",
		 intval(ACCOUNT_ROLE_ADMIN)
	);
	if(! ($r && count($r)))
		return false;

	$admins = array();

	foreach($r as $rr) {
		if(strlen($rr['account_email'])) {
			$admins[] = array('email' => $rr['account_email'], 'lang' => $rr['account_lang']);
		}
	}

	if(! count($admins))
		return false;

	$hash = random_string();

	// [hilmar ->
	// code before fetches the $admins as recipients for the approval request mail
	// $arr has a user (self registered) account
	// ... $arr['email'] ???
	// ... reg expiration ?
	$r = q("INSERT INTO register ( reg_hash, reg_email, reg_created, reg_uid, reg_pass, reg_lang, reg_stuff )"
		. " VALUES ( '%s', '%s', '%s', %d, '', '%s', '' ) ",
		dbesc($hash),
		dbesc($arr['account']['account_email']),
		dbesc(datetime_convert()),
		intval($arr['account']['account_id']),
		dbesc($arr['account']['account_language'])
	);

	$ip = $_SERVER['REMOTE_ADDR'];

	$details = (($ip) ? $ip . ' [' . gethostbyaddr($ip) . ']' : '[unknown or stealth IP]');

	$delivered = 0;

	foreach($admins as $admin) {
		if(strlen($admin['lang']))
			push_lang($admin['lang']);
		else
			push_lang('en');

		$email_msg = replace_macros(get_intltext_template('register_verify_eml.tpl'), array(
			'$sitename' => get_config('system','sitename'),
			'$siteurl'  =>  z_root(),
			'$email'    => $arr['email'],
			'$uid'      => $arr['account']['account_id'],
			'$hash'     => $hash,
			'$details'  => $details
		 ));

		$res = z_mail(
			[
			'toEmail' => $admin['email'],
			'messageSubject' => sprintf( t('Registration request at %s'), get_config('system','sitename')),
			'textVersion' => $email_msg,
			]
		);

		if($res)
			$delivered ++;
		else
			logger('send_reg_approval_email: failed to ' . $admin['email'] . 'account_id: ' . $arr['account']['account_id']);

		pop_lang();
	}

	return($delivered ? true : false);
}

function send_register_success_email($email,$password) {

	$email_msg = replace_macros(get_intltext_template('register_open_eml.tpl'), array(
		'$sitename' => get_config('system','sitename'),
		'$siteurl' =>  z_root(),
		'$email'    => $email,
		'$password' => t('your registration password'),
	));

	$res = z_mail(
		[
			'toEmail' => $email,
			'messageSubject' => sprintf( t('Registration details for %s'), get_config('system','sitename')),
			'textVersion' => $email_msg,
		]
	);

	return($res ? true : false);
}

/**
 * @brief Allows a user registration.
 *
 * @param string $hash
 * @return array|boolean
 */
function account_allow($hash) {

	$ret = array('success' => false);

	$register = q("SELECT * FROM register WHERE reg_hash = '%s' LIMIT 1",
		dbesc($hash)
	);

	if(! $register)
		return $ret;

	$account = q("SELECT * FROM account WHERE account_id = %d LIMIT 1",
		intval($register[0]['reg_uid'])
	);

	// a register entry without account assigned to
	if(! $account)
		return $ret;

	// [hilmar ->

	q("START TRANSACTION");
	//q("DELETE FROM register WHERE reg_hash = '%s'",
	//	dbesc($register[0]['reg_hash'])
	//);
	$r1 = q("UPDATE register SET reg_vital = 0 WHERE reg_hash = '%s'",
		dbesc($register[0]['reg_hash'])
	);

	/* instead of ...

	// unblock
	q("UPDATE account SET    account_flags = (account_flags & ~%d) "
		.			" WHERE (account_flags & %d)>0 AND account_id = %d",
		intval(ACCOUNT_BLOCKED),
		intval(ACCOUNT_BLOCKED),
		intval($register[0]['reg_uid'])
	);

	// unpend
	q("UPDATE account SET    account_flags = (account_flags & ~%d) "
		. 			" WHERE (account_flags & %d)>0 AND account_id = %d",
		intval(ACCOUNT_PENDING),
		intval(ACCOUNT_PENDING),
		intval($register[0]['reg_uid'])
	);

	*/
	// together unblock and unpend
	$r2 = q("UPDATE account SET account_flags = %d WHERE account_id = %d",
		intval($account['account_flags']
			&= $account['account_flags'] ^ (ACCOUNT_BLOCKED | ACCOUNT_PENDING)),
		intval($register[0]['reg_uid'])
	);

	if($r1 && $r2) {
		q("COMMIT");

		// <- hilmar]

		push_lang($register[0]['reg_lang']);

		$email_tpl = get_intltext_template("register_open_eml.tpl");
		$email_msg = replace_macros($email_tpl, array(
				'$sitename' => get_config('system','sitename'),
				'$siteurl' =>  z_root(),
				'$username' => $account[0]['account_email'],
				'$email' => $account[0]['account_email'],
				'$password' => '',
				'$uid' => $account[0]['account_id']
		));

		$res = z_mail(
			[
			'toEmail' => $account[0]['account_email'],
			'messageSubject' => sprintf( t('Registration details for %s'), get_config('system','sitename')),
			'textVersion' => $email_msg,
			]
		);

		pop_lang();

		if(get_config('system', 'auto_channel_create', 1))
			auto_channel_create($register[0]['uid']);

		if ($res) {
			info( t('Account approved.') . EOL );
			return true;
		}

	// [hilmar ->
	} else {
		q("ROLLBACK");
	}
	// <- hilmar]
}


/**
 * @brief Denies an account registration.
 *
 * This does not have to go through user_remove() and save the nickname
 * permanently against re-registration, as the person was not yet
 * allowed to have friends on this system
 *
 * @param string $hash
 * @return boolean
 */

function account_deny($hash) {

	// [hilmar->
	$register = q("SELECT * FROM register WHERE reg_hash = '%s' AND reg_vital = 1 LIMIT 1",
		dbesc($hash)
	);
	//  <-hilmar]

	if(! count($register))
		return false;

	$account = q("SELECT account_id, account_email FROM account WHERE account_id = %d LIMIT 1",
		intval($register[0]['reg_uid'])
	);

	if(! $account)
		return false;

	// [hilmar ->
	q("START TRANSACTION");

	$r1 = q("DELETE FROM account WHERE account_id = %d",
		intval($register[0]['reg_uid'])
	);
	// q("DELETE FROM register WHERE reg_id = %d",
	//	dbesc($register[0]['reg_id'])
	//);
	$r2 = q("UPDATE register SET reg_vital = 0 WHERE reg_id = %d AND reg_vital = 1",
		dbesc($register[0]['reg_id'])
	);

	if($r1 && $r2) {
		q("COMMIT");
		notice( 'ZAR0512I,' . sprintf( t('Registration revoked for %s'),
							$account[0]['account_email']) . EOL);
		return true;

	} else {

		q("ROLLBACK");
		notice( 'ZAR0513F,' . sprintf( t('Could not revoke registration for %s'),
							$account[0]['account_email']) . EOL);
		return false;
	}
	// <- hilmar]
}

/**
 * called from Regver to allow/revoke an account
 * Use case is under REGISTER_OPEN with APPROVAL
 * Ref Regver, Email_validation, Email_resend
 * ZAR052+
 */
function account_approve($hash) {

	$ret = false;

	// Note: when the password in the register table is 'verify', the uid actually contains the account_id
	// hmm

	$register = q("SELECT * FROM register WHERE reg_hash = '%s' and reg_pass = 'verify' LIMIT 1",
		dbesc($hash)
	);

	if(! $register)
		return $ret;

	$account = q("SELECT * FROM account WHERE account_id = %d LIMIT 1",
		intval($register[0]['reg_uid'])
	);

	if(! $account)
		return $ret;

	// tr ?

	q("DELETE FROM register WHERE reg_hash = '%s' and reg_pass = 'verify'",
		dbesc($register[0]['reg_hash'])
	);

	q("update account set account_flags = (account_flags & ~%d) where (account_flags & %d)>0 and account_id = %d",
		intval(ACCOUNT_BLOCKED),
		intval(ACCOUNT_BLOCKED),
		intval($register[0]['reg_uid'])
	);

	q("update account set account_flags = (account_flags & ~%d) where (account_flags & %d)>0 and account_id = %d",
		intval(ACCOUNT_PENDING),
		intval(ACCOUNT_PENDING),
		intval($register[0]['reg_uid'])
	);

	q("update account set account_flags = (account_flags & ~%d) where (account_flags & %d)>0 and account_id = %d",
		intval(ACCOUNT_UNVERIFIED),
		intval(ACCOUNT_UNVERIFIED),
		intval($register[0]['reg_uid'])
	);

	/*
	// together unblock unpend and verified
	q("UPDATE account SET account_flags = %d WHERE account_id = %d",
		intval($account['account_flags']
			&= $account['account_flags']
			^ (ACCOUNT_BLOCKED | ACCOUNT_PENDING | ACCOUNT_UNVERIFIED)),
		intval($register[0]['reg_uid'])
	);
	*/


	// get a fresh copy after we've modified it.

	$account = q("SELECT * FROM account WHERE account_id = %d LIMIT 1",
		intval($register[0]['reg_uid'])
	);

	if(! $account)
		return $ret;

	if(get_config('system','auto_channel_create'))
		auto_channel_create($register[0]['reg_uid']);
	else {
		$_SESSION['login_return_url'] = 'new_channel';
		authenticate_success($account[0],null,true,true,false,true);
	}

	return true;
}


function verify_register_scheme() {

	$dbc = db_columns('register');
	if ($dbc) {

		if ($dbc[0]=='id') {
			// v1 format
			dbq("START TRANSACTION");

			if(ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
				$r1 = dbq("ALTER TABLE register RENAME TO register100;");

				$r2 = dbq("CREATE TABLE register ("
					. "reg_id      serial  NOT NULL,"
					. "reg_vital   int     DEFAULT 1 NOT NULL,"
					. "reg_flags   bigint  DEFAULT 0 NOT NULL,"
					. "reg_didx    char(1) DEFAULT '' NOT NULL,"
					. "reg_did2    text    DEFAULT '' NOT NULL,"
					. "reg_hash    text    DEFAULT '' NOT NULL,"
					. "reg_email   text    DEFAULT '' NOT NULL,"
					. "reg_created timestamp NOT NULL DEFAULT '0001-01-01 00:00:00',"
					. "reg_startup timestamp NOT NULL DEFAULT '0001-01-01 00:00:00',"
					. "reg_expires timestamp NOT NULL DEFAULT '0001-01-01 00:00:00',"
					. "reg_byc     bigint  DEFAULT 0 NOT NULL,"
					. "reg_uid     bigint  DEFAULT 0 NOT NULL,"
					. "reg_atip    text    DEFAULT '' NOT NULL,"
					. "reg_pass    text    DEFAULT '' NOT NULL,"
					. "reg_lang    varchar(16) DEFAULT '' NOT NULL,"
					. "reg_stuff   text    NOT NULL,"
					. "PRIMARY KEY (reg_id) );"
				);
				$r0 = dbq("CREATE INDEX ix_reg_vital ON register (reg_vital);");
				$r0 = dbq("CREATE INDEX ix_reg_flags ON register (reg_flags);");
				$r0 = dbq("CREATE INDEX ix_reg_didx ON register (reg_didx);");
				$r0 = dbq("CREATE INDEX ix_reg_did2 ON register (reg_did2);");
				$r0 = dbq("CREATE INDEX ix_reg_hash ON register (reg_hash);");
				$r0 = dbq("CREATE INDEX ix_reg_email ON register (reg_email);");
				$r0 = dbq("CREATE INDEX ix_reg_created ON register (reg_created);");
				$r0 = dbq("CREATE INDEX ix_reg_startup ON register (reg_startup);");
				$r0 = dbq("CREATE INDEX ix_reg_expires ON register (reg_expires);");
				$r0 = dbq("CREATE INDEX ix_reg_byc ON register (reg_byc);");
				$r0 = dbq("CREATE INDEX ix_reg_uid ON register (reg_uid);");
				$r0 = dbq("CREATE INDEX ix_reg_atip ON register (reg_atip);");

				$r3 = dbq("INSERT INTO register (reg_id, reg_hash, reg_created, reg_uid, reg_pass, reg_lang, reg_stuff) "
					. "SELECT id, hash, created, uid, password, lang, '' FROM register100;");

				$r4 = dbq("DROP TABLE register100");

			}
			else {
				$r1 = dbq("RENAME TABLE register TO register100;");

				$r2 = dbq("CREATE TABLE IF NOT EXISTS register ("
	 				. "reg_id 		int(10) UNSIGNED 	NOT NULL AUTO_INCREMENT,"
  					. "reg_vital	int(10) UNSIGNED 	NOT NULL DEFAULT 1,"
  					. "reg_flags	int(10) UNSIGNED 	NOT NULL DEFAULT 0,"
	  				. "reg_didx 	char(1) 			NOT NULL DEFAULT '',"
  					. "reg_did2 	char(191) 			NOT NULL DEFAULT '',"
 					. "reg_hash 	char(191) 			NOT NULL DEFAULT '',"
	  				. "reg_email 	char(191) 			NOT NULL DEFAULT '',"
  					. "reg_created 	datetime 			NOT NULL DEFAULT '0001-01-01 00:00:00',"
  					. "reg_startup 	datetime 			NOT NULL DEFAULT '0001-01-01 00:00:00',"
 	 				. "reg_expires 	datetime 			NOT NULL DEFAULT '0001-01-01 00:00:00',"
  					. "reg_byc 		int(10) UNSIGNED 	NOT NULL DEFAULT 0 ,"
  					. "reg_uid 		int(10) UNSIGNED 	NOT NULL DEFAULT 0 ,"
	   				. "reg_atip     char(191) 			NOT NULL DEFAULT '',"
					. "reg_pass 	char(191) 			NOT NULL DEFAULT '',"
  					. "reg_lang		char(16) 			NOT NULL DEFAULT '',"
	  				. "reg_stuff 	text				NOT NULL,"
  					. "PRIMARY KEY (reg_id),"
  					. "KEY ix_reg_hash	(reg_hash),"
	  				. "KEY ix_reg_vital	(reg_vital),"
  					. "KEY ix_reg_flags	(reg_flags),"
  					. "KEY ix_reg_didx	(reg_didx),"
	  				. "KEY ix_reg_did2	(reg_did2),"
  					. "KEY ix_reg_email (reg_email),"
  					. "KEY ix_reg_created (reg_created),"
	  				. "KEY ix_reg_startup (reg_startup),"
  					. "KEY ix_reg_expires (reg_expires),"
  					. "KEY ix_reg_byc 	(reg_byc),"
	  				. "KEY ix_reg_uid 	(reg_uid),"
 					. "KEY ix_reg_atip 	(reg_atip)"
					. ") ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;"
				);

				$r3 = dbq("INSERT INTO register (reg_id, reg_hash, reg_created, reg_uid, reg_pass, reg_lang, reg_stuff) "
					. "SELECT id, hash, created, uid, password, lang, '' FROM register100;");

				$r4 = dbq("DROP TABLE register100");
			}

			// $r = ($r1 && $r2 && $r3 && $r4);
			// the only important
			$r = $r2;

			if($r) {
				dbq("COMMIT");
				return UPDATE_SUCCESS;
			}

			dbq("ROLLBACK");
			return UPDATE_FAILED;
		}
		elseif ( count($dbc) != 16 ) {
			// ffu
			// fields in v2.0.0 = 16
		}
	}
}


/**
 * @brief Checks for accounts that have past their expiration date.
 *
 * If the account has a service class which is not the site default,
 * the service class is reset to the site default and expiration reset to never.
 * If the account has no service class it is expired and subsequently disabled.
 * called from include/poller.php as a scheduled task.
 *
 * Reclaiming resources which are no longer within the service class limits is
 * not the job of this function, but this can be implemented by plugin if desired.
 * Default behaviour is to stop allowing additional resources to be consumed.
 */
function downgrade_accounts() {

	$r = q("select * from account where not ( account_flags & %d ) > 0
		and account_expires > '%s'
		and account_expires < %s ",
		intval(ACCOUNT_EXPIRED),
		dbesc(NULL_DATE),
		db_getfunc('UTC_TIMESTAMP')
	);

	if(! $r)
		return;

	$basic = get_config('system','default_service_class');

	foreach($r as $rr) {
		if(($basic) && ($rr['account_service_class']) && ($rr['account_service_class'] != $basic)) {
			q("UPDATE account set account_service_class = '%s', account_expires = '%s'
				where account_id = %d",
				dbesc($basic),
				dbesc(NULL_DATE),
				intval($rr['account_id'])
			);
			$ret = array('account' => $rr);
			call_hooks('account_downgrade', $ret );
			logger('downgrade_accounts: Account id ' . $rr['account_id'] . ' downgraded.');
		}
		else {
			q("UPDATE account SET account_flags = (account_flags | %d) where account_id = %d",
				intval(ACCOUNT_EXPIRED),
				intval($rr['account_id'])
			);
			$ret = array('account' => $rr);
			call_hooks('account_downgrade', $ret);
			logger('downgrade_accounts: Account id ' . $rr['account_id'] . ' expired.');
		}
	}
}


/**
 * @brief Check service_class restrictions.
 *
 * If there are no service_classes defined, everything is allowed.
 * If $usage is supplied, we check against a maximum count and return true if
 * the current usage is less than the subscriber plan allows. Otherwise we
 * return boolean true or false if the property is allowed (or not) in this
 * subscriber plan. An unset property for this service plan means the property
 * is allowed, so it is only necessary to provide negative properties for each
 * plan, or what the subscriber is not allowed to do.
 *
 * Like account_service_class_allows() but queries directly by account rather
 * than channel. Service classes are set for accounts, so we look up the
 * account for the channel and fetch the service class restrictions of the
 * account.
 *
 * @see account_service_class_allows() if you have a channel_id already
 * @see service_class_fetch()
 *
 * @param int $uid The channel_id to check
 * @param string $property The service class property to check for
 * @param string|boolean $usage (optional) The value to check against
 * @return boolean
 */
function service_class_allows($uid, $property, $usage = false) {
	$limit = service_class_fetch($uid, $property);

	if($limit === false)
		return true; // No service class set => everything is allowed

	$limit = engr_units_to_bytes($limit);
	if($limit == 0)
	    return true; // 0 means no limits
	if($usage === false) {
		// We use negative values for not allowed properties in a subscriber plan
		return ((x($limit)) ? (bool) $limit : true);
	} else {
		return (((intval($usage)) < intval($limit)) ? true : false);
	}
}

/**
 * @brief Check service class restrictions by account.
 *
 * If there are no service_classes defined, everything is allowed.
 * If $usage is supplied, we check against a maximum count and return true if
 * the current usage is less than the subscriber plan allows. Otherwise we
 * return boolean true or false if the property is allowed (or not) in this
 * subscriber plan. An unset property for this service plan means the property
 * is allowed, so it is only necessary to provide negative properties for each
 * plan, or what the subscriber is not allowed to do.
 *
 * Like service_class_allows() but queries directly by account rather than channel.
 *
 * @see service_class_allows() if you have a channel_id instead of an account_id
 * @see account_service_class_fetch()
 *
 * @param int $aid The account_id to check
 * @param string $property The service class property to check for
 * @param int|boolean $usage (optional) The value to check against
 * @return boolean
 */
function account_service_class_allows($aid, $property, $usage = false) {

	$limit = account_service_class_fetch($aid, $property);

	if($limit === false)
		return true; // No service class is set => everything is allowed

	$limit = engr_units_to_bytes($limit);

	if($usage === false) {
		// We use negative values for not allowed properties in a subscriber plan
		return ((x($limit)) ? (bool) $limit : true);
	} else {
		return (((intval($usage)) < intval($limit)) ? true : false);
	}
}

/**
 * @brief Queries a service class value for a channel and property.
 *
 * Service classes are set for accounts, so look up the account for this channel
 * and fetch the service classe of the account.
 *
 * If no service class is available it returns false and everything should be
 * allowed.
 *
 * @see account_service_class_fetch()
 *
 * @param int $uid The channel_id to query
 * @param string $property The service property name to check for
 * @return boolean|int
 *
 * @todo Should we merge this with account_service_class_fetch()?
 */
function service_class_fetch($uid, $property) {


	if($uid == local_channel()) {
		$service_class = App::$account['account_service_class'];
	}
	else {
		$r = q("select account_service_class as service_class
				from channel c, account a
				where c.channel_account_id=a.account_id and c.channel_id= %d limit 1",
				intval($uid)
		);
		if($r !== false and count($r)) {
			$service_class = $r[0]['service_class'];
		}
	}
	if(! x($service_class))
		return false; // everything is allowed

	$arr = get_config('service_class', $service_class);

	if(! is_array($arr) || (! count($arr)))
		return false;

	return((array_key_exists($property, $arr) && $arr[$property] != 0) ? $arr[$property] : false);
}

/**
 * @brief Queries a service class value for an account and property.
 *
 * Like service_class_fetch() but queries by account rather than channel.
 *
 * @see service_class_fetch() if you have channel_id.
 * @see account_service_class_allows()
 *
 * @param int $aid The account_id to query
 * @param string $property The service property name to check for
 * @return boolean|int
 */
function account_service_class_fetch($aid, $property) {

	$service_class = null;

	$r = q("select account_service_class as service_class from account where account_id = %d limit 1",
		intval($aid)
	);
	if($r !== false && count($r)) {
		$service_class = $r[0]['service_class'];
	}

	if(! isset($service_class))
		return false; // everything is allowed

	$arr = get_config('service_class', $service_class);

	if(! is_array($arr) || (! count($arr)))
		return false;

	return((array_key_exists($property, $arr)) ? $arr[$property] : false);
}


function upgrade_link($bbcode = false) {
	$l = get_config('service_class', 'upgrade_link');
	if(! $l)
		return '';
	if($bbcode)
		$t = sprintf('[zrl=%s]' . t('Click here to upgrade.') . '[/zrl]', $l);
	else
		$t = sprintf('<a href="%s">' . t('Click here to upgrade.') . '</div>', $l);
	return $t;
}

function upgrade_message($bbcode = false) {
	$x = upgrade_link($bbcode);
	return t('This action exceeds the limits set by your subscription plan.') . (($x) ? ' ' . $x : '') ;
}

function upgrade_bool_message($bbcode = false) {
	$x = upgrade_link($bbcode);
	return t('This action is not available under your subscription plan.') . (($x) ? ' ' . $x : '') ;
}


function get_account_techlevel($account_id = 0) {

	return (5);

}

function zar_log($msg='') {

	if(get_config('system', 'register_logfile', 0)) {
		file_put_contents('./zar.log',
			date('Y-m-d_H:i:s') . ' ' . $msg . ', ip: § ' . $_SERVER['REMOTE_ADDR'] . ' §' . "\n", FILE_APPEND);
	}
	else {
		logger('zar_log: ' . $msg . ', ip: § ' . $_SERVER['REMOTE_ADDR'] . ' §');
	}

	return;
}

function zar_reg_mail($reonar=false) {
	if ($reonar) {
		$zem = z_mail(
			[
			'toEmail'        => $reonar['to'],
			'fromName'       => ' ',
			'fromEmail'      => $reonar['from'],
			'messageSubject' => $reonar['subject'],
			'textVersion'    => $reonar['txttemplate'],
			]
		);
		return $zem;
	}
}

/**
 * ckeck current day and time against register duties
 *
 * @author Hilmar Runge
 * @since  2020-02-25
 * @param  the current date and time is taken as default
 * @return  ['isduty'] true/false
 *			['nowfmt'] the textmsg about the current state
 *			['atform'] the disabled html attribute for form input fields
 *
 */
function zar_register_dutystate( $now=NULL, $day=NULL ) {

	is_null($now) ? $now = date('Hi') : '';
	is_null($day) ? $day = date('N') : '';

	$isduty = zarIsDuty($day, $now, 'isOpen');

	if ( $isduty === false ) {
		return array( 'isduty' => $isduty, 'nowfmt' => '', 'atform' => '' );
	}

	$dutyis = $isduty ? t('open') : t('closed');
	$atform = $isduty ? '' : 'disabled';
	$utc_now = datetime_convert(date_default_timezone_get(), 'UTC', $now, 'c');

	$nowfmt = '';

	if (!$isduty) {
		$nowfmt	 = t('Registration is currently');
		$nowfmt .= ' (<span data-utc="' . $utc_now . '" class="register_date">' . $utc_now . '</span>) ';
		$nowfmt .= $dutyis . ',<br>';

		$pernext = zarIsDuty($day, $now, 'nextOpen');
		$week_days = ['','monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
		$utc_next_open = datetime_convert(date_default_timezone_get(), 'UTC', $week_days[$pernext[0]] . ' ' . $pernext[1], 'c');

		if (is_array($pernext)) {
			$nowfmt .= t('please come back');
			$nowfmt .= ' <span data-utc="' . $utc_next_open . '" class="register_date">' . $utc_next_open . '</span>.';
		}
	}
	return array( 'isduty' => $isduty, 'nowfmt' => $nowfmt, 'atform' => $atform);

}

function get_pending_accounts($get_all = false) {

	$sql_extra = " AND (reg_flags & " . ACCOUNT_UNVERIFIED . ") = 0 ";

	if($get_all)
		$sql_extra = '';

	$r = q("SELECT reg_did2, reg_created, reg_startup, reg_expires, reg_email, reg_atip, reg_hash, reg_id, reg_flags, reg_stuff
		FROM register WHERE reg_vital = 1 $sql_extra AND (reg_flags & %d) >= 0",
		intval(ACCOUNT_PENDING)
	);

	return $r;
}

function remove_expired_registrations() {
	q("DELETE FROM register WHERE (reg_expires < '%s' OR reg_expires = '%s') AND (reg_flags & %d) > 0",
		dbesc(datetime_convert()),
		dbesc(NULL_DATE),
		dbesc(ACCOUNT_UNVERIFIED)
	);
}
