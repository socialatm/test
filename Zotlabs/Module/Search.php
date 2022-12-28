<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Zotfinger;

class Search extends Controller {

	function init() {
		if (x($_REQUEST, 'search'))
			App::$data['search'] = escape_tags($_REQUEST['search']);
	}

	function get($update = 0, $load = false) {

		if ((get_config('system', 'block_public')) || (get_config('system', 'block_public_search'))) {
			if ((!local_channel()) && (!remote_channel())) {
				notice(t('Public access denied.') . EOL);
				return;
			}
		}

		nav_set_selected('Search');

		require_once('include/bbcode.php');
		require_once('include/conversation.php');
		require_once('include/items.php');
		require_once('include/security.php');


		$format = $_REQUEST['format'] ?? '';
		if ($format !== '') {
			$update = $load = 1;
		}

		$observer      = App::get_observer();
		$observer_hash = (($observer) ? $observer['xchan_hash'] : '');

		$o = '<div class="generic-content-wrapper-styled">' . "\r\n";

		$o .= '<h2>' . t('Search') . '</h2>';

		if (x(App::$data, 'search'))
			$search = trim(App::$data['search']);
		else
			$search = ((x($_GET, 'search')) ? trim(escape_tags(rawurldecode($_GET['search']))) : '');

		$tag = false;
		if (x($_GET, 'tag')) {
			$tag    = true;
			$search = ((x($_GET, 'tag')) ? trim(escape_tags(rawurldecode($_GET['tag']))) : '');
		}

		$o .= search($search, 'search-box', '/search', ((local_channel()) ? true : false));

		if (local_channel() && strpos($search, 'https://') === 0 && !$update && !$load) {

			$url = htmlspecialchars_decode($search);

			if (strpos($url, 'b64.') !== false) {
				if (strpos($url, '?') !== false) {
					$url = strtok($url, '?');
				}

				$url = unpack_link_id(basename($url));
			}

			$f = Libzot::fetch_conversation(App::get_channel(), punify($url), true);

			if ($f) {
				$mid = $f[0]['message_id'];
				foreach ($f as $m) {
					if (strpos($search, $m['message_id']) === 0) {
						$mid = $m['message_id'];
						break;
					}
				}

				goaway(z_root() . '/hq/' . gen_link_id($mid));
			}
			else {
				// try other fetch providers (e.g. diaspora, pubcrawl)
				$hookdata = [
					'url' => punify($url)
				];
				call_hooks('fetch_provider', $hookdata);
			}
		}

		if (strpos($search, '#') === 0) {
			$tag    = true;
			$search = substr($search, 1);
		}
		elseif(strpos($search, '@') === 0) {
			$search = substr($search, 1);
			goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
		}
		elseif(strpos($search, '!') === 0) {
			$search = substr($search, 1);
			goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
		}
		elseif(strpos($search, '?') === 0) {
			$search = substr($search, 1);
			goaway(z_root() . '/help' . '?f=1&navsearch=1&search=' . $search);
		}

		// look for a naked webbie
		if (strpos($search, '@') !== false && strpos($search, 'http') !== 0) {
			goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
		}

		if (!$search)
			return $o;

		if ($tag) {
			$wildtag   = str_replace('*', '%', $search);
			$sql_extra = sprintf(" AND item.id IN (select oid from term where otype = %d and ttype in ( %d , %d) and term like '%s') ",
				intval(TERM_OBJ_POST),
				intval(TERM_HASHTAG),
				intval(TERM_COMMUNITYTAG),
				dbesc(protect_sprintf($wildtag))
			);
		}
		else {
			$regstr    = db_getfunc('REGEXP');
			$sql_extra = sprintf(" AND (item.title $regstr '%s' OR item.body $regstr '%s') ", dbesc(protect_sprintf(preg_quote($search))), dbesc(protect_sprintf(preg_quote($search))));
		}

		// Here is the way permissions work in the search module...
		// Only public posts can be shown
		// OR your own posts if you are a logged in member
		// No items will be shown if the member has a blocked profile wall.


		if ((!$update) && (!$load)) {

			// This is ugly, but we can't pass the profile_uid through the session to the ajax updater,
			// because browser prefetching might change it on us. We have to deliver it with the page.

			$o .= '<div id="live-search"></div>' . "\r\n";
			$o .= "<script> var profile_uid = " . ((intval(local_channel())) ? local_channel() : (-1))
				. "; var netargs = '?f='; var profile_page = " . App::$pager['page'] . "; </script>\r\n";

			App::$page['htmlhead'] = replace_macros(get_markup_template("build_query.tpl"), [
				'$baseurl' => z_root(),
				'$pgtype'  => 'search',
				'$uid'     => App::$profile['profile_uid'] ?? '0',
				'$gid'     => '0',
				'$cid'     => '0',
				'$cmin'    => '(-1)',
				'$cmax'    => '(-1)',
				'$star'    => '0',
				'$liked'   => '0',
				'$conv'    => '0',
				'$spam'    => '0',
				'$fh'      => '0',
				'$dm'      => '0',
				'$nouveau' => '0',
				'$wall'    => '0',
				'$list'    => ((x($_REQUEST, 'list')) ? intval($_REQUEST['list']) : 0),
				'$page'    => ((App::$pager['page'] != 1) ? App::$pager['page'] : 1),
				'$search'  => (($tag) ? urlencode('#') : '') . $search,
				'$xchan'   => '',
				'$order'   => '',
				'$file'    => '',
				'$cats'    => '',
				'$tags'    => '',
				'$mid'     => '',
				'$verb'    => '',
				'$net'     => '',
				'$dend'    => '',
				'$dbegin'  => ''
			]);


		}

		$r = null;

		if (($update) && ($load)) {
			$itemspage = get_pconfig(local_channel(), 'system', 'itemspage');
			App::set_pager_itemspage(((intval($itemspage)) ? $itemspage : 10));
			$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));

			$item_normal = item_normal_search();
			$pub_sql     = item_permissions_sql(0, $observer_hash);

			$sys = get_sys_channel();

			// in case somebody turned off public access to sys channel content using permissions
			// make that content unsearchable by ensuring the owner uid can't match
			$sys_id = perm_is_allowed($sys['channel_id'], $observer_hash, 'view_stream') ? $sys['channel_id'] : 0;

			if ($load) {
				if (local_channel()) {
					$r = q("SELECT mid, MAX(id) AS item_id FROM item
						WHERE (( item.allow_cid = '' AND item.allow_gid = '' AND item.deny_cid  = '' AND item.deny_gid  = '' AND item.item_private = 0 )
						OR ( item.uid = %d ))
						$item_normal
						$sql_extra
						GROUP BY mid, created ORDER BY created DESC $pager_sql ",
						intval(local_channel())
					);
				}

				if ($r === null) {
					$r = q("SELECT mid, MAX(id) AS item_id FROM item
						WHERE (((( item.allow_cid = '' AND item.allow_gid = '' AND item.deny_cid  = ''	AND item.deny_gid  = '' AND item.item_private = 0 )
						AND item.uid IN ( " . stream_perms_api_uids(($observer_hash) ? (PERMS_NETWORK | PERMS_PUBLIC) : PERMS_PUBLIC) . " ))
						$pub_sql ) OR item.uid = %d)
						$item_normal
						$sql_extra
						GROUP BY mid, created ORDER BY created DESC $pager_sql",
						intval($sys_id)
					);
				}

				if ($r) {
					$str = ids_to_querystr($r, 'item_id');
					$r   = dbq("select *, id as item_id from item where id in ( " . $str . ") order by created desc");
				}
			}
		}

		$items = [];

		if ($r) {
			xchan_query($r);
			$items = fetch_post_tags($r, true);
		}

		if ($format === 'json') {
			$result = [];
			require_once('include/conversation.php');
			foreach ($items as $item) {
				$item['html'] = zidify_links(bbcode($item['body']));
				$x            = encode_item($item);
				$x['html']    = prepare_text($item['body'], $item['mimetype']);
				$result[]     = $x;
			}
			json_return_and_die(['success' => true, 'messages' => $result]);
		}

		if ($tag)
			$o .= '<h2>' . sprintf(t('Items tagged with: %s'), $search) . '</h2>';
		else
			$o .= '<h2>' . sprintf(t('Search results for: %s'), $search) . '</h2>';

		$o .= conversation($items, 'search', $update, 'client');

		$o .= '</div>';

		return $o;
	}


}
