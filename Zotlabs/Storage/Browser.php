<?php

namespace Zotlabs\Storage;

use Sabre\DAV;
use App;

/**
 * @brief Provides a DAV frontend for the webbrowser.
 *
 * Browser is a SabreDAV server-plugin to provide a view to the DAV storage
 * for the webbrowser.
 *
 * @extends \\Sabre\\DAV\\Browser\\Plugin
 *
 * @link http://framagit.org/hubzilla/core/
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class Browser extends DAV\Browser\Plugin {

	public $build_page = false;
	/**
	 * @see set_writeable()
	 * @see \\Sabre\\DAV\\Auth\\Backend\\BackendInterface
	 * @var BasicAuth $auth
	 */
	private $auth;

	/**
	 * @brief Constructor for Browser class.
	 *
	 * $enablePost will be activated through set_writeable() in a later stage.
	 * At the moment the write_storage permission is only valid for the whole
	 * folder. No file specific permissions yet.
	 * @todo disable enablePost by default and only activate if permissions
	 * grant edit rights.
	 *
	 * Disable assets with $enableAssets = false. Should get some thumbnail views
	 * anyway.
	 *
	 * @param BasicAuth &$auth
	 */
	public function __construct(&$auth) {
		$this->auth = $auth;
		parent::__construct(true, false);
	}

	/**
	 * The DAV browser is instantiated after the auth module and directory classes
	 * but before we know the current directory and who the owner and observer
	 * are. So we add a pointer to the browser into the auth module and vice versa.
	 * Then when we've figured out what directory is actually being accessed, we
	 * call the following function to decide whether or not to show web elements
	 * which include writeable objects.
	 *
	 * @fixme It only disable/enable the visible parts. Not the POST handler
	 * which handels the actual requests when uploading files or creating folders.
	 *
	 * @todo Maybe this whole way of doing this can be solved with some
	 * $server->subscribeEvent().
	 */
	public function set_writeable() {
		if (! $this->auth->owner_id) {
			$this->enablePost = false;
		}

		if (! perm_is_allowed($this->auth->owner_id, get_observer_hash(), 'write_storage')) {
			$this->enablePost = false;
		} else {
			$this->enablePost = true;
		}
	}

	/**
	 * @brief Creates the directory listing for the given path.
	 *
	 * @param string $path which should be displayed
	 */
	public function generateDirectoryIndex($path) {

		require_once('include/conversation.php');
		require_once('include/text.php');

		$nick = $this->auth->owner_nick;
		$channel_id = $this->auth->owner_id;

		// Is visitor owner of this directory?
		$is_owner = ((local_channel() && $channel_id == local_channel()) ? true : false);
		$cat = ((x($_REQUEST,'cat')) ? $_REQUEST['cat'] : '');

		if ($this->auth->getTimezone()) {
			date_default_timezone_set($this->auth->getTimezone());
		}

		$files = $this->server->getPropertiesForPath($path, [], 1);
		$parent = $this->server->tree->getNodeForPath($path);

		$arr = explode('/', $parent->os_path);
		end($arr);
		$folder_parent = ((isset($arr[1])) ? prev($arr) : '');

		$folder_list = attach_folder_select_list($channel_id);

		$siteroot_disabled = get_config('system', 'cloud_disable_siteroot');
		$is_root_folder = (($path === 'cloud/' . $nick) ? true : false);

		$parent_path = '';

		if ($channel_id && ! $cat && !($siteroot_disabled && $is_root_folder)) {
			list($parent_uri) = \Sabre\Uri\split($path);
			$parent_path = \Sabre\HTTP\encodePath($this->server->getBaseUri() . $parent_uri);
		}

		$embedable_video_types = [
			'video/mp4',
			'video/ogg',
			'video/webm'
		];

		$embedable_audio_types = [
			'audio/mpeg',
			'audio/wav',
			'audio/ogg',
			'audio/webm'
		];

		$f = [];

		foreach ($files as $file) {

			$ft = [];
			$type = null;

			$href = rtrim($file['href'], '/');

			// This is the current directory - skip it
			if ($href === $path)
				continue;

			$node = $this->server->tree->getNodeForPath($href);
			$data = $node->data;
			$attach_hash = $data['hash'];
			$folder_hash = $node->folder_hash;

			list(, $filename) = \Sabre\Uri\split($href);

			$name = isset($file[200]['{DAV:}displayname']) ? $file[200]['{DAV:}displayname'] : $filename;
			$name = $this->escapeHTML($name);

			$size = isset($file[200]['{DAV:}getcontentlength']) ? (int)$file[200]['{DAV:}getcontentlength'] : '';

			$lastmodified = ((isset($file[200]['{DAV:}getlastmodified'])) ? $file[200]['{DAV:}getlastmodified']->getTime()->format('Y-m-d H:i:s') : '');

			if (isset($file[200]['{DAV:}resourcetype'])) {

				$type = $file[200]['{DAV:}resourcetype']->getValue();

				// resourcetype can have multiple values
				if (!is_array($type)) $type = array($type);

				foreach ($type as $k=>$v) {
					// Some name mapping is preferred
					switch ($v) {
						case '{DAV:}collection' :
							$type[$k] = 'Collection';
							break;
						case '{DAV:}principal' :
							$type[$k] = 'Principal';
							break;
						case '{urn:ietf:params:xml:ns:carddav}addressbook' :
							$type[$k] = 'Addressbook';
							break;
						case '{urn:ietf:params:xml:ns:caldav}calendar' :
							$type[$k] = 'Calendar';
							break;
						case '{urn:ietf:params:xml:ns:caldav}schedule-inbox' :
							$type[$k] = 'Schedule Inbox';
							break;
						case '{urn:ietf:params:xml:ns:caldav}schedule-outbox' :
							$type[$k] = 'Schedule Outbox';
							break;
						case '{http://calendarserver.org/ns/}calendar-proxy-read' :
							$type[$k] = 'Proxy-Read';
							break;
						case '{http://calendarserver.org/ns/}calendar-proxy-write' :
							$type[$k] = 'Proxy-Write';
							break;
					}
				}
				$type = implode(', ', $type);
			}

			// If no resourcetype was found, we attempt to use
			// the contenttype property
			if (! $type && isset($file[200]['{DAV:}getcontenttype'])) {
				$type = $file[200]['{DAV:}getcontenttype'];
			}

			if (! $type) {
				$type = $data['filetype'];
			}

			$type = $this->escapeHTML($type);

			// generate preview icons for tile view.
			// Currently we only handle images, but this could potentially be extended with plugins
			// to provide document and video thumbnails. SVG, PDF and office documents have some
			// security concerns and should only be allowed on single-user sites with tightly controlled
			// upload access. system.thumbnail_security should be set to 1 if you want to include these
			// types

			$is_creator = false;
			$photo_icon = '';
			$preview_style = intval(get_config('system','thumbnail_security',0));

			$is_creator = (($data['creator'] === get_observer_hash()) ? true : false);

			if(strpos($type,'image/') === 0 && $attach_hash) {
				$p = q("select resource_id, imgscale from photo where resource_id = '%s' and imgscale in ( %d, %d ) order by imgscale asc limit 1",
					dbesc($attach_hash),
					intval(PHOTO_RES_320),
					intval(PHOTO_RES_PROFILE_80)
				);
				if($p) {
					$photo_icon = 'photo/' . $p[0]['resource_id'] . '-' . $p[0]['imgscale'];
				}
				if($type === 'image/svg+xml' && $preview_style > 0) {
					$photo_icon = $href;
				}
			}

			$g = [ 'resource_id' => $attach_hash, 'thumbnail' => $photo_icon, 'security' => $preview_style ];
			call_hooks('file_thumbnail', $g);
			$photo_icon = $g['thumbnail'];

			$lockstate = (($data['allow_cid'] || $data['allow_gid'] || $data['deny_cid'] || $data['deny_gid']) ? 'lock' : 'unlock');
			$id = $data['id'];

			if($id) {
				$terms = q("select * from term where oid = %d AND otype = %d",
					intval($id),
					intval(TERM_OBJ_FILE)
				);

				$categories = [];
				$terms_str = '';
				if($terms) {
					foreach($terms as $t) {
						$term = htmlspecialchars($t['term'],ENT_COMPAT,'UTF-8',false) ;
						if(! trim($term))
							continue;
						$categories[] = array('term' => $term, 'url' => $t['url']);
						if ($terms_str)
							$terms_str .= ',';
						$terms_str .= $term;
					}
					$ft['terms'] = replace_macros(get_markup_template('item_categories.tpl'),array(
						'$categories' => $categories
					));
				}
			}

			// put the array for this file together
			$ft['attach_id'] = $id;
			// $ft['icon'] = $icon;
			$ft['photo_icon'] = $photo_icon;
			$ft['is_creator'] = $is_creator;
			$ft['rel_path'] = (($data) ? '/cloud/' . $nick .'/' . $data['display_path'] : $href);
			$ft['full_path'] = z_root() . (($data) ? '/cloud/' . $nick .'/' . $data['display_path'] : $href);
			$ft['name'] = $name;
			$ft['type'] = $type;
			$ft['size'] = $size;
			$ft['collection'] = (($type === 'Collection') ? true : false);
			$ft['size_formatted'] = userReadableSize($size);
			$ft['last_modified'] = (($lastmodified) ? datetime_convert('UTC', date_default_timezone_get(), $lastmodified) : '');
			$ft['icon_from_type'] = getIconFromType($type);

			$ft['allow_cid'] = acl2json($data['allow_cid']);
			$ft['allow_gid'] = acl2json($data['allow_gid']);
			$ft['deny_cid'] = acl2json($data['deny_cid']);
			$ft['deny_gid'] = acl2json($data['deny_gid']);

			$ft['raw_allow_cid'] = $data['allow_cid'];
			$ft['raw_allow_gid'] = $data['allow_gid'];
			$ft['raw_deny_cid'] = $data['deny_cid'];
			$ft['raw_deny_gid'] = $data['deny_gid'];

			$ft['lockstate'] = $lockstate;
			$ft['resource'] = $data['hash'];
			$ft['folder'] = $data['folder'];
			$ft['revision'] = $data['revision'];
			$ft['newfilename'] = ['newfilename_' . $id, t('Change filename to'), $name];
			$ft['categories'] = ['categories_' . $id, t('Categories'), $terms_str];

			// create a copy of the list which we can alter for the current resource
			$folders = $folder_list;

			if($data['is_dir']) {

				$rm_path = $folders[$folder_hash];
				// can not copy a folder into itself or own child folders
				foreach($folders as $k => $v) {
					if(strpos($v, $rm_path) === 0)
						unset($folders[$k]);
				}

			}

			$ft['newfolder'] = ['newfolder_' . $id, t('Select a target location'), $data['folder'], '', $folders];
			$ft['copy'] = ['copy_' . $id, t('Copy to target location'), 0, '', [t('No'), t('Yes')]];
			$ft['recurse'] = ['recurse_' . $id, t('Set permissions for all files and sub folders'), 0, '', [t('No'), t('Yes')]];
			$ft['notify'] = ['notify_edit_' . $id, t('Notify your contacts about this file'), 0, '', [t('No'), t('Yes')]];

			$embed_bbcode = '';
			$link_bbcode = '';
			$attach_bbcode = '';

			if($data['is_photo']) {
				$embed_bbcode = '[zmg]' . $ft['full_path'] . '[/zmg]';
			}
			elseif(strpos($type, 'video') === 0 && in_array($type, $embedable_video_types)) {
				$embed_bbcode = '[zvideo]' . $ft['full_path'] . '[/zvideo]';
			}
			elseif(strpos($type, 'audio') === 0 && in_array($type, $embedable_audio_types)) {
				$embed_bbcode = '[zaudio]' . $ft['full_path'] . '[/zaudio]';
			}
			$ft['embed_bbcode'] = $embed_bbcode;

			if(! $data['is_dir']) {
				$attach_bbcode = '[attachment]' . $data['hash'] . ',' . $data['revision'] . '[/attachment]';
			}
			$ft['attach_bbcode'] = $attach_bbcode;

			$link_bbcode = '[zrl=' . $ft['full_path'] . ']' . $ft['name'] . '[/zrl]';
			$ft['link_bbcode'] = $link_bbcode;

			$f[] = $ft;

		}

		$output = '';
		if ($this->enablePost) {
			$this->server->emit('onHTMLActionsPanel', [$parent, &$output, $path]);
		}

		$deftiles = (($is_owner) ? 0 : 1);

		$tiles = ((array_key_exists('cloud_tiles',$_SESSION)) ? intval($_SESSION['cloud_tiles']) : $deftiles);
		$_SESSION['cloud_tiles'] = $tiles;

		$header = (($cat) ? t('File category') . ": " . $this->escapeHTML($cat) : t('Files'));

		$channel = channelx_by_n($channel_id);
		$lockstate = null;
		$channel_acl = null;

		if($channel) {
			$acl = new \Zotlabs\Access\AccessList($channel);
			$channel_acl = $acl->get();
			$lockstate = (($acl->is_private()) ? 'lock' : 'unlock');
		}

		$html = replace_macros(get_markup_template('cloud.tpl'), array(
				'$header' => $header,
				'$total' => t('Total'),
				'$actionspanel' => $output,
				'$shared' => t('Shared'),
				'$create' => t('Create'),
				'$upload' => t('Add Files'),
				'$is_owner' => $is_owner,
				'$is_admin' => is_site_admin(),
				'$admin_delete_label' => t('Admin Delete'),
				'$parentpath' => $parent_path,
				'$folder_parent' => $folder_parent,
				'$folder' => $parent->folder_hash,
				'$is_root_folder' => $is_root_folder,
				'$cpath' => bin2hex(App::$query_string),
				'$tiles' => intval($_SESSION['cloud_tiles']),
				'$entries' => $f,
				'$name' => t('Name'),
				'$type' => t('Type'),
				'$size' => t('Size'),
				'$lastmod' => t('Last Modified'),
				'$parent' => t('parent'),
				'$submit_label' => t('Submit'),
				'$cancel_label' => t('Cancel'),
				'$delete_label' => t('Delete'),
				'$channel_id' => $channel_id,
				'$cpdesc' => t('Copy/paste this code to attach file to a post'),
				'$cpldesc' => t('Copy/paste this URL to link file from a web page'),
				'$categories' => ['categories', t('Categories')],
				'$recurse' => ['recurse', t('Set permissions for all files and sub folders'), 0, '', [t('No'), t('Yes')]],
				'$newfolder' => ['newfolder', t('Select a target location'), $parent->folder_hash, '', $folder_list],
				'$copy' => ['copy', t('Copy to target location'), 0, '', [t('No'), t('Yes')]],
				'$return_path' => $path,
				'$lockstate' => $lockstate,
				'$allow_cid' => ((isset($channel_acl['allow_cid'])) ? acl2json($channel_acl['allow_cid']) : ''),
				'$allow_gid' => ((isset($channel_acl['allow_gid'])) ? acl2json($channel_acl['allow_gid']) : ''),
				'$deny_cid' => ((isset($channel_acl['deny_cid'])) ? acl2json($channel_acl['deny_cid']) : ''),
				'$deny_gid' => ((isset($channel_acl['deny_gid'])) ? acl2json($channel_acl['deny_gid']) : ''),
				'$is_owner' => $is_owner,
				'$select_all_label' => t('Select All'),
				'$bulk_actions_label' => t('Bulk Actions'),
				'$adjust_permissions_label' => t('Adjust Permissions'),
				'$move_copy_label' => t('Move or Copy'),
				'$categories_label' => t('Categories'),
				'$download_label' => t('Download'),
				'$info_label' => t('Info'),
				'$rename_label' => t('Rename'),
				'$post_label' => t('Post'),
				'$attach_bbcode_label' => t('Attachment BBcode'),
				'$embed_bbcode_label' => t('Embed BBcode'),
				'$link_bbcode_label' => t('Link BBcode'),
				'$close_label' => t('Close')
			));

		$a = false;

		nav_set_selected('Files');

		App::$page['content'] = $html;
		load_pdl();

		$current_theme = \Zotlabs\Render\Theme::current();

		$theme_info_file = 'view/theme/' . $current_theme[0] . '/php/theme.php';
		if (file_exists($theme_info_file)) {
			require_once($theme_info_file);
			if (function_exists(str_replace('-', '_', $current_theme[0]) . '_init')) {
				$func = str_replace('-', '_', $current_theme[0]) . '_init';
				$func($a);
			}
		}
		$this->server->httpResponse->setHeader('Content-Security-Policy', "script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'");
		$this->build_page = true;
	}

	/**
	 * @brief Creates a form to add new folders and upload files.
	 *
	 * @param \Sabre\DAV\INode $node
	 * @param[in,out] string &$output
	 * @param string $path
	 */
	public function htmlActionsPanel(DAV\INode $node, &$output, $path) {
		if(! $node instanceof DAV\ICollection)
			return;

		// We also know fairly certain that if an object is a non-extended
		// SimpleCollection, we won't need to show the panel either.
		if (get_class($node) === 'Sabre\\DAV\\SimpleCollection')
			return;

		require_once('include/acl_selectors.php');

		$aclselect = null;
		$lockstate = '';
		$limit = 0;

		$cat = $_REQUEST['cat'] ?? '';
		$cloud_tiles = $_SESSION['cloud_tiles'] ?? 0;

		if($this->auth->owner_id) {
			$channel = channelx_by_n($this->auth->owner_id);
			if($channel) {
				$acl = new \Zotlabs\Access\AccessList($channel);
				$channel_acl = $acl->get();
				$lockstate = (($acl->is_private()) ? 'lock' : 'unlock');

				$aclselect = ((local_channel() == $this->auth->owner_id) ? populate_acl($channel_acl,false, \Zotlabs\Lib\PermissionDescription::fromGlobalPermission('view_storage')) : '');
			}

			// Storage and quota for the account (all channels of the owner of this directory)!
			$limit = engr_units_to_bytes(service_class_fetch($this->auth->owner_id, 'attach_upload_limit'));
		}

		if((! $limit) && get_config('system','cloud_report_disksize')) {
			$limit = engr_units_to_bytes(disk_free_space('store'));
		}

		$r = q("SELECT SUM(filesize) AS total FROM attach WHERE aid = %d",
			intval($this->auth->channel_account_id)
		);
		$used = $r[0]['total'];
		if($used) {
			$quotaDesc = t('You are using %1$s of your available file storage.');
			$quotaDesc = sprintf($quotaDesc,
				userReadableSize($used));
		}
		if($limit && $used) {
			$quotaDesc = t('You are using %1$s of %2$s available file storage. (%3$s&#37;)');
			$quotaDesc = sprintf($quotaDesc,
				userReadableSize($used),
				userReadableSize($limit),
				round($used / $limit, 1) * 100);
		}
		// prepare quota for template
		$quota = array();
		$quota['used'] = $used;
		$quota['limit'] = $limit;
		$quota['desc'] = $quotaDesc;
		$quota['warning'] = ((($limit) && ((round($used / $limit, 1) * 100) >= 90)) ? t('WARNING:') : ''); // 10485760 bytes = 100MB

		// strip 'cloud/nickname', but only at the beginning of the path

		$special = 'cloud/' . $this->auth->owner_nick;
		$count   = strlen($special);



		if(strpos($path,$special) === 0)
			$display_path = trim(substr($path,$count),'/');

		$breadcrumbs_html = '';

		if ($display_path && !$cat && !$cloud_tiles) {
			$breadcrumbs = [];
			$folders = explode('/', $display_path);
			$folder_hashes = explode('/', $node->os_path);
			$breadcrumb_path = z_root() . '/cloud/' . $this->auth->owner_nick;

			$breadcrumbs[] = [
				'name' => $this->auth->owner_nick,
				'hash' => '',
				'path' => $breadcrumb_path
			];

			foreach($folders as $i => $name) {
					$breadcrumb_path .= '/' . $name;
					$breadcrumbs[] = [
						'name' => $name,
						'hash' => $folder_hashes[$i],
						'path' => $breadcrumb_path
					];
			}

			$breadcrumbs_html = replace_macros(get_markup_template('breadcrumb.tpl'), array(
				'$breadcrumbs' => $breadcrumbs
			));
		}

		$output .= replace_macros(get_markup_template('cloud_actionspanel.tpl'), array(
				'$folder_header' => t('Create new folder'),
				'$folder_submit' => t('Create'),
				'$upload_header' => t('Upload file'),
				'$upload_submit' => t('Upload'),
				'$quota' => $quota,
				'$channick' => $this->auth->owner_nick,
				'$aclselect' => $aclselect,
				'$allow_cid' => acl2json($channel_acl['allow_cid']),
				'$allow_gid' => acl2json($channel_acl['allow_gid']),
				'$deny_cid' => acl2json($channel_acl['deny_cid']),
				'$deny_gid' => acl2json($channel_acl['deny_gid']),
				'$lockstate' => $lockstate,
				'$return_url' => $path,
				'$folder' => $node->folder_hash,
				'$dragdroptext' => t('Drop files here to immediately upload'),
				'$notify' => ['notify', t('Show in your contacts shared folder'), 0, '', [t('No'), t('Yes')]],
				'$breadcrumbs_html' => $breadcrumbs_html,
				'$drop_area_label' => t('You can select files via the upload button or drop them right here or into an existing folder.')
			));
	}

	/**
	 * This method takes a path/name of an asset and turns it into url
	 * suiteable for http access.
	 *
	 * @param string $assetName
	 * @return string
	 */
	protected function getAssetUrl($assetName) {
		return z_root() . '/cloud/?sabreAction=asset&assetName=' . urlencode($assetName);
	}

	/**
	 * @brief Return the hash of an attachment.
	 *
	 * Given the owner, the parent folder and and attach name get the attachment
	 * hash.
	 *
	 * @param int $owner
	 *  The owner_id
	 * @param string $parentHash
	 *  The parent's folder hash
	 * @param string $attachName
	 *  The name of the attachment
	 * @return string
	 */
	protected function findAttachHash($owner, $parentHash, $attachName) {
		$r = q("SELECT hash FROM attach WHERE uid = %d AND folder = '%s' AND filename = '%s' ORDER BY edited DESC LIMIT 1",
			intval($owner),
			dbesc($parentHash),
			dbesc($attachName)
		);
		$hash = '';
		if ($r) {
			foreach ($r as $rr) {
				$hash = $rr['hash'];
			}
		}

		return $hash;
	}

	protected function findAttachHashFlat($owner, $attachName) {
		$r = q("SELECT hash FROM attach WHERE uid = %d AND filename = '%s' ORDER BY edited DESC LIMIT 1",
			intval($owner),
			dbesc($attachName)
		);
		$hash = '';
		if ($r) {
			foreach ($r as $rr) {
				$hash = $rr['hash'];
			}
		}

		return $hash;
	}

	/**
	 * @brief Returns an attachment's id for a given hash.
	 *
	 * This id is used to access the attachment in filestorage/
	 *
	 * @param string $attachHash
	 *  The hash of an attachment
	 * @return string
	 */
	protected function findAttachIdByHash($attachHash) {
		$r = q("SELECT id FROM attach WHERE hash = '%s' ORDER BY edited DESC LIMIT 1",
			dbesc($attachHash)
		);
		$id = "";
		if ($r) {
			foreach ($r as $rr) {
				$id = $rr['id'];
			}
		}
		return $id;
	}
}
