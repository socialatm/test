<form action="{{$dest_url}}" id="{{$form_id}}" method="post" >
	<input type="hidden" name="auth-params" value="login" />
	<div class="login-wrapper d-grid gap-2">
		{{include file="field_input.tpl" field=$lname}}
		{{include file="field_password.tpl" field=$lpassword}}
		{{include file="field_checkbox.tpl" field=$remember_me}}
		<button type="submit" name="submit" class="btn btn-primary">{{$login}}</button>
		{{if $register}}<a href="{{$register.link}}" title="{{$register.title}}" class="register-link float-end">{{$register.desc}}</a>{{/if}}
		{{if $lostlink}}<a href="lostpass" title="{{$lostpass}}" class="lost-pass-link">{{$lostlink}}</a>{{/if}}
		<hr>
		<a href="rmagic" class="btn btn-outline-success">{{$remote_login}}</a>
	</div>
	{{foreach $hiddens as $k=>$v}}
		<input type="hidden" name="{{$k}}" value="{{$v}}" />
	{{/foreach}}
</form>
{{if $login_page}}
<script type="text/javascript"> $(document).ready(function() { $("#id_{{$lname.0}}").focus();} );</script>
{{/if}}
