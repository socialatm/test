<h3 class="mb-3">{{$app.name}}{{if $app.price}} ({{$app.price}}){{/if}}</h3>
<a class="app-icon" href="{{$app.url}}" >
	{{if $icon}}
	<i class="app-icon fa fa-fw fa-{{$icon}} mb-3"></i>
	{{else}}
	<img src="{{$app.photo}}" width="80" height="80" class="mb-3" />
	{{/if}}
</a>
<div class="mb-3">
	{{if $app.desc}}{{$app.desc}}{{/if}}
</div>
{{if $action_label}}
<div class="app-tools">
	<form action="{{$hosturl}}appman" method="post">
		<input type="hidden" name="papp" value="{{$app.papp}}" />
		{{if $action_label}}
		<button type="submit" name="install" value="{{$action_label}}" class="btn btn-{{if $installed}}outline-secondary{{else}}success{{/if}} btn-sm" title="{{$action_label}}" ><i class="fa fa-fw {{if $installed}}fa-refresh{{else}}fa-arrow-circle-o-down{{/if}}" ></i> {{$action_label}}</button>
		{{/if}}
		{{if $purchase && $app.type !== 'system'}}
		<a href="{{$app.page}}" class="btn btn-sm btn-link" title="{{$purchase}}" ><i class="fa fa-external-link"></i></a>
		{{/if}}
	</form>
</div>
{{/if}}


