<a class="navbar-app nav-link{{if $app.active}} active{{/if}}" href="{{$app.url}}" title="{{$app.name}}">
	{{if $icon}}
	<i class="fa fa-fw fa-{{$icon}}"></i>
	<span class="d-lg-none">{{$app.name}}</span>
	{{else}}
	<img src="{{$app.photo}}" width="16" height="16" />
	<div class="d-lg-none d-inline-block" style="margin-left: 9px">{{$app.name}}</div>
	{{/if}}
</a>
