<div id="follow-sidebar" class="widget">
	<h3>{{$connect}}</h3>
	<form action="follow" method="post" />
		<div class="input-group">
			<input class="form-control" type="text" name="url" title="{{$hint}}" placeholder="{{$desc}}" />
			<button class="btn btn-sm btn-success" type="submit" name="submit" value="{{$follow}}" title="{{$follow}}"><i class="fa fa-fw fa-plus"></i></button>
		</div>
	</form>
	{{if $abook_usage_message}}
	<div class="usage-message" id="abook-usage-message">{{$abook_usage_message}}</div>
	{{/if}}
</div>
