/**
 * JavaScript used by mod/photos
 */
$(document).ready(function() {

	// call initialization file
	if (window.File && window.FileList && window.FileReader) {
		UploadInit();
	}

	$(".comment-edit-form  textarea").editor_autocomplete(baseurl+"/acl?f=&n=1");
	$('textarea').editor_autocomplete(baseurl+"/acl");
	$('textarea').bbco_autocomplete('bbcode');
});

// initialize
function UploadInit() {

	var nickname = $('#invisible-photos-file-upload').data('nickname');
	var fileselect = $("#photos-upload-choose");
	var filedrag = $("#photos-upload-form");
	var submit = $("#dbtn-submit");
	var idx = 0;
	var reload = false;


	$('#invisible-photos-file-upload').fileupload({
		url: 'photos/' + nickname,
		dataType: 'json',
		dropZone: filedrag,
		maxChunkSize: 4 * 1024 * 1024,

		add: function(e,data) {

			idx++;
			data.files[0].idx = idx;
			prepareHtml(data.files[0]);

			var allow_cid = ($('#photos-upload-form').data('allow_cid') || []);
			var allow_gid = ($('#photos-upload-form').data('allow_gid') || []);
			var deny_cid  = ($('#photos-upload-form').data('deny_cid') || []);
			var deny_gid  = ($('#photos-upload-form').data('deny_gid') || []);

			$('.acl-field').remove();

			$(allow_gid).each(function(i,v) {
				$('#photos-upload-form').append("<input class='acl-field' type='hidden' name='group_allow[]' value='"+v+"'>");
			});
			$(allow_cid).each(function(i,v) {
				$('#photos-upload-form').append("<input class='acl-field' type='hidden' name='contact_allow[]' value='"+v+"'>");
			});
			$(deny_gid).each(function(i,v) {
				$('#photos-upload-form').append("<input class='acl-field' type='hidden' name='group_deny[]' value='"+v+"'>");
			});
			$(deny_cid).each(function(i,v) {
				$('#photos-upload-form').append("<input class='acl-field' type='hidden' name='contact_deny[]' value='"+v+"'>");
			});

			data.formData = $('#photos-upload-form').serializeArray();

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

	$('#dbtn-submit').click(function(event) { event.preventDefault(); $('#invisible-photos-file-upload').trigger('click'); return false;});

}


function prepareHtml(f) {
	var num = f.idx - 1;
	var i = f.idx;
	$('#upload-index #new-upload-progress-bar-' + num.toString()).after(
		'<tr id="new-upload-' + i + '" class="new-upload">' +
		'<td></td>' +
		'<td><i class="fa fa-fw ' + getIconFromType(f.type) + '" title="' + f.type + '"></i></td>' +
		'<td>' + f.name + '</td>' +
		'<td id="upload-progress-' + i + '"></td><td></td><td></td>' +
		'<td class="d-none d-md-table-cell">' + formatSizeUnits(f.size) + '</td><td class="d-none d-md-table-cell"></td>' +
		'</tr>' +
		'<tr id="new-upload-progress-bar-' + i + '" class="new-upload">' +
		'<td colspan="8" class="upload-progress-bar">' +
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
