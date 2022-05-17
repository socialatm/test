<div class="mb-3 field custom">
	<label for="id_{{$form_id}}">{{$label}}</label>
	<select class="form-control" name="{{$form_id}}" id="{{$form_id}}" >
	{{foreach $groups as $group}}
	<option value="{{$group.id}}" {{if $group.selected}}selected="selected"{{/if}} >{{$group.name}}</option>
	{{/foreach}}
	</select>
</div>
