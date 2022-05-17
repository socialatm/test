	<!--div class='clearfix mb-3'>
		<label class="mainlabel" for='id_{{$field.0}}'>{{$field.1}}</label>
		<div class='onoff' id="id_{{$field.0}}_onoff">
			<input  type="hidden" name='{{$field.0}}' id='id_{{$field.0}}' value="{{$field.2}}">
			<a href="#" class='off'>
				{{if $field.4}}{{$field.4.0}}{{else}}OFF{{/if}}
			</a>
			<a href="#" class='on'>
				{{if $field.4}}{{$field.4.1}}{{else}}ON{{/if}}
			</a>
		</div>
		<small class='form-text text-muted'>{{$field.3}}</small>
	</div-->

<div class="form-check form-switch">
	<input class="form-check-input" type="checkbox" id="id_{{$field.0}}" value="{{$field.2}}" name="{{$field.0}}">
	<label class="form-check-label" for="id_{{$field.0}}">{{$field.1}}</label>
	<small class='form-text text-muted'>{{$field.3}}</small>
</div>
