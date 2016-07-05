<?php

	require "lib/function.php";
	
	/*
		due to the lack of a posts value in the database
		getting the post number requires to fetch all the posts
		and manually count all
	*/

	// General
	$page		= filter_int($_GET['page']);
	$id			= filter_int($_GET['id']);
	
	// Defaults
	if (!isset($_GET['time'])) 	$time 	= 86400;
	else 						$time 	= filter_int($_GET['time']);
	
	// Posts by forum
	$forum 	= filter_int($_GET['forum']);

	if (!$id)
		errorpage("No user specified.");
	
	$user = $sql->fetchq("SELECT $userfields, u.posts FROM users u WHERE u.id=$id");
	
	if (!$user)
		errorpage("Invalid user.");
	
	$isadmin 	= powlcheck(4); // for invalid threads/forums
	$txt 		= "";
	
	// Posts by forums mode
	if (isset($_GET['fmode'])){
		$pcount = array();
		/*
		while($post=$sql->fetch($posts)){
			filter_int($pcount[$post['fid']]); // never print out useless unrecognized variable stuff
			$pcount[$post['fid']]+=1;
		}
		*/
		$posts = $sql->query("
			SELECT COUNT(*) rcount, t.forum
			FROM posts p
			LEFT JOIN threads t
			ON p.thread=t.id
			WHERE p.user=$id
			GROUP BY t.forum
		");
		
		if ($posts)
			while($post=$sql->fetch($posts))
				$pcount[filter_int($post['forum'])] = $post['rcount'];

		$forums = $sql->fetchq("SELECT id, name, powerlevel FROM forums", true);
		
		foreach($forums as $forum){
			if (!filter_int($pcount[$forum['id']])) continue;
				
			if ($forum['powerlevel'] && $loguser['powerlevel'] < $forum['powerlevel'])
				$link = "<i>(Restricted forum)</i>";
			else
				$link = "<a href='listposts.php?id=$id&forum={$forum['id']}'>{$forum['name']}</a>";
			
			$txt .= "<tr><td class='dim'>$link</td><td class='light c'>".filter_int($pcount[$forum['id']])."</td></tr>";
			unset($pcount[$forum['id']]); // to count invalid posts later
		}
		// Invalid posts
		$extra = "
		<tr>
			<td class='dim'>
				".($isadmin ? "<a href='listposts.php?id=$id&invalid' class='danger' style='background: #fff'><b>Invalid</b></a>" : "<i>(Restricted forum)</i>")."
			</td>
			<td class='light'>
				".array_sum($pcount)."
			</td>
		</tr>";
		
		pageheader("Posts by forum");
		
		print "
		<table class='main w c'>
		
			<tr><td class='head' colspan=3>Posts by forum - ".makeuserlink(false, $user)."</td></tr>
			
			<tr>
				<td class='head'>Forum</td>
				<td class='head'>Posts</td>
			</tr>
			$txt
			$extra
			</table>
		";
		pagefooter();
	}
	
	// Date filtering for the query
	if ($time) 	$time_txt = "AND p.time>".(ctime()-$time);
	else 		$time_txt = "";
	
	// Special catch for quick search of invalid posts
	if (isset($_GET['invalid'])){
		if (!$isadmin) errorpage("invalid");
		//$forums 		= array_extract($sql->fetchq("SELECT id FROM forums", true), "id"); // what was this even for?
		$invalid 		= "AND (t.forum is NULL OR p.thread IS NULL)";
	}
	else $invalid = "";
	
	$new_check = $loguser['id'] ? "(p.time > n.user{$loguser['id']})" : "0";
	
	$posts = $sql->query("
		SELECT p.id, p.time, t.name, f.powerlevel, t.id tid, f.id fid, p.thread, t.forum, f.name fname, $new_check new
		FROM posts p
		LEFT JOIN threads      t ON p.thread = t.id
		LEFT JOIN forums       f ON t.forum  = f.id
		LEFT JOIN threads_read n ON t.id     = n.id
		WHERE p.user=$id
		$invalid
		$time_txt
		ORDER BY p.id DESC
		LIMIT ".($page*50).", 50
	");
	

	pageheader("List posts");

	
	
	$i = $user['posts']-$page*50; //starting post
	
	if ($posts){
		
		while ($post = $sql->fetch($posts)){
			
			if ($forum && $post['fid'] != $forum) continue; // posts by forum specific
		
			if ($loguser['powerlevel']<$post['powerlevel'] || (!$isadmin && !(/*$post['tid'] && */$post['fid']))) $link = "<i>(Restricted forum)</i>";
			else {
				if (!$post['tid'])
					$post['name'] 	 = "[Invalid thread ID #{$post['thread']}]";
				else if (!$post['fid'])
					$post['name'] 	.= "[Invalid forum ID #{$post['forum']}]";
				
				$new = $post['new'] ? "<img src='images/status/new.gif'> " : "";
				
				
				$link = "$new<a ".($post['tid'] && $post['fid'] ? "" : "class='danger' style='background: #fff'")." href='thread.php?pid={$post['id']}#{$post['id']}'>{$post['name']}</a>";
			}

			$txt .= "
				<tr>
					<td class='light'>{$post['id']}</td>
					<td class='light'>$i</td>
					<td class='light'><nobr>".printdate($post['time'])."</nobr></td>
					<td class='dim' style='text-align: left'>$link</td>
				</tr>
				";
			$i--;
			$fname = $post['fname']; // again
			
		}
		
		if (!$txt){ // again
			$txt = "<tr><td class='light' colspan=4>There are no posts to show.</td></tr>";
			$realcount = 0;
		}
		
		else $realcount = $sql->resultq("
			SELECT COUNT(*) rcount
			FROM posts p
			LEFT JOIN threads t ON p.thread=t.id
			WHERE p.user = $id
			$invalid
			$time_txt
			".($forum ? " AND t.forum=$forum" : "")
		); 
	}
	else{
		$txt = "<tr><td class='light' colspan=4>There are no posts to show.</td></tr>";
		$realcount = 0;
	}
	
	// Page Counter
	$extra = "";
	if ($invalid)	$extra .= "&invalid";
	if ($forum) 	$extra .= "&forum=$forum";
	$notime = "id=$id$extra";
	if ($time)	 	$extra .= "&time=$time";
	$pagectrl = dopagelist($realcount, 50, "listposts", $extra);
	
	$forumtitle = $forum ? " in ".filter_string($fname) : "";
	$when = $time ? " in the last ".choosetime($time) : " in total";
	
	print "<div class='fonts'>
	<a href='listposts.php?$notime&time=3600'>During last hour</a> |
	<a href='listposts.php?$notime&time=86400'>During last day</a> |
	<a href='listposts.php?$notime&time=604800'>During last week</a> |
	<a href='listposts.php?$notime&time=2592000'>During last 30 days</a> |
	<a href='listposts.php?$notime&time=0'>Total</a></div>
		
	Posts by ".makeuserlink(false, $user)." on the board$forumtitle$when: ($realcount posts found)<br/>
	$pagectrl<table class='main w c fonts'>
	
		"./*<tr><td class='head' colspan=4>Posts by ".makeuserlink(false, $user)."$forumtitle</td></tr>*/"
		<tr>
			<td class='head' style='width: 50px'>#</td>
			<td class='head' style='width: 50px'>Post</td>
			<td class='head' style='width: 130px'>Date</td>
			<td class='head'>Thread</td>
		</tr>
		$txt
		</table>$pagectrl
	";
	
	
	pagefooter();
	
?>