<div class="generic-content-wrapper">
	<div class="section-title-wrapper clearfix">
		<div class="btn-group float-end">
			<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{$sort}}">
				<i class="fa fa-sort"></i>
			</button>
			<div class="dropdown-menu dropdown-menu-end">
				<a class="dropdown-item" href="directory?f=&order=date{{$suggest}}">{{$date}}</a>
				<a class="dropdown-item" href="directory?f=&order=normal{{$suggest}}">{{$normal}}</a>
				<a class="dropdown-item" href="directory?f=&order=reversedate{{$suggest}}">{{$reversedate}}</a>
				<a class="dropdown-item" href="directory?f=&order=reverse{{$suggest}}">{{$reverse}}</a>
			</div>
		</div>
		<h2>{{$dirlbl}}{{if $search}}:&nbsp;{{$safetxt}}{{/if}}</h2>
	</div>
	{{foreach $entries as $entry}}
		{{include file="direntry.tpl"}}
	{{/foreach}}
	{{** make sure this element is at the bottom - we rely on that in endless scroll **}}
	<div id="page-end" class="float-start w-100"></div>
</div>
<script>$(document).ready(function() { loadingPage = false;});</script>
<div id="page-spinner" class="spinner-wrapper">
	<div class="spinner m"></div>
</div>
