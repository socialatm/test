<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Lang extends Controller {

	const MYP = 	'ZIN';
	const VERSION =	'2.0.0';

	function post() {

		$re = [];
		$isajax = is_ajax();
		$eol 	= $isajax ? "\n" : EOL;

		if (! Apps::system_app_installed(local_channel(), 'Language')) {
			$re['msg'] = 'ZIN0202E, ' . t('Language App') . ' (' . t('Not Installed') . ')' ;
			notice( $re['msg'] . EOL);
			if ($isajax) {
				echo json_encode( $re );
				killme();
				exit;
			} else {
				return;
			}
		}

		$lc = x($_POST['zinlc']) && preg_match('/^\?\?|[a-z]{2,2}[x_\-]{0,1}[a-zA-Z]{0,2}$/', $_POST['zinlc'])
			? $_POST['zinlc'] : '';
		$lcs= x($_POST['zinlcs']) && preg_match('/^[a-z,_\-]{0,191}$/', $_POST['zinlcs'])
			? $_POST['zinlcs'] : '';

		if ($isajax) {

			if ($lc == '??') {
				$re['lc'] = get_best_language();
				$re['lcs'] = language_list();
			} else {
				$re['lc'] = $lc;
				$re['alc'] = App::$language;
				$re['slc'] = $_SESSION['language'];
				$_SESSION['language'] = $lc;
				App::$language = $lc;
				load_translation_table($lc, true);
			}

			echo json_encode( $re );
			killme();
			exit;
		}
	}

	function get() {

		if(local_channel()) {
			if(! Apps::system_app_installed(local_channel(), 'Language')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Language');
			return Apps::app_render($papp, 'module');
			}
		}

		nav_set_selected('Language');
		return lang_selector();

	}

}
