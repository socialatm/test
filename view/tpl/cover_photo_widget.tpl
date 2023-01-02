<script>
//	var aside_padding_top;
//	var section_padding_top;
//	var coverSlid = false;
//	var hide_cover = Boolean({{$hide_cover}});
	var cover_height;

	$(document).ready(function() {
		if(! $('#cover-photo').length)
			return;

		$('#cover-photo').removeClass('d-none');
		cover_height = Math.ceil($(window).width()/2.75862069);
		$('#cover-photo').css('height', cover_height + 'px');
		datasrc2src('#cover-photo > img');

	});

/*



	function coverVisibleActions() {
		$('body').css('cursor', 'n-resize');
		$('.navbar').removeClass('fixed-top');
		$('main').css('margin-top', - $('nav').outerHeight(true) + 'px');
		$('main').css('opacity', 0);
	}

	function coverHiddenActions() {
		$('body').css('cursor', '');
		$('.navbar').addClass('fixed-top');
		$('main').css('margin-top', '');
		$('main').css('opacity', 1);
	}
*/

</script>

<div class="d-none" id="cover-photo" title="{{$hovertitle}}">
	{{$photo_html}}
	<div id="cover-photo-caption">
		<h2>{{$title}}</h2>
		<h4>{{$subtitle}}</h4>
	</div>
</div>
