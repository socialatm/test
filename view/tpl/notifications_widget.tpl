<script>
	var sse_bs_active = false;
	var sse_offset = 0;
	var sse_type;
	var sse_partial_result = false;
	var sse_rmids = [];
	var sse_fallback_interval;

	$(document).ready(function() {
		let notifications_parent;
		if ($('#notifications_wrapper').length) {
			notifications_parent = $('#notifications_wrapper')[0].parentElement.id;
		}

		$('.notifications-btn').click(function() {
			if($('#notifications_wrapper').hasClass('fs')) {
				$('#notifications_wrapper').prependTo('#' + notifications_parent);
			}
			else {
				$('#notifications_wrapper').prependTo('section');
			}

			$('#notifications_wrapper').toggleClass('fs');
			if($('#navbar-collapse-2').hasClass('show')){
				$('#navbar-collapse-2').removeClass('show');
			}
		});

		$(document).on('click', '.notification', function() {
			if($('#notifications_wrapper').hasClass('fs')) {
				$('#notifications_wrapper').prependTo('#' + notifications_parent).removeClass('fs');
			}
		});

		if(sse_enabled) {
			if(typeof(window.SharedWorker) === 'undefined') {
				// notifications with multiple tabs open will not work very well in this scenario
				var evtSource = new EventSource('/sse');

				evtSource.addEventListener('notifications', function(e) {
					var obj = JSON.parse(e.data);
					sse_handleNotifications(obj, false, false);
				}, false);

				document.addEventListener('visibilitychange', function() {
					if (!document.hidden) {
						sse_offset = 0;
						sse_bs_init();
					}
				}, false);
			}
			else {
				var myWorker = new SharedWorker('/view/js/sse_worker.js', localUser);

				myWorker.port.onmessage = function(e) {
					obj = e.data;
					console.log(obj);
					sse_handleNotifications(obj, false, false);
				}

				myWorker.onerror = function(e) {
					myWorker.port.close();
				}
				myWorker.port.start();
			}
		}
		else {
			if (!document.hidden)
				sse_fallback_interval = setInterval(sse_fallback, updateInterval);

			document.addEventListener('visibilitychange', function() {
				if (document.hidden) {
					clearInterval(sse_fallback_interval);
				}
				else {
					sse_offset = 0;
					sse_bs_init();
					sse_fallback_interval = setInterval(sse_fallback, updateInterval);
				}

			}, false);
		}

		$('.notification-link').on('click', { replace: true, followup: false }, sse_bs_notifications);

		$('.notification-filter').on('keypress', function(e) {
			if(e.which == 13) { // enter
				this.blur();
				sse_offset = 0;
				$("#nav-" + sse_type + "-menu").html('');
				$("#nav-" + sse_type + "-loading").show();

				var cn_val = $('#cn-' + sse_type + '-input').length ? $('#cn-' + sse_type + '-input').val().toString().toLowerCase() : '';

				$.get('/sse_bs/' + sse_type + '/' + sse_offset + '?nquery=' + encodeURIComponent(cn_val), function(obj) {
					console.log('sse: bootstraping ' + sse_type);
					console.log(obj);

					sse_bs_active = false;
					sse_partial_result = true;
					sse_offset = obj[sse_type].offset;
					if(sse_offset < 0)
						$("#nav-" + sse_type + "-loading").hide();

					sse_handleNotifications(obj, true, false);

				});
			}
		});

		$('.notifications-textinput-clear').on('click', function(e) {
			if(! sse_partial_result)
				return;

			$("#nav-" + sse_type + "-menu").html('');
			$("#nav-" + sse_type + "-loading").show();
			$.get('/sse_bs/' + sse_type, function(obj) {
				console.log('sse: bootstraping ' + sse_type);
				console.log(obj);

				sse_bs_active = false;
				sse_partial_result = false;
				sse_offset = obj[sse_type].offset;
				if(sse_offset < 0)
					$("#nav-" + sse_type + "-loading").hide();

				sse_handleNotifications(obj, true, false);

			});
		});

		$('.notification-content').on('scroll', function() {
			if(this.scrollTop > this.scrollHeight - this.clientHeight - (this.scrollHeight/7)) {
				sse_bs_notifications(sse_type, false, true);
			}
		});

	});

	$(document).on('hz:sse_setNotificationsStatus', function(e, data) {
		sse_setNotificationsStatus(data);
	});

	$(document).on('hz:sse_bs_init', function() {
		sse_bs_init();
	});

	$(document).on('hz:sse_bs_counts', function() {
		sse_bs_counts();
	});

	{{foreach $notifications as $notification}}
	{{if $notification.filter}}
	$(document).on('click', '#tt-{{$notification.type}}-only', function(e) {
		if($(this).hasClass('active sticky-top')) {
			$('#nav-{{$notification.type}}-menu .notification[data-thread_top=false]').removeClass('tt-filter-active');
			$(this).removeClass('active sticky-top');
		}
		else {
			$('#nav-{{$notification.type}}-menu .notification[data-thread_top=false]').addClass('tt-filter-active');
			$(this).addClass('active sticky-top');
			// load more notifications if visible notifications count is low
			if(sse_type  && sse_offset != -1 && $('#nav-' + sse_type + '-menu').children(':visible').length < 15) {
				sse_bs_notifications(sse_type, false, true);
			}
		}

	});

	$(document).on('click', '#cn-{{$notification.type}}-input-clear', function(e) {
		$('#cn-{{$notification.type}}-input').val('');
		$('#cn-{{$notification.type}}-only').removeClass('active sticky-top');
		$("#nav-{{$notification.type}}-menu .notification").removeClass('cn-filter-active');
		$('#cn-{{$notification.type}}-input-clear').addClass('d-none');
	});

	$(document).on('input', '#cn-{{$notification.type}}-input', function(e) {
		var val = $('#cn-{{$notification.type}}-input').val().toString().toLowerCase();
		if(val) {
			val = val.indexOf('%') == 0 ? val.substring(1) : val;
			$('#cn-{{$notification.type}}-only').addClass('active sticky-top');
			$('#cn-{{$notification.type}}-input-clear').removeClass('d-none');
		}
		else {
			$('#cn-{{$notification.type}}-only').removeClass('active sticky-top');
			$('#cn-{{$notification.type}}-input-clear').addClass('d-none');
		}

		$("#nav-{{$notification.type}}-menu .notification").each(function(i, el){
			var cn = $(el).data('contact_name').toString().toLowerCase();
			var ca = $(el).data('contact_addr').toString().toLowerCase();

			if(cn.indexOf(val) === -1 && ca.indexOf(val) === -1)
				$(this).addClass('cn-filter-active');
			else
				$(this).removeClass('cn-filter-active');
		});
	});
	{{/if}}
	{{/foreach}}

	function sse_bs_init() {
		if(sessionStorage.getItem('notification_open') !== null || typeof sse_type !== 'undefined' ) {
			if(typeof sse_type === 'undefined')
				sse_type = sessionStorage.getItem('notification_open');

			$("#nav-" + sse_type + "-sub").addClass('show');
			sse_bs_notifications(sse_type, true, false);
		}
		else {
			sse_bs_counts();
		}
	}

	function sse_bs_counts() {
		if(sse_bs_active)
			return;

		sse_bs_active = true;

		$.ajax({
			type: 'post',
			url: '/sse_bs',
			data: { sse_rmids }
		}).done( function(obj) {
			console.log(obj);
			sse_bs_active = false;
			sse_rmids = [];
			sse_handleNotifications(obj, true, false);
		});
	}

	function sse_bs_notifications(e, replace, followup) {

		if(sse_bs_active)
			return;

		var manual = false;

		if(typeof replace === 'undefined')
			replace = e.data.replace;

		if(typeof followup === 'undefined')
			followup = e.data.followup;

		if(typeof e === 'string') {
			sse_type = e;
		}
		else {
			manual = true;
			sse_offset = 0;
			sse_type = e.target.dataset.sse_type;
		}

		if(typeof sse_type === 'undefined')
			return;

		if(followup || !manual || !$('#notification-link-' + sse_type).hasClass('collapsed')) {

			if(sse_offset >= 0) {
				$("#nav-" + sse_type + "-loading").show();
			}

			sessionStorage.setItem('notification_open', sse_type);
			if(sse_offset !== -1 || replace) {

				var cn_val = (($('#cn-' + sse_type + '-input').length && sse_partial_result) ? $('#cn-' + sse_type + '-input').val().toString().toLowerCase() : '');

				$("#nav-" + sse_type + "-loading").show();

				sse_bs_active = true;

				$.ajax({
					type: 'post',
					url: '/sse_bs/' + sse_type + '/' + sse_offset,
					nquery: encodeURIComponent(cn_val),
					data: { sse_rmids }
				}).done(function(obj) {
					console.log('sse: bootstraping ' + sse_type);
					console.log(obj);
					sse_bs_active = false;
					sse_rmids = [];
					$("#nav-" + sse_type + "-loading").hide();
					sse_offset = obj[sse_type].offset;
					sse_handleNotifications(obj, replace, followup);
				});
			}
			else {
				$("#nav-" + sse_type + "-loading").hide();
			}
		}
		else {
			sessionStorage.removeItem('notification_open');
		}
	}

	function sse_handleNotifications(obj, replace, followup) {

		var primary_notifications = ['dm', 'home', 'intros', 'register', 'notify', 'files'];
		var secondary_notifications = ['network', 'forums', 'all_events', 'pubs'];
		var all_notifications = primary_notifications.concat(secondary_notifications);

		all_notifications.forEach(function(type, index) {
			if(typeof obj[type] === typeof undefined)
				return true;

			if(obj[type].count) {
				$('.' + type + '-button').fadeIn();
				if(replace || followup)
					$('.' + type + '-update').html(Number(obj[type].count));
				else
					$('.' + type + '-update').html(Number(obj[type].count) + Number($('.' + type + '-update').html()));
			}
			else {
				$('.' + type + '-update').html('0');
				$('#nav-' + type + '-sub').removeClass('show');
				$('.' + type + '-button').fadeOut(function() {
					sse_setNotificationsStatus();
				});
			}
			if(obj[type].notifications.length)
				sse_handleNotificationsItems(type, obj[type].notifications, replace, followup);
		});

		sse_setNotificationsStatus();

		// notice and info
		$.jGrowl.defaults.closerTemplate = '<div>[ ' + aStr.closeAll + ']</div>';

		if(obj.notice) {
			$(obj.notice.notifications).each(function() {
				$.jGrowl(this, { sticky: true, theme: 'notice' });
			});
		}

		if(obj.info) {
			$(obj.info.notifications).each(function(){
				$.jGrowl(this, { sticky: false, theme: 'info', life: 10000 });
			});
		}

		// load more notifications if visible notifications count becomes low
		if(sse_type  && sse_offset != -1 && $('#nav-' + sse_type + '-menu').children(':not(.tt-filter-active)').length < 15) {
			sse_bs_notifications(sse_type, false, true);
		}
	}

	function sse_handleNotificationsItems(notifyType, data, replace, followup) {

		var notifications_tpl = ((notifyType == 'forums') ? decodeURIComponent($("#nav-notifications-forums-template[rel=template]").html().replace('data-src', 'src')) : decodeURIComponent($("#nav-notifications-template[rel=template]").html().replace('data-src', 'src')));
		var notify_menu = $("#nav-" + notifyType + "-menu");
		var notify_loading = $("#nav-" + notifyType + "-loading");
		var notify_count = $("." + notifyType + "-update");

		if(replace && !followup) {
			notify_menu.html('');
			notify_loading.hide();
		}

		$(data).each(function() {

			// do not add a notification if it is already present
			if($('#nav-' + notifyType + '-menu .notification[data-b64mid=\'' + this.b64mid + '\']').length)
				return true;

			if(!replace && !followup && (this.thread_top && notifyType === 'network')) {
				$(document).trigger('hz:handleNetworkNotificationsItems', this);
			}

			html = notifications_tpl.format(this.notify_link,this.photo,this.name,this.addr,this.message,this.when,this.hclass,this.b64mid,this.notify_id,this.thread_top,this.unseen,this.private_forum, encodeURIComponent(this.mids), this.body);
			notify_menu.append(html);
		});

		if(!replace && !followup) {
			$("#nav-" + notifyType + "-menu .notification").sort(function(a,b) {
				a = new Date(a.dataset.when);
				b = new Date(b.dataset.when);
				return a > b ? -1 : a < b ? 1 : 0;
			}).appendTo('#nav-' + notifyType + '-menu');
		}

		$("#nav-" + notifyType + "-menu .notifications-autotime").timeago();

		if($('#tt-' + notifyType + '-only').hasClass('active'))
			$('#nav-' + notifyType + '-menu [data-thread_top=false]').addClass('tt-filter-active');

		if($('#cn-' + notifyType + '-input').length) {
			var filter = $('#cn-' + notifyType + '-input').val().toString().toLowerCase();
			if(filter) {
				filter = filter.indexOf('%') == 0 ? filter.substring(1) : filter;

				$('#nav-' + notifyType + '-menu .notification').each(function(i, el) {
					var cn = $(el).data('contact_name').toString().toLowerCase();
					var ca = $(el).data('contact_addr').toString().toLowerCase();
					if(cn.indexOf(filter) === -1 && ca.indexOf(filter) === -1)
						$(el).addClass('cn-filter-active');
					else
						$(el).removeClass('cn-filter-active');
				});
			}
		}
	}

	function sse_updateNotifications(type, mid) {

		if(type === 'pubs')
			return true;

		if(type === 'notify' && (mid !== bParam_mid || sse_type !== 'notify'))
			return true;
	/*
		var count = Number($('.' + type + '-update').html());

		count--;

		if(count < 1) {
			$('.' + type + '-update').html(count);
			$('.' + type + '-button').fadeOut(function() {
				sse_setNotificationsStatus();
			});
		}
		else {
			$('.' + type + '-update').html(count);
		}
	*/

		$('#nav-' + type + '-menu .notification[data-b64mid=\'' + mid + '\']').fadeOut(function() {
			this.remove();
		});
	}

	function sse_setNotificationsStatus(data) {
		var primary_notifications = ['dm', 'home', 'intros', 'register', 'notify', 'files'];
		var secondary_notifications = ['network', 'forums', 'all_events', 'pubs'];
		var all_notifications = primary_notifications.concat(secondary_notifications);

		var primary_available = false;
		var any_available = false;

		all_notifications.forEach(function(type, index) {
			if($('.' + type + '-button').css('display') == 'block') {
				any_available = true;
				if(primary_notifications.indexOf(type) > -1)
					primary_available = true;
			}
		});

		if(primary_available) {
			$('.notifications-btn-icon').removeClass('fa-exclamation-circle');
			$('.notifications-btn-icon').addClass('fa-exclamation-triangle');
		}
		else {
			$('.notifications-btn-icon').removeClass('fa-exclamation-triangle');
			$('.notifications-btn-icon').addClass('fa-exclamation-circle');
		}

		if(any_available) {
			$('.notifications-btn').css('opacity', 1);
			$('#no_notifications').hide();
			$('#notifications').show();
		}
		else {
			$('.notifications-btn').css('opacity', 0.5);
			$('#navbar-collapse-1').removeClass('show');
			$('#no_notifications').show();
			$('#notifications').hide();
		}

		if (typeof data !== typeof undefined) {
			data.forEach(function(nmid, index) {

				sse_rmids.push(nmid);

				if($('.notification[data-b64mid=\'' + nmid + '\']').length) {
					$('.notification[data-b64mid=\'' + nmid + '\']').each(function() {
						var n = this.parentElement.id.split('-');
						return sse_updateNotifications(n[1], nmid);
					});
				}

				// special handling for forum notifications
				$('.notification-forum').filter(function() {
					var fmids = decodeURIComponent($(this).data('b64mids'));
					var n = this.parentElement.id.split('-');
					if(fmids.indexOf(nmid) > -1) {
						var fcount = Number($('.' + n[1] + '-update').html());
						fcount--;
						$('.' + n[1] + '-update').html(fcount);
						if(fcount < 1) {
							$('.' + n[1] + '-button').fadeOut();
							$('#nav-' + n[1] + '-sub').removeClass('show');
						}
						var count = Number($(this).find('.bg-secondary').html());
						count--;
						$(this).find('.bg-secondary').html(count);
						if(count < 1)
							$(this).remove();
					}
				});
			});
		}

	}

	function sse_fallback() {
		$.get('/sse', function(obj) {
			if(! obj)
				return;

			console.log('sse fallback');
			console.log(obj);

			sse_handleNotifications(obj, false, false);
		});
	}
</script>

<div id="notifications_wrapper" class="mb-4">
	<div id="no_notifications" class="d-xl-none">
		{{$no_notifications}}
	</div>
	<div id="nav-notifications-template" rel="template">
		<a class="list-group-item text-decoration-none text-dark clearfix notification {6}" href="{0}" title="{13}" data-b64mid="{7}" data-notify_id="{8}" data-thread_top="{9}" data-contact_name="{2}" data-contact_addr="{3}" data-when="{5}">
			<img class="menu-img-3" data-src="{1}" loading="lazy">
			<div class="contactname"><span class="text-dark fw-bold">{2}</span> <span class="text-muted">{3}</span></div>
			<span class="text-muted">{4}</span><br>
			<span class="text-muted notifications-autotime" title="{5}">{5}</span>
		</a>
	</div>
	<div id="nav-notifications-forums-template" rel="template">
		<a class="list-group-item text-decoration-none clearfix notification notification-forum" href="{0}" title="{4} - {3}" data-b64mid="{7}" data-notify_id="{8}" data-thread_top="{9}" data-contact_name="{2}" data-contact_addr="{3}" data-b64mids='{12}'>
			<span class="float-end badge bg-secondary">{10}</span>
			<img class="menu-img-1" data-src="{1}" loading="lazy">
			<span class="">{2}</span>
			<i class="fa fa-{11} text-muted"></i>
		</a>
	</div>
	<div id="notifications" class="border border-top-0 rounded navbar-nav collapse">
		{{foreach $notifications as $notification}}
		<div class="rounded-top rounded-bottom border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse {{$notification.type}}-button">
			<a id="notification-link-{{$notification.type}}" class="collapsed list-group-item fakelink notification-link" href="#" title="{{$notification.title}}" data-bs-target="#nav-{{$notification.type}}-sub" data-bs-toggle="collapse" data-sse_type="{{$notification.type}}">
				<i class="fa fa-fw fa-{{$notification.icon}}"></i> {{$notification.label}}
				<span class="float-end badge bg-{{$notification.severity}} {{$notification.type}}-update"></span>
			</a>
		</div>
		<div id="nav-{{$notification.type}}-sub" class="rounded-bottom border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse notification-content" data-bs-parent="#notifications" data-sse_type="{{$notification.type}}">
			{{if $notification.viewall}}
			<a class="list-group-item text-decoration-none text-dark" id="nav-{{$notification.type}}-see-all" href="{{$notification.viewall.url}}">
				<i class="fa fa-fw fa-external-link"></i> {{$notification.viewall.label}}
			</a>
			{{/if}}
			{{if $notification.markall}}
			<div class="list-group-item cursor-pointer" id="nav-{{$notification.type}}-mark-all" onclick="markRead('{{$notification.type}}'); return false;">
				<i class="fa fa-fw fa-check"></i> {{$notification.markall.label}}
			</div>
			{{/if}}
			{{if $notification.filter}}
			{{if $notification.filter.posts_label}}
			<div class="list-group-item cursor-pointer" id="tt-{{$notification.type}}-only">
				<i class="fa fa-fw fa-filter"></i> {{$notification.filter.posts_label}}
			</div>
			{{/if}}
			{{if $notification.filter.name_label}}
			<div class="list-group-item clearfix notifications-textinput" id="cn-{{$notification.type}}-only">
				<div class="text-muted notifications-textinput-filter"><i class="fa fa-fw fa-filter"></i></div>
				<input id="cn-{{$notification.type}}-input" type="text" class="notification-filter form-control form-control-sm" placeholder="{{$notification.filter.name_label}}">
				<div id="cn-{{$notification.type}}-input-clear" class="text-muted notifications-textinput-clear d-none"><i class="fa fa-times"></i></div>
			</div>
			{{/if}}
			{{/if}}
			<div id="nav-{{$notification.type}}-menu" class="list-group list-group-flush"></div>
			<div id="nav-{{$notification.type}}-loading" class="list-group-item" style="display: none;">
				{{$loading}}<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span>
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
