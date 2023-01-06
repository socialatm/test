[h2]Database updates[/h2]

In the [observer.baseurl]/admin/dbsync page the administrator can check if any update was not successful and, if so, retry it.

If an update has failed but doesn't register as failed for some reason, the administrator can attempt to re-execute the update. For example for DB update #1999, by visiting the webpage:

https://hubzilla.com.bradmin/dbsync/1999

[h2]Database Tables[/h2]
    [table border=1 class="table"]
        [tr]
            [th]Table[/th][th]Description[/th]
        [/tr]
[tr][td][zrl=[baseurl]/help/database/db_abconfig]abconfig[/zrl][/td][td]arbitrary storage for connections of local channels[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_abook]abook[/zrl][/td][td]connections of local channels[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_account]account[/zrl][/td][td]service provider account[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_addon]addon[/zrl][/td][td]registered plugins[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_app]app[/zrl][/td][td]personal app data[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_attach]attach[/zrl][/td][td]file attachments[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_auth_codes]auth_codes[/zrl][/td][td]OAuth usage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_cache]cache[/zrl][/td][td]OEmbed cache[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_cal]cal[/zrl][/td][td]CalDAV containers for events[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_channel]channel[/zrl][/td][td]local channels[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_chat]chat[/zrl][/td][td]chat room content[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_chatpresence]chatpresence[/zrl][/td][td]channel presence information for chat[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_chatroom]chatroom[/zrl][/td][td]data for the actual chat room[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_clients]clients[/zrl][/td][td]OAuth usage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_config]config[/zrl][/td][td]main configuration storage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_conv]conv[/zrl][/td][td]Diaspora private messages meta conversation structure[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_event]event[/zrl][/td][td]Events[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_pgrp_member]pgrp_member[/zrl][/td][td]privacy groups (collections), group info[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_pgrp]pgrp[/zrl][/td][td]privacy groups (collections), member info[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_hook]hook[/zrl][/td][td]plugin hook registry[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_hubloc]hubloc[/zrl][/td][td]xchan location storage, ties a hub location to an xchan[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_iconfig]iconfig[/zrl][/td][td]extensible arbitrary storage for items[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_issue]issue[/zrl][/td][td]future bug/issue database[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_item]item[/zrl][/td][td]all posts and webpages[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_item_id]item_id[/zrl][/td][td](deprecated by iconfig) other identifiers on other services for posts[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_likes]likes[/zrl][/td][td]likes of 'things'[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_mail]mail[/zrl][/td][td]private messages[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_menu]menu[/zrl][/td][td]webpage menu data[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_menu_item]menu_item[/zrl][/td][td]entries for webpage menus[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_notify]notify[/zrl][/td][td]notifications[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_obj]obj[/zrl][/td][td]object data for things (x has y)[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_outq]outq[/zrl][/td][td]output queue[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_pconfig]pconfig[/zrl][/td][td]personal (per channel) configuration storage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_photo]photo[/zrl][/td][td]photo storage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_poll]poll[/zrl][/td][td]data for polls[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_poll_elm]poll_elm[/zrl][/td][td]data for poll elements[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_profdef]profdef[/zrl][/td][td]custom profile field definitions[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_profext]profext[/zrl][/td][td]custom profile field data[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_profile]profile[/zrl][/td][td]channel profiles[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_profile_check]profile_check[/zrl][/td][td]DFRN remote auth use, may be obsolete[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_register]register[/zrl][/td][td]registrations requiring admin approval[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_session]session[/zrl][/td][td]web session storage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_shares]shares[/zrl][/td][td]shared item information[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_sign]sign[/zrl][/td][td]Diaspora signatures.  To be phased out.[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_site]site[/zrl][/td][td]site table to find directory peers[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_source]source[/zrl][/td][td]channel sources data[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_sys_perms]sys_perms[/zrl][/td][td]extensible permissions for OAuth[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_term]term[/zrl][/td][td]item taxonomy (categories, tags, etc.) table[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_tokens]tokens[/zrl][/td][td]OAuth usage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_updates]updates[/zrl][/td][td]directory sync updates[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_verify]verify[/zrl][/td][td]general purpose verification structure[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_vote]vote[/zrl][/td][td]vote data for polls[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_xchan]xchan[/zrl][/td][td]list of known channels in the universe[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_xchat]xchat[/zrl][/td][td]bookmarked chat rooms[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_xconfig]xconfig[/zrl][/td][td]as pconfig but for channels with no local account[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_xign]xign[/zrl][/td][td]channels ignored by friend suggestions[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_xlink]xlink[/zrl][/td][td]"friends of friends" linkages derived from poco, also ratings storage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_xperm]xperm[/zrl][/td][td]OAuth/OpenID-Connect extensible permissions permissions storage[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_xprof]xprof[/zrl][/td][td]if this hub is a directory server, contains basic public profile info of everybody in the network[/td][/tr]
[tr][td][zrl=[baseurl]/help/database/db_xtag]xtag[/zrl][/td][td]if this hub is a directory server, contains tags or interests of everybody in the network[/td][/tr]
[/table]
