<?php

	require "lib/function.php";
	
	$id = filter_int($_GET['id']);
	/*
	if (isset($_GET['time'])){
		print ctime()."<br/>".mktime(0,0,0,12,8,1997)."";
		x_die();
	}
	*/
	$user = $sql->fetchq("
	SELECT u.id id, u.name, u.displayname, u.title, u.powerlevel, u.sex, u.namecolor, u.icon, u.lastip, u.ban_expire, u.since,
			u.head, u.sign, u.lastview, u.bio, u.posts, u.threads, u.homepage, u.homepage_name, u.email, u.twitter, u.facebook, u.youtube,
			u.tzoff, u.ppp, u.tpp, u.realname, u.location, u.birthday, u.theme, u.coins, u.gcoins,
			p.time, t.id tid, t.name tname, t.forum tforum, f.name fname,
			r.*
	FROM users AS u
	LEFT JOIN posts AS p
	ON u.id=p.user
	LEFT JOIN threads AS t
	ON p.thread=t.id
	LEFT JOIN forums AS f
	ON t.forum=f.id
	LEFT JOIN user_avatars AS a
	ON u.id = a.user
	LEFT JOIN users_rpg AS r
	ON u.id = r.id
	WHERE u.id=$id
	ORDER BY p.time DESC");
	
	$ratings = $sql->resultq("SELECT COUNT(*) FROM ratings WHERE userto=$id");
	if (!$user)
		errorpage("This user doesn't exist.");
		
	pageheader("Profile for ".($user['displayname'] ? $user['displayname'] : $user['name']));
	
	$power_txt = array(
	'-2'=> "Permabanned",
	'-1'=> "Banned",
	0 	=> "Normal User",
	1 	=> "Privileged",
	2 	=> "Local Moderator",
	3 	=> "Global Moderator",
	4 	=> "Administrator",
	5 	=> "Sysadmin",
	);

	$isadmin = powlcheck(4);
	$totaldays = (ctime()-$user['since'])/86400;
	$user['rating'] = $sql->resultq("SELECT AVG(rating) FROM ratings WHERE userto = $id"); 
		
	$fields["General information"] = array(
		"Also known as" => ($user['displayname'] ? $user['name'] : ""),
		"Powerlevel" 	=> $power_txt[$user['powerlevel']],
		"Total posts" 	=> ($user['posts'] ? $user['posts'].sprintf(" (%.02f posts per day)", $user['posts']/$totaldays) : "None"),
		"Total threads" => ($user['threads'] ? $user['threads'].sprintf(" (%.02f threads per day)", $user['threads']/$totaldays) : "None"),
		"User rating"	=> $user['rating'] ? sprintf("%.02f",$user['rating'])." (".$ratings." vote".($ratings==1 ? "" : "s").")".($isadmin ? " <a href='rateuser.php?id=".$user['id']."&view'>View ratings</a>" : "") : "None",
		"EXP"			=> calcexp($user['since'], $user['posts']),
		"Registered on" => printdate($user['since'])." (".choosetime(ctime()-$user['since'])." ago)",
		"Last post"		=> ($user['posts'] ? printdate($user['time']).", in ".(canviewforum($user['tforum']) ? "<a href='thread.php?id=".$user['tid']."'>".$user['tname']."</a> (<a href='forum.php?id=".$user['tforum']."'>".$user['fname']."</a>)" : "<i>(Restricted forum)</i>") : "None"),
		"Last activity"	=> printdate($user['lastview']),
		"Last IP"		=> ($isadmin ? $user['lastip'] : ""),
		"Unban date"	=> ($user['powerlevel']<0 ? ($user['ban_expire'] ? printdate($user['ban_expire'])." (".sprintf("%d",($user['ban_expire']-ctime())/86400)." days remaining)" : "Never") : ""),
	);
	
	$fields["Contact information"] = array(
		"Email address" => ($loguser['id'] ? $user['email'] : ""), //TODO: email protection
		"Homepage" 		=> $user['homepage'] ? "<a href='".$user['homepage']."'>".$user['homepage_name']."</a> - ".$user['homepage'] : "",
		"Youtube"		=> $user['youtube'] ? "<a href='https://youtube.com/user/".$user['youtube']."'>".$user['youtube']."</a>" : "",
		"Twitter"		=> $user['twitter'] ? "<a href='https://twitter.com/".$user['twitter']."'>".$user['twitter']."</a>" : "",
		"Facebook"		=> $user['facebook'] ? "<a href='https://facebook.com/".$user['facebook']."'>".$user['facebook']."</a>" : "",
	);

	$fields["User settings"] = array(
		"Timezone offset" 	=> $user['tzoff']." hours from the server, ".($loguser['tzoff']-$user['tzoff'])." hours from you (current time: ".printdate(ctime()+$user['tzoff']).")",
		"Items per page" 	=> $user['tpp']." threads, ".$user['ppp']." posts",
		"Theme" 			=> findthemes()[$user['theme']]['name'],
	);


	$fields["Personal information"] = array(
		"Real name" 	=> $user['realname'],
		"Location"	 	=> output_filters($user['location'], true),
		"Birthday"	 	=> isset($user['birthday']) ? date("l, F j Y", $user['birthday'])." (".getyeardiff($user['birthday'],ctime())." years old)" : "",
		"Bio"		 	=> output_filters($user['bio'], true),
	);
	
	$field_txt = "";
	foreach($fields as $title => $field){
		$field_txt .= "<table class='main w'><tr><td class='head c' colspan=2>$title</td></tr>";
		foreach($field as $desc => $info)
			if ($info) $field_txt .= "<tr><td class='light t' style='width: 150px'><b>$desc</b></td><td class='dim t'>$info</td></tr>";
		$field_txt .= "</table></br>";
	}

	
	// As the categories aren't fixed you have to build the table here
	$item_txt = "";

	$q = getuseritems($user);

	if ($q){
		$itemdb = $sql->query("
		SELECT i.name item, c.name cat
		FROM shop_items i
		LEFT JOIN shop_categories c
		ON i.cat = c.id
		WHERE i.id IN (".implode(", ", $q).")
		");
	
		while ($item = $sql->fetch($itemdb))
			$item_txt .= "<tr class='c'><td class='light fonts'>".$item['cat']."</td><td class='dim fonts w'>".$item['item']."</td></tr>";
	}
	else $item_txt = "<tr><td class='light fonts c' colspan=2>No items bought</td></tr>";
		
	$stats_txt = "<center>
			".dorpgstatus($user)."
	<br/>
	
	<table class='main w'>
		<tr><td class='head c' colspan=2>Equipped items</td></tr>
		$item_txt
	</table></center>
	";
	

	$data = getpostcount($user['id'], true);
	$postids = $data[0];
	$lastpost = $data[1];

	
	$sample = array(
		'id' => 0,
		'user' => $id,
		'ip' => $user['lastip'],
		'deleted' => 0,
		'rev' => 0,
		'text' => "Sample text.[quote=fhqwhgads]A sample quote, with a <a href='about:blank'>link</a>, for testing your layout[/quote]This is how your post will appear.",
		'time' => ctime(),
		'nolayout' => 0,
		'nosmilies' => 0,
		'nohtml' => 0,
		'thread' => 0,
		'postcur' => $user['posts'],
		'lastpost' => $user['posts'] ? max($lastpost[$user['id']]) : ctime(),
		'trev' => 0,
		'lastedited' => 0,
		'avatar'	=> 0,
	);

	print "
	Profile for ".makeuserlink(false, $user, true)."
	<table><tr><td class='w'>$field_txt</td><td valign='top'>$stats_txt</td></tr></table>
				
	".threadpost($user+$sample, false, false, true)."
	<br/>
	<table class='main w'>
		<tr><td class='head c'><small>User Controls</small></td></tr>
		<tr>
			<td class='dim c'><small>
				<a href='thread.php?user=$id'>Show posts</a> |
				".($isadmin ? "<a href='editprofile.php?id=$id'>Edit user</a> | <a href='editavatars.php?id=$id'>Edit avatars</a> |" : "")."
				<a href='forum.php?user=$id'>View threads by this user</a> |
				<a href='private.php?act=send&id=$id'>Send private message</a> |
				".($isadmin && $id!=1 ? "<a href='private.php?id=$id'>View private messages</a> |" : "")."
				<a href='rateuser.php?id=$id'>Rate user</a>
			</td>
		</tr>
		<tr>
			<td class='dim c'><small>
				<a href='listposts.php?id=$id'>List posts by this user</a> |
				<a href='postsbytime.php?id=$id'>Posts by time of day</a> |
				<a href='postsbythread.php?id=$id'>Posts by thread</a> |
				<a href='listposts.php?id=$id&fmode'>Posts by forum</a>
			</small></td>
		</tr>
	</table>
	";
	
	pagefooter();

?>