<?php
	require "lib/function.php";
	
	pageheader($config['board-name'], false);
	
	// Header 2
	
	//$misc = $sql->fetchq("SELECT threads, posts FROM misc");
	
	$newuser = $sql->fetchq("SELECT COUNT(id) as count, MAX(id) as maxid FROM users");
	$d = ctime()-86400;
	
	// could possibly shrink this down to a query, but Select Distinct is weird
	$dayusers = $sql->resultq("SELECT COUNT(*) FROM (SELECT DISTINCT user FROM posts WHERE time>$d) AS N");
	$daythreads = $sql->resultq("SELECT COUNT(*) FROM (SELECT DISTINCT thread FROM posts WHERE time>$d) AS K");

	$p = ($dayusers==1) ? "" : "s";
	$k = ($daythreads==1) ? "" : "s";
	$birthday_txt = "Birthdays for ".date("F j").": ";
	$birthdays = $sql->query("SELECT id, name, displayname, powerlevel, namecolor, sex, birthday FROM users WHERE DAYOFYEAR(FROM_UNIXTIME(birthday)) = ".date("z", ctime()+$loguser['tzoff']*3600));
	if ($birthdays)
		while ($x = $sql->fetch($birthdays))
			$birthday_txt .= makeuserlink(false, $x)." (".getyeardiff($x['birthday'],ctime()).")";
	else $birthday_txt .= "None";
	
	print "<br/>
	<table class='main w fonts'>
		<tr>
			<td class='light' style='text-align: left; border-right: none;'>You are ".($loguser['id'] ? "logged in as ".makeuserlink($loguser['id']) : "not logged in").".</td>
			<td class='light' style='text-align: right'>".$newuser['count']." registered users<br/>Latest registered user: ".makeuserlink($newuser['maxid'])."</td>
		</tr>
		<tr>
			<td class='dim c'colspan=2>$birthday_txt</td>
		</tr>
		<tr>
			<td class='dim c' colspan=2>".$miscdata['threads']." threads and ".$miscdata['posts']." posts in the board | $dayusers user$p active in $daythreads thread$k during the last day.</td>
		</tr>
		<tr>
			<td class='light c' colspan=2>".onlineusers()."</td>
		</tr>
	</table><br/>
	";
	unset ($newuser, $dayusers, $daythreads, $p, $k, $d, $x);
	
	
	
	$hidden = powlcheck(3) ? "" : "AND hidden=0";
	$catsel = isset($_GET['cat']) ? "AND c.id=".filter_int($_GET['cat']) : "";
	// change to fetch,true to save queries on forummods fetch
	$forums = $sql->query("
	SELECT f.id id, f.name name, title, hidden, threads, posts, category, c.name catname
	FROM forums as f
	LEFT JOIN categories AS c
	ON category = c.id
	WHERE (f.powerlevel<=".$loguser['powerlevel']." AND c.powerlevel <=".$loguser['powerlevel']." $hidden $catsel)
	ORDER BY c.ord , f.ord, f.id
	");
	
	if (!$forums)
		print "<table class='main w c'><tr><td class='dark'>The admin hasn't configured the forum list yet.<br/>Come back later</td></tr></table>";
	else{
		print "
		<table class='main w nb'>
<!--			<tr><td colspan=5 class='head b'>Forum list</td></tr> -->
			
			<tr>
				<td class='head'>&nbsp;</td>
				<td class='head w c'>Forum</td>
				<td class='head c'>Threads</td>
				<td class='head c'>Posts</td>
				<td class='head c'><nobr>Last post</nobr></td>
			</tr>";
			
		$cat = NULL;
			
		while ($forum = $sql->fetch($forums)){
			
			if ($cat != $forum['category']){
				$cat = $forum['category'];
				
				print "<tr><td class='dark c b' colspan=5><a href='index.php?cat=$cat'>".$forum['catname']."</a></td></tr>";
			}
			
			$postdata = $sql->fetchq("
				SELECT posts.id as id, posts.time as time, posts.user as user, posts.thread as thread, threads.id as tid, threads.forum as tforum
				FROM `posts`
				INNER JOIN threads
				ON posts.thread=threads.id
				WHERE threads.forum = ".$forum['id']."
				ORDER BY `time` DESC
				LIMIT 1");
				
			
			if ($postdata) $lastpost = "<nobr>".printdate($postdata['time'])."<br/><small><nobr> by ".makeuserlink($postdata['user'])." <a href='thread.php?pid=".$postdata['id']."#".$postdata['id']."'><img src='images/status/getlast.png'></a></nobr></small>";
			else $lastpost = "Nothing";
			
			print "
			<tr>
				<td class='light c'>&nbsp;</td>
				<td class='dim br'><a href='forum.php?id=".$forum['id']."'>".$forum['name']."</a><small><br/>".$forum['title']."<br/>".doforummods($forum['id'])."</small></td>
				<td class='light c'>".$forum['threads']."</td>
				<td class='light c'>".$forum['posts']."</td>
				<td class='dim c'>$lastpost</td>
			</tr>";
			
		}
		
		print "</table>";
	}
	
	
	pagefooter();

?>