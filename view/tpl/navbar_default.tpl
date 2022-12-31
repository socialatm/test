<nav class="navbar fixed-top navbar-expand-sm navbar-dark bg-primary text-white">
	<div class="container-fluid flex-nowrap">
			{{* start Toggle button *}}
		<button
			class="navbar-toggler"
			type="button"
			data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
			<i class="fa fa-bars"></i>
	  	</button>
			{{* end Toggle button *}}

		<!-- start Collapsible wrapper -->
		<div class="collapse navbar-collapse" id="navbarSupportedContent">

			<a class="navbar-brand m-2" href="{{$baseurl}}">
				<img src="/images/hz-white-32.png" alt="Home">
  			</a>
		
			{{if $userinfo}}

				{{* start new search *}}
			<form class="d-flex" role="search" action="network">
				<input class="form-control m-2" id="search" name="search" type="search" placeholder="Search" aria-label="Search" required>
				<button class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i></button>
			</form>
				{{* end new search *}}

			<div class="d-flex flex-row-reverse float-end">
				<div class="dropdown">
					<div class="fakelink usermenu" data-bs-toggle="dropdown">
						<img id="avatar" src="{{$userinfo.icon}}" alt="{{$userinfo.name}}" class="rounded-circle">
						<i class="fa fa-caret-down"></i>
					</div>
							{{* start of the left side dropdown menu under the user profile pic *}}
						{{if $is_owner}} 
					<div class="dropdown-menu" role="menu">
						{{foreach $nav.usermenu as $usermenu}}
						<a class="dropdown-item{{if $usermenu.2}} active{{/if}}"  href="{{$usermenu.0}}" title="{{$usermenu.3}}" role="menuitem" id="{{$usermenu.4}}">{{$usermenu.1}}</a>
						{{/foreach}}
						{{if $nav.group}}
							<a class="dropdown-item" href="{{$nav.group.0}}" title="{{$nav.group.3}}" role="menuitem" id="{{$nav.group.4}}">{{$nav.group.1}}</a>
						{{/if}}

						{{if $nav.manage}}
							<a class="dropdown-item{{if $sel.name == Manage}} active{{/if}}" href="{{$nav.manage.0}}" title="{{$nav.manage.3}}" role="menuitem" id="{{$nav.manage.4}}">{{$nav.manage.1}}</a>
						{{/if}}

						{{if $nav.channels}}
							{{foreach $nav.channels as $chan}}
							<a class="dropdown-item" href="manage/{{$chan.channel_id}}" title="{{$chan.channel_name}}" role="menuitem"><i class="fa fa-circle{{if $localuser == $chan.channel_id}} text-success{{else}} invisible{{/if}}"></i> {{$chan.channel_name}}</a>
						{{/foreach}}
						{{/if}}

						{{if $nav.profiles}}
							<a class="dropdown-item" href="{{$nav.profiles.0}}" title="{{$nav.profiles.3}}" role="menuitem" id="{{$nav.profiles.4}}">{{$nav.profiles.1}}</a>
						{{/if}}

						{{if $nav.settings}}
						<div class="dropdown-divider"></div>
							<a class="dropdown-item{{if $sel.name == Settings}} active{{/if}}" href="{{$nav.settings.0}}" title="{{$nav.settings.3}}" role="menuitem" id="{{$nav.settings.4}}">{{$nav.settings.1}}</a>
						{{/if}}

						{{if $nav.admin}}
						<div class="dropdown-divider"></div>
							<a class="dropdown-item{{if $sel.name == Admin}} active{{/if}}" href="{{$nav.admin.0}}" title="{{$nav.admin.3}}" role="menuitem" id="{{$nav.admin.4}}">{{$nav.admin.1}}</a>
							{{/if}}

							{{if $nav.logout}}
							<div class="dropdown-divider"></div>
								<a class="dropdown-item" href="{{$nav.logout.0}}" title="{{$nav.logout.3}}" role="menuitem" id="{{$nav.logout.4}}">{{$nav.logout.1}}</a>
							{{/if}}
						</div>
						{{/if}}
							{{* end of the left side dropdown menu under the user profile pic *}}

						{{if ! $is_owner}} <!-- this is the remote user menu with take me home & log me out of this site -->
						<div class="dropdown-menu" role="menu" aria-labelledby="avatar">
							<a class="dropdown-item" href="{{$nav.rusermenu.0}}" role="menuitem">{{$nav.rusermenu.1}}</a>
							<a class="dropdown-item" href="{{$nav.rusermenu.2}}" role="menuitem">{{$nav.rusermenu.3}}</a>
						</div>
						{{/if}}
						<!-- end the remote user menu with take me home & log me out of this site -->
					</div>

						{{* start print the page location uncomment if you want to use it
					{{if $sel.name}} 
					<div id="nav-app-link-wrapper" class="navbar-nav{{if $sitelocation}} has_location{{/if}}">
						<a id="nav-app-link" href="{{$url}}" class="nav-link text-truncate" style="width: 100%">
							{{$sel.name}}
							{{if $sitelocation}}
							<br><small>{{$sitelocation}}</small>
							{{/if}}
						</a>
					</div>
					{{/if}} 
						end print the page location *}}

						{{* start the setting link with the cog icon *}}
					{{if $settings_url}} 
					<div id="nav-app-settings-link-wrapper" class="navbar-nav">
						<a id="nav-app-settings-link" href="{{$settings_url}}/?f=&rpath={{$url}}" class="nav-link">
							<i class="fa fa-fw fa-cog"></i>
						</a>
					</div>
					{{/if}}
						{{* end the setting link with the cog icon *}}

					<!-- notifications button -->
					{{if $localuser || $nav.pubs}}
					<div id="notifyBtn" class="btn btn-primary notifyBtn">
						<a class="nav-link text-white" data-bs-toggle="modal" data-bs-target="#notifyModal"><i class="fa fa-bell-o" aria-hidden="true"></i><span class="float-end badge bg-danger notify-update rounded-circle"></span></a>
					</div>
					{{/if}}
					<!-- end notifications button -->

					<!-- start new apps button -->
					<button type="button" class="btn btn-primary appBtn" data-bs-toggle="modal" data-bs-target="#appModal">
						<i class="fa fa-cubes" aria-hidden="true"></i>
					</button>
					<!-- end new apps button -->

					<!-- Button trigger for contextual help modal -->
					<button type="button" class="btn btn-primary helpBtn" data-bs-toggle="modal" data-bs-target="#helpModal">
						<i class="fa fa-question" aria-hidden="true"></i>
					</button>
				<!-- end Button trigger for contextual help modal -->
			</div>
			{{/if}}
		
			<!-- start login/logout/register -->
			<ul class="navbar-nav">
				{{if $nav.login && !$userinfo}}
				<li class="nav-item d-lg-flex">
					{{if $nav.loginmenu.1.4}}
					<a class="nav-link" href="#" title="{{$nav.loginmenu.1.3}}" id="{{$nav.loginmenu.1.4}}" data-bs-toggle="modal" data-bs-target="#nav-login">
					{{$nav.loginmenu.1.1}}
					</a>
					{{else}}
					<a class="nav-link" href="login" title="{{$nav.loginmenu.1.3}}">
						{{$nav.loginmenu.1.1}}
					</a>
					{{/if}}
				</li>
				{{/if}}
				{{if $nav.register}}
				<li class="nav-item {{$nav.register.2}} d-lg-flex">
					<a class="nav-link" href="{{$nav.register.0}}" title="{{$nav.register.3}}" id="{{$nav.register.4}}">{{$nav.register.1}}</a>
				</li>
				{{/if}}
				{{if $nav.alogout}}
				<li class="nav-item {{$nav.alogout.2}} d-lg-flex">
					<a class="nav-link" href="{{$nav.alogout.0}}" title="{{$nav.alogout.3}}" id="{{$nav.alogout.4}}">{{$nav.alogout.1}}</a>
				</li>
				{{/if}}
			</ul>
			<!-- end login/logout/register -->
		</div> <!-- end Collapsible wrapper -->
	</div><!-- end of container -->
</nav>

<!-- Modal for contextual help -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  	<div class="modal-dialog">
    	<div class="modal-content">
      		<div class="modal-header">
        		<h1 class="modal-title fs-5" id="helpModalLabel">{{$nav.help.3}}</h1>
        		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      		</div>
      		<div class="modal-body">
        		{{$nav.help.5}}
      		</div>
      		<div class="modal-footer">
        		<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      		</div>
    	</div>
  	</div>
</div>
<!-- end modal for contextual help -->

<!-- start new app modal -->

<div class="modal fade" id="appModal" tabindex="-1" aria-labelledby="appModalLabel" aria-hidden="true">
  	<div class="modal-dialog">
    	<div class="modal-content">
      		<div class="modal-header">
        		<h1 class="modal-title fs-5" id="appModalLabel">Apps</h1>
        		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      		</div>
      		<div class="modal-body">
				<div>
					{{if $channel_apps.0}}
					<div class="text-uppercase text-muted">
						{{$channelapps}}
					</div>
					<div class="nav nav-pills flex-column">
						{{foreach $channel_apps as $channel_app}}
							{{$channel_app}}
						{{/foreach}}
					</div>
					{{/if}}

					{{if $navbar_apps.0}}
					<div class="d-lg-none dropdown-header text-uppercase text-muted">
						{{$pinned_apps}}
					</div>
					<div id="nav-app-bin-container" class="d-lg-none nav nav-pills flex-column">
						{{foreach $navbar_apps as $navbar_app}}
							{{$navbar_app|replace:'fa':'generic-icons-nav fa'}}
						{{/foreach}}
					{{/if}}
				</div>

					{{if $is_owner}}
				<div class="text-uppercase text-muted nav-link">
					{{$featured_apps}}
				</div>

				<div id="app-bin-container" data-token="{{$form_security_token}}" class="nav nav-pills flex-column">
					{{foreach $nav_apps as $nav_app}}
						{{$nav_app}}
					{{/foreach}}
				</div>

				<hr>

				<div class="nav nav-pills flex-column">
					<a class="nav-link" href="/apps"><i class="generic-icons-nav fa fa-fw fa-plus"></i>{{$addapps}}</a>
				</div>
					{{else}}
				<div class="text-uppercase text-muted nav-link">
					{{$sysapps}}
				</div>
				<div class="nav nav-pills flex-column">
					{{foreach $nav_apps as $nav_app}}
						{{$nav_app}}
					{{/foreach}}
				</div>
					{{/if}}
				</div>
      		</div>
      		<div class="modal-footer">
        		<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      		</div>
    	</div>
  	</div>
</div>
<!-- end new app modal -->
