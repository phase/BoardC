<?php
	// based on postsbytime.php
	require "lib/function.php";
	
	$id	= filter_int($_GET['id']);
	if (!$id) errorpage("No user specified.");
	
	if (!isset($_GET['time'])) $time = 86400; // 1 day
	else $time	= filter_int($_GET['time']);
	
	
	
	$user = $sql->fetchq("
		SELECT $userfields
		FROM users u
		WHERE u.id=$id
	");
	if (!$user)	errorpage("Invalid user.");
		
	$isadmin = powlcheck(4);
	
	if ($time) $time_txt = "AND p.time>".(ctime()-$time);
	else $time_txt = "";
	$posts = $sql->query("
	SELECT COUNT(p.id) pcount, p.thread, n.user".$loguser['id']." new,
	t.id tid, t.name tname, t.forum, t.replies,
	f.id fid, f.name fname, f.powerlevel
	FROM posts p
	LEFT JOIN threads t ON p.thread = t.id
    LEFT JOIN forums f ON t.forum = f.id	
	LEFT JOIN new_posts n ON p.id = n.id
	WHERE p.user = $id
	$time_txt
	GROUP BY p.thread
	ORDER BY p.time DESC
	");
	

	for ($i=1, $txt=""; $post=$sql->fetch($posts); $i++){
		if ($loguser['powerlevel'] < $post['powerlevel'] || (!$isadmin && !$post['fid'])){
			$forum = "(restricted forum)";
			$thread = "(private thread)";
		}
		else{
			if (!$post['fid']) $forum = "<a class='danger' style='background: #fff' href='forum.php?id=".filter_int($post['forum'])."'>Invalid forum ID #".filter_int($post['forum'])."</a>";
			else $forum = "<a href='".$post['forum']."'>".htmlspecialchars($post['fname'])."</a>";
			
			$new = $post['new'] ? "<img src='images/status/new.gif'> " : "";
			
			if (!$post['tid']){
				$thread = "$new<a class='danger' style='background: #fff' href='thread.php?id=".filter_int($post['thread'])."'>Invalid thread ID #".filter_int($post['thread'])."</a>";
				$total = "-";
			}
			else {
				$thread = "$new<a href='".$post['thread']."'>".htmlspecialchars($post['tname'])."</a>";
				$total = $post['replies']+1;
			}
		}
		
		$txt .= "
			<tr>
				<td class='light c'>$i</td>
				<td class='light c'>$forum</td>
				<td class='dim'>$thread</td>
				<td class='dim c'>".$post['pcount']."</td>
				<td class='dim c'>$total</td>
			</tr>";	
	}
	
	$when = $time ? " during the last ".choosetime($time) : " in total";
	
	pageheader("Posts by time of day");
	print "
	<div class='fonts'>Timeframe: 
	<a href='postsbythread.php?id=$id&time=86400'>During last day</a> |
	<a href='postsbythread.php?id=$id&time=604800'>During last week</a> |
	<a href='postsbythread.php?id=$id&time=2592000'>During last 30 days</a> |
	<a href='postsbythread.php?id=$id&time=31536000'>During last year</a> |
	<a href='postsbythread.php?id=$id&time=0'>Total</a></div>
	Posts by ".makeuserlink(false, $user)." in threads $when:
	
	<table class='main w'>
		<tr class='c'>
			<td class='head' style='width: 30px'>&nbsp;</td>
			<td class='head' style='width: 300px'>Forum</td>
			<td class='head'>Thread</td>
			<td class='head' style='width: 70px'>Posts</td>
			<td class='head' style='width: 90px'>Thread total</td>
		</tr>
	$txt
	</table>
	";
	
	pagefooter();
?>