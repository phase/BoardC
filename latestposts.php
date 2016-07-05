<?php

	require "lib/function.php";
	
	$time 		= filter_int($_GET['t']);
	$maxposts 	= filter_int($_GET['p']);
	
	// default limit: 50 threads (match Jul's defaults)
	if (!$time){
		$postlimit = "LIMIT 0, ".($maxposts ? $maxposts : "50");
		$timelimit = "";
	}
	else{
		$postlimit = "";
		$timelimit = "AND p.time > ".(ctime()-$time);
	}
	
	$isadmin = powlcheck(4);
	
	// Invalid posts will always fail the powerlevel check, so skip this if you're an admin
	// Admins can read all posts (including invalid ones) anyway
	
	$new_check = $loguser['id'] ? "(p.time > n.user{$loguser['id']})" : "0";
	
	$posts = $sql->query("
		SELECT p.id pid, p.time, p.thread tid, $userfields,
		t.name tname, t.id tinvchk, t.forum fid, f.id finvchk, f.name fname, f.powerlevel fpowl, $new_check new
		FROM posts p
		
		LEFT JOIN threads      t ON p.thread = t.id
		LEFT JOIN forums       f ON t.forum  = f.id
		LEFT JOIN users        u ON p.user   = u.id
		LEFT JOIN threads_read n ON t.id     = n.id
		
		".($isadmin ? "" : "WHERE f.powerlevel <= {$loguser['powerlevel']} AND NOT ISNULL(f.id)")."
		$timelimit
		ORDER BY p.time DESC
		$postlimit
	");
	
	$txt = "";
	
	if ($posts){
		while($post = $sql->fetch($posts)){
			$new = $post['new'] ? "<img src='images/status/new.gif'> " : "";
			$txt .= "
			<tr>
				<td class='dim c'>{$post['pid']}</td>
				<td class='dim c'><a href='forum.php?id={$post['fid']}'>".($post['finvchk'] ? $post['fname'] : "<div class='danger' style='background: #fff'>Invalid forum ID #{$post['fid']}</div>")."</a></td>
				<td class='light'>$new<a href='thread.php?pid={$post['pid']}#{$post['pid']}'>".($post['tinvchk'] ? $post['tname'] : "<div class='danger' style='background: #fff'>Invalid thread ID #{$post['tid']}</div>")."</a></td>
				<td class='light c'>".makeuserlink(false, $post)."</td>
				<td class='dim c'>".choosetime(ctime()-$post['time'])."</td>
			</tr>";
		}
	}
	
	pageheader("Latest posts");
	// hurf durf part of this html imported from Jul because lazy
	print "
	Show:			
	<div class='fonts'>Last <a href='?t=1800'>30 minutes</a> - <a href='?t=3600'>1 hour</a> - <a href='?t=18000'>5 hours</a> - <a href='?t=86400'>1 day</a>
	<br>Most recent <a href='?p=20'>20 posts</a> - <a href='?p=50'>50 posts</a> - <a href='?p=100'>100 posts</a></div>
	<table class='main w'>
		<tr><td class='dark c' colspan=5><b>Latest Posts</b></td></tr>
		<tr>
			<td class='head c' style='width: 30px;'>&nbsp</td>
			<td class='head c' style='width: 280px;'>Forum</td>
			<td class='head c'>Thread</td>
			<td class='head c' style='width: 200px;'>User</td>
			<td class='head c' style='width: 130px;'>Time</td>
		</tr>
		$txt
	</table>
	";
	
	pagefooter();
?>