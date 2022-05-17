$(document).ready(function() {

	typeof(window.tao) == 'undefined' ? window.tao = {} : '';
	tao.zar = { vsn: '2.0.0', form: {}, msg: {} };
	tao.zar.patano = /^d[0-9]{5,10}$/;
	tao.zar.patema = /^[^@\s]{1,64}@[a-z0-9.-]{2,32}\.[a-z]{2,12}$/;

	$('.register_date').each( function () {
		var date = new Date($(this).data('utc'));
		$(this).html(date.toLocaleString(undefined, {weekday: 'short', hour: 'numeric', minute: 'numeric'}));
	});

	$('#zar014').click( function () {
		$('#zar015').toggle();
	});

	$('#id_invite_code').blur(function() {
		if($('#id_invite_code').val() === '')
			return;

		$('#invite-spinner').show();
		var zreg_invite = $('#id_invite_code').val();
		$.get('register/invite_check.json?f=&invite_code=' + encodeURIComponent(zreg_invite),function(data) {
			if(!data.error) {
				$('#register-form input, #register-form button').removeAttr('disabled');
				// email is always mandatory if using invite code
				$('#help_email').removeClass('text-muted').addClass('text-danger').html(aStr['email_required']);
			}
			$('#invite-spinner').hide();
		});
	});

	$('#id_email').change(function() {
		tao.zar.form.email = $('#id_email').val();

		if (tao.zar.patema.test(tao.zar.form.email) == false ) {
			$('#help_email').removeClass('text-muted').addClass('text-danger').html(aStr['email_not_valid']);
		} else {
			$.get('register/email_check.json?f=&email=' + encodeURIComponent(tao.zar.form.email), function(data) {
				$('#help_email').removeClass('text-muted').addClass('text-danger').html(data.message);
			});
		}
	});

	$('#id_password').change(function() {
		if(($('#id_password').val()).length < 6 ) {
			$('#help_password').removeClass('text-muted').addClass('text-danger').html(aStr.pwshort);
			zFormError('#help_password', true);
		}
		else {
			$('#help_password').html('');
			zFormError('#help_password', false);
			$('#id_password2').focus();
			$('#id_password2').val().length > 0 ? $('#id_password2').trigger('change') : '';
		}
	});

	$('#id_password2').change(function() {
		if($('#id_password').val() != $('#id_password2').val()) {
			$('#help_password2').removeClass('text-muted').addClass('text-danger').html(aStr.pwnomatch);
			zFormError('#help_password2', true);
			$('#id_password').focus();
		}
		else {
			$('#help_password2').html('');
			zFormError('#help_password2', false);
		}
	});

	$('#id_name').blur(function() {
		if($('#id_name').val() == '')
			return;

		$('#name-spinner').fadeIn();
		var zreg_name = $('#id_name').val();
		$.get('new_channel/autofill.json?f=&name=' + encodeURIComponent(zreg_name),function(data) {
			$('#id_nickname').val(data);
			if(data.error) {
				$('#help_name').html('');
				zFormError('#help_name',data.error);
			}
			$('#name-spinner').fadeOut();
		});
	});

	$('#id_nickname').blur(function() {
		if($('#id_name').val() == '')
			return;

		$('#nick-spinner').fadeIn();
		$('#nick-hub').fadeOut();
		var zreg_nick = $('#id_nickname').val();
		$.get('new_channel/checkaddr.json?f=&nick=' + encodeURIComponent(zreg_nick),function(data) {
			$('#id_nickname').val(data);
			if(data.error) {
				$('#help_nickname').html('');
				zFormError('#help_nickname',data.error);
			}
			$('#nick-spinner').fadeOut();
			$('#nick-hub').fadeIn();
		});
	});

	$('#register-form').submit(function(e) {
		if ($('.zform-error').length > 0) {
			e.preventDefault();
			return false;
		}
	});
});
