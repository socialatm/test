<div class="rounded mb-3 vcard-card h-card">
	<div class="card mb-2">
		<div class="row">
			<div class="col-4" style="width: fit-content;">
				<div id="profile-photo-wrapper">
					<img class="rounded-start u-photo" src="{{$profile.photo}}?rev={{$profile.picdate}}" alt="{{$profile.fullname}}" style="width: 6rem; height:6rem;">
				</div>
			</div>
			<div class="col-7 m-1 p-0">
				{{if $editmenu.multi}}
				<div class="dropdown float-end">
					<a class="profile-edit-side-link float-end" data-bs-toggle="dropdown" href="#" ><i class="fa fa-pencil" title="{{$editmenu.edit.1}}"></i></a>
					<div class="dropdown-menu dropdown-menu-end" role="menu">
						{{foreach $editmenu.menu.entries as $e}}
						<a href="profiles/{{$e.id}}" class="dropdown-item"><img class="menu-img-1" src='{{$e.photo}}'> {{$e.profile_name}}</a>
						{{/foreach}}
						{{if $editmenu.menu.cr_new}}
						<a href="profiles/new" id="profile-listing-new-link" class="dropdown-item">{{$editmenu.menu.cr_new}}</a>
						{{/if}}
					</div>
				</div>
				{{elseif $editmenu}}
				<a class="profile-edit-side-link float-end" href="{{$editmenu.edit.0}}" ><i class="fa fa-pencil" title="{{$editmenu.edit.1}}"></i></a>
				{{/if}}

				<div class="text-truncate">
					<strong class="fn p-name">{{$profile.fullname}}{{if $profile.online}}<i class="fa fa-fw fa-asterisk text-danger ps-2" title="{{$profile.online}}"></i>{{/if}}</strong>
				</div>
				<div class="text-truncate">
					<small class="text-muted p-adr">{{$profile.reddress}}</small>
				</div>
				{{if $connect}}
				<div class="mt-1">
					<a href="{{$connect_url}}" class="btn btn-success btn-sm" rel="nofollow">
						<i class="fa fa-plus"></i> {{$connect}}
					</a>
				</div>
				{{/if}}
			</div>
		</div>
	</div>
	{{if $details}}
	<div class="vcard ps-2 pe-2">
		{{if $profile.pdesc}}
		<div class="mb-2">{{$profile.pdesc}}</div>
		{{/if}}
		{{if $location}}
		<dl class="mb-0 pb-1">
			<dt class="location-label">{{$location}}</dt>
			<dd class="adr h-adr">
				{{if $profile.address}}
				<div class="street-address p-street-address">{{$profile.address}}</div>
				{{/if}}
				<div class="city-state-zip">
					<span class="postal-code p-postal-code">{{$profile.postal_code}}</span>
					<span class="locality p-locality">{{$profile.locality}}</span>
				</div>
				{{if $profile.region}}
				<div class="region p-region">{{$profile.region}}</div>
				{{/if}}
				{{if $profile.country_name}}
				<div class="country-name p-country-name">{{$profile.country_name}}</div>
				{{/if}}
			</dd>
		</dl>
		{{/if}}
		{{if $hometown}}
		<dl class="mb-0 pb-1">
			<dt class="hometown-label">{{$hometown}}</dt>
			<dd class="p-hometown">{{$profile.hometown}}</dd>
		</dl>
		{{/if}}
		{{if $gender}}
		<dl class="mb-0 pb-1">
			<dt class="gender-label">{{$gender}}</dt>
			<dd class="p-gender">{{if $profile.gender_icon}}<i class="fa fa-{{$profile.gender_icon}}"></i>&nbsp;{{/if}}{{$profile.gender}}</dd>
		</dl>
		{{/if}}
		{{if $marital}}
		<dl class="mb-0 pb-1">
			<dt class="marital-label"><span class="heart"><i class="fa fa-heart"></i>&nbsp;</span>{{$marital}}</dt>
			<dd class="marital-text">{{$profile.marital}}</dd>
		</dl>
		{{/if}}
		{{if $homepage}}
		<dl class="mb-0 pb-1">
			<dt class="homepage-label">{{$homepage}}</dt>
			<dd class="homepage-url u-url">{{$profile.homepage}}</dd>
		</dl>
		{{/if}}
		<div class="hcard-addon"></div>
	</div>
	{{/if}}
</div>
{{if $details}}
{{$chanmenu}}
{{$contact_block}}
{{/if}}


