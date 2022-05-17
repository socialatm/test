<button id="contact-tools" class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	<i class="fa fa-cog"></i>&nbsp;{{$tools_label}}
</button>
<div class="dropdown-menu">
	<a class="dropdown-item contact-tool" href="#" title="{{$tools.refresh.title}}" data-cmd="refresh">{{$tools.refresh.label}}</a>
	<a class="dropdown-item contact-tool" href="#" title="{{$tools.rephoto.title}}" data-cmd="resetphoto">{{$tools.rephoto.label}}</a>
	<div class="dropdown-divider"></div>
	<a class="dropdown-item contact-tool" href="#" title="{{$tools.block.title}}"  data-cmd="block">{{$tools.block.label}}</a>
	<a class="dropdown-item contact-tool" href="#" title="{{$tools.ignore.title}}" data-cmd="ignore">{{$tools.ignore.label}}</a>
	<a class="dropdown-item contact-tool" href="#" title="{{$tools.archive.title}}" data-cmd="archive">{{$tools.archive.label}}</a>
	<a class="dropdown-item contact-tool" href="#" title="{{$tools.hide.title}}" data-cmd="hide">{{$tools.hide.label}}</a>
	<div class="dropdown-divider"></div>
	<a class="dropdown-item contact-tool" href="#" title="{{$tools.delete.title}}" data-cmd="drop">{{$tools.delete.label}}</a>
</div>
