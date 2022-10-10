{{if !$preview }}
<div id="threads-begin"></div>
{{/if}}
{{if $photo_item}}
{{$photo_item}}
{{/if}}
{{foreach $threads as $thread_item}}
{{include file="{{$thread_item.template}}" item=$thread_item}}
{{/foreach}}
{{if !$preview }}
<div id="threads-end"></div>
<div id="conversation-end"></div>
<div class="modal" id="conversation_settings" tabindex="-1" role="dialog" aria-labelledby="conversation_settings_label" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="conversation_settings_label">{{$conversation_tools}}</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
			</div>
			<div class="modal-body" id="conversation_settings_body">
				{{$wait}}
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{/if}}
