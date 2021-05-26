<?php

use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Verify;

function is_matrix_url($url) {

	// in-memory cache to avoid repeated queries for the same host
	static $remembered = [];

	$m = @parse_url($url);
	if($m['host']) {

		if(array_key_exists($m['host'],$remembered))
			return $remembered[$m['host']];

		$r = q("select hubloc_url from hubloc where hubloc_host = '%s' and hubloc_network = 'zot6' limit 1",
			dbesc($m['host'])
		);
		if($r) {
			$remembered[$m['host']] = true;
			return true;
		}
		$remembered[$m['host']] = false;
	}

	return false;
}

/**
 * @brief Adds a zid parameter to a url.
 *
 * @param string $s
 *   The url to accept the zid
 * @param boolean $address
 *   $address to use instead of session environment
 * @return string
 */
function zid($s, $address = '') {
	if (! strlen($s) || strpos($s,'zid='))
		return $s;

	$m = parse_url($s);
	$fragment = ((array_key_exists('fragment',$m) && $m['fragment']) ? $m['fragment'] : false);
	if($fragment !== false)
		$s = str_replace('#' . $fragment,'',$s);

	$has_params = ((strpos($s,'?')) ? true : false);
	$num_slashes = substr_count($s, '/');
	if (! $has_params)
		$has_params = ((strpos($s, '&')) ? true : false);

	$achar = strpos($s,'?') ? '&' : '?';

	$mine = get_my_url();
	$myaddr = (($address) ? $address : get_my_address());

	$mine_parsed = parse_url($mine);
	$s_parsed = parse_url($s);

	if(isset($mine_parsed['host']) && isset($s_parsed['host']) && $mine_parsed['host'] === $s_parsed['host'])
		$url_match = true;

	if ($mine && $myaddr && (! $url_match))
		$zurl = $s . (($num_slashes >= 3) ? '' : '/') . (($achar === '?') ? '?f=&' : '&') . 'zid=' . urlencode($myaddr);
	else
		$zurl = $s;

	// put fragment at the end

	if($fragment)
		$zurl .= '#' . $fragment;

	$arr = [
		'url' => $s,
		'zid' => urlencode($myaddr),
		'result' => $zurl
	];
	/**
	 * @hooks zid
	 *   Called when adding the observer's zid to a URL.
	 *   * \e string \b url - url to accept zid
	 *   * \e string \b zid - urlencoded zid
	 *   * \e string \b result - the return string we calculated, change it if you want to return something else
	 */
	call_hooks('zid', $arr);

	return $arr['result'];
}


function strip_query_param($s, $param) {
	return drop_query_params($s, [$param]);
	//return preg_replace('/[\?&]' . $param . '=(.*?)(&|$)/ism','$2',$s);
}

function strip_zids($s) {
	return drop_query_params($s, ['zid']);
	//return preg_replace('/[\?&]zid=(.*?)(&|$)/ism','$2',$s);
}

function strip_owt($s) {
	return drop_query_params($s, ['owt']);
	//return preg_replace('/[\?&]owt=(.*?)(&|$)/ism','$2',$s);
}

function strip_zats($s) {
	return drop_query_params($s, ['zat']);
	//return preg_replace('/[\?&]zat=(.*?)(&|$)/ism','$2',$s);
}

function strip_escaped_zids($s) {
	$x = preg_replace('/&amp\;zid=(.*?)(&|$)/ism','$2',$s);
	return strip_query_param($x,'f');
}


function clean_query_string($s = '') {

	$x = (($s) ? $s : \App::$query_string);
	return drop_query_params($x, ['zid', 'owt', 'zat', 'sort', 'f']);

/*
	$x = strip_zids(($s) ? $s : \App::$query_string);
	$x = strip_owt($x);
	$x = strip_zats($x);
	$x = strip_query_param($x,'sort');

	return strip_query_param($x,'f');
*/
}

/**
 * @brief Remove parameters from query string.
 *
 * @param string $s
 *   The query string
 * @param array $p
 *   $p array of parameters to remove
 * @return string
 */

function drop_query_params($s, $p) {
		$parsed = parse_url($s);

		$query = '';
		$query_args = null;
		if(isset($parsed['query'])) {
			parse_str($parsed['query'], $query_args);
		}

		if(is_array($query_args)) {
			foreach($query_args as $k => $v) {
				if(in_array($k, $p))
					continue;
				$query .= (($query) ? '&' : '') . urlencode($k) . '=' . urlencode($v);
			}
		}

		if($query)
			$parsed['query'] = $query;

		return unparse_url($parsed);
}


/**
 * zidify_callback() and zidify_links() work together to turn any HTML a tags with class="zrl" into zid links
 * These will typically be generated by a bbcode '[zrl]' tag. This is done inside prepare_text() rather than bbcode()
 * because the latter is used for general purpose conversions and the former is used only when preparing text for
 * immediate display.
 *
 * @TODO Issues: Currently the order of HTML parameters in the text is somewhat rigid and inflexible.
 *    We assume it looks like \<a class="zrl" href="xxxxxxxxxx"\> and will not work if zrl and href appear in a different order.
 *
 * @param array $match
 * @return string
 */
function zidify_callback($match) {

	$arr = [ 'zid' => ((strpos($match[1],'zrl')) ? true : false), 'url' => $match[2] ];
	call_hooks('zidify', $arr);

	$replace = '<a' . $match[1] . ' href="' . (intval($arr['zid']) ? zid($arr['url']) : $arr['url']) . '"';

	$x = str_replace($match[0], $replace, $match[0]);

	return $x;
}

function zidify_img_callback($match) {

	$arr = [ 'zid' => ((strpos($match[1],'zrl')) ? true : false), 'url' => $match[2] ];
	call_hooks('zidify', $arr);

	$replace = '<img' . $match[1] . ' src="' . (intval($arr['zid']) ? zid($arr['url']) : $arr['url']) . '"';

	$x = str_replace($match[0], $replace, $match[0]);

	return $x;
}


function zidify_links($s) {
	$s = preg_replace_callback('/\<a(.*?)href\=\"(.*?)\"/ism','zidify_callback',$s);
	$s = preg_replace_callback('/\<img(.*?)src\=\"(.*?)\"/ism','zidify_img_callback',$s);

	return $s;
}


function zidify_text_callback($match) {
	$is_zid = is_matrix_url($match[2]);
	$replace = '<a' . $match[1] . ' href="' . (($is_zid) ? zid($match[2]) : $match[2]) . '"';

	$x = str_replace($match[0], $replace, $match[0]);

	return $x;
}

function zidify_text_img_callback($match) {
	$is_zid = is_matrix_url($match[2]);
	$replace = '<img' . $match[1] . ' src="' . (($is_zid) ? zid($match[2]) : $match[2]) . '"';

	$x = str_replace($match[0], $replace, $match[0]);

	return $x;
}

function zidify_text($s) {

	$s = preg_replace_callback('/\<a(.*?)href\=\"(.*?)\"/ism','zidify_text_callback',$s);
	$s = preg_replace_callback('/\<img(.*?)src\=\"(.*?)\"/ism','zidify_text_img_callback',$s);

	return $s;
}


/**
 * @brief preg_match function when fixing 'naked' links in mod item.php.
 *
 * Check if we've got a hubloc for the site and use a zrl if we do, a url if we don't.
 * Remove any existing zid= param which may have been pasted by mistake - and will have
 * the author's credentials. zid's are dynamic and can't really be passed around like
 * that.
 *
 * @param array $matches
 * @return string
 */
function red_zrl_callback($matches) {

    // Catch and exclude trailing punctuation
    preg_match("/[.,;:!?)]*$/i", $matches[2], $pts);
    $matches[2] = substr($matches[2], 0, strlen($matches[2])-strlen($pts[0]));

    $zrl = is_matrix_url($matches[2]);

    $t = strip_zids($matches[2]);
    if($t !== $matches[2]) {
        $zrl = true;
        $matches[2] = $t;
    }

    if($matches[1] === '#^')
        $matches[1] = '';

    if($zrl)
        return $matches[1] . '#^[zrl=' . $matches[2] . ']' . $matches[2] . '[/zrl]' . $pts[0];

    return $matches[1] . '#^[url=' . $matches[2] . ']' . $matches[2] . '[/url]' . $pts[0];
}

/**
 * If we've got a url or zrl tag with a naked url somewhere in the link text,
 * escape it with quotes unless the naked url is a linked photo.
 *
 * @param array $matches
 * @return string
 */
function red_escape_zrl_callback($matches) {

	// Uncertain why the url/zrl forms weren't picked up by the non-greedy regex.

	if((strpos($matches[3], 'zmg') !== false) || (strpos($matches[3], 'img') !== false) || (strpos($matches[3],'zrl') !== false) || (strpos($matches[3],'url') !== false))
		return $matches[0];

	return '[' . $matches[1] . 'rl' . $matches[2] . ']' . $matches[3] . '"' . $matches[4] . '"' . $matches[5] . '[/' . $matches[6] . 'rl]';
}

function red_escape_codeblock($m) {
	return '[$b64' . $m[2] . base64_encode($m[1]) . '[/' . $m[2] . ']';
}

function red_unescape_codeblock($m) {
	return '[' . $m[2] . base64_decode($m[1]) . '[/' . $m[2] . ']';
}


function red_zrlify_img_callback($matches) {

	$zrl = is_matrix_url($matches[2]);

	$t = strip_zids($matches[2]);
	if($t !== $matches[2]) {
		$zrl = true;
		$matches[2] = $t;
	}

	if($zrl)
		return '[zmg' . $matches[1] . ']' . $matches[2] . '[/zmg]';

	return $matches[0];
}


/**
 * @brief OpenWebAuth authentication.
 *
 * @param string $token
 */
function owt_init($token) {

	Verify::purge('owt', '3 MINUTE');

	$ob_hash = Verify::get_meta('owt', 0, $token);

	if($ob_hash === false) {
		return;
	}

	$r = q("select * from hubloc left join xchan on xchan_hash = hubloc_hash
		where hubloc_id_url = '%s' order by hubloc_id desc",
		dbesc($ob_hash)
	);

	if(! $r) {
		// finger them if they can't be found.
		$wf = discover_by_webbie($ob_hash);
		if($wf) {
			$r = q("select * from hubloc left join xchan on xchan_hash = hubloc_hash
				where hubloc_id_url = '%s' order by hubloc_id desc",
				dbesc($ob_hash)
			);
		}
	}
	if(! $r) {
		logger('owt: unable to finger ' . $ob_hash);
		return;
	}

	$r = Libzot::zot_record_preferred($r);

	$hubloc = $r;

	$_SESSION['authenticated'] = 1;

	$delegate_success = false;
	if($_REQUEST['delegate']) {
		$r = q("select * from channel left join xchan on channel_hash = xchan_hash where xchan_addr = '%s' limit 1",
			dbesc($_REQUEST['delegate'])
		);
		if ($r && intval($r[0]['channel_id'])) {
			$allowed = perm_is_allowed($r[0]['channel_id'],$hubloc['xchan_hash'],'delegate');
			if($allowed) {
				$_SESSION['delegate_channel'] = $r[0]['channel_id'];
				$_SESSION['delegate'] = $hubloc['xchan_hash'];
				$_SESSION['account_id'] = intval($r[0]['channel_account_id']);
				require_once('include/security.php');
				// this will set the local_channel authentication in the session
				change_channel($r[0]['channel_id']);
				$delegate_success = true;
			}
		}
	}

	if (! $delegate_success) {
		// normal visitor (remote_channel) login session credentials
		$_SESSION['visitor_id'] = $hubloc['xchan_hash'];
		$_SESSION['my_url'] = $hubloc['xchan_url'];
		$_SESSION['my_address'] = $hubloc['hubloc_addr'];
		$_SESSION['remote_hub'] = $hubloc['hubloc_url'];
		$_SESSION['DNT'] = 1;
	}

	$arr = [
			'xchan' => $hubloc,
			'url' => App::$query_string,
			'session' => $_SESSION
	];
	/**
	 * @hooks magic_auth_success
	 *   Called when a magic-auth was successful.
	 *   * \e array \b xchan
	 *   * \e string \b url
	 *   * \e array \b session
	 */
	call_hooks('magic_auth_success', $arr);

	App::set_observer($hubloc);
	require_once('include/security.php');
	App::set_groups(init_groups_visitor($_SESSION['visitor_id']));
	if(! get_config('system', 'hide_owa_greeting'))
		info(sprintf( t('OpenWebAuth: %1$s welcomes %2$s'),App::get_hostname(), $hubloc['xchan_name']));

	logger('OpenWebAuth: auth success from ' . $hubloc['xchan_addr']);
}


function observer_auth($ob_hash) {

	if($ob_hash === false) {
		return;
	}

	$r = q("select * from hubloc left join xchan on xchan_hash = hubloc_hash
		where hubloc_addr = '%s' or hubloc_id_url = '%s' or hubloc_hash = '%s' order by hubloc_id desc",
		dbesc($ob_hash),
		dbesc($ob_hash),
		dbesc($ob_hash)
	);

	if(! $r) {
		// finger them if they can't be found.
		$wf = discover_by_webbie($ob_hash);
		if($wf) {
			$r = q("select * from hubloc left join xchan on xchan_hash = hubloc_hash
				where hubloc_addr = '%s' or hubloc_id_url = '%s' or hubloc_hash = '%s' order by hubloc_id desc",
				dbesc($ob_hash),
				dbesc($ob_hash),
				dbesc($ob_hash)
			);
		}
	}
	if(! $r) {
		logger('unable to finger ' . $ob_hash);
		return;
	}

	$hubloc = Libzot::zot_record_preferred($r);

	$_SESSION['authenticated'] = 1;

	// normal visitor (remote_channel) login session credentials
	$_SESSION['visitor_id'] = $hubloc['xchan_hash'];
	$_SESSION['my_url'] = $hubloc['xchan_url'];
	$_SESSION['my_address'] = $hubloc['hubloc_addr'];
	$_SESSION['remote_hub'] = $hubloc['hubloc_url'];
	$_SESSION['DNT'] = 1;

	App::set_observer($hubloc);
	require_once('include/security.php');
	App::set_groups(init_groups_visitor($_SESSION['visitor_id']));

}
