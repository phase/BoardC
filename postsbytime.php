<?php
	// based on listposts.php
	require "lib/function.php";
	
	$id	= filter_int($_GET['id']);
	if (!$id) errorpage("No user specified.");
	
	if (!isset($_GET['time'])) $time = 86400; // 1 day
	else $time	= filter_int($_GET['time']);
	
	
	
	$user = $sql->fetchq("
		SELECT id, name, displayname, namecolor, powerlevel, sex
		FROM users
		WHERE id=$id
	");
	if (!$user)	errorpage("Invalid user.");
		
		
	if ($time) $time_txt = "AND time>".(ctime()-$time);
	else $time_txt = "";
	$posts = $sql->query("SELECT time FROM posts WHERE user = $id $time_txt");
	
	$postdb = array_fill('0', '24', 0);
	$total = 0;
	
	while ($post = $sql->fetch($posts)){
		$postdb[ date('G', $post['time']) ]++;
		$total++;
	}
	
	
	$txt = "";
	
	$max = max($postdb);
	if ($max != 0) $mul = 100/$max;
	else $mul = 0;
	
	foreach ($postdb as $i => $count){
		
		$txt .= "
			<tr class='fonts'>
				<td class='light c'>$i:00 - $i:59</td>
				<td class='light c'>$count</td>
				<td class='dim'><img src='images/temp/bar-on.gif' height='8' width='".($mul*$count)."%'></td>				
			</tr>";
	}
	
	
	
	$when = $time ? " during the last ".choosetime($time) : " in total";
	
	pageheader("Posts by time of day");
	print "
	<div class='fonts'>Timeframe: 
	<a href='postsbytime.php?id=$id&time=86400'>During last day</a> |
	<a href='postsbytime.php?id=$id&time=604800'>During last week</a> |
	<a href='postsbytime.php?id=$id&time=2592000'>During last 30 days</a> |
	<a href='postsbytime.php?id=$id&time=31536000'>During last year</a> |
	<a href='postsbytime.php?id=$id&time=0'>Total</a></div>
	Posts from ".makeuserlink(false, $user)." by time of day $when:
	
	<table class='main w'>
		<tr>
			<td class='head c' style='width: 100px'>Time</td>
			<td class='head c' style='width: 50px'>Posts</td>
			<td class='head c'></td>
		</tr>
	$txt
	</table>
	";
	
	pagefooter();
?>