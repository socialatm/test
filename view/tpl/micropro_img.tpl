<div class="contact-block-div{{if $class}} {{$class}}{{/if}}">
    <a class="contact-block-link{{if $class}} {{$class}}{{/if}}{{if $click}} fakelink{{/if}}" href="{{if $click}}#{{else}}{{$url}}{{/if}}" {{if $click}}onclick="{{$click}}"{{/if}}><img class="contact-block-img{{if $class}} {{$class}}{{/if}}" src="{{$photo}}" title="{{$title}}" alt="" loading="lazy" >{{if $perminfo}}{{include "connstatus.tpl"}}{{/if}}
    </a>
</div>
