{{foreach $tabs as $tab}}
<a class="nav-link{{if $tab.sel}} {{$tab.sel}}{{/if}}" href="{{$tab.url}}"{{if $tab.title}} title="{{$tab.title}}"{{/if}}><i class="fa fa-fw fa-{{$tab.icon}} generic-icons-nav"></i>{{$tab.label}}</a>
{{/foreach}}
