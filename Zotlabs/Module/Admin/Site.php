<?php

namespace Zotlabs\Module\Admin;


class Site {


	/**
	 * @brief POST handler for Admin Site Page.
	 *
	 */
	function post(){
		// [hilmar->
		$this->isajax = is_ajax();
		$this->eol = $this->isajax ? "\n" : EOL;
		// ]
		if (!x($_POST, 'page_site')) {
		// [
			if (!$this->isajax)
		// ]
				return;
		}
		// [
		$this->msgbg = '';
		// <-hilmar]

		check_form_security_token_redirectOnErr('/admin/site', 'admin_site');

		$sitename 			=	((x($_POST,'sitename'))			? notags(trim($_POST['sitename']))			: '');

		$banner				=	((x($_POST,'banner'))			? trim($_POST['banner'])				: false);

		$admininfo			=	((x($_POST,'admininfo'))		? trim($_POST['admininfo'])				: false);
		$siteinfo			=	((x($_POST,'siteinfo'))		    ? trim($_POST['siteinfo'])				: '');
		$language			=	((x($_POST,'language'))			? notags(trim($_POST['language']))			: '');
		$theme				=	((x($_POST,'theme'))			? notags(trim($_POST['theme']))				: '');
		//		$theme_mobile			=	((x($_POST,'theme_mobile'))		? notags(trim($_POST['theme_mobile']))			: '');
		//		$site_channel			=	((x($_POST,'site_channel'))	? notags(trim($_POST['site_channel']))				: '');
		$maximagesize		=	((x($_POST,'maximagesize'))		? intval(trim($_POST['maximagesize']))				:  0);

		$register_policy	=	((x($_POST,'register_policy'))	? intval(trim($_POST['register_policy']))	:  0);
		$register_wo_email	=	((x($_POST,'register_wo_email'))	? intval(trim($_POST['register_wo_email']))	:  0);
		$minimum_age           = ((x($_POST,'minimum_age'))          ? intval(trim($_POST['minimum_age']))    : 13);
		$access_policy	=	((x($_POST,'access_policy'))	? intval(trim($_POST['access_policy']))	:  0);
		$reg_autochannel	= ((x($_POST,'auto_channel_create'))		? True	: False);
		$invitation_only	= ((x($_POST,'invitation_only'))		? True	: False);
		$invitation_also	= ((x($_POST,'invitation_also'))		? True	: False);
		$abandon_days	=	((x($_POST,'abandon_days'))	    ? intval(trim($_POST['abandon_days']))	    :  0);

		$register_text		=	((x($_POST,'register_text'))	? notags(trim($_POST['register_text']))		: '');
		$site_sellpage		=	((x($_POST,'site_sellpage'))	? notags(trim($_POST['site_sellpage']))		: '');
		$site_location		=	((x($_POST,'site_location'))	? notags(trim($_POST['site_location']))		: '');
		$frontpage			=	((x($_POST,'frontpage'))	? notags(trim($_POST['frontpage']))		: '');
		$firstpage		    =	((x($_POST,'firstpage'))	? notags(trim($_POST['firstpage']))		: 'profiles');
		$first_page		    =	((x($_POST,'first_page'))	? notags(trim($_POST['first_page']))		: 'profiles');
		// check value after trim
		if(! $first_page) {
			$first_page = 'profiles';
		}
		$mirror_frontpage	=	((x($_POST,'mirror_frontpage'))	? intval(trim($_POST['mirror_frontpage']))		: 0);
		$directory_server	=	((x($_POST,'directory_server')) ? trim($_POST['directory_server']) : '');
		$allowed_sites		=	((x($_POST,'allowed_sites'))	? notags(trim($_POST['allowed_sites']))		: '');
		$force_publish		=	((x($_POST,'publish_all'))		? True	: False);
		$disable_discover_tab =	((x($_POST,'disable_discover_tab'))		? False	:	True);
		$site_firehose      =   ((x($_POST,'site_firehose')) ? True : False);
		$open_pubstream     =   ((x($_POST,'open_pubstream')) ? True : False);
		$login_on_homepage	=	((x($_POST,'login_on_homepage'))		? True	:	False);
		$enable_context_help = ((x($_POST,'enable_context_help'))		? True	:	False);
		$global_directory     = ((x($_POST,'directory_submit_url'))	? notags(trim($_POST['directory_submit_url']))	: '');
		$no_community_page    = !((x($_POST,'no_community_page'))	? True	:	False);
		$default_expire_days  = ((array_key_exists('default_expire_days',$_POST)) ? intval($_POST['default_expire_days']) : 0);
		$active_expire_days  = ((array_key_exists('active_expire_days',$_POST)) ? intval($_POST['active_expire_days']) : 7);

		$reply_address      = ((array_key_exists('reply_address',$_POST) && trim($_POST['reply_address'])) ? trim($_POST['reply_address']) : 'noreply@' . \App::get_hostname());
		$from_email         = ((array_key_exists('from_email',$_POST) && trim($_POST['from_email'])) ? trim($_POST['from_email']) : 'Administrator@' . \App::get_hostname());
		$from_email_name    = ((array_key_exists('from_email_name',$_POST) && trim($_POST['from_email_name'])) ? trim($_POST['from_email_name']) : \Zotlabs\Lib\System::get_site_name());


		$sse_enabled       = ((x($_POST,'sse_enabled'))      ? true : false);

		$verifyssl         = ((x($_POST,'verifyssl'))        ? True : False);
		$proxyuser         = ((x($_POST,'proxyuser'))        ? notags(trim($_POST['proxyuser']))  : '');
		$proxy             = ((x($_POST,'proxy'))            ? notags(trim($_POST['proxy']))      : '');
		$timeout           = ((x($_POST,'timeout'))          ? intval(trim($_POST['timeout']))    : 60);
		$delivery_interval = ((x($_POST,'delivery_interval'))? intval(trim($_POST['delivery_interval'])) : 0);
		$delivery_batch_count = ((x($_POST,'delivery_batch_count') && $_POST['delivery_batch_count'] > 0)? intval(trim($_POST['delivery_batch_count'])) : 1);
		$poll_interval     = ((x($_POST,'poll_interval'))    ? intval(trim($_POST['poll_interval'])) : 0);
		$maxloadavg        = ((x($_POST,'maxloadavg'))       ? intval(trim($_POST['maxloadavg'])) : 50);
		$feed_contacts     = ((x($_POST,'feed_contacts'))    ? intval($_POST['feed_contacts'])    : 0);
		$verify_email      = ((x($_POST,'verify_email'))     ? 1 : 0);
		$register_perday   = ((x($_POST,'register_perday'))	 ? intval(trim($_POST['register_perday'])) : 50);
		$register_sameip   = ((x($_POST,'register_sameip'))	 ? intval(trim($_POST['register_sameip'])) : 3);

		$regdelayn 		   = ((x($_POST,'zardelayn'))	 	? intval(trim($_POST['zardelayn'])) : 0);
		$regdelayu 		   = ((x($_POST,'zardelay'))	 	? notags(trim($_POST['zardelay'])) : '');
		$reg_delay 		   = (preg_match('/^[a-z]{1,1}$/', $regdelayu) ? $regdelayn . $regdelayu : '');
		$regexpiren 	   = ((x($_POST,'zarexpiren'))	 	? intval(trim($_POST['zarexpiren'])) : 0);
		$regexpireu 	   = ((x($_POST,'zarexpire'))	 	? notags(trim($_POST['zarexpire'])) : '');
		$reg_expire 	   = (preg_match('/^[a-z]{1,1}$/', $regexpireu) ? $regexpiren . $regexpireu : '');

		$imagick_path      = ((x($_POST,'imagick_path'))     ? trim($_POST['imagick_path'])   : '');
		$force_queue       = ((intval($_POST['force_queue']) > 0) ? intval($_POST['force_queue'])   : 3000);
		$pub_incl = escape_tags(trim($_POST['pub_incl']));
		$pub_excl = escape_tags(trim($_POST['pub_excl']));

		$permissions_role = escape_tags(trim($_POST['permissions_role']));

		// [hilmar->
		$this->register_duty = ((x($_POST,'register_duty')) ? notags(trim($_POST['register_duty']))  : '');
		if (! preg_match('/^[0-9 .,:\-]{0,191}$/', $this->register_duty)) {
			$this->msgbg .= 'ZAR0131E,' . t('Invalid input') . $this->eol;
			$this->error++;
		} else {

			$this->duty();

			if ($this->isajax) {
				echo json_encode(array('msgbg' => $this->msgbg, 'me' => 'zar'));
				// that mission is complete
				killme();
				exit;

			} else {

				//logger( print_r( $this->msgbg, true) );
				//logger( print_r( $this->joo, true) );
				if ($this->error === 0) {
					set_config('system', 'register_duty', $this->register_duty);
					set_config('system', 'register_duty_jso', $this->joo);
				} else {
					notice('ZAR0130E,'.t('Errors') . ': ' . $this->error) . EOL . $this->msgfg;
				}
			}
		}
		// <-hilmar]

		set_config('system', 'feed_contacts', $feed_contacts);
		set_config('system', 'delivery_interval', $delivery_interval);
		set_config('system', 'delivery_batch_count', $delivery_batch_count);
		set_config('system', 'poll_interval', $poll_interval);
		set_config('system', 'maxloadavg', $maxloadavg);
		set_config('system', 'frontpage', $frontpage);
		set_config('system', 'sellpage', $site_sellpage);
		set_config('system', 'workflow_channel_next', $first_page);
		set_config('system', 'site_location', $site_location);
		set_config('system', 'mirror_frontpage', $mirror_frontpage);
		set_config('system', 'sitename', $sitename);
		set_config('system', 'login_on_homepage', $login_on_homepage);
		set_config('system', 'enable_context_help', $enable_context_help);
		set_config('system', 'verify_email', $verify_email);
		set_config('system', 'max_daily_registrations', $register_perday);
		set_config('system', 'register_sameip', $register_sameip);
		set_config('system', 'register_delay', $reg_delay);
		set_config('system', 'register_expire', $reg_expire);
		set_config('system', 'default_expire_days', $default_expire_days);
		set_config('system', 'active_expire_days', $active_expire_days);
		set_config('system', 'reply_address', $reply_address);
		set_config('system', 'from_email', $from_email);
		set_config('system', 'from_email_name' , $from_email_name);
		set_config('system', 'imagick_convert_path' , $imagick_path);
		set_config('system', 'default_permissions_role', $permissions_role);
		set_config('system', 'pubstream_incl',$pub_incl);
		set_config('system', 'pubstream_excl',$pub_excl);


		if($directory_server)
			set_config('system','directory_server',$directory_server);

		if ($banner == '') {
			del_config('system', 'banner');
		} else {
			set_config('system', 'banner', $banner);
		}

		if ($admininfo == ''){
			del_config('system', 'admininfo');
		} else {
			require_once('include/text.php');
			linkify_tags($admininfo, local_channel());
			set_config('system', 'admininfo', $admininfo);
		}
		set_config('system','siteinfo',$siteinfo);
		set_config('system', 'language', $language);
		set_config('system', 'theme', $theme);
		//		if ( $theme_mobile === '---' ) {
		//			del_config('system', 'mobile_theme');
		//		} else {
		//			set_config('system', 'mobile_theme', $theme_mobile);
		//		}
		//	set_config('system','site_channel', $site_channel);
		set_config('system','maximagesize', $maximagesize);

		set_config('system','register_policy', $register_policy);
		set_config('system','register_wo_email', $register_wo_email);
		set_config('system','minimum_age', $minimum_age);
		set_config('system','auto_channel_create', $reg_autochannel);
		set_config('system', 'invitation_only', $invitation_only);
		set_config('system', 'invitation_also', $invitation_also);
		set_config('system','access_policy', $access_policy);
		set_config('system','account_abandon_days', $abandon_days);
		set_config('system','register_text', $register_text);
		set_config('system','allowed_sites', $allowed_sites);
		set_config('system','publish_all', $force_publish);
		set_config('system','disable_discover_tab', $disable_discover_tab);
		set_config('system','site_firehose', $site_firehose);
		set_config('system','open_pubstream', $open_pubstream);
		set_config('system','force_queue_threshold', $force_queue);
		if ($global_directory == '') {
			del_config('system', 'directory_submit_url');
		} else {
			set_config('system', 'directory_submit_url', $global_directory);
		}

		set_config('system','no_community_page', $no_community_page);
		set_config('system','no_utf', $no_utf);

		set_config('system','sse_enabled', $sse_enabled);

		set_config('system','verifyssl', $verifyssl);
		set_config('system','proxyuser', $proxyuser);
		set_config('system','proxy', $proxy);
		set_config('system','curl_timeout', $timeout);

		info( t('Site settings updated.') . EOL);
		goaway(z_root() . '/admin/site' );
	}

	/**
	 * @brief Admin page site.
	 *
	 * @return string with HTML
	 */
	function get() {

		/* Installed langs */
		$lang_choices = array();
		$langs = glob('view/*/hstrings.php');

		if(is_array($langs) && count($langs)) {
			if(! in_array('view/en/hstrings.php',$langs))
				$langs[] = 'view/en/';
			asort($langs);
			foreach($langs as $l) {
				$t = explode("/",$l);
				$lang_choices[$t[1]] = $t[1];
			}
		}

		/* Installed themes */
		$theme_choices_mobile["---"] = t("Default");
		$theme_choices = array();
		$files = glob('view/theme/*');
		if($files) {
			foreach($files as $file) {
				$vars = '';
				$f = basename($file);

				$info = get_theme_info($f);
				$compatible = check_plugin_versions($info);
				if(!$compatible) {
					$theme_choices[$f] = $theme_choices_mobile[$f] = sprintf(t('%s - (Incompatible)'), $f);
					continue;
				}

				if (file_exists($file . '/library'))
					continue;
				if (file_exists($file . '/mobile'))
					$vars = t('mobile');
				if (file_exists($file . '/experimental'))
					$vars .= t('experimental');
				if (file_exists($file . '/unsupported'))
					$vars .= t('unsupported');
				if ($vars) {
					$theme_choices[$f] = $f . ' (' . $vars . ')';
					$theme_choices_mobile[$f] = $f . ' (' . $vars . ')';
				}
				else {
					$theme_choices[$f] = $f;
					$theme_choices_mobile[$f] = $f;
				}
			}
		}

		$dir_choices = null;
		$dirmode = get_config('system','directory_mode');
		$realm = get_directory_realm();

		// directory server should not be set or settable unless we are a directory client
		// avoid older redmatrix servers which don't have modern encryption

		if($dirmode == DIRECTORY_MODE_NORMAL) {
			$x = q("select site_url from site where site_flags in (%d,%d) and site_realm = '%s' and site_dead = 0 and site_project != 'redmatrix'",
				intval(DIRECTORY_MODE_SECONDARY),
				intval(DIRECTORY_MODE_PRIMARY),
				dbesc($realm)
			);
			if($x) {
				$dir_choices = array();
				foreach($x as $xx) {
					$dir_choices[$xx['site_url']] = $xx['site_url'];
				}
			}
		}

		/* Banner */

		$banner = get_config('system', 'banner');
		if($banner === false)
			$banner = get_config('system','sitename');

		$banner = htmlspecialchars($banner);

		/* Admin Info */
		$admininfo = get_config('system', 'admininfo');

		/* Register policy */
		$register_choices = Array(
			REGISTER_CLOSED  => t("No"),
			REGISTER_APPROVE => t("Yes - with approval"),
			REGISTER_OPEN    => t("Yes")
		);
		$this->register_duty = get_config('system', 'register_duty', '-:-');
		$register_perday = get_config('system','max_daily_registrations', 50);

		/* Acess policy */
		$access_choices = Array(
			ACCESS_PRIVATE => t("My site is not a public server"),
			ACCESS_PAID => t("My site has paid access only"),
			ACCESS_FREE => t("My site has free access only"),
			ACCESS_TIERED => t("My site offers free accounts with optional paid upgrades")
		);

		$discover_tab = get_config('system','disable_discover_tab');

		// $disable public streams by default
		if($discover_tab === false)
			$discover_tab = 1;
		// now invert the logic for the setting.
		$discover_tab = (1 - $discover_tab);

		$perm_roles = \Zotlabs\Access\PermissionRoles::channel_roles();
		$default_role = get_config('system', 'default_permissions_role', 'personal');

		if (!in_array($default_role, array_keys($perm_roles))) {
			$default_role = 'personal';
		}

		$role = array('permissions_role' , t('Default permission role for new accounts'), $default_role, t('This role will be used for the first channel created after registration.'),$perm_roles);

		$homelogin = get_config('system','login_on_homepage');
		$enable_context_help = get_config('system','enable_context_help');

		// for reuse reg_delay and reg_expire
		$reg_rabots = array(
 					'i'	=> t('Minute(s)'),
 					'h' => t('Hour(s)')  ,
 					'd' => t('Day(s)')   ,
 					'w' => t('Week(s)')  ,
 					'm' => t('Month(s)') ,
 					'y' => t('Year(s)')
		);
		$regdelay_n = $regdelay_u = false;
		$regdelay = get_config('system','register_delay');
		if ($regdelay)
			list($regdelay_n, $regdelay_u) = array(substr($regdelay,0,-1),substr($regdelay,-1));
		$reg_delay = replace_macros(get_markup_template('field_duration.qmc.tpl'),
			 array(
			 	'label'  	=> t('Register verification delay'),
			 	'qmc'	 	=> 'zar',
				'qmcid'		=> '',
				'help'		=> t('Time to wait before a registration can be verified'),
			 	'field' => 	array(
			 		'name'  => 'delay',
			 		'title' => t('duration up from now'),
			 		'value' => ($regdelay_n === false ? 0 : $regdelay_n),
			 		'min'   => '0',
			 		'max'   => '99',
			 		'size'  => '2',
					'default' => ($regdelay_u === false ? 'i' : $regdelay_u)
			 	),
			 	'rabot'	=> 	$reg_rabots
 			)
		);
		$regexpire_n = $regexpire_u = false;
		$regexpire = get_config('system','register_expire');
		if ($regexpire)
			list($regexpire_n, $regexpire_u) = array(substr($regexpire,0,-1),substr($regexpire,-1));
		$reg_expire = replace_macros(get_markup_template('field_duration.qmc.tpl'),
			 array(
			 	'label'  	=> t('Register verification expiration time'),
			 	'qmc'	 	=> 'zar',
				'qmcid'		=> '',
			 	'help'		=> t('Time before an unverified registration will expire'),
			 	'field' => 	array(
			 		'name'  => 'expire',
			 		'title' => t('duration up from now'),
			 		'value' => ($regexpire_n === false ? 3 : $regexpire_n),
			 		'min'  => '0',
			 		'max'  => '99',
			 		'size' => '2',
					'default' => ($regexpire_u === false ? 'd' : $regexpire_u)
			 	),
			 	'rabot'	=> 	$reg_rabots
			 )
		);

		$tao = '';
		$t = get_markup_template("admin_site.tpl");
		return replace_macros($t, array(
			'$title' => t('Administration'),
			// interfacing js vars
			'$tao' => $tao,
			'$page' => t('Site'),
			'$submit' => t('Submit'),
			'$registration' => t('Registration'),
			'$upload' => t('File upload'),
			'$corporate' => t('Policies'),
			'$advanced' => t('Advanced'),

			'$baseurl' => z_root(),
			// name, label, value, help string, extra data...
			'$sitename' 		=> array('sitename', t("Site name"), htmlspecialchars(get_config('system','sitename'), ENT_QUOTES, 'UTF-8'),''),

			'$banner'			=> array('banner', t("Banner/Logo"), $banner, t('Unfiltered HTML/CSS/JS is allowed')),
			'$admininfo'		=> array('admininfo', t("Administrator Information"), $admininfo, t("Contact information for site administrators.  Displayed on siteinfo page.  BBCode can be used here")),
			'$siteinfo'		=> array('siteinfo', t('Site Information'), get_config('system','siteinfo'), t("Publicly visible description of this site.  Displayed on siteinfo page.  BBCode can be used here")),
			'$language' 		=> array('language', t("System language"), get_config('system','language'), "", $lang_choices),
			'$theme' 			=> array('theme', t("System theme"), get_config('system','theme'), t("Default system theme - may be over-ridden by user profiles - <a href='#' id='cnftheme'>change theme settings</a>"), $theme_choices),
		//			'$theme_mobile' 	=> array('theme_mobile', t("Mobile system theme"), get_config('system','mobile_theme'), t("Theme for mobile devices"), $theme_choices_mobile),
		//			'$site_channel' 	=> array('site_channel', t("Channel to use for this website's static pages"), get_config('system','site_channel'), t("Site Channel")),
			'$feed_contacts'    => array('feed_contacts', t('Allow Feeds as Connections'),get_config('system','feed_contacts'),t('(Heavy system resource usage)')),
			'$maximagesize'		=> array('maximagesize', t("Maximum image size"), intval(get_config('system','maximagesize')), t("Maximum size in bytes of uploaded images. Default is 0, which means no limits.")),
			'$minimum_age'		=> array('minimum_age', t("Minimum age"), (x(get_config('system','minimum_age'))?get_config('system','minimum_age'):13), t("Minimum age (in years) for who may register on this site.")),
			'$access_policy'	=> array('access_policy', t("Which best describes the types of account offered by this hub?"), get_config('system','access_policy'), t("This is displayed on the public server site list."), $access_choices),

			// Register
			// [hilmar->
			'$register_text'	=> [
				'register_text',
				t("Register text"),
				htmlspecialchars(get_config('system','register_text'), ENT_QUOTES, 'UTF-8'),
				t("This text will be displayed prominently at the registration page")
			],
			'$register_policy'	=> [
				'register_policy',
				t("Does this site allow new member registration?"),
				get_config('system','register_policy'),
				"",
				$register_choices,
			],
			'$register_duty' => [
				'register_duty',
				t('Configure the registration open days/hours'),
				get_config('system', 'register_duty', '-:-'),
				t('Empty or \'-:-\' value will keep registration open 24/7 (default)') . EOL .
				t('Weekdays and hours must be separated by colon \':\', From-To ranges with a dash `-` example: 1:800-1200') . EOL .
				t('Weekday:Hour pairs must be separated by space \' \' example: 1:900-1700 2:900-1700') . EOL .
				t('From-To ranges must be separated by comma \',\' example: 1:800-1200,1300-1700 or 1-2,4-5:900-1700') . EOL .
				t('Advanced examples:') . ' 1-5:0900-1200,1300-1700 6:900-1230 ' . t('or') . ' 1-2,4-5:800-1800<br>' . EOL .
				'<a id="zar083a" class="btn btn-sm btn-outline-secondary zuia">' . t('Check your configuration') . '</a>'. EOL
			],
			'$register_perday' => [
				'register_perday',
				t('Max account registrations per day'),
				get_config('system', 'max_daily_registrations', 50),
				t('Unlimited if zero or no value - default 50')
			],
			'$register_sameip' => [
				'register_sameip',
				t('Max account registrations from same IP'),
				get_config('system', 'register_sameip', 3),
				t('Unlimited if zero or no value - default 3')
			],
			'$reg_delay' => $reg_delay,
			'$reg_expire' => $reg_expire,
			'$reg_autochannel'		=> [
				'auto_channel_create',
				t("Auto channel create"),
				get_config('system','auto_channel_create', 1),
				t("If disabled the channel will be created in a separate step during the registration process")
			],
			'$invitation_only' => [
				'invitation_only',
				t("Require invite code"),
				get_config('system', 'invitation_only', 0)
			],
			'$invitation_also' => [
				'invitation_also',
				t("Allow invite code"),
				get_config('system', 'invitation_also', 0)
			],
			'$verify_email'		=> [
				'verify_email',
				t("Require email address"),
				get_config('system','verify_email'),
				t("The provided email address will be verified (recommended)")
			],
			'$abandon_days' => [
				'abandon_days',
				t('Abandon account after x days'),
				get_config('system','account_abandon_days'),
				t('Will not waste system resources polling external sites for abandonded accounts. Enter 0 for no time limit.')
			],
			// <-hilmar]

			'$role'         => $role,
			'$frontpage'	=> array('frontpage', t("Site homepage to show visitors (default: login box)"), get_config('system','frontpage'), t("example: 'pubstream' to show public stream, 'page/sys/home' to show a system webpage called 'home' or 'include:home.html' to include a file.")),
			'$mirror_frontpage'	=> array('mirror_frontpage', t("Preserve site homepage URL"), get_config('system','mirror_frontpage'), t('Present the site homepage in a frame at the original location instead of redirecting')),
			'$allowed_sites'	=> array('allowed_sites', t("Allowed friend domains"), get_config('system','allowed_sites'), t("Comma separated list of domains which are allowed to establish friendships with this site. Wildcards are accepted. Empty to allow any domains")),
			'$force_publish'	=> array('publish_all', t("Force publish"), get_config('system','publish_all'), t("Check to force all profiles on this site to be listed in the site directory.")),
			'$disable_discover_tab'	=> array('disable_discover_tab', t('Import Public Streams'), $discover_tab, t('Import and allow access to public content pulled from other sites. Warning: this content is unmoderated.')),
			'$site_firehose'	=> array('site_firehose', t('Site only Public Streams'), get_config('system','site_firehose'), t('Allow access to public content originating only from this site if Imported Public Streams are disabled.')),
			'$open_pubstream'	=> array('open_pubstream', t('Allow anybody on the internet to access the Public streams'), get_config('system','open_pubstream',1), t('Disable to require authentication before viewing. Warning: this content is unmoderated.')),
			'$incl'           => array('pub_incl',t('Only import Public stream posts with this text'), get_config('system','pubstream_incl'),t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')),
			'$excl'           => array('pub_excl',t('Do not import Public stream posts with this text'), get_config('system','pubstream_excl'),t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')),


			'$login_on_homepage'	=> array('login_on_homepage', t("Login on Homepage"),((intval($homelogin) || $homelogin === false) ? 1 : '') , t("Present a login box to visitors on the home page if no other content has been configured.")),
			'$enable_context_help'	=> array('enable_context_help', t("Enable context help"),((intval($enable_context_help) === 1 || $enable_context_help === false) ? 1 : 0) , t("Display contextual help for the current page when the help button is pressed.")),

			'$reply_address' => [ 'reply_address', t('Reply-to email address for system generated email.'), get_config('system','reply_address','noreply@' . \App::get_hostname()),'' ],
			'$from_email' => [ 'from_email', t('Sender (From) email address for system generated email.'), get_config('system','from_email','Administrator@' . \App::get_hostname()),'' ],
			'$from_email_name' => [ 'from_email_name', t('Name of email sender for system generated email.'), get_config('system','from_email_name',\Zotlabs\Lib\System::get_site_name()),'' ],

			'$directory_server' => (($dir_choices) ? array('directory_server', t("Directory Server URL"), get_config('system','directory_server'), t("Default directory server"), $dir_choices) : null),

			'$sse_enabled'		=> array('sse_enabled', t('Enable SSE Notifications'), get_config('system', 'sse_enabled', 0), t('If disabled, traditional polling will be used. Warning: this setting might not be suited for shared hosting')),

			'$proxyuser'		=> array('proxyuser', t("Proxy user"), get_config('system','proxyuser'), ""),
			'$proxy'			=> array('proxy', t("Proxy URL"), get_config('system','proxy'), ""),
			'$timeout'			=> array('timeout', t("Network timeout"), (x(get_config('system','curl_timeout'))?get_config('system','curl_timeout'):60), t("Value is in seconds. Set to 0 for unlimited (not recommended).")),
			'$delivery_interval'			=> array('delivery_interval', t("Delivery interval"), (x(get_config('system','delivery_interval'))?get_config('system','delivery_interval'):2), t("Delay background delivery processes by this many seconds to reduce system load. Recommend: 4-5 for shared hosts, 2-3 for virtual private servers. 0-1 for large dedicated servers.")),
			'$delivery_batch_count' => array('delivery_batch_count', t('Deliveries per process'),(x(get_config('system','delivery_batch_count'))?get_config('system','delivery_batch_count'):1), t("Number of deliveries to attempt in a single operating system process. Adjust if necessary to tune system performance. Recommend: 1-5.")),
			'$force_queue'			=> array('force_queue', t("Queue Threshold"), get_config('system','force_queue_threshold',3000), t("Always defer immediate delivery if queue contains more than this number of entries.")),
			'$poll_interval'			=> array('poll_interval', t("Poll interval"), (x(get_config('system','poll_interval'))?get_config('system','poll_interval'):2), t("Delay background polling processes by this many seconds to reduce system load. If 0, use delivery interval.")),
			'$imagick_path'			=> array('imagick_path', t("Path to ImageMagick convert program"), get_config('system','imagick_convert_path'), t("If set, use this program to generate photo thumbnails for huge images ( > 4000 pixels in either dimension), otherwise memory exhaustion may occur. Example: /usr/bin/convert")),
			'$maxloadavg'			=> array('maxloadavg', t("Maximum Load Average"), ((intval(get_config('system','maxloadavg')) > 0)?get_config('system','maxloadavg'):50), t("Maximum system load before delivery and poll processes are deferred - default 50.")),
			'$default_expire_days' => array('default_expire_days', t('Expiration period in days for imported (grid/network) content'), intval(get_config('system','default_expire_days')), t('0 for no expiration of imported content')),
			'$active_expire_days' => array('active_expire_days', t('Do not expire any posts which have comments less than this many days ago'), intval(get_config('system','active_expire_days',7)), ''),
			'$sellpage' => array('site_sellpage', t('Public servers: Optional landing (marketing) webpage for new registrants'), get_config('system','sellpage',''), sprintf( t('Create this page first. Default is %s/register'),z_root())),
			'$first_page' => array('first_page', t('Page to display after creating a new channel'), get_config('system','workflow_channel_next','profiles'), t('Default: profiles')),
			'$location' => array('site_location', t('Optional: site location'), get_config('system','site_location',''), t('Region or country')),
			'$form_security_token' => get_form_security_token("admin_site"),
		));
	}

	/**
	 * @brief Admin page site common post submit and ajax interaction
	 * @author hilmar runge
	 * @since  2020-02-04
	 * Configure register office duty weekdays and hours
	 * Syntax: weekdays:hours [weekdays:hours]
	 *         [.d[,d-d.]]]:hhmm-hhmm[,hhmm-hhmm...]
	 *		   ranges are between blanks, days are 1-7, where 1 = Monday
	 *		   hours are [h]hmm 3-4digit 24 clock values
	 *      ie 0900-1200,1300-1800 for hours
	 *      ie 1-2,4,5 for weekdays
	 *		ie 1-2:900-1800		monday and tuesday open from 9 to 18h
	 *
	 * @var $register_duty is the input field from the admin -> site page
	 * @return the results are in the class vars $error, $msgbg and $jsoo
	 *		$jsoo is
	 */

	// 3-4 digit 24h clock regex
	const regxTime34 = '/^(?:2[0-3]|[01][0-9]|[0-9])[0-5][0-9]$/';
	var $wdconst = array('','mo','tu','we','th','fr','sa','so');

	// in
	var $register_duty;
	// intermediate
	var $isajax;
	// return
	var $jsoo;
	var $msgbg;
	var $error = 0;
	var $msgfg = '';

	private function duty() {

		$aro=array_fill(1, 7, 0);

		if ($this->isajax) {
			$op = (preg_match('/[a-z]{2,4}/', $_REQUEST['zarop'])) ? $_REQUEST['zarop'] : '';
			if ($op == 'zar083') {
				$this->msgbg = 'Testmode:' . $this->eol . $this->msgbg;
			} else {
				killme();
				exit;
			}
		}

		$ranges = preg_split('/\s+/', $this->register_duty);
		$this->msgbg .= '..ranges: ' . print_r(count($ranges),true) . $this->eol;

		foreach ($ranges as $rn => $range) {
			list($ws,$hs,) = explode(':', $range);

			$ws ? $arw = explode( ',', $ws) : $arw=array();
			$this->msgbg .= ($rn+1).'.weekday ranges: ' . count($arw) . $this->eol;
			// $this->msgbg .= print_r($arw,true);
			$hs ? $arh = explode( ',', $hs) : $arh=array();
			$this->msgbg .= ($rn+1).'.hour ranges: ' . count($arh) . $this->eol;

			$this->msgbg .= ($rn+1).'.wdays: ' . ( $ws ? print_r($ws,true) : 'none') . ' : '
					.  ' hours: ' . print_r($hs,true) . $this->eol;

			// several hs may belog to one wd
			// aro[0] is tmp store
			foreach ($arh as $hs) {
				list($ho,$hc,) = explode( '-', $hs );

				// no value forces open very early, and be sure having valid hhmm values
				!$ho ? $ho = "0000" : '';
				!$hc ? $hc = "0000" : ''; // pseudo
				if (preg_match(self::regxTime34, $ho)
				 && preg_match(self::regxTime34, $hc)) {

					// fix pseudo, allow no reverse range
					$hc == "0000" || $ho > $hc ? $hc = "2400" : '';

					$aro[0][$ho] = 0;
					$aro[0][$hc] = 1;

					$this->msgbg .= ($ho ? ' .open:' . $ho : '') . ($hc ? ' close:' . $hc : '') .$this->eol;
				} else {
					$this->msgbg .= ' .' . t('Invalid 24h time value (hhmm/hmm)') . $this->eol;
					$this->msgfg  .= ' .ZAR0132E,' . t('Invalid 24h time value (hhmm/hmm)') . $this->eol;
					$this->error++;
				}
			}

			// the weekday(s) values or ranges
			foreach ($arw as $ds) {
				$wd=explode('-', $ds);
				array_key_exists("1", $wd) && $wd[1]=="" ? $wd[1] = "7" : '';	 // a case 3-
				array_key_exists("1", $wd) && $wd[0]=="" ? $wd[0] = "1" : '';	 // a case -3
				!array_key_exists("1", $wd)				 ? $wd[1] = $wd[0] : ''; // a case 3
				if ($wd[0] > $wd[1]) continue; //  reverse order will be ignored // a case 5-3
				if (preg_match('/^[1-7]{1}$/', $wd[0])) {
					if (preg_match('/^[1-7]{1}$/', $wd[1])) {
						// $this->msgbg .= print_r($wd,true);
						for ($i=$wd[0]; $i<=$wd[1]; $i++) {
							// take the tmp store for the selected day(s)
							$aro[$i]=$aro[0];
						}
					}
				}
			}
			//$this->msgbg .= 'aro0: ' . print_r($aro,true) . $this->eol; // 4devels
			// clear the tmp store
			$aro[0]=array();
		}
		// discart the tmp store
		unset($aro[0]);
		// not configured days close at the beginning 0000h
		for ($i=1;$i<=7;$i++) { is_array($aro[$i]) ? '' : $aro[$i] = array("0000" => 1); }
		// $this->msgbg .= 'aro: ' . print_r($aro,true) . $this->eol; // 4devels

		if ($this->isajax) {
			// tell what we have
			// $this->msgbg .= 'aro0: ' . print_r($aro,true) . $this->eol; // 4devels
			$this->msgbg .= 'Duty time table:' . $this->eol;
			foreach ($aro as $dow => $hrs) {
				$this->msgbg .= ' ' . $this->wdconst[$dow] . ' ';
				// $this->msgbg .= '**' . print_r($hrs,true);
				foreach ($hrs as $h => $o) {
					$this->msgbg .= ((!$o) ? $h . ':open' : $h . ':close') . ', ';
				}
				$this->msgbg = rtrim($this->msgbg, ', ') . $this->eol;
			}

			$this->msgbg .= 'Generating 6 random times to check duty hours: ' . $this->eol;
			// we only need some random dates from anyway in past or future
			// because only the weekday and the clock is to test
			for ($i=0; $i<6; $i++) {
				$adow = rand(1, 7); // 1 to 7 (days)
				$cdow = $this->wdconst[$adow];
				// below is the essential algo to verify a date (of format Hi) meets an open or closed condition
				$t = date('Hi', ( rand(time(), 60*60*24+time()) ) );
				$how='close';
				foreach ($aro[$adow] as $o => $v) {
					// $this->msgbg .= 'debug: ' . $o . ' gt ' . $t . ' / ' . $v . $this->eol; // 4devels
					if ($o > $t) {
						$how = ($v ? 'open' : 'close');
						break;
					}
				}
				// now we know
				$this->msgbg .= ' ' . $cdow . '.' . $t . '=' . $how . ', ';
			}
			$this->msgbg = rtrim($this->msgbg, ', ') . $this->eol;
		}

		/*
		//$jov1 = array( 'view1' => $aro, 'view2' => '');
		$jov2=array();
		foreach ($aro as $d => $ts) {
			foreach ($ts as $t => $ft) {
				$jov2['view2'][$ft][] = $d.$t;
				//$ft=="1" && $t=="0000" ? $jov2['view2']["0"][] = $d."2400" : '';
			}
		}
		 $this->msgbg .= print_r($jov2, true) . $this->eol; // 4devels
		*/

		$this->joo = json_encode($aro);
		// $this->msgbg .= $this->joo . $this->eol; // 4devels
		// $this->msgbg .= print_r($aro, true) . $this->eol; // 4devels
		$okko = (json_decode($this->joo, true) ? true : false);
		if (!$okko) {
			$this->msgbg .= 'ZAR0139D,json 4 duty KO crash' . $this->eol;
			$this->msgfg .= 'ZAR0139D,json 4 duty KO crash' . $this->eol;
			$this->error++;
		}
		return ;
	}


}
