<tr>
	<td>
		<label class="mainlabel" for="me_id_{{$field.0}}">{{$field.1}}</label>
		<span class="field_abook_help">{{$field.6}}</span>
	</td>
	<td>
		{{if $field.5}}
		<span class="text-nowrap text-danger">
			{{$inherited}}
			{{if $field.7}}
			<i class="fa fa-check-square-o"></i>
			{{else}}
			<i class="fa fa-square-o"></i>
			{{/if}}
		</span>
		{{/if}}
	</td>
	<td>
		{{if $is_system_role}}
		{{if $field.3}}
		<i class="fa fa-check-square-o"></i>
		{{else}}
		<i class="fa fa-square-o"></i>
		{{/if}}
		{{else}}
		<input type="checkbox" name="{{$field.0}}" value="{{$field.4}}" {{if $field.3}}checked="checked"{{/if}} />
		{{/if}}

	</td>

</tr>
