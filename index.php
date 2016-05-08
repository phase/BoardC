<?php
	require "lib/function.php";
	
	if (isset($_GET['markforumread'])){
		if ($loguser['id']){
			
			$val = isset($_GET['r']) ? 1 : 0;
			
			if (filter_int($_GET['forumid'])){
				$join = "LEFT JOIN posts p ON n.id = p.id LEFT JOIN threads t ON p.thread = t.id";
				$where = "WHERE t.forum = ".intval($_GET['forumid']);
			}
			else{
				$join = "";
				$where = "";
			}
			
			$sql->query("UPDATE new_posts n $join SET n.user".$loguser['id']."= $val $where");
		}
		
		header("Location: ".(filter_int($_GET['forumid']) ? "forum.php?id=".$_GET['forumid'] : "index.php"));
	}	
	
	pageheader($config['board-name'], false);
	
	// Header 2
	

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
	
	// index page PM box
	if ($loguser['id']){
		$pm =  $sql->fetchq("
		
			SELECT  p.id pid, COUNT(p.id) pcount,p.time, SUM(p.new) new,
					$userfields
			FROM pms p
			LEFT JOIN users u ON p.user = u.id
			WHERE p.userto = ".$loguser['id']."
			ORDER by p.id DESC
			
		");
		
		if ($pm['pcount']){
			$new = $pm['new'] ? "<img src='images/status/new.gif'>" : "";
			$pm_txt = "You have ".$pm['pcount']." private message".($pm['pcount']==1 ? "" : "s	")." (".$pm['new']." new). <a href='private.php?act=view&id=".$pm['pid']."'>Last message</a> from ".makeuserlink(false, $pm)." on ".printdate($pm['time']);
		}
		else{
			$new = "";
			$pm_txt = "You have no private messages.";
		}
		
		print "
		<table class='main w'>
			<tr><td class='head fonts c' colspan=2>Private messages</td></tr>
			
			<tr>
				<td class='light c'>$new</td>
				<td class='dim'>
					<a href='private.php'>Private messages</a> -- $pm_txt
				</td>
			</tr>
		</table><br/>
		";
	}
	
	$hidden = powlcheck(3) ? "" : "AND hidden=0";
	$catsel = isset($_GET['cat']) ? "AND c.id=".filter_int($_GET['cat']) : "";

	// subquery replaced by a wall of LEFT JOINs, number of new posts returned
	$forums = $sql->query("
	SELECT f.id fid, f.name fname, f.title, f.hidden, f.threads, f.posts, f.category, f.lastpostid, f.lastpostuser, f.lastposttime, c.name catname, SUM(n.user".$loguser['id'].") new, $userfields
	FROM forums f
	LEFT JOIN categories c ON f.category = c.id
	LEFT JOIN users u ON f.lastpostuser = u.id
	LEFT JOIN threads t ON f.id = t.forum
	LEFT JOIN posts p ON p.thread = t.id
	LEFT JOIN new_posts n ON n.id = p.id

	WHERE (f.powerlevel<=".$loguser['powerlevel']." AND c.powerlevel <=".$loguser['powerlevel']." $hidden $catsel)
	GROUP BY f.id ASC
	ORDER BY c.ord , f.ord, f.id
	");
	
	if (!$forums)
		print "<table class='main w c'><tr><td class='dark'>The admin hasn't configured the forum list yet.<br/>Come back later</td></tr></table>";
	else{
		
		$forummods = $sql->fetchq("SELECT f.fid, $userfields FROM forummods f LEFT JOIN users u ON f.uid = u.id", true, PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
		//d($forummods);
		
		print "
		<table class='main w nb'>
			".doannbox()."
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
			
			if ($forum['lastpostid']) $lastpost = "<nobr>".printdate($forum['lastposttime'])."<br/><small><nobr> by ".makeuserlink(false, $forum)." <a href='thread.php?pid=".$forum['lastpostid']."#".$forum['lastpostid']."'><img src='images/status/getlast.png'></a></nobr></small>";
			else $lastpost = "Nothing";
			
			for ($i=0;isset($forummods[$forum['fid']][$i]); $i++)
				$mods[] = makeuserlink(false, $forummods[$forum['fid']][$i]);
			
			$new = $forum['new'] ? "<img src='images/status/new.gif'><small>".$forum['new']."</small>" : ""; 
			
			print "
			<tr>
				<td class='light c'>$new</td>
				<td class='dim br'><a href='forum.php?id=".$forum['fid']."'>".$forum['fname']."</a><small>".($forum['title'] ? "<br/>".$forum['title'] : "")."<br/>".(isset($mods) ? "(Moderated by: ".implode(", ", $mods).")" : "")."</small></td>
				<td class='light c'>".$forum['threads']."</td>
				<td class='light c'>".$forum['posts']."</td>
				<td class='dim c'>$lastpost</td>
			</tr>";
			
			$mods = NULL;
		}
		
		print "</table>";
	}
	
	
	pagefooter();

?>