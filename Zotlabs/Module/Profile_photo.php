<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Daemon\Master;
use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

/*
 * @file Profile_photo.php
 * @brief Module-file with functions for handling of profile-photos
 *
 */


require_once('include/photo/photo_driver.php');
require_once('include/photos.php');
require_once('include/channel.php');

/* @brief Function for sync'ing  permissions of profile-photos and their profile
 *
 */
class Profile_photo extends Controller {


	/* @brief Initalize the profile-photo edit view
	 *
	 */

	function init() {

		if (!local_channel()) {
			return;
		}

		$channel = App::get_channel();
		$profile = App::$argv[1];

		profile_load($channel['channel_address'], $profile);


	}

	/* @brief Evaluate posted values
	 *
	 */

	function post() {

		if (!local_channel()) {
			return;
		}

		$channel = App::get_channel();

		check_form_security_token_redirectOnErr('/profile_photo', 'profile_photo');

		$r = q("select id, profile_guid, is_default, gender from profile where uid = %d",
			intval(local_channel())
		);

		$profile_id         = intval($_POST['profile']);
		$default_profile_id = null;
		$profile            = [];

		foreach ($r as $rr) {
			if ($rr['is_default']) {
				$default_profile_id = intval($rr['id']);
			}

			if ($profile_id === intval($rr['id'])) {
				$profile = $rr;
			}
		}

		$is_default_profile = ($profile_id === $default_profile_id);

		// Remove profile photo
		if (isset($_POST['remove'])) {

			if ($is_default_profile) {

				$r = q("SELECT resource_id FROM photo WHERE photo_usage = %d AND uid = %d LIMIT 1",
					intval(PHOTO_PROFILE),
					intval(local_channel())
				);

				if ($r) {
					q("update photo set photo_usage = %d where photo_usage = %d and uid = %d",
						intval(PHOTO_NORMAL),
						intval(PHOTO_PROFILE),
						intval(local_channel())
					);

					q("update profile set photo = '%s', thumb = '%s' where is_default = 1 and uid = %d",
						dbesc(z_root() . '/photo/profile/l/' . local_channel()),
						dbesc(z_root() . '/photo/profile/m/' . local_channel()),
						intval(local_channel())
					);
				}
			}
			else {
				q("update profile set photo = '%s', thumb = '%s' where id = %d and uid = %d",
					dbesc(z_root() . '/' . get_default_profile_photo(300)),
					dbesc(z_root() . '/' . get_default_profile_photo(80)),
					intval($profile_id),
					intval(local_channel())
				);
			}

			$sync = attach_export_data($channel, $r[0]['resource_id']);
			if ($sync)
				Libsync:: build_sync_packet($channel['channel_id'], ['file' => [$sync]]);

			$_SESSION['reload_avatar'] = true;

			goaway(z_root() . '/profiles/' . $profile_id);
		}

		if ((array_key_exists('cropfinal', $_POST)) && (intval($_POST['cropfinal']) == 1)) {

			// logger('crop: ' . print_r($_POST,true));

			// phase 2 - we have finished cropping

			if (argc() != 2) {
				notice(t('Image uploaded but image cropping failed.') . EOL);
				return;
			}

			$image_id = argv(1);

			if (substr($image_id, -2, 1) == '-') {
				$scale    = substr($image_id, -1, 1);
				$image_id = substr($image_id, 0, -2);
			}

			$srcX = intval($_POST['xstart']);
			$srcY = intval($_POST['ystart']);
			$srcW = intval($_POST['xfinal']) - $srcX;
			$srcH = intval($_POST['yfinal']) - $srcY;

			$r = q("SELECT * FROM photo WHERE resource_id = '%s' AND uid = %d AND imgscale = %d LIMIT 1",
				dbesc($image_id),
				dbesc(local_channel()),
				intval($scale)
			);

			if ($r) {

				$base_image            = $r[0];
				$base_image['content'] = (($r[0]['os_storage']) ? @file_get_contents(dbunescbin($base_image['content'])) : dbunescbin($base_image['content']));

				$im = photo_factory($base_image['content'], $base_image['mimetype']);
				if ($im->is_valid()) {

					$im->cropImage(300, $srcX, $srcY, $srcW, $srcH);

					$aid = get_account_id();

					$p = [
						'aid'          => $aid,
						'uid'          => local_channel(),
						'resource_id'  => $base_image['resource_id'],
						'filename'     => $base_image['filename'],
						'album'        => t('Profile Photos'),
						'os_path'      => $base_image['os_path'],
						'display_path' => $base_image['display_path'],
						'photo_usage'  => (($is_default_profile) ? PHOTO_PROFILE : PHOTO_NORMAL),
						'edited'       => dbescdate($base_image['edited'])
					];

					$r1 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_300);

					$im->scaleImage(80);
					$r2 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_80);

					$im->scaleImage(48);
					$r3 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_48);

					if ($r1 === false || $r2 === false || $r3 === false) {
						// if one failed, delete them all so we can start over.
						notice(t('Image resize failed.') . EOL);
						q("delete from photo where resource_id = '%s' and uid = %d and imgscale in ( %d, %d, %d )",
							dbesc($base_image['resource_id']),
							local_channel(),
							intval(PHOTO_RES_PROFILE_300),
							intval(PHOTO_RES_PROFILE_80),
							intval(PHOTO_RES_PROFILE_48)
						);

						$x = q("SELECT content FROM photo WHERE resource_id = '%s' AND uid = %d AND os_storage = 1 AND imgscale IN ( %d, %d, %d )",
							dbesc($base_image['resource_id']),
							local_channel(),
							intval(PHOTO_RES_PROFILE_300),
							intval(PHOTO_RES_PROFILE_80),
							intval(PHOTO_RES_PROFILE_48)
						);
						if ($x) {
							foreach ($x as $xx) {
								@unlink(dbunescbin($xx['content']));
							}
						}

						return;
					}

					// If setting for the default profile, unset the profile photo flag from any other photos I own

					if ($is_default_profile) {
						q("update profile set photo = '%s', thumb = '%s' where is_default = 1 and uid = %d",
							dbesc(z_root() . '/photo/profile/l/' . local_channel()),
							dbesc(z_root() . '/photo/profile/m/' . local_channel()),
							intval(local_channel())
						);

						q("UPDATE photo SET photo_usage = %d WHERE photo_usage = %d
							AND resource_id != '%s' AND uid = %d",
							intval(PHOTO_NORMAL),
							intval(PHOTO_PROFILE),
							dbesc($base_image['resource_id']),
							intval(local_channel())
						);

						send_profile_photo_activity($channel, $base_image, $profile);
					}
					else {
						q("update profile set photo = '%s', thumb = '%s' where id = %d and uid = %d",
							dbesc(z_root() . '/photo/' . $base_image['resource_id'] . '-4'),
							dbesc(z_root() . '/photo/' . $base_image['resource_id'] . '-5'),
							intval($profile_id),
							intval(local_channel())
						);
					}

					// set $send to false in profiles_build_sync() to return the data
					// so that we only send one sync packet.

					$sync_profiles = profiles_build_sync(local_channel(), false);

					// We'll set the updated profile-photo timestamp even if it isn't the default profile,
					// so that browsers will do a cache update unconditionally
					// Also set links back to site-specific profile photo url in case it was
					// changed to a generic URL by a clone operation. Otherwise the new photo may
					// not get pushed to other sites correctly.

					q("UPDATE xchan set xchan_photo_mimetype = '%s', xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s'
						where xchan_hash = '%s'",
						dbesc($im->getType()),
						dbescdate($base_image['edited']),
						dbesc(z_root() . '/photo/profile/l/' . $channel['channel_id']),
						dbesc(z_root() . '/photo/profile/m/' . $channel['channel_id']),
						dbesc(z_root() . '/photo/profile/s/' . $channel['channel_id']),
						dbesc($channel['xchan_hash'])
					);

					photo_profile_setperms(local_channel(), $base_image['resource_id'], $profile_id);

					$sync = attach_export_data($channel, $base_image['resource_id']);
					if ($sync)
						Libsync::build_sync_packet($channel['channel_id'], ['file' => [$sync], 'profile' => $sync_profiles]);


					// Similarly, tell the nav bar to bypass the cache and update the avatar image.
					$_SESSION['reload_avatar'] = true;

					info(t('Shift-reload the page or clear browser cache if the new photo does not display immediately.') . EOL);

					// Update directory in background
					Master::Summon(['Directory', $channel['channel_id']]);

				}
				else
					notice(t('Unable to process image') . EOL);
			}

			goaway(z_root() . '/profiles/' . $profile_id);
			return; // NOTREACHED
		}

		// A new photo was uploaded. Store it and save some important details
		// in App::$data for use in the cropping function

		$hash      = photo_new_resource();
		$importing = false;
		$smallest  = 0;

		if ($_REQUEST['importfile']) {
			$hash      = $_REQUEST['importfile'];
			$importing = true;
		}
		else {

			$matches = [];
			$partial = false;

			if (array_key_exists('HTTP_CONTENT_RANGE', $_SERVER)) {
				$pm = preg_match('/bytes (\d*)\-(\d*)\/(\d*)/', $_SERVER['HTTP_CONTENT_RANGE'], $matches);
				if ($pm) {
					logger('Content-Range: ' . print_r($matches, true));
					$partial = true;
				}
			}

			if ($partial) {
				$x = save_chunk($channel, $matches[1], $matches[2], $matches[3]);

				if ($x['partial']) {
					header('Range: bytes=0-' . (($x['length']) ? $x['length'] - 1 : 0));
					json_return_and_die($x);
				}
				else {
					header('Range: bytes=0-' . (($x['size']) ? $x['size'] - 1 : 0));

					$_FILES['userfile'] = [
						'name'     => $x['name'],
						'type'     => $x['type'],
						'tmp_name' => $x['tmp_name'],
						'error'    => $x['error'],
						'size'     => $x['size']
					];
				}
			}
			else {
				if (!array_key_exists('userfile', $_FILES)) {
					$_FILES['userfile'] = [
						'name'     => $_FILES['files']['name'],
						'type'     => $_FILES['files']['type'],
						'tmp_name' => $_FILES['files']['tmp_name'],
						'error'    => $_FILES['files']['error'],
						'size'     => $_FILES['files']['size']
					];
				}
			}

			$res = attach_store(App::get_channel(), get_observer_hash(), '', ['album' => t('Profile Photos'), 'hash' => $hash, 'nosync' => true, 'source' => 'photos']);

			json_return_and_die(['message' => $hash]);

		}

		if (($res && intval($res['data']['is_photo'])) || $importing) {
			$i = q("select * from photo where resource_id = '%s' and uid = %d order by imgscale",
				dbesc($hash),
				intval(local_channel())
			);

			if (!$i) {
				notice(t('Image upload failed.') . EOL);
				return;
			}

			$os_storage = false;

			foreach ($i as $ii) {
				if (intval($ii['imgscale']) < PHOTO_RES_640) {
					$smallest   = intval($ii['imgscale']);
					$os_storage = intval($ii['os_storage']);
					$imagedata  = $ii['content'];
					$filetype   = $ii['mimetype'];
				}
			}
		}

		$imagedata = (($os_storage) ? @file_get_contents(dbunescbin($imagedata)) : dbunescbin($imagedata));
		$ph        = photo_factory($imagedata, $filetype);

		if (!$ph->is_valid()) {
			notice(t('Unable to process image.') . EOL);
			return;
		}

		return $this->profile_photo_crop_ui_head($ph, $hash, $smallest);

		// This will "fall through" to the get() method, and since
		// App::$data['imagecrop'] is set, it will proceed to cropping
		// rather than present the upload form
	}


	/* @brief Generate content of profile-photo view
	 *
	 */


	function get() {

		if (!local_channel()) {
			notice(t('Permission denied.') . EOL);
			return;
		}

		$channel            = App::get_channel();
		$profile_id         = (($_REQUEST['profile']) ? intval($_REQUEST['profile']) : intval(argv(1)));
		$default_profile_id = null;

		$r = q("select id, profile_name as name, is_default from profile where uid = %d order by id asc",
			intval(local_channel())
		);


		foreach ($r as $rr) {
			if ($rr['is_default']) {
				$default_profile_id = intval($rr['id']);

				if (!$profile_id) {
					$profile_id = $default_profile_id;
				}
			}

			if ($profile_id === intval($rr['id'])) {
				$profile = $rr;
			}
		}

		$is_default_profile = ($profile_id === $default_profile_id);

		if (argv(1) === 'use') {
			if (argc() < 3) {
				notice(t('Permission denied.') . EOL);
				return;
			};

			$resource_id = argv(2);

			$r = q("SELECT id, album, imgscale FROM photo WHERE uid = %d AND resource_id = '%s' ORDER BY imgscale ASC",
				intval(local_channel()),
				dbesc($resource_id)
			);
			if (!$r) {
				notice(t('Photo not available.') . EOL);
				return;
			}
			$havescale = false;
			foreach ($r as $rr) {
				if ($rr['imgscale'] == PHOTO_RES_PROFILE_80)
					$havescale = true;
			}

			// set an already loaded and cropped photo as profile photo

			if ($havescale) {

				if ($is_default_profile) {

					// unset any existing profile photos
					q("UPDATE photo SET photo_usage = %d WHERE photo_usage = %d AND uid = %d",
						intval(PHOTO_NORMAL),
						intval(PHOTO_PROFILE),
						intval(local_channel())
					);

					$edited = datetime_convert();

					q("UPDATE photo SET photo_usage = %d, edited = '%s' WHERE uid = %d AND resource_id = '%s' AND imgscale > 0",
						intval(PHOTO_PROFILE),
						dbescdate($edited),
						intval(local_channel()),
						dbesc($resource_id)
					);

					q("UPDATE xchan SET xchan_photo_date = '%s' WHERE xchan_hash = '%s'",
						dbescdate($edited),
						dbesc($channel['xchan_hash'])

					);

				}

				q("update profile set photo = '%s', thumb = '%s' where id = %d and uid = %d",
					dbesc(z_root() . '/photo/' . $resource_id . '-4'),
					dbesc(z_root() . '/photo/' . $resource_id . '-5'),
					intval($profile_id),
					intval(local_channel())
				);

				photo_profile_setperms(local_channel(), $resource_id, $profile_id);

				$sync = attach_export_data($channel, $resource_id);
				if ($sync)
					Libsync::build_sync_packet($channel['channel_id'], ['file' => [$sync]]);

				$_SESSION['reload_avatar'] = true;

				Master::Summon(['Directory', local_channel()]);

				goaway(z_root() . '/profiles/' . $profile_id);
			}

			$r = q("SELECT content, mimetype, resource_id, os_storage FROM photo WHERE id = %d and uid = %d limit 1",
				intval($r[0]['id']),
				intval(local_channel())

			);
			if (!$r) {
				notice(t('Photo not available.') . EOL);
				return;
			}

			if (intval($r[0]['os_storage'])) {
				$data = @file_get_contents(dbunescbin($r[0]['content']));
			}
			else {
				$data = dbunescbin($r[0]['content']);
			}

			$ph       = photo_factory($data, $r[0]['mimetype']);
			$smallest = 0;

			if ($ph->is_valid()) {

				// go ahead as if we have just uploaded a new photo to crop
				$i = q("select resource_id, imgscale from photo where resource_id = '%s' and uid = %d order by imgscale",
					dbesc($r[0]['resource_id']),
					intval(local_channel())
				);

				if ($i) {
					$hash = $i[0]['resource_id'];
					foreach ($i as $ii) {
						if (intval($ii['imgscale']) < PHOTO_RES_640) {
							$smallest = intval($ii['imgscale']);
						}
					}
				}
			}

			$this->profile_photo_crop_ui_head($ph, $hash, $smallest);

			// falls through with App::$data['imagecrop'] set so we go straight to the cropping section

		}


		$importing = ((array_key_exists('importfile', App::$data)) ? true : false);

		if (!x(App::$data, 'imagecrop')) {

			$tpl = get_markup_template('profile_photo.tpl');

			$o = replace_macros($tpl, [
				'$user'                   => App::$channel['channel_address'],
				'$info'                   => (($is_default_profile) ? t('This profile photo will be visible to anybody on the internet and may be distributed to other websites.') : t('This profile photo will be visible only to channels with permission to view this profile.')),
				'$importfile'             => (($importing) ? App::$data['importfile'] : ''),
				'$title'                  => (($importing) ? t('Use Photo for Profile') : t('Change Profile Photo')),
				'$submit'                 => t('Upload'),
				'$remove'                 => t('Reset to default'),
				'$profile_id'             => $profile_id,
				'$profile'                => $profile,
				'$embedPhotos'            => t('Use a photo from your albums'),
				'$embedPhotosModalTitle'  => t('Use a photo from your albums'),
				'$embedPhotosModalCancel' => t('Cancel'),
				'$embedPhotosModalOK'     => t('OK'),
				'$modalchooseimages'      => t('Choose images to embed'),
				'$modalchoosealbum'       => t('Choose an album'),
				'$modaldiffalbum'         => t('Choose a different album'),
				'$modalerrorlist'         => t('Error getting album list'),
				'$modalerrorlink'         => t('Error getting photo link'),
				'$modalerroralbum'        => t('Error getting album'),
				'$form_security_token'    => get_form_security_token("profile_photo"),
				'$select'                 => t('Select existing'),
			]);

			call_hooks('profile_photo_content_end', $o);

			return $o;
		}
		else {

			// present a cropping form

			$filename = App::$data['imagecrop'] . '-' . App::$data['imagecrop_resolution'];
			$tpl      = get_markup_template("cropbody.tpl");

			$o = replace_macros($tpl, [
				'$filename'            => $filename,
				'$profile'             => $profile_id,
				'$resource'            => App::$data['imagecrop'] . '-' . App::$data['imagecrop_resolution'],
				'$image_url'           => z_root() . '/photo/' . $filename,
				'$title'               => t('Crop Image'),
				'$desc'                => t('Please adjust the image cropping for optimum viewing.'),
				'$form_security_token' => get_form_security_token("profile_photo"),
				'$done'                => t('Done editing')
			]);

			return $o;
		}

	}

	/* @brief Generate the UI for photo-cropping
	 *
	 * @param $ph
	 * @param $hash
	 * @param $smallest
	 *
	 */


	function profile_photo_crop_ui_head($ph, $hash, $smallest) {

		$max_length = get_config('system', 'max_image_length');

		if (!$max_length) {
			$max_length = MAX_IMAGE_LENGTH;
		}
		if ($max_length > 0) {
			$ph->scaleImage($max_length);
		}

		App::$data['width']  = $ph->getWidth();
		App::$data['height'] = $ph->getHeight();

		if (App::$data['width'] < 500 || App::$data['height'] < 500) {
			$ph->scaleImageUp(400);
			App::$data['width']  = $ph->getWidth();
			App::$data['height'] = $ph->getHeight();
		}

		App::$data['imagecrop']            = $hash;
		App::$data['imagecrop_resolution'] = $smallest;
		App::$page['htmlhead']             .= replace_macros(get_markup_template("crophead.tpl"), []);

		return;
	}


}
