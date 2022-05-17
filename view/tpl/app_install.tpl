<div>
	<h2>{{$papp.name}}</h2>
</div>
<div class="mb-3">
	{{$papp.desc}}
</div>
<form action="appman" method="post">
	<input type="hidden" name="papp" value="{{$papp.papp}}" />
	<input type="hidden" name="return_path" value="{{$return_path}}" />
	<button type="submit" name="install" value="install" class="btn btn-success">
		<i class="fa fa-fw fa-arrow-circle-o-down"></i> {{$install}}
	</button>
</form>
