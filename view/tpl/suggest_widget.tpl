<div class="widget suggestions-widget">
<h3>{{$title}}</h3>
{{if $entries}}
{{foreach $entries as $child}}
{{include file="suggest_friends.tpl" entry=$child}}
{{/foreach}}
{{/if}}
<div class="clear"></div>
<div class="suggest-widget-more"><a href="directory?f=&suggest=1">{{$more}}</a></div>
</div>
