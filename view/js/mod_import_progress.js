$(document).ready(function() {
	setInterval(get_progress, 5000);

	function get_progress(){
		$.get('import_progress', function(data) {
			update_progress(data);
		});
	}

	function update_progress(data){

		// items
		if (typeof data.cprogress == 'number') {
			$('#cprogress-label').html(data.cprogress + '%');
			$('#cprogress-bar').css('width', data.cprogress + '%');

			if (data.cprogress == 100) {
				$('#cprogress-resume').addClass('d-none');
				$('#cprogress-completed').removeClass('d-none');
				$('#cprogress-bar').removeClass('progress-bar-animated');
			}
			else if (data.cprogress < 100) {
				$('#cprogress-resume').removeClass('d-none');
				$('#cprogress-completed').addClass('d-none');
				$('#cprogress-bar').addClass('progress-bar-animated');
			}
		}
		else {
			$('#cprogress-label').html(data.cprogress);
			$('#cprogress-bar').css('width', '0%');
		}

		// files
		if (typeof data.fprogress == 'number') {
			$('#fprogress-label').html(data.fprogress + '%');
			$('#fprogress-bar').css('width', data.fprogress + '%');

			if (data.fprogress == 100) {
				$('#fprogress-resume').addClass('d-none');
				$('#fprogress-completed').removeClass('d-none');
				$('#fprogress-bar').removeClass('progress-bar-animated');
			}
			else if (data.fprogress < 100) {
				$('#fprogress-resume').removeClass('d-none');
				$('#fprogress-completed').addClass('d-none');
				$('#fprogress-bar').addClass('progress-bar-animated');
			}
		}
		else {
			$('#fprogress-label').html(data.fprogress);
			$('#fprogress-bar').css('width', '0%');
		}
	}
});
