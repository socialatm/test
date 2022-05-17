/**
 * JavaScript for mod/cloud
 */

$(document).ready(function () {

	// call initialization file
	if (window.File && window.FileList && window.FileReader) {
		UploadInit();
	}

	var attach_drop_id;
	var attach_draging;

	// Per File Tools

	$('.cloud-tool-info-btn').on('click', function (e) {
		e.preventDefault();
		let id = $(this).data('id');
		close_and_deactivate_all_panels();
		$('#cloud-tool-info-' + id).toggle();
		$('#cloud-index-' + id).addClass('cloud-index-active');
	});

	$('.cloud-tool-perms-btn').on('click', function (e) {
		e.preventDefault();
		let id = $(this).data('id');
		activate_id(id);
	});

	$('.cloud-tool-rename-btn').on('click', function (e) {
		e.preventDefault();
		let id = $(this).data('id');
		activate_id(id);
		$('#cloud-tool-rename-' + id).show();
	});

	$('.cloud-tool-move-btn').on('click', function (e) {
		e.preventDefault();
		let id = $(this).data('id');
		activate_id(id);
		$('#cloud-tool-move-' + id).show();
	});

	$('.cloud-tool-categories-btn').on('click', function (e) {
		e.preventDefault();
		let id = $(this).data('id');
		activate_id(id);
		$('#id_categories_' + id).tagsinput({
			tagClass: 'badge rounded-pill bg-warning text-dark'
		});
		$('#cloud-tool-categories-' + id).show();
	});

	$('.cloud-tool-download-btn').on('click', function (e) {
		close_and_deactivate_all_panels();
	});

	$('.cloud-tool-dir-download-btn').on('click', function (e) {
		e.preventDefault();
		close_and_deactivate_all_panels()

		let id = $(this).data('id');
		if(! id) {
			return false;
		}

		close_and_deactivate_all_panels();

		$('body').css('cursor', 'wait');
		$.jGrowl(aStr.download_info, { sticky: false, theme: 'info', life: 10000 });

		let data = [
			{name: 'attach_path', value: window.location.pathname},
			{name: 'channel_id', value: channelId},
			{name: 'attach_ids[]', value: id}
		]

		$.post('attach', data, function (data) {
			if (data.success) {
				$('body').css('cursor', 'auto');
				window.location.href = '/attach/download?token=' + data.token;
			}
		});

	});

	$('.cloud-tool-delete-btn').on('click', function (e) {
		e.preventDefault();
		let id = $(this).data('id');

		close_and_deactivate_all_panels();

		let confirm = confirmDelete();
		if (confirm) {
			$('body').css('cursor', 'wait');
			$('#cloud-index-' + id).css('opacity', 0.33);

			let form = $('#attach_edit_form_' + id).serializeArray();
			form.push({name: 'delete', value: 1});

			$.post('attach_edit', form, function (data) {
				if (data.success) {
					$('#cloud-index-' + id + ', #cloud-tools-' + id).remove();
					$('body').css('cursor', 'auto');
				}
				return true;
			});

		}
		return false;
	});

	$('.cloud-tool-cancel-btn').on('click', function (e) {
		e.preventDefault();
		let id = $(this).data('id');
		close_and_deactivate_all_panels();
		$('#attach_edit_form_' + id).trigger('reset');
		$('#id_categories_' + id).tagsinput('destroy');
	});

	// Per File Tools Eend

	// DnD

	$(document).on('drop', function (e) {
		e.preventDefault();
		e.stopPropagation();
	});

	$(document).on('dragover', function (e) {
		e.preventDefault();
		e.stopPropagation();
	});

	$(document).on('dragleave', function (e) {
		e.preventDefault();
		e.stopPropagation();
	});

	$('.cloud-index.attach-drop').on('drop', function (e) {

		let target = $(this);
		let folder = target.data('folder');
		let id = target.data('id');


		if(typeof folder === typeof undefined) {
			return false;
		}

		// Check if it's a file
		if (typeof e.dataTransfer !== typeof undefined && e.dataTransfer.files[0]) {
			$('#file-folder').val(folder);
			return true;
		}

		if(id === attach_drop_id) {
			return false;
		}

		if(target.hasClass('attach-drop-zone') && attach_draging) {
			return false;
		}

		target.removeClass('attach-drop-ok');

		$.post('attach_edit', {'channel_id': channelId, 'dnd': 1, 'attach_id': attach_drop_id, ['newfolder_' + attach_drop_id]: folder }, function (data) {
			if (data.success) {
				$('#cloud-index-' + attach_drop_id + ', #cloud-tools-' + attach_drop_id).remove();
				attach_drop_id = null;
			}
		});
	});

	$('.cloud-index.attach-drop').on('dragover', function (e) {
		let target = $(this);

		if(target.hasClass('attach-drop-zone') && attach_draging) {
			return false;
		}

		target.addClass('attach-drop-ok');
	});

	$('.cloud-index').on('dragleave', function (e) {
		let target = $(this);
		target.removeClass('attach-drop-ok');
	});

	$('.cloud-index').on('dragstart', function (e) {
		let target = $(this);
		attach_drop_id = target.data('id');
		// dragstart is not fired if a file is draged onto the window
		// we use this to distinguish between drags and file drops
		attach_draging = true;
	});

	$('.cloud-index').on('dragend', function (e) {
		let target = $(this);
		target.removeClass('attach-drop-ok');
		attach_draging = false;
	});

	// DnD End

	// Multi Tools

	$('#cloud-multi-tool-select-all').on('change', function (e) {
		if ($(this).is(':checked')) {
			$('.cloud-multi-tool-checkbox').prop('checked', true);
			$('.cloud-index:not(#cloud-index-up)').addClass('cloud-index-selected cloud-index-active');
			$('.cloud-tools').addClass('cloud-index-selected');
		}
		else {
			$('.cloud-multi-tool-checkbox').prop('checked', false);
			$('.cloud-index').removeClass('cloud-index-selected cloud-index-active');
			$('.cloud-tools').removeClass('cloud-index-selected');
		}

		$('.cloud-multi-tool-checkbox').trigger('change');
	});


	$('.cloud-multi-tool-checkbox').on('change', function (e) {
		let id = $(this).val();

		if ($(this).is(':checked')) {
			$('#cloud-index-' + id).addClass('cloud-index-selected cloud-index-active');
			$('#cloud-tools-' + id).addClass('cloud-index-selected');
			$('<input id="aid_' + id + '" class="attach-ids-input" type="hidden" name="attach_ids[]" value="' + id + '">').prependTo('#attach_multi_edit_form');
		}
		else {
			$('#cloud-index-' + id).removeClass('cloud-index-selected cloud-index-active');
			$('#cloud-tools-' + id).removeClass('cloud-index-selected');
			if ($('#cloud-multi-tool-select-all').is(':checked'))
				$('#cloud-multi-tool-select-all').prop('checked', false);

			$('#aid_' + id).remove();
		}

		if($('.cloud-multi-tool-checkbox:checked').length) {
			close_all_panels();
			$('#cloud-multi-actions').addClass('bg-warning');
			$('#multi-dropdown-button').fadeIn();
		}
		else {
			$('#cloud-multi-actions').removeClass('bg-warning');
			$('#multi-dropdown-button').fadeOut();
			close_and_deactivate_all_panels();
			disable_multi_acl();
		}

	});

	$('#cloud-multi-tool-perms-btn').on('click', function (e) {
		e.preventDefault();

		close_all_panels();
		enable_multi_acl();

		$('#cloud-multi-tool-submit').show();
	});

	$('#cloud-multi-tool-move-btn').on('click', function (e) {
		e.preventDefault();

		close_all_panels();
		disable_multi_acl();

		$('#cloud-multi-tool-submit, #cloud-multi-tool-move').show();
	});

	$('#cloud-multi-tool-categories-btn').on('click', function (e) {
		e.preventDefault();

		close_all_panels();
		disable_multi_acl();

		$('#id_categories').tagsinput({
			tagClass: 'badge rounded-pill bg-warning text-dark'
		});

		$('#cloud-multi-tool-submit, #cloud-multi-tool-categories').show();
	});

	$('#cloud-multi-tool-download-btn').on('click', function (e) {
		e.preventDefault();

		let post_data = $('.cloud-multi-tool-checkbox:checked').serializeArray();

		if(! post_data.length) {
			return false;
		}

		close_and_deactivate_all_panels();

		$('body').css('cursor', 'wait');
		$.jGrowl(aStr.download_info, { sticky: false, theme: 'info', life: 10000 });

		post_data.push(
			{name: 'attach_path', value: window.location.pathname},
			{name: 'channel_id', value: channelId}
		);

		$.post('attach', post_data, function (data) {
			if (data.success) {
				$('body').css('cursor', 'auto');
				window.location.href = '/attach/download?token=' + data.token;
			}
		});

	});

	$('#cloud-multi-tool-delete-btn').on('click', function (e) {
		e.preventDefault();

		close_and_deactivate_all_panels();

		let post_data = $('.cloud-multi-tool-checkbox:checked').serializeArray();

		if(! post_data.length) {
			return false;
		}

		let confirm = confirmDelete();
		if (confirm) {
			$('body').css('cursor', 'wait');
			$('.cloud-index-selected').css('opacity', 0.33);

			post_data.push(
				{ name: 'channel_id', value: channelId },
				{ name: 'delete', value: 1},
			);

			$.post('attach_edit', post_data, function (data) {
				if (data.success) {
					console.log(data);
					$('.cloud-index-selected').remove();
					$('body').css('cursor', 'auto');
				}
				return true;
			});
		}
		return false;

	});

	$('.cloud-multi-tool-cancel-btn').on('click', function (e) {
		e.preventDefault();

		close_and_deactivate_all_panels();
		disable_multi_acl();

		$('#attach_multi_edit_form').trigger('reset');
		$('#id_categories').tagsinput('destroy');
	});

	// Multi Tools End

	// Helper Functions

	function disable_multi_acl() {
		$('#multi-perms').val(0);
		$('#multi-dbtn-acl, #recurse_container').hide();
		$('#attach-multi-edit-perms').removeClass('btn-group');
	}

	function enable_multi_acl() {
		$('#multi-perms').val(1);
		$('#multi-dbtn-acl, #recurse_container').show();
		$('#attach-multi-edit-perms').addClass('btn-group');
	}

	function close_all_panels() {
		$('.cloud-tool, .cloud-multi-tool').hide();
	}

	function deactivate_all_panels() {
		$('.cloud-index').removeClass('cloud-index-active');
	}

	function close_and_deactivate_all_panels() {
		close_all_panels();
		deactivate_all_panels();
	}

	function activate_id(id) {
		close_and_deactivate_all_panels();
		$('#cloud-multi-tool-select-all, .cloud-multi-tool-checkbox').prop('checked', false).trigger('change');

		$('#cloud-tool-submit-' + id).show();
		$('#cloud-index-' + id).addClass('cloud-index-active');
	}

});




// initialize
function UploadInit() {

	var submit = $("#upload-submit");
	var idx = 0;
	var filedrag = $(".cloud-index.attach-drop");
	var reload = false;

	$('#invisible-cloud-file-upload').fileupload({
		url: 'file_upload',
		dataType: 'json',
		dropZone: filedrag,
		maxChunkSize: 4 * 1024 * 1024,
		add: function(e,data) {

			idx++;
			data.files[0].idx = idx;
			prepareHtml(data.files[0]);

			var allow_cid = ($('#ajax-upload-files').data('allow_cid') || []);
			var allow_gid = ($('#ajax-upload-files').data('allow_gid') || []);
			var deny_cid  = ($('#ajax-upload-files').data('deny_cid') || []);
			var deny_gid  = ($('#ajax-upload-files').data('deny_gid') || []);

			$('.acl-field').remove();

			$(allow_gid).each(function(i,v) {
				$('#ajax-upload-files').append("<input class='acl-field' type='hidden' name='group_allow[]' value='"+v+"'>");
			});
			$(allow_cid).each(function(i,v) {
				$('#ajax-upload-files').append("<input class='acl-field' type='hidden' name='contact_allow[]' value='"+v+"'>");
			});
			$(deny_gid).each(function(i,v) {
				$('#ajax-upload-files').append("<input class='acl-field' type='hidden' name='group_deny[]' value='"+v+"'>");
			});
			$(deny_cid).each(function(i,v) {
				$('#ajax-upload-files').append("<input class='acl-field' type='hidden' name='contact_deny[]' value='"+v+"'>");
			});

			data.formData = $('#ajax-upload-files').serializeArray();

			// trick it into not uploadiong all files at once
			$('#new-upload-' + data.files[0].idx).one('fileupload_trigger', function () {
				data.submit();
			});

			$('#new-upload-1').trigger('fileupload_trigger');
		},

		progress: function(e,data) {
			var id = data.files[0].idx;
			if(data.loaded == data.total) {
				if(id == data.originalFiles.length) {
					reload = true;
				}
				else {
					// trigger uploading the next file
					var next_id = id + 1;
					setTimeout(function(){ $('#new-upload-' + next_id).trigger('fileupload_trigger'); }, 1000);
				}
			}

			// Dynamically update the percentage complete displayed in the file upload list
			$('#upload-progress-' + id).html(Math.round(data.loaded / data.total * 100) + '%');
			$('#upload-progress-bar-' + id).css('width', Math.round(data.loaded / data.total * 100) + '%');

		},

		stop: function(e,data) {
			if(reload) {
				console.log('Upload completed');
				window.location.href = window.location.href;
			}
		}
	});

	$('#upload-submit').click(function(event) { event.preventDefault(); $('#invisible-cloud-file-upload').trigger('click');});

}

function prepareHtml(f) {
	var num = f.idx - 1;
	var i = f.idx;
	$('#cloud-index #new-upload-progress-bar-' + num.toString()).after(
		'<tr id="new-upload-' + i + '" class="new-upload">' +
		'<td></td>' +
		'<td><i class="fa fa-fw ' + getIconFromType(f.type) + '" title="' + f.type + '"></i></td>' +
		'<td>' + f.name + '</td>' +
		'<td id="upload-progress-' + i + '"></td><td></td><td></td>' +
		'<td class="d-none d-md-table-cell">' + formatSizeUnits(f.size) + '</td><td class="d-none d-md-table-cell"></td>' +
		'</tr>' +
		'<tr id="new-upload-progress-bar-' + i + '" class="new-upload">' +
		'<td colspan="9" class="upload-progress-bar">' +
		'<div class="progress" style="height: 1px;">' +
		'<div id="upload-progress-bar-' + i + '" class="progress-bar bg-info" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>' +
		'</div>' +
		'</td>' +
		'</tr>'
	);
}

function formatSizeUnits(bytes){
	if      (bytes>=1000000000) {bytes=(bytes/1000000000).toFixed(2)+' GB';}
	else if (bytes>=1000000)    {bytes=(bytes/1000000).toFixed(2)+' MB';}
	else if (bytes>=1000)       {bytes=(bytes/1000).toFixed(2)+' KB';}
	else if (bytes>1)           {bytes=bytes+' bytes';}
	else if (bytes==1)          {bytes=bytes+' byte';}
	else                        {bytes='0 byte';}
	return bytes;
}

// this is basically a js port of include/text.php getIconFromType() function
function getIconFromType(type) {
	var map = {
		//Common file
		'application/octet-stream': 'fa-file-o',
		//Text
		'text/plain': 'fa-file-text-o',
		'application/msword': 'fa-file-word-o',
		'application/pdf': 'fa-file-pdf-o',
		'application/vnd.oasis.opendocument.text': 'fa-file-word-o',
		'application/epub+zip': 'fa-book',
		//Spreadsheet
		'application/vnd.oasis.opendocument.spreadsheet': 'fa-file-excel-o',
		'application/vnd.ms-excel': 'fa-file-excel-o',
		//Image
		'image/jpeg': 'fa-picture-o',
		'image/png': 'fa-picture-o',
		'image/gif': 'fa-picture-o',
		'image/svg+xml': 'fa-picture-o',
		//Archive
		'application/zip': 'fa-file-archive-o',
		'application/x-rar-compressed': 'fa-file-archive-o',
		//Audio
		'audio/mpeg': 'fa-file-audio-o',
		'audio/mp3': 'fa-file-audio-o', //webkit browsers need that
		'audio/wav': 'fa-file-audio-o',
		'application/ogg': 'fa-file-audio-o',
		'audio/ogg': 'fa-file-audio-o',
		'audio/webm': 'fa-file-audio-o',
		'audio/mp4': 'fa-file-audio-o',
		//Video
		'video/quicktime': 'fa-file-video-o',
		'video/webm': 'fa-file-video-o',
		'video/mp4': 'fa-file-video-o',
		'video/x-matroska': 'fa-file-video-o'
	};

	var iconFromType = 'fa-file-o';

	if (type in map) {
		iconFromType = map[type];
	}

	return iconFromType;
}


