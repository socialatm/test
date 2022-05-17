	<div id='id_{{$field.0}}_wrapper' class='mb-3 field select'>
		<label for='id_{{$field.0}}'>{{$field.1}}</label>
		<select class="form-control" name='{{$field.0}}' id='id_{{$field.0}}'>
			{{foreach $field.4 as $group=>$opts}}
				<optgroup label='{{$group}}'>
				{{foreach $opts as $opt=>$val}}
					<option value="{{$opt}}" {{if $opt==$field.2}}selected="selected"{{/if}}>{{$val}}</option>{{/foreach}}
				{{/foreach}}
				</optgroup>
		</select>
		<small class='help-block'>{{$field.3}}</small>
	</div>
