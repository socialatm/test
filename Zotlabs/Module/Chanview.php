<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Webfinger;
use Zotlabs\Lib\Zotfinger;


class Chanview extends \Zotlabs\Web\Controller {

	function get() {

		$observer = App::get_observer();
		$xchan = null;

		$r = null;

		if($_REQUEST['hash']) {
			$r = q("select * from xchan where xchan_hash = '%s' and xchan_deleted = 0",
				dbesc($_REQUEST['hash'])
			);
		}
		if($_REQUEST['address']) {
			$r = q("select * from xchan where xchan_addr = '%s' and xchan_deleted = 0",
				dbesc(punify($_REQUEST['address']))
			);
		}
		elseif(local_channel() && intval($_REQUEST['cid'])) {
			$r = q("SELECT abook.*, xchan.*
				FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE abook_channel = %d and abook_id = %d and xchan_deleted = 0",
				intval(local_channel()),
				intval($_REQUEST['cid'])
			);
		}
		elseif($_REQUEST['url']) {

			// if somebody re-installed they will have more than one xchan, use the most recent name date as this is
			// the most useful consistently ascending table item we have.

			$r = q("select * from xchan where xchan_url = '%s' and xchan_deleted = 0 order by xchan_name_date desc",
				dbesc($_REQUEST['url'])
			);
		}
		if($r) {
			App::$poi = Libzot::zot_record_preferred($r, 'xchan_network');
		}


		// Here, let's see if we have an xchan. If we don't, how we proceed is determined by what
		// info we do have. If it's a URL, we can offer to visit it directly. If it's a webbie or
		// address, we can and should try to import it. If it's just a hash, we can't continue, but we
		// probably wouldn't have a hash if we don't already have an xchan for this channel.

		if(! App::$poi) {
			logger('mod_chanview: fallback');

			if($_REQUEST['address']) {
				$href = Webfinger::zot_url(punify($_REQUEST['address']));
				if($href) {
					$_REQUEST['url'] = $href;
				}
			}

			$r = null;

			if($_REQUEST['url']) {

				$zf = Zotfinger::exec($_REQUEST['url'], null);

				if(array_path_exists('signature/signer',$zf) && $zf['signature']['signer'] === $_REQUEST['url'] && intval($zf['signature']['header_valid'])) {
					Libzot::import_xchan($zf['data']);
					$r = q("select * from xchan where xchan_url = '%s' and xchan_deleted = 0",
						dbesc($_REQUEST['url'])
					);
					if($r) {
						App::$poi = Libzot::zot_record_preferred($r, 'xchan_network');
					}
				}
				if(! $r) {
					if(discover_by_webbie($_REQUEST['url'])) {
						$r = q("select * from xchan where xchan_url = '%s' and xchan_deleted = 0",
							dbesc($_REQUEST['url'])
						);
						if($r) {
							App::$poi = Libzot::zot_record_preferred($r, 'xchan_network');
						}
					}
				}
			}
		}

		if(! App::$poi) {
			notice( t('Channel not found.') . EOL);
			return;
		}

		$is_zot = false;
		$connected = false;

		$url = App::$poi['xchan_url'];
		if(App::$poi['xchan_network'] === 'zot6') {
			$is_zot = true;
		}
		if(local_channel()) {
			$c = q("select abook_id from abook where abook_channel = %d and abook_xchan = '%s' limit 1",
				intval(local_channel()),
				dbesc(App::$poi['xchan_hash'])
			);
			if($c)
				$connected = true;
		}

		// We will load the chanview template if it's a foreign network,
		// just so that we can provide a connect button along with a profile
		// photo. Chances are we can't load the remote profile into an iframe
		// because of cross-domain security headers. So provide a link to
		// the remote profile.
		// If we are already connected, just go to the profile.
		// Zot channels will usually have a connect link.

		if($is_zot || $connected) {
			if($is_zot && $observer) {
				$url = zid($url);
			}
			goaway($url);
		}
		else {
			$o = replace_macros(get_markup_template('chanview.tpl'),array(
				'$url' => $url,
				'$full' => t('toggle full screen mode')
			));

			return $o;
		}
	}

}
