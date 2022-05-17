	<div id="id_{{$field.0}}_wrapper" class="mb-3">
		<label for="id_{{$field.0}}" id="label_{{$field.0}}">{{$field.1}}{{if $field.4}}<sup class="required zuiqmid"> {{$field.4}}</sup>{{/if}}</label>
		<input class="form-control" name="{{$field.0}}" id="id_{{$field.0}}" type="text" value="{{$field.2}}"{{if $field.5}} {{$field.5}}{{/if}}>
		<small id="help_{{$field.0}}" class="form-text text-muted">{{$field.3}}</small>
	</div>
{{*
	COMMENTS for this template:
	@author hilmar runge, 2020.01
	$field array index:
		.0	field name: name=... for input, id=id_... for input, id=label_... for label, id=help_... for text
		.1	label text
		.2	field value
		.3	help text
		.4	label text addition, used for qmc
		.5	additional html attributes
	css classes used:
		.required, .code
		.form-control, .form-text, .text-muted
*}}
