<a class="list-group-item{{if $class}} {{$class}}{{/if}} generic-content-wrapper p-2 fakelink" href="{{if $click}}#{{else}}{{$url}}{{/if}}" {{if $click}}onclick="{{$click}}"{{/if}}>
	<img class="menu-img-3 me-2" src="{{$photo}}" title="{{$title}}" alt="" loading="lazy" >{{if $perminfo}}{{include "connstatus.tpl"}}{{/if}}
	<span class="contactname">{{$name}}</span>
	<span class="dropdown-sub-text">{{$addr}}<br>{{$network}}</span>
</a>
