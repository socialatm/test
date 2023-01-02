<script>
	$(document).ready(function() {
		if(! $('#cover-photo').length)
			return;

		datasrc2src('#cover-photo > img');
	});
</script>

<div class="" id="cover-photo" title="{{$hovertitle}}">
	{{$photo_html}}
	<div id="cover-photo-caption">
		<h2>{{$title}}</h2>
		<h4>{{$subtitle}}</h4>
	</div>
</div>
