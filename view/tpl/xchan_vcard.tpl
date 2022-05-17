<div class="card mb-3 h-card">
	<div class="row">
		<div class="col-4" style="width: 7rem;">
			<a href="{{$link}}" >
				<img class="u-photo rounded-start" src="{{$photo}}" alt="{{$name}}" style="width: 6rem; height:6rem;">
			</a>
		</div>
		<div class="col-7 m-1 p-0">
			<div class="text-truncate">
				<strong class="fn p-name">{{$name}}</strong>
			</div>
			<div class="text-truncate">
				<small class="text-muted p-adr">{{$addr}}</small>
			</div>
			{{if $connect}}
			<div class="mt-1">
				<a href="follow?f=&url={{$follow}}&interactive=1" class="btn btn-success btn-sm" rel="nofollow">
					<i class="fa fa-plus"></i> {{$connect}}
				</a>
			</div>
			{{/if}}
		</div>
	</div>
</div>

