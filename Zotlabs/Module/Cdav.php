<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Libsync;


require_once('include/event.php');

require_once('include/auth.php');
require_once('include/security.php');
require_once('include/cdav.php');

class Cdav extends Controller {

	function init() {

		$record = null;
		$channel_login = false;

		if((argv(1) !== 'calendar') && (argv(1) !== 'addressbook')) {

			foreach([ 'REDIRECT_REMOTE_USER', 'HTTP_AUTHORIZATION' ] as $head) {

				/* Basic authentication */

				if(array_key_exists($head,$_SERVER) && substr(trim($_SERVER[$head]),0,5) === 'Basic') {
					$userpass = @base64_decode(substr(trim($_SERVER[$head]),6)) ;
					if(strlen($userpass)) {
						list($name, $password) = explode(':', $userpass);
						$_SERVER['PHP_AUTH_USER'] = $name;
						$_SERVER['PHP_AUTH_PW']   = $password;
					}
					break;
				}

				/* Signature authentication */

				if(array_key_exists($head,$_SERVER) && substr(trim($_SERVER[$head]),0,9) === 'Signature') {
					if($head !== 'HTTP_AUTHORIZATION') {
						$_SERVER['HTTP_AUTHORIZATION'] = $_SERVER[$head];
						continue;
					}

					$sigblock = HTTPSig::parse_sigheader($_SERVER[$head]);
					if($sigblock) {
						$keyId = str_replace('acct:','',$sigblock['keyId']);
						if($keyId) {
							$r = q("select * from hubloc where hubloc_id_url = '%s'",
								dbesc($keyId)
							);
							if($r) {
								$r = Libzot::zot_record_preferred($r);
								$c = channelx_by_hash($r['hubloc_hash']);
								if($c) {
									$a = q("select * from account where account_id = %d limit 1",
										intval($c['channel_account_id'])
									);
									if($a) {
										$record = [ 'channel' => $c, 'account' => $a[0] ];
										$channel_login = $c['channel_id'];
									}
								}
							}
							if(! $record)
								continue;

							if($record) {
								$verified = HTTPSig::verify('',$record['channel']['channel_pubkey']);
								if(! ($verified && $verified['header_signed'] && $verified['header_valid'])) {
									$record = null;
								}
								if($record['account']) {
							        authenticate_success($record['account']);
							        if($channel_login) {
							            change_channel($channel_login);
									}
								}
								break;
							}
						}
					}
				}
			}


			/**
			 * This server combines both CardDAV and CalDAV functionality into a single
			 * server. It is assumed that the server runs at the root of a HTTP domain (be
			 * that a domainname-based vhost or a specific TCP port.
			 *
			 * This example also assumes that you're using SQLite and the database has
			 * already been setup (along with the database tables).
			 *
			 * You may choose to use MySQL instead, just change the PDO connection
			 * statement.
			 */

			/**
			 * UTC or GMT is easy to work with, and usually recommended for any
			 * application.
			 */
			date_default_timezone_set('UTC');

			/**
			 * Make sure this setting is turned on and reflect the root url for your WebDAV
			 * server.
			 *
			 * This can be for example the root / or a complete path to your server script.
			 */

			$baseUri = '/cdav/';

			/**
			 * Database
			 *
			 */

			$pdo = \DBA::$dba->db;

			// Autoloader
			require_once 'vendor/autoload.php';

			/**
			 * The backends. Yes we do really need all of them.
			 *
			 * This allows any developer to subclass just any of them and hook into their
			 * own backend systems.
			 */

			$auth = new \Zotlabs\Storage\BasicAuth();
			$auth->setRealm(ucfirst(\Zotlabs\Lib\System::get_platform_name()) . 'CalDAV/CardDAV');

			if(local_channel()) {

				logger('loggedin');

				if((argv(1) == 'addressbooks') && (!Apps::system_app_installed(local_channel(), 'CardDAV'))) {
					killme();
				}

				$channel = App::get_channel();
				$auth->setCurrentUser($channel['channel_address']);
				$auth->channel_id = $channel['channel_id'];
				$auth->channel_hash = $channel['channel_hash'];
				$auth->channel_account_id = $channel['channel_account_id'];
				if($channel['channel_timezone'])
					$auth->setTimezone($channel['channel_timezone']);
				$auth->observer = $channel['channel_hash'];

				$principalUri = 'principals/' . $channel['channel_address'];
				if(! cdav_principal($principalUri)) {
					$this->activate($pdo, $channel);
					if(! cdav_principal($principalUri)) {
						return;
					}
				}

			}

			// Track CDAV updates from remote clients

			$httpmethod = $_SERVER['REQUEST_METHOD'];

			if($httpmethod === 'PUT' || $httpmethod === 'DELETE') {

				$channel = channelx_by_nick(argv(2));
				$principalUri = 'principals/' . $channel['channel_address'];
				$httpuri = $_SERVER['REQUEST_URI'];

				logger("debug: method: " . $httpmethod, LOGGER_DEBUG);
				logger("debug: uri: " . $httpuri, LOGGER_DEBUG);

				if(strpos($httpuri, 'cdav/addressbooks') !== false) {
					$sync = 'addressbook';
					$cdavtable = 'addressbooks';
				}
				elseif(strpos($httpuri, 'cdav/calendars') !== false) {
					$sync = 'calendar';
					$cdavtable = 'calendarinstances';
				}
				else {
					$sync = false;
				}

				if($sync) {

					$uri = basename($httpuri);
					$httpbody = file_get_contents('php://input');

					logger("debug: body: " . $httpbody, LOGGER_DEBUG);

					if($x = get_cdav_id($principalUri, argv(3), $cdavtable)) {

						$cdavdata = $this->get_cdav_data($x['id'], $cdavtable);
						$etag = (isset($_SERVER['HTTP_IF_MATCH']) ? $_SERVER['HTTP_IF_MATCH'] : false);

						// delete
						if($httpmethod === 'DELETE' && $cdavdata['etag'] == $etag) {
							Libsync::build_sync_packet($channel['channel_id'], [
								$sync => [
									'action' => 'delete_card',
									'uri' => $cdavdata['uri'],
									'carduri' => $uri
								]
							]);
						}
						else {
							if($etag && $cdavdata['etag'] !== $etag) {
								// update
								Libsync::build_sync_packet($channel['channel_id'], [
									$sync => [
										'action' => 'update_card',
										'uri' => $cdavdata['uri'],
										'carduri' => $uri,
										'card' => $httpbody
									]
								]);
							}
							else {
								// new
								Libsync::build_sync_packet($channel['channel_id'], [
									$sync => [
										'action' => 'import',
										'uri' => $cdavdata['uri'],
										'ids' => [ $uri ],
										'card' => $httpbody
									]
								]);
							}
						}
					}
				}
			}

			$principalBackend = new \Sabre\DAVACL\PrincipalBackend\PDO($pdo);
			$carddavBackend   = new \Sabre\CardDAV\Backend\PDO($pdo);
			$caldavBackend    = new \Sabre\CalDAV\Backend\PDO($pdo);

			/**
			 * The directory tree
			 *
			 * Basically this is an array which contains the 'top-level' directories in the
			 * WebDAV server.
			 */

			$nodes = [
				// /principals
				new \Sabre\CalDAV\Principal\Collection($principalBackend),

				// /calendars
				new \Sabre\CalDAV\CalendarRoot($principalBackend, $caldavBackend),

				// /addressbook
				new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend)
			];


			// The object tree needs in turn to be passed to the server class

			$server = new \Sabre\DAV\Server($nodes);

			if(isset($baseUri))
				$server->setBaseUri($baseUri);

			// Plugins
			$server->addPlugin(new \Sabre\DAV\Auth\Plugin($auth));
			// $server->addPlugin(new \Sabre\DAV\Browser\Plugin());
			$server->addPlugin(new \Sabre\DAV\Sync\Plugin());
			$server->addPlugin(new \Sabre\DAV\Sharing\Plugin());
			$server->addPlugin(new \Sabre\DAVACL\Plugin());

			// CalDAV plugins
			$server->addPlugin(new \Sabre\CalDAV\Plugin());
			$server->addPlugin(new \Sabre\CalDAV\SharingPlugin());
			// $server->addPlugin(new \Sabre\CalDAV\Schedule\Plugin());
			$server->addPlugin(new \Sabre\CalDAV\ICSExportPlugin());

			// CardDAV plugins
			$server->addPlugin(new \Sabre\CardDAV\Plugin());
			$server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());

			// And off we go!
			$server->start();

			killme();

		}

	}

	function post() {
		if(! local_channel())
			return;

		if((argv(1) === 'addressbook') && (! Apps::system_app_installed(local_channel(), 'CardDAV'))) {
			return;
		}

		$channel = App::get_channel();
		$principalUri = 'principals/' . $channel['channel_address'];

		if(!cdav_principal($principalUri))
			return;

		$pdo = \DBA::$dba->db;

		require_once 'vendor/autoload.php';

		if(argc() == 2 && argv(1) === 'calendar') {

			$caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);
			$calendars = $caldavBackend->getCalendarsForUser($principalUri);

			//create new calendar
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['create']) {
				do {
					$duplicate = false;
					$calendarUri = random_string(40);

					$r = q("SELECT uri FROM calendarinstances WHERE principaluri = '%s' AND uri = '%s' LIMIT 1",
						dbesc($principalUri),
						dbesc($calendarUri)
					);

					if (count($r))
						$duplicate = true;
				} while ($duplicate == true);

				$properties = [
					'{DAV:}displayname' => $_REQUEST['{DAV:}displayname'],
					'{http://apple.com/ns/ical/}calendar-color' => $_REQUEST['color'],
					'{urn:ietf:params:xml:ns:caldav}calendar-description' => $channel['channel_name']
				];

				$id = $caldavBackend->createCalendar($principalUri, $calendarUri, $properties);

				// set new calendar to be visible
				set_pconfig(local_channel(), 'cdav_calendar' , $id[0], 1);

				Libsync::build_sync_packet($channel['channel_id'], [
					'calendar' => [
						'action' => 'create',
						'uri' => $calendarUri,
						'properties' => $properties
					]
				]);
			}

			//create new calendar object via ajax request
			if($_REQUEST['submit'] === 'create_event' && $_REQUEST['title'] && $_REQUEST['target'] && $_REQUEST['dtstart']) {

				$id = explode(':', $_REQUEST['target']);

				if(!cdav_perms($id[0],$calendars,true))
					return;

				$cdavdata = $this->get_cdav_data($id[0], 'calendarinstances');

				$timezone = ((x($_POST,'timezone_select')) ? escape_tags(trim($_POST['timezone_select'])) : '');
				$tz = (($timezone) ? $timezone : date_default_timezone_get());

				$allday = $_REQUEST['allday'];

				$title = $_REQUEST['title'];
				$start = datetime_convert('UTC', 'UTC', $_REQUEST['dtstart']);
				$dtstart = new \DateTime($start);

				if($_REQUEST['dtend']) {
					$end = datetime_convert('UTC', 'UTC', $_REQUEST['dtend']);
					$dtend = new \DateTime($end);
				}
				$description = $_REQUEST['description'];
				$location = $_REQUEST['location'];

				do {
					$duplicate = false;
					$objectUri = random_string(40) . '.ics';

					$r = q("SELECT uri FROM calendarobjects WHERE calendarid = %s AND uri = '%s' LIMIT 1",
						intval($id[0]),
						dbesc($objectUri)
					);

					if (count($r))
						$duplicate = true;
				} while ($duplicate == true);


				$vcalendar = new \Sabre\VObject\Component\VCalendar([
				    'VEVENT' => [
					'SUMMARY' => $title,
					'DTSTART' => $dtstart
				    ]
				]);

				if($dtend) {
					$vcalendar->VEVENT->add('DTEND', $dtend);
					if($allday)
						$vcalendar->VEVENT->DTEND['VALUE'] = 'DATE';
					else
						$vcalendar->VEVENT->DTEND['TZID'] = $tz;
				}
				if($description)
					$vcalendar->VEVENT->add('DESCRIPTION', $description);
				if($location)
					$vcalendar->VEVENT->add('LOCATION', $location);

				if($allday)
					$vcalendar->VEVENT->DTSTART['VALUE'] = 'DATE';
				else
					$vcalendar->VEVENT->DTSTART['TZID'] = $tz;

				$calendarData = $vcalendar->serialize();
				$caldavBackend->createCalendarObject($id, $objectUri, $calendarData);

				Libsync::build_sync_packet($channel['channel_id'], [
					'calendar' => [
						'action' => 'import',
						'uri' => $cdavdata['uri'],
						'ids' => [ $objectUri ],
						'card' => $calendarData
					]
				]);

				killme();
			}

			//edit calendar name and color
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['edit'] && $_REQUEST['id']) {

				$id = explode(':', $_REQUEST['id']);

				if(! cdav_perms($id[0],$calendars))
					return;

				$cdavdata = $this->get_cdav_data($id[0], 'calendarinstances');

				$mutations = [
					'{DAV:}displayname' => $_REQUEST['{DAV:}displayname'],
					'{http://apple.com/ns/ical/}calendar-color' => $_REQUEST['color']
				];

				$patch = new \Sabre\DAV\PropPatch($mutations);
				$caldavBackend->updateCalendar($id, $patch);
				$patch->commit();

				Libsync::build_sync_packet($channel['channel_id'], [
					'calendar' => [
						'action' => 'edit',
						'uri' => $cdavdata['uri'],
						'mutations' => $mutations,
					]
				]);
			}

			//edit calendar object via ajax request
			if($_REQUEST['submit'] === 'update_event' && $_REQUEST['uri'] && $_REQUEST['title'] && $_REQUEST['target'] && $_REQUEST['dtstart']) {

				$id = explode(':', $_REQUEST['target']);

				if(! cdav_perms($id[0],$calendars,true))
					return;

				$cdavdata = $this->get_cdav_data($id[0], 'calendarinstances');

				$timezone = ((x($_POST,'timezone_select')) ? escape_tags(trim($_POST['timezone_select'])) : '');
				$tz = (($timezone) ? $timezone : date_default_timezone_get());

				$allday = $_REQUEST['allday'];

				$uri = $_REQUEST['uri'];
				$title = $_REQUEST['title'];
				$start = datetime_convert('UTC', 'UTC', $_REQUEST['dtstart']);
				$dtstart = new \DateTime($start);
				if($_REQUEST['dtend']) {
					$end = datetime_convert('UTC', 'UTC', $_REQUEST['dtend']);
					$dtend = new \DateTime($end);
				}
				$description = $_REQUEST['description'];
				$location = $_REQUEST['location'];

				$object = $caldavBackend->getCalendarObject($id, $uri);

				$vcalendar = \Sabre\VObject\Reader::read($object['calendardata']);

				if($title)
					$vcalendar->VEVENT->SUMMARY = $title;
				if($dtstart) {
					$vcalendar->VEVENT->DTSTART = $dtstart;
					if($allday)
						$vcalendar->VEVENT->DTSTART['VALUE'] = 'DATE';
					else
						$vcalendar->VEVENT->DTSTART['TZID'] = $tz;
				}
				if($dtend) {
					$vcalendar->VEVENT->DTEND = $dtend;
					if($allday)
						$vcalendar->VEVENT->DTEND['VALUE'] = 'DATE';
					else
						$vcalendar->VEVENT->DTEND['TZID'] = $tz;
				}
				else
					unset($vcalendar->VEVENT->DTEND);

				if($description)
					$vcalendar->VEVENT->DESCRIPTION = $description;
				if($location)
					$vcalendar->VEVENT->LOCATION = $location;

				$calendarData = $vcalendar->serialize();
				$caldavBackend->updateCalendarObject($id, $uri, $calendarData);

				Libsync::build_sync_packet($channel['channel_id'], [
					'calendar' => [
						'action' => 'update_card',
						'uri' => $cdavdata['uri'],
						'carduri' => $uri,
						'card' => $calendarData
					]
				]);

				killme();
			}

			//delete calendar object via ajax request
			if($_REQUEST['delete'] && $_REQUEST['uri'] && $_REQUEST['target']) {

				$id = explode(':', $_REQUEST['target']);

				if(! cdav_perms($id[0],$calendars,true))
					return;

				$cdavdata = $this->get_cdav_data($id[0], 'calendarinstances');

				$uri = $_REQUEST['uri'];

				$caldavBackend->deleteCalendarObject($id, $uri);

				Libsync::build_sync_packet($channel['channel_id'], [
					'calendar' => [
						'action' => 'delete_card',
						'uri' => $cdavdata['uri'],
						'carduri' => $uri
					]
				]);

				killme();
			}

			//edit calendar object date/timeme via ajax request (drag and drop)
			if($_REQUEST['update'] && $_REQUEST['id'] && $_REQUEST['uri']) {

				$id = [$_REQUEST['id'][0], $_REQUEST['id'][1]];

				if(! cdav_perms($id[0],$calendars,true))
					return;

				$cdavdata = $this->get_cdav_data($id[0], 'calendarinstances');

				$timezone = ((x($_POST,'timezone_select')) ? escape_tags(trim($_POST['timezone_select'])) : '');
				$tz = (($timezone) ? $timezone : date_default_timezone_get());

				$allday = $_REQUEST['allday'];

				$uri = $_REQUEST['uri'];
				$start = datetime_convert('UTC', 'UTC', $_REQUEST['dtstart']);
				$dtstart = new \DateTime($start);
				if($_REQUEST['dtend']) {
					$end = datetime_convert('UTC', 'UTC', $_REQUEST['dtend']);
					$dtend = new \DateTime($end);
				}

				$object = $caldavBackend->getCalendarObject($id, $uri);

				$vcalendar = \Sabre\VObject\Reader::read($object['calendardata']);

				if($dtstart) {
					$vcalendar->VEVENT->DTSTART = $dtstart;
					if($allday)
						$vcalendar->VEVENT->DTSTART['VALUE'] = 'DATE';
					else
						$vcalendar->VEVENT->DTSTART['TZID'] = $tz;
				}
				if($dtend) {
					$vcalendar->VEVENT->DTEND = $dtend;
					if($allday)
						$vcalendar->VEVENT->DTEND['VALUE'] = 'DATE';
					else
						$vcalendar->VEVENT->DTEND['TZID'] = $tz;
				}
				else
					unset($vcalendar->VEVENT->DTEND);

				$calendarData = $vcalendar->serialize();
				$caldavBackend->updateCalendarObject($id, $uri, $calendarData);

				Libsync::build_sync_packet($channel['channel_id'], [
					'calendar' => [
						'action' => 'update_card',
						'uri' => $cdavdata['uri'],
						'carduri' => $uri,
						'card' => $calendarData
					]
				]);

				killme();
			}

			//share a calendar - this only works on local system (with channels on the same server)
			if($_REQUEST['sharee'] && $_REQUEST['share']) {

				$id = [intval($_REQUEST['calendarid']), intval($_REQUEST['instanceid'])];

				if(! cdav_perms($id[0],$calendars))
					return;

				$hash = $_REQUEST['sharee'];

				$sharee_arr = channelx_by_hash($hash);

				$sharee = new \Sabre\DAV\Xml\Element\Sharee();

				$sharee->href = 'mailto:' . $sharee_arr['xchan_addr'];
				$sharee->principal = 'principals/' . $sharee_arr['channel_address'];
				$sharee->access = intval($_REQUEST['access']);
				$sharee->properties = ['{DAV:}displayname' => $channel['channel_name']];

				$caldavBackend->updateInvites($id, [$sharee]);
			}
		}

		if(argc() >= 2 && argv(1) === 'addressbook') {

			$carddavBackend = new \Sabre\CardDAV\Backend\PDO($pdo);
			$addressbooks = $carddavBackend->getAddressBooksForUser($principalUri);

			//create new addressbook
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['create']) {
				do {
					$duplicate = false;
					$addressbookUri = random_string(20);

					$r = q("SELECT uri FROM addressbooks WHERE principaluri = '%s' AND uri = '%s' LIMIT 1",
						dbesc($principalUri),
						dbesc($addressbookUri)
					);

					if (count($r))
						$duplicate = true;
				} while ($duplicate == true);

				$properties = ['{DAV:}displayname' => $_REQUEST['{DAV:}displayname']];

				$carddavBackend->createAddressBook($principalUri, $addressbookUri, $properties);

				Libsync::build_sync_packet($channel['channel_id'], [
                                        'addressbook' => [
                                                'action' => 'create',
						'uri' => $addressbookUri,
                                                'properties' => $properties
                                        ]
                                ]);
			}

			//edit addressbook
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['edit'] && intval($_REQUEST['id'])) {

				$id = $_REQUEST['id'];

				if(! cdav_perms($id,$addressbooks))
					return;

				$cdavdata = $this->get_cdav_data($id, 'addressbooks');

				$mutations = [
					'{DAV:}displayname' => $_REQUEST['{DAV:}displayname']
				];

				$patch = new \Sabre\DAV\PropPatch($mutations);
				$carddavBackend->updateAddressBook($id, $patch);
				$patch->commit();

				Libsync::build_sync_packet($channel['channel_id'], [
					'addressbook' => [
						'action' => 'edit',
						'uri' => $cdavdata['uri'],
						'mutations' => $mutations,
					]
				]);
			}

			//create addressbook card
			if($_REQUEST['create'] && $_REQUEST['target'] && $_REQUEST['fn']) {

				$id = $_REQUEST['target'];

				$cdavdata = $this->get_cdav_data($id, 'addressbooks');

				do {
					$duplicate = false;
					$uri = random_string(40) . '.vcf';

					$r = q("SELECT uri FROM cards WHERE addressbookid = %s AND uri = '%s' LIMIT 1",
						intval($id),
						dbesc($uri)
					);

					if (count($r))
						$duplicate = true;
				} while ($duplicate == true);

				//TODO: this mostly duplictes the procedure in update addressbook card. should move this part to a function to avoid duplication
				$fn = $_REQUEST['fn'];

				$vcard = new \Sabre\VObject\Component\VCard([
					'FN' => $fn,
					'N' => array_reverse(explode(' ', $fn))
				]);

				$fields = $this->request_to_array($_REQUEST);

				process_cdav_card($fields, $vcard);

				$cardData = $vcard->serialize();
				$carddavBackend->createCard($id, $uri, $cardData);

				Libsync::build_sync_packet($channel['channel_id'], [
					'addressbook' => [
						'action' => 'import',
						'uri' => $cdavdata['uri'],
						'ids' => [ $uri ],
						'card' => $cardData
					]
				]);
			}

			//edit addressbook card
			if($_REQUEST['update'] && $_REQUEST['uri'] && $_REQUEST['target']) {

				$id = $_REQUEST['target'];

				if(! cdav_perms($id,$addressbooks))
					return;

				$cdavdata = $this->get_cdav_data($id, 'addressbooks');

				$uri = $_REQUEST['uri'];

				$object = $carddavBackend->getCard($id, $uri);
				$vcard = \Sabre\VObject\Reader::read($object['carddata']);

				$fn = $_REQUEST['fn'];
				if($fn) {
					$vcard->FN = $fn;
					$vcard->N = array_reverse(explode(' ', $fn));
				}

				$fields = $this->request_to_array($_REQUEST);

				process_cdav_card($fields, $vcard, true);

				$cardData = $vcard->serialize();

				$carddavBackend->updateCard($id, $uri, $cardData);

				Libsync::build_sync_packet($channel['channel_id'], [
					'addressbook' => [
						'action' => 'update_card',
						'uri' => $cdavdata['uri'],
						'carduri' => $uri,
						'card' => $cardData
					]
				]);

			}

			//delete addressbook card
			if($_REQUEST['delete'] && $_REQUEST['uri'] && $_REQUEST['target']) {

				$id = $_REQUEST['target'];

				if(! cdav_perms($id,$addressbooks))
					return;

				$cdavdata = $this->get_cdav_data($id, 'addressbooks');

				$uri = $_REQUEST['uri'];

				$carddavBackend->deleteCard($id, $uri);

				Libsync::build_sync_packet($channel['channel_id'], [
					'addressbook' => [
						'action' => 'delete_card',
						'uri' => $cdavdata['uri'],
						'carduri' => $uri
					]
				]);
			}
		}

		//Import calendar or addressbook
		if(($_FILES) && array_key_exists('userfile',$_FILES) && intval($_FILES['userfile']['size']) && $_REQUEST['target']) {

			$src = $_FILES['userfile']['tmp_name'];

			if($src) {

				$carddata = @file_get_contents($src);

				if($_REQUEST['c_upload']) {
					if($_REQUEST['target'] == 'channel_calendar') {
						$result = parse_ical_file($src,local_channel());
						if($result)
							info( t('Calendar entries imported.') . EOL);
						else
							notice( t('No calendar entries found.') . EOL);

						@unlink($src);
						return;
					}

					$id = explode(':', $_REQUEST['target']);
					$ext = 'ics';
					$table = 'calendarobjects';
					$column = 'calendarid';
					$sync = 'calendar';
					$objects = new \Sabre\VObject\Splitter\ICalendar($carddata);
					$profile = \Sabre\VObject\Node::PROFILE_CALDAV;
					$backend = new \Sabre\CalDAV\Backend\PDO($pdo);

					$cdavdata = $this->get_cdav_data($id, 'calendarinstances');
				}

				if($_REQUEST['a_upload']) {
					$id = intval($_REQUEST['target']);
					$ext = 'vcf';
					$table = 'cards';
					$column = 'addressbookid';
					$sync = 'addressbook';
					$objects = new \Sabre\VObject\Splitter\VCard($carddata);
					$profile = \Sabre\VObject\Node::PROFILE_CARDDAV;
					$backend = new \Sabre\CardDAV\Backend\PDO($pdo);

					$cdavdata = $this->get_cdav_data($id, 'addressbooks');
				}

				$ids = [];
				import_cdav_card($id, $ext, $table, $column, $objects, $profile, $backend, $ids, true);

				Libsync::build_sync_packet($channel['channel_id'], [
					$sync => [
						'action' => 'import',
						'uri' => $cdavdata['uri'],
						'ids' => $ids,
						'card' => $carddata
					]
				]);
			}
			@unlink($src);
		}
	}

	function get() {

		if(!local_channel())
			return;

		if((argv(1) === 'addressbook') && (! Apps::system_app_installed(local_channel(), 'CardDAV'))) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('CardDAV');
			return Apps::app_render($papp, 'module');
		}

		App::$profile_uid = local_channel();

		$channel = App::get_channel();
		$principalUri = 'principals/' . $channel['channel_address'];

		$pdo = \DBA::$dba->db;

		require_once 'vendor/autoload.php';

		if(!cdav_principal($principalUri)) {
			$this->activate($pdo, $channel);
			if(!cdav_principal($principalUri)) {
				return;
			}
		}

		if(argv(1) === 'calendar') {
			nav_set_selected('Calendar');
			$caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);
			$calendars = $caldavBackend->getCalendarsForUser($principalUri);
		}

		//Display calendar(s) here
		if(argc() <= 3 && argv(1) === 'calendar') {

			head_add_css('/library/fullcalendar/packages/core/main.min.css');
			head_add_css('/library/fullcalendar/packages/daygrid/main.min.css');
			head_add_css('/library/fullcalendar/packages/timegrid/main.min.css');
			head_add_css('/library/fullcalendar/packages/list/main.min.css');
			head_add_css('cdav_calendar.css');

			head_add_js('/library/fullcalendar/packages/core/main.min.js');
			head_add_js('/library/fullcalendar/packages/interaction/main.min.js');
			head_add_js('/library/fullcalendar/packages/daygrid/main.min.js');
			head_add_js('/library/fullcalendar/packages/timegrid/main.min.js');
			head_add_js('/library/fullcalendar/packages/list/main.min.js');

			$sources = '';
			$resource_id = '';
			$resource = null;

			if(argc() == 3)
				$resource_id = argv(2);

			if($resource_id) {
				$r = q("SELECT event.*, item.author_xchan, item.owner_xchan, item.plink, item.id as item_id FROM event LEFT JOIN item ON event.event_hash = item.resource_id
					WHERE event.uid = %d AND event.event_hash = '%s' LIMIT 1",
					intval(local_channel()),
					dbesc($resource_id)
				);
				if($r) {
					xchan_query($r);
					$r = fetch_post_tags($r,true);

					$tz = get_iconfig($r[0], 'event', 'timezone');
					if(! $tz)
						$tz = 'UTC';

					$r[0]['timezone'] = $tz;
					$r[0]['dtstart'] = (($r[0]['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $r[0]['dtstart'], 'c') : datetime_convert('UTC', 'UTC', $r[0]['dtstart'], 'c'));
					$r[0]['dtend'] = (($r[0]['adjust']) ? datetime_convert('UTC', date_default_timezone_get(), $r[0]['dtend'], 'c') : datetime_convert('UTC', 'UTC' ,$r[0]['dtend'], 'c'));

					$r[0]['plink'] = [$r[0]['plink'], t('Link to source')];

					$resource = $r[0];

					$catsenabled = feature_enabled(local_channel(),'categories');
					$categories = '';
					if($catsenabled){
						if($r[0]['term']) {
							$cats = get_terms_oftype($r[0]['term'], TERM_CATEGORY);
							foreach ($cats as $cat) {
								if(strlen($categories))
									$categories .= ', ';
								$categories .= $cat['term'];
							}
						}
					}

					if($r[0]['dismissed'] == 0) {
						q("UPDATE event SET dismissed = 1 WHERE event.uid = %d AND event.event_hash = '%s'",
							intval(local_channel()),
							dbesc($resource_id)
						);
					}
				}
			}

			if(get_pconfig(local_channel(), 'cdav_calendar', 'channel_calendar')) {
				$sources .= '{
					id: \'channel_calendar\',
					url: \'/channel_calendar/json/\',
					color: \'#3a87ad\'
				}, ';
			}

			$channel_calendars[] = [
				'displayname' => $channel['channel_name'],
				'id' => 'channel_calendar'
			];

			foreach($calendars as $calendar) {
				$editable = (($calendar['share-access'] == 2) ? 'false' : 'true');  // false/true must be string since we're passing it to javascript
				$color = (($calendar['{http://apple.com/ns/ical/}calendar-color']) ? $calendar['{http://apple.com/ns/ical/}calendar-color'] : '#6cad39');
				$sharer = (($calendar['share-access'] == 3) ? $calendar['{urn:ietf:params:xml:ns:caldav}calendar-description'] : '');
				$switch = get_pconfig(local_channel(), 'cdav_calendar', $calendar['id'][0]);
				if($switch) {
					$sources .= '{
						id: ' . $calendar['id'][0] . ',
						url: \'/cdav/calendar/json/' . $calendar['id'][0] . '/' . $calendar['id'][1] . '\',
						color: \'' . $color . '\'
					 }, ';
				}

				if($calendar['share-access'] != 2) {
					$writable_calendars[] = [
						'displayname' => $calendar['{DAV:}displayname'],
						'sharer' => $sharer,
						'id' => $calendar['id']
					];
				}
			}

			$sources = rtrim($sources, ', ');

			$first_day = feature_enabled(local_channel(), 'cal_first_day');
			$first_day = (($first_day) ? $first_day : 0);

			$title = ['title', t('Event title') ];
			$dtstart = ['dtstart', t('Start date and time')];
			$dtend = ['dtend', t('End date and time')];
			$timezone_select = ['timezone_select' , t('Timezone:'), date_default_timezone_get(), '', get_timezones()];

			$description = ['description', t('Description')];
			$location = ['location', t('Location')];

			$catsenabled = feature_enabled(local_channel(), 'categories');

			require_once('include/acl_selectors.php');

			$accesslist = new \Zotlabs\Access\AccessList($channel);
			$perm_defaults = $accesslist->get();

			//$acl = (($orig_event['event_xchan']) ? '' : populate_acl(((x($orig_event)) ? $orig_event : $perm_defaults), false, \Zotlabs\Lib\PermissionDescription::fromGlobalPermission('view_stream')));
			$acl = populate_acl($perm_defaults, false, \Zotlabs\Lib\PermissionDescription::fromGlobalPermission('view_stream'));

			$permissions = (($resource_id) ? $resource : $perm_defaults);

			$o .= replace_macros(get_markup_template('cdav_calendar.tpl'), [
				'$sources' => $sources,
				'$color' => $color,
				'$lang' => App::$language,
				'$timezone' => date_default_timezone_get(),
				'$first_day' => $first_day,
				'$prev'	=> t('Previous'),
				'$next'	=> t('Next'),
				'$today' => t('Today'),
				'$month' => t('Month'),
				'$week' => t('Week'),
				'$day' => t('Day'),
				'$list_month' => t('List month'),
				'$list_week' => t('List week'),
				'$list_day' => t('List day'),
				'$title' => $title,
				'$channel_calendars' => $channel_calendars,
				'$writable_calendars' => $writable_calendars,
				'$dtstart' => $dtstart,
				'$dtend' => $dtend,
				'$description' => $description,
				'$location' => $location,
				'$more' => t('More'),
				'$less' => t('Less'),
				'$update' => t('Update'),
				'$calendar_select_label' => t('Select calendar'),
				'$calendar_optiopns_label' => [t('Channel Calendars'), t('CalDAV Calendars')],
				'$delete' => t('Delete'),
				'$delete_all' => t('Delete all'),
				'$cancel' => t('Cancel'),
				'$create' => t('Create'),
				'$recurrence_warning' => t('Sorry! Editing of recurrent events is not yet implemented.'),
				'$disabled_warning' => t('Could not fetch calendar resource. The selected calendar might be disabled.'),

				'$channel_hash' => $channel['channel_hash'],
				'$acl' => $acl,
				'$lockstate' => (($accesslist->is_private()) ? 'lock' : 'unlock'),
				'$allow_cid' => acl2json($permissions['allow_cid']),
				'$allow_gid' => acl2json($permissions['allow_gid']),
				'$deny_cid' => acl2json($permissions['deny_cid']),
				'$deny_gid' => acl2json($permissions['deny_gid']),
				'$catsenabled' => $catsenabled,
				'$categories_label' => t('Categories'),

				'$resource' => json_encode($resource),
				'$categories' => $categories,
				'$timezone_select' => ((feature_enabled(local_channel(),'event_tz_select')) ? $timezone_select : '')
			]);

			return $o;

		}

		//Provide json data for calendar
		if(argc() == 5 && argv(1) === 'calendar' && argv(2) === 'json'  && intval(argv(3)) && intval(argv(4))) {

			$events = [];

			$id = [argv(3), argv(4)];

			if(! cdav_perms($id[0],$calendars))
				json_return_and_die($events);

			if (x($_GET,'start'))
				$start = new \DateTime($_GET['start']);
			if (x($_GET,'end'))
				$end = new \DateTime($_GET['end']);

			$filters['name'] = 'VCALENDAR';
			$filters['prop-filters'][0]['name'] = 'VEVENT';
			$filters['comp-filters'][0]['name'] = 'VEVENT';
			$filters['comp-filters'][0]['time-range']['start'] = $start;
			$filters['comp-filters'][0]['time-range']['end'] = $end;

			$uris = $caldavBackend->calendarQuery($id, $filters);
			if($uris) {

				$objects = $caldavBackend->getMultipleCalendarObjects($id, $uris);
				foreach($objects as $object) {

					$vcalendar = \Sabre\VObject\Reader::read($object['calendardata']);

					if(isset($vcalendar->VEVENT->RRULE)) {
						// expanding recurrent events seems to loose timezone info
						// save it here so we can add it later
						$recurrent_timezone = (string)$vcalendar->VEVENT->DTSTART['TZID'];
						$vcalendar = $vcalendar->expand($start, $end);
					}

					foreach($vcalendar->VEVENT as $vevent) {
						$title = (string)$vevent->SUMMARY;
						$dtstart = (string)$vevent->DTSTART;
						$dtend = (string)$vevent->DTEND;
						$description = (string)$vevent->DESCRIPTION;
						$location = (string)$vevent->LOCATION;
						$timezone_str = (string)$vevent->DTSTART['TZID'];
						$rw = ((cdav_perms($id[0],$calendars,true)) ? true : false);
						$editable = $rw ? true : false;
						$recurrent = ((isset($vevent->{'RECURRENCE-ID'})) ? true : false);

						if($recurrent) {
							$editable = false;
							$timezone_str = $recurrent_timezone;
						}

						// Try to get an usable olson format timezone
						$timezone_obj = \Sabre\VObject\TimeZoneUtil::getTimeZone($timezone_str, $vcalendar);
						$timezone = $timezone_obj->getName();

						// If we got nothing fallback to UTC
						if(! $timezone)
							$timezone = 'UTC';

						$allDay = (((string)$vevent->DTSTART['VALUE'] == 'DATE') ? true : false);

						$events[] = [
							'calendar_id' => $id,
							'uri' => $object['uri'],
							'title' => $title,
							'timezone' => $timezone,
							'start' => datetime_convert($timezone, date_default_timezone_get(), $dtstart, 'c'),
							'end' => (($dtend) ? datetime_convert($timezone, date_default_timezone_get(), $dtend, 'c') : ''),
							'description' => $description,
							'location' => $location,
							'allDay' => $allDay,
							'editable' => $editable,
							'recurrent' => $recurrent,
							'rw' => $rw
						];
					}
				}
			}
			json_return_and_die($events);
		}

		//enable/disable calendars
		if(argc() == 5 && argv(1) === 'calendar' && argv(2) === 'switch'  && argv(3) && (argv(4) == 1 || argv(4) == 0)) {
			$id = argv(3);

			if(! cdav_perms($id,$calendars))
				killme();

			$cdavdata = $this->get_cdav_data($id, 'calendarinstances');

			set_pconfig(local_channel(), 'cdav_calendar', $id, argv(4));

			Libsync::build_sync_packet(local_channel(), [
				'calendar' => [
					'action' => 'switch',
					'uri' => $cdavdata['uri'],
					'switch' => intval(argv(4))
				]
			]);

			killme();
		}

		//drop calendar
		if(argc() == 5 && argv(1) === 'calendar' && argv(2) === 'drop' && intval(argv(3)) && intval(argv(4))) {
			$id = [argv(3), argv(4)];

			if(! cdav_perms($id[0],$calendars))
				killme();

			// get metadata before we delete it
			$cdavdata = $this->get_cdav_data($id[0], 'calendarinstances');

			$caldavBackend->deleteCalendar($id);

			Libsync::build_sync_packet($channel['channel_id'], [
				'calendar' => [
					'action' => 'drop',
					'uri' => $cdavdata['uri']
				]
			]);

			killme();
		}

		//drop sharee
		if(argc() == 6 && argv(1) === 'calendar' && argv(2) === 'dropsharee'  && intval(argv(3)) && intval(argv(4))) {

			$id = [argv(3), argv(4)];
			$hash = argv(5);

			if(! cdav_perms($id[0],$calendars))
				killme();

			$sharee_arr = channelx_by_hash($hash);

			$sharee = new \Sabre\DAV\Xml\Element\Sharee();

			$sharee->href = 'mailto:' . $sharee_arr['xchan_addr'];
			$sharee->principal = 'principals/' . $sharee_arr['channel_address'];
			$sharee->access = 4;
			$caldavBackend->updateInvites($id, [$sharee]);

			killme();
		}


		if(argv(1) === 'addressbook') {
			nav_set_selected('CardDAV');
			$carddavBackend = new \Sabre\CardDAV\Backend\PDO($pdo);
			$addressbooks = $carddavBackend->getAddressBooksForUser($principalUri);
		}

		//Display Adressbook here
		if(argc() == 3 && argv(1) === 'addressbook' && intval(argv(2))) {

			$id = argv(2);

			$displayname = cdav_perms($id,$addressbooks);

			if(!$displayname)
				return;

			head_add_css('cdav_addressbook.css');

			$o = '';

			$sabrecards = $carddavBackend->getCards($id);
			foreach($sabrecards as $sabrecard) {
				$uris[] = $sabrecard['uri'];
			}

			if($uris) {
				$objects = $carddavBackend->getMultipleCards($id, $uris);

				foreach($objects as $object) {
					$vcard = \Sabre\VObject\Reader::read($object['carddata']);

					$photo = '';
					if($vcard->PHOTO) {
						$photo_value = strtolower($vcard->PHOTO->getValueType()); // binary or uri
						if($photo_value === 'binary') {
							$photo_type = strtolower($vcard->PHOTO['TYPE']); // mime jpeg, png or gif
							$photo = 'data:image/' . $photo_type . ';base64,' . base64_encode((string)$vcard->PHOTO);
						}
						else {
							$url = parse_url((string)$vcard->PHOTO);
							$photo = 'data:' . $url['path'];
						}
					}

					$fn = '';
					if($vcard->FN) {
						$fn = (string)$vcard->FN;
					}

					$org = '';
					if($vcard->ORG) {
						$org = (string)$vcard->ORG;
					}

					$title = '';
					if($vcard->TITLE) {
						$title = (string)$vcard->TITLE;
					}

					$tels = [];
					if($vcard->TEL) {
						foreach($vcard->TEL as $tel) {
							$type = (($tel['TYPE']) ? translate_type((string)$tel['TYPE']) : '');
							$tels[] = [
								'type' => $type,
								'nr' => (string)$tel
							];
						}
					}

					$emails = [];
					if($vcard->EMAIL) {
						foreach($vcard->EMAIL as $email) {
							$type = (($email['TYPE']) ? translate_type((string)$email['TYPE']) : '');
							$emails[] = [
								'type' => $type,
								'address' => (string)$email
							];
						}
					}

					$impps = [];
					if($vcard->IMPP) {
						foreach($vcard->IMPP as $impp) {
							$type = (($impp['TYPE']) ? translate_type((string)$impp['TYPE']) : '');
							$impps[] = [
								'type' => $type,
								'address' => (string)$impp
							];
						}
					}

					$urls = [];
					if($vcard->URL) {
						foreach($vcard->URL as $url) {
							$type = (($url['TYPE']) ? translate_type((string)$url['TYPE']) : '');
							$urls[] = [
								'type' => $type,
								'address' => (string)$url
							];
						}
					}

					$adrs = [];
					if($vcard->ADR) {
						foreach($vcard->ADR as $adr) {
							$type = (($adr['TYPE']) ? translate_type((string)$adr['TYPE']) : '');
							$adrs[] = [
								'type' => $type,
								'address' => $adr->getParts()
							];
						}
					}

					$note = '';
					if($vcard->NOTE) {
						$note = (string)$vcard->NOTE;
					}

					$cards[] = [
						'id' => $object['id'],
						'uri' => $object['uri'],

						'photo' => $photo,
						'fn' => $fn,
						'org' => $org,
						'title' => $title,
						'tels' => $tels,
						'emails' => $emails,
						'impps' => $impps,
						'urls' => $urls,
						'adrs' => $adrs,
						'note' => $note
					];
				}

				usort($cards, function($a, $b) { return strcasecmp($a['fn'], $b['fn']); });
			}

			$o .= replace_macros(get_markup_template('cdav_addressbook.tpl'), [
				'$id' => $id,
				'$cards' => $cards,
				'$displayname' => $displayname,
				'$name_label' => t('Name'),
				'$org_label' => t('Organisation'),
				'$title_label' => t('Title'),
				'$tel_label' => t('Phone'),
				'$email_label' => t('Email'),
				'$impp_label' => t('Instant messenger'),
				'$url_label' => t('Website'),
				'$adr_label' => t('Address'),
				'$note_label' => t('Note'),
				'$mobile' => t('Mobile'),
				'$home' => t('Home'),
				'$work' => t('Work'),
				'$other' => t('Other'),
				'$add_card' => t('Add Contact'),
				'$add_field' => t('Add Field'),
				'$create' => t('Create'),
				'$update' => t('Update'),
				'$delete' => t('Delete'),
				'$cancel' => t('Cancel'),
				'$po_box' => t('P.O. Box'),
				'$extra' => t('Additional'),
				'$street' => t('Street'),
				'$locality' => t('Locality'),
				'$region' => t('Region'),
				'$zip_code' => t('ZIP Code'),
				'$country' => t('Country')
			]);

			return $o;
		}

		//delete addressbook
		if(argc() > 3 && argv(1) === 'addressbook' && argv(2) === 'drop' && intval(argv(3))) {
			$id = argv(3);

			if(! cdav_perms($id,$addressbooks))
				return;

			// get metadata before we delete it
			$cdavdata = $this->get_cdav_data($id, 'addressbooks');

			$carddavBackend->deleteAddressBook($id);

			if($cdavdata)
				Libsync::build_sync_packet($channel['channel_id'], [
					'addressbook' => [
						'action' => 'drop',
						'uri' => $cdavdata['uri']
					]
				]);

			killme();
		}

	}

	function activate($pdo, $channel) {

		if(! $channel)
			return;

		$uri = 'principals/' . $channel['channel_address'];


		$r = q("select * from principals where uri = '%s' limit 1",
			dbesc($uri)
		);
		if($r) {
			$r = q("update principals set email = '%s', displayname = '%s' where uri = '%s' ",
				dbesc($channel['xchan_addr']),
				dbesc($channel['channel_name']),
				dbesc($uri)
			);
		}
		else {
			$r = q("insert into principals ( uri, email, displayname ) values('%s','%s','%s') ",
				dbesc($uri),
				dbesc($channel['xchan_addr']),
				dbesc($channel['channel_name'])
			);

			//create default calendar
			$caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);
			$properties = [
				'{DAV:}displayname' => t('Default Calendar'),
				'{http://apple.com/ns/ical/}calendar-color' => '#6cad39',
				'{urn:ietf:params:xml:ns:caldav}calendar-description' => $channel['channel_name']
			];

			$id = $caldavBackend->createCalendar($uri, 'default', $properties);
			set_pconfig(local_channel(), 'cdav_calendar' , $id[0], 1);
			set_pconfig(local_channel(), 'cdav_calendar' , 'channel_calendar', 1);

			//create default addressbook
			$carddavBackend = new \Sabre\CardDAV\Backend\PDO($pdo);
			$properties = ['{DAV:}displayname' => t('Default Addressbook')];
			$carddavBackend->createAddressBook($uri, 'default', $properties);

		}
	}


	function get_cdav_data($id, $table) {

		$r = q("SELECT * FROM $table WHERE id = %d LIMIT 1",
			intval($id)
		);

		if(! $r)
			return false;

		return $r[0];
	}

	function request_to_array($req) {

		$f = [];

 		$f['org'] = $req['org'];
		$f['title'] = $req['title'];
		$f['tel'] = $req['tel'];
		$f['tel_type'] = $req['tel_type'];
		$f['email'] = $req['email'];
		$f['email_type'] = $req['email_type'];
		$f['impp'] = $req['impp'];
		$f['impp_type'] = $req['impp_type'];
		$f['url'] = $req['url'];
		$f['url_type'] = $req['url_type'];
		$f['adr'] = $req['adr'];
		$f['adr_type'] = $req['adr_type'];
		$f['note'] = $req['note'];

		return $f;
	}
}
