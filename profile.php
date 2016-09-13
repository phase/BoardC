<?php

	require "lib/function.php";
	
	$id = filter_int($_GET['id']);

	/*
		The amount of fields to select in 'users' were ridiculous, so we simply select them all
	*/
	$user = $sql->fetchq("
		SELECT  u.*, t.id tid, t.name tname, t.forum tforum, f.name fname, r.bonus_exp,
				(SELECT COUNT(p.user) FROM posts p WHERE p.user = $id) rposts
		
		FROM users u
		
		LEFT JOIN posts       p ON p.user   = u.id
		LEFT JOIN threads     t ON p.thread = t.id
		LEFT JOIN forums      f ON t.forum  = f.id
		LEFT JOIN rpg_classes r ON u.class  = r.id
		
		WHERE u.id = $id
		ORDER BY p.time DESC
	");
	
	
	if (!$user)	errorpage("This user doesn't exist.");
	
	$ratings = $sql->resultq("SELECT COUNT(*) FROM ratings WHERE userto = $id");
	
	pageheader("Profile for ".($user['displayname'] ? $user['displayname'] : $user['name']));

	$totaldays 		= (ctime()-$user['since'])/86400;
	$user['rating'] = $sql->resultq("SELECT AVG(rating) FROM ratings WHERE userto = $id"); 

	$email = $user['email'];
	// Don't bother with the alt messages if there's no email specified
	if ($email){
		switch($user['publicemail']){
			case 0:
				if (!$isadmin && $id != $loguser['id']){
					$email = "<i>Private</i>";
				}
				break;
			case 1:
				if (!$loguser['id']){
					// ah, 1.B~
					$email = "Email witheld from guests. Log in to see it.";
				}
				break;
		}
	}
	
	$exp = calcexp($user['posts'], $totaldays, $user['bonus_exp']);
	if ($user['bonus_exp']){
		// I don't know if I'll change the algorithm for bonus exp at some point
		// Right now these are lazily added to the exp count, but it will probably change later on
		$normalexp = calcexp($user['posts'], $totaldays);
		$bonus_out = $exp - $normalexp;
		$expdetail = "$normalexp Base + $bonus_out RPG class bonus, ";
	} else {
		$expdetail = "";
	}
	$level 		= calclvl($exp);
	$expleft 	= calcexpleft($exp);
	if ($user['posts']){
		$exppost = calcexpgainpost($user['posts'], $totaldays);
		$exptime = calcexpgaintime($user['posts'], $totaldays);
		$expgain = "Gain: $exppost EXP per post, $exptime seconds to gain 1 EXP when idle";
	} else {
		$expgain = "";
	}
	$exp_txt = "
		Level: $level<br>
		EXP: $exp ({$expdetail}for next level: $expleft)<br>
		$expgain
	";

	/*
		I have no idea how to organize this well in an array
		I guess I'll leave it as-is :|
	*/
	$fields["General information"] = array(
		"Also known as" => ($user['displayname'] ? $user['name'] : ""),
		"Power Level" 	=> $power_txt[$user['powerlevel']],
		"Title"			=> ($user['title'] ? $user['title'] : ""),
		"Total posts" 	=> ($user['rposts'] ? $user['posts']." (".$user['rposts']." found, ".sprintf("%.02f posts per day)", $user['rposts']/$totaldays).($user['posts'] < 5000 ? " -- Projected date for 5000 posts: ".printdate(ctime()+5000/($user['rposts']/($totaldays*86400))) : "") : "None"),
		"Total threads" => ($user['threads'] ? $user['threads'].sprintf(" (%.02f threads per day)", $user['threads']/$totaldays) : "None"),
		"User rating"	=> $user['rating'] ? sprintf("%.02f",$user['rating'])." (".$ratings." vote".($ratings==1 ? "" : "s").")".($isadmin ? " <a href='rateuser.php?id=".$user['id']."&view'>View ratings</a>" : "") : "None",
		"EXP"			=> $exp_txt,
		"Registered on" => printdate($user['since'])." (".choosetime(ctime()-$user['since'])." ago)",
		"Last post"		=> ($user['posts'] ? printdate($user['lastpost']).", in ".(canviewforum($user['tforum']) ? "<a href='thread.php?id=".$user['tid']."'>".$user['tname']."</a> (<a href='forum.php?id=".$user['tforum']."'>".$user['fname']."</a>)" : "<i>(Restricted forum)</i>") : "None"),
		"Last activity"	=> printdate($user['lastview']),
		"Last IP"		=> ($isadmin ? $user['lastip'] : ""),
		"Unban date"	=> ($user['powerlevel']<0 ? ($user['ban_expire'] ? printdate($user['ban_expire'])." (".sprintf("%d",($user['ban_expire']-ctime())/86400)." days remaining)" : "Never") : ""),
	);
	
	$fields["Contact information"] = array(
		"Email address" => $email,
		"Homepage" 		=> $user['homepage'] ? "<a href='{$user['homepage']}'>{$user['homepage_name']}</a> - {$user['homepage']}" : "",
		"Youtube"		=> $user['youtube'] ? "<a href='https://youtube.com/user/{$user['youtube']}'>{$user['youtube']}</a>" : "",
		"Twitter"		=> $user['twitter'] ? "<a href='https://twitter.com/{$user['twitter']}'>{$user['twitter']}</a>" : "",
		"Facebook"		=> $user['facebook'] ? "<a href='https://facebook.com/{$user['facebook']}'>{$user['facebook']}</a>" : "",
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
	
	/*
		Convert arrays to html
	*/
	$field_txt = "";
	foreach($fields as $title => $field){
		$field_txt .= "
			<table class='main w'>
				<tr>
					<td class='head c' colspan=2>
						$title
					</td>
				</tr>";
		foreach($field as $desc => $info){
			if ($info) $field_txt .= "
				<tr>
					<td class='light t' style='width: 150px'>
						<b>$desc</b>
					</td>
					<td class='dim t'>
						$info
					</td>
				</tr>";
		}
		$field_txt .= "</table><br>";
	}

	
	/*
		RPG Status / Equipped items
	*/
	$item_txt = "";

	$itemdb = getuseritems($id, true);

	if ($itemdb){
		$cat = $sql->fetchq("SELECT id, name FROM shop_categories", true, PDO::FETCH_KEY_PAIR);
	
		foreach($itemdb as $catid => $item){
			$item_txt .= "
			<tr class='c'>
				<td class='light fonts'>
					".$cat[$catid]."
				</td>
				<td class='dim fonts w'>
					{$item['name']}
				</td>
			</tr>";
		}
	} else {
		$item_txt = "
			<tr>
				<td class='light fonts c' colspan=2>
					No items bought
				</td>
			</tr>";
	}
	
	
	$stats_txt = dorpgstatus($id)."
	<br>
	
	<table class='main w'>
		<tr><td class='head c' colspan=2>Equipped items</td></tr>
		$item_txt
	</table>
	";
	
	
	/*
		Sample post
	*/
	$sample = array(
		'id' 		=> 0,
		'user' 		=> $id,
		'ip'		=> $user['lastip'],
		'deleted'	=> 0,
		'rev' 		=> 0,
		'text' 		=> "Sample text.[quote=fhqwhgads]A sample quote, with a <a href='about:blank'>link</a>, for testing your layout[/quote]This is how your post will appear.",
		'time' 		=> ctime(),
		'nolayout' 	=> 0,
		'nosmilies' => 0,
		'nohtml' 	=> 0,
		'thread' 	=> 0,
		'trev' 		=> 0,
		'lastedited'=> 0,
		'avatar'	=> 0,
		'new'		=> 0,
		'noob'		=> 0,
	);
	
	$ranks 		= doranks($id, true);
	$layouts[$id] = array(
		'head'	=> output_filters($user['head']),
		'sign'	=> output_filters($user['sign'])
	);

	print "
	<span style='vertical-align: middle'>Profile for ".makeuserlink(false, $user, true)."</span>
	
	
	<table>
		<tr>
			<td class='w'>
				$field_txt
			</td>
			<td>&nbsp;</td>
			<td valign='top'>
				$stats_txt
			</td>
		</tr>
	</table>
	
	<br>
	
	<table class='main w c'>
		<tr>
			<td class='head'>
				Sample post
			</td>
		</tr>
	</table>
	
	".threadpost($user+$sample, false, false, true, false, true)."
	
	<br>
	
	<table class='main w fonts'>
		<tr><td class='head c'>Options</td></tr>
		
		<tr>
			<td class='dim c'>
				<a href='showposts.php?id=$id'>Show posts</a> |
				".($isadmin ? "<a href='editprofile.php?id=$id'>Edit user</a> | <a href='editavatars.php?id=$id'>Edit avatars</a> |" : "")."
				<a href='forum.php?user=$id'>View threads by this user</a> |
				<a href='private.php?act=send&id=$id'>Send private message</a> |
				".($isadmin && $id!=1 ? "<a href='private.php?id=$id'>View private messages</a> |" : "")."
				<a href='rateuser.php?id=$id'>Rate user</a>
			</td>
		</tr>
		
		<tr>
			<td class='dim c'>
				<a href='listposts.php?id=$id'>List posts by this user</a> |
				<a href='postsbytime.php?id=$id'>Posts by time of day</a> |
				<a href='postsbythread.php?id=$id'>Posts by thread</a> |
				<a href='listposts.php?id=$id&fmode'>Posts by forum</a>
			</td>
		</tr>
	</table>
	";
	
	pagefooter();

?>