<h3>{{$chtitle_str}}: 100%</h3>

<div>
	<div class="progress mb-2">
		<div class="progress-bar progress-bar-striped bg-primary" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
	</div>
	<div>
		<span class="text-muted">{{$chcompleted_str}}</span>
	</div>
</div>

<hr>

<h3>{{$ctitle_str}}: <span id="cprogress-label">{{$cprogress_str}}</span></h3>

<div id="cprogress">
	<div class="progress mb-2">
		<div id="cprogress-bar" class="progress-bar progress-bar-striped bg-warning{{if $cprogress < 100}} progress-bar-animated{{/if}}" role="progressbar" style="width: {{$cprogress}}%" aria-valuenow="{{$cprogress}}" aria-valuemin="0" aria-valuemax="100"></div>
	</div>
	<div id="cprogress-resume" class="{{if $cprogress == 100}}d-none{{/if}}">
		<a href="/import_progress/resume_itemsync" class="text-capitalize">[ {{$resume_str}} ]</a> <span class="text-muted">{{$resume_helper_str}}</span>
	</div>
	<div id="cprogress-completed" class="{{if $cprogress < 100}}d-none{{/if}}">
		<span class="text-muted">{{$ccompleted_str}}</span>
	</div>
</div>

<hr>

<h3>{{$ftitle_str}}: <span id="fprogress-label">{{$fprogress_str}}</span></h3>

<div id="fprogress">
	<div  class="progress mb-2">
		<div id="fprogress-bar" class="progress-bar progress-bar-striped bg-info{{if $fprogress < 100}} progress-bar-animated{{/if}}" role="progressbar" style="width: {{$fprogress}}%" aria-valuenow="{{$fprogress}}" aria-valuemin="0" aria-valuemax="100"></div>
	</div>
	<div id="fprogress-resume" class="{{if $fprogress == 100}}d-none{{/if}}">
		<a href="/import_progress/resume_filesync" class="text-capitalize">[ {{$resume_str}} ]</a> <span class="text-muted">{{$resume_helper_str}}</span>
	</div>
	<div id="fprogress-completed" class="{{if $fprogress < 100}}d-none{{/if}}">
		<span class="text-muted">{{$fcompleted_str}}</span>
	</div>
</div>
