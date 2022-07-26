<div class="mb-1 text-uppercase">
	<a href="{{$url}}"><i class="fa fa-fw fa-{{$icon}} generic-icons-nav"></i>{{$label}}</a>
</div>
<div id="photo-album" class="mb-4">
	{{foreach $items as $i}}
	<a href="{{$i.url}}" title="{{$i.alt}}">
		<img src="{{$i.src}}" width="{{$i.width}}" height="{{$i.height}}" alt="{{$i.alt}}">
		<div class='jg-caption autotime' title="{{$i.edited}}"></div>
	</a>
	{{/foreach}}
</div>
<script>
	$('#photo-album').justifiedGallery({
		border: 0,
		margins: 3,
		maxRowsCount: 1,
		waitThumbnailsLoad: false
	});
</script>
