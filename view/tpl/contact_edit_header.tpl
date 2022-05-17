<div class="float-start me-2">
	<a href="{{$href}}" title="{{$link_label}}" target="_blank">
		<img src="{{$img_src}}" class="rounded" style="width: 3rem; height: 3rem;" />
	</a>
</div>
<div class="m-1">
	<div class="text-truncate h3 m-0"><strong>{{if $is_group}}<i class="fa fa-comments-o" title="{{$group_label}}"></i> {{/if}}{{$name}}</strong></div>
	<div class="text-truncate text-muted">{{$addr}}</div>
</div>
