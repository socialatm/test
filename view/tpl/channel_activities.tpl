<div class="mb-1 text-uppercase">
	<a href="{{$url}}"><i class="fa fa-fw fa-{{$icon}} generic-icons-nav"></i>{{$label}}</a>
</div>
<div class="row mb-3">
	{{foreach $items as $i}}
	<div class="col-sm-4 mb-3">
		<div class="card">
			<a href="{{$i.url}}" class="text-dark">
				<div class="card-body">
					{{if $i.title}}
						<strong>{{$i.title}}</strong>
						<hr>
					{{/if}}
					{{$i.summary}}
				</div>
				<div class="card-footer text-muted autotime" title="{{$i.footer}}">{{$i.footer}}</div>
			</a>
		</div>
	</div>
	{{/foreach}}
</div>
