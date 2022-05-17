<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<base href="{{$baseurl}}/" />
<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, user-scalable={{$user_scalable}}" />
{{$metas}}
{{$head_css}}
{{$js_strings}}
{{$head_js}}
{{$linkrel}}
{{$plugins}}
<script>
	var updateInterval = {{$update_interval}};
	var sse_enabled = {{$sse_enabled}};
	var localUser = {{if $local_channel}}{{$local_channel}}{{else}}false{{/if}};
	var zid = {{if $zid}}'{{$zid}}'{{else}}null{{/if}};
	var justifiedGalleryActive = false;
	{{if $channel_hash}}var channelHash = '{{$channel_hash}}';{{/if}}
	var channelId = {{if $channel_id}}{{$channel_id}}{{else}}false{{/if}};{{* Used in e.g. autocomplete *}}
	var preloadImages = {{$preload_images}};
	var auto_save_draft = {{$auto_save_draft}};
	{{if $module}}var module = '{{$module}}';{{/if}}
</script>



