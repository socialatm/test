<?php
namespace Zotlabs\Module;


class Dreport extends \Zotlabs\Web\Controller {

	function get() {

		if(! local_channel()) {
			notice( t('Permission denied') . EOL);
			return;
		}

		$table = 'item';
		$channel = \App::get_channel();
		$mid = ((argc() > 1) ? unpack_link_id(argv(1)) : '');

		if($mid === 'push') {
			$table = 'push';
			$mid = ((argc() > 2) ? unpack_link_id(argv(2)) : '');

			if($mid) {
				$i = q("select id from item where mid = '%s' and uid = %d and ( author_xchan = '%s' or ( owner_xchan = '%s' and item_wall = 1 )) ",
					dbesc($mid),
					intval($channel['channel_id']),
					dbesc($channel['channel_hash']),
					dbesc($channel['channel_hash'])
				);
				if($i) {
					\Zotlabs\Daemon\Master::Summon([ 'Notifier', 'edit_post', $i[0]['id'] ]);
				}
			}
			sleep(3);
			goaway(z_root() . '/dreport/' . gen_link_id($mid));
		}

		if(! $mid) {
			notice( t('Invalid message') . EOL);
			return;
		}

		switch($table) {
			case 'item':
				$i = q("select id from item where mid = '%s' and ( author_xchan = '%s' or ( owner_xchan = '%s' and item_wall = 1 )) ",
					dbesc($mid),
					dbesc($channel['channel_hash']),
					dbesc($channel['channel_hash'])
				);
				break;
			default:
				break;
		}

		if(! $i) {
			notice( t('Permission denied') . EOL);
			return;
		}

		$r = q("select * from dreport where dreport_xchan = '%s' and (dreport_mid = '%s' or dreport_mid = '%s' or dreport_mid = '%s' or dreport_mid = '%s')",
			dbesc($channel['channel_hash']),
			dbesc($mid),
			dbesc($mid . '#sync'),
			dbesc(str_replace('/item/', '/activity/', $mid)),
			dbesc(str_replace('/item/', '/activity/', $mid) . '#sync')
		);

		if(! $r) {
			notice( t('no results') . EOL);
//			return;
		}

		for($x = 0; $x < count($r); $x++ ) {

			// This has two purposes: 1. make the delivery report strings translateable, and
			// 2. assign an ordering to item delivery results so we can group them and provide
			// a readable report with more interesting events listed toward the top and lesser
			// interesting items towards the bottom

			switch($r[$x]['dreport_result']) {
				case 'channel sync processed':
					$r[$x]['gravity'] = 0;
					$r[$x]['dreport_result'] = t('channel sync processed');
					break;
				case 'queued':
					$r[$x]['gravity'] = 2;
					$r[$x]['dreport_result'] = t('queued');
					break;
				case 'posted':
					$r[$x]['gravity'] = 3;
					$r[$x]['dreport_result'] = t('posted');
					break;
				case 'accepted for delivery':
					$r[$x]['gravity'] = 4;
					$r[$x]['dreport_result'] = t('accepted for delivery');
					break;
				case 'updated':
					$r[$x]['gravity'] = 5;
					$r[$x]['dreport_result'] = t('updated');
					break;
				case 'update ignored':
					$r[$x]['gravity'] = 6;
					$r[$x]['dreport_result'] = t('update ignored');
					break;
				case 'permission denied':
					$r[$x]['dreport_result'] = t('permission denied');
					$r[$x]['gravity'] = 6;
					break;
				case 'recipient not found':
					$r[$x]['dreport_result'] = t('recipient not found');
					break;
				default:
					$r[$x]['gravity'] = 1;
					break;
			}
		}

		usort($r,'self::dreport_gravity_sort');

		$entries = array();
		foreach($r as $rr) {
			$entries[] = [
				'name' => escape_tags($rr['dreport_name'] ?: $rr['dreport_recip']),
				'result' => escape_tags($rr['dreport_result']),
				'time' => escape_tags(datetime_convert('UTC',date_default_timezone_get(),$rr['dreport_time']))
			];
		}

		$o = replace_macros(get_markup_template('dreport.tpl'), array(
			'$title' => sprintf( t('Delivery report for %1$s'),basename($mid)) . '...',
			'$table' => $table,
			'$mid' => urlencode($mid),
			'$safe_mid' => urlencode(gen_link_id($mid)),
			'$options' => t('Options'),
			'$push' => t('Redeliver'),
			'$entries' => $entries
		));


		return $o;



	}

	private static function dreport_gravity_sort($a,$b) {
		if($a['gravity'] == $b['gravity']) {
			if($a['dreport_name'] === $b['dreport_name'])
				return strcmp($a['dreport_time'],$b['dreport_time']);
			return strcmp($a['dreport_name'],$b['dreport_name']);
		}
		return (($a['gravity'] > $b['gravity']) ? 1 : (-1));
	}

}
