<?php

	if (isset($_POST['fjumpgo']))
		header("Location: forum.php?id=".$_POST['forumjump']);
	
	require "lib/function.php";
	
	$id = filter_int($_GET['id']);
	$user = filter_int($_GET['user']);
	$isadmin = powlcheck(4);
	
	if ($user){
		update_hits();
		$where = ",LEAST(n.id, 0) new, SUM(n.user".$loguser['id'].") ncount, t.forum , f.id fid, f.name fname, f.powerlevel
		FROM threads t
		LEFT JOIN forums f ON t.forum = f.id
		LEFT JOIN posts p ON p.thread = t.id
		LEFT JOIN new_posts n ON n.id = p.id AND n.user".$loguser['id']." = 1
		WHERE t.user = $user
		GROUP BY t.id DESC
		";
		
		$userdata = $sql->fetchq("
			SELECT u.name, u.displayname, u.threads
			FROM users u
			LEFT JOIN posts p ON u.id = p.user
			WHERE u.id = $user
			ORDER BY p.time DESC
		");
		
		if (!$userdata)
			errorpage("This user doesn't exist!");
		
		// hack hack hack
		$forum['name'] = "Threads by ".($userdata['displayname'] ? $userdata['displayname'] : $userdata['name']);
		$forum['threads'] = $userdata['threads'];
		pageheader($forum['name']);
		
	}
	else if ($id)	{
		$where = ", MIN(n.id) new, SUM(n.user".$loguser['id'].") ncount
		FROM threads t
		LEFT JOIN posts p ON p.thread = t.id
		LEFT JOIN new_posts n ON n.id = p.id AND n.user".$loguser['id']." = 1
		WHERE t.forum = $id
		GROUP BY t.id DESC
		";
	
		$forum = $sql->fetchq("SELECT name, powerlevel, threads, theme FROM forums WHERE id = $id");
		
		if ((!$forum && !$isadmin) || $loguser['powerlevel']<$forum['powerlevel'])
			errorpage("Couldn't enter this restricted forum.");
		else if (!$forum){
			errorpage("This forum ID doesn't exist.");
		}
		// online update, revised
		update_hits($id);

		if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme']);
		pageheader($forum['name'], true, $id);
	}
	else errorpage("No forum ID specified.");

	print "<table class='main w fonts'><tr><td class='light c'>".onlineusers($id)."</td></tr></table>
	<table class='w'>
		<tr>
			<td class='w'>
				<a href='index.php'>".$config['board-name']."</a> - ".$forum['name']."</td>
			<td>&nbsp;</td>
			".($user ? "" : "<td style='text align: right'><nobr><a href='new.php?act=newpoll&id=$id'><img src='images/text/newpoll.png'></a> - <a href='new.php?act=newthread&id=$id'><img src='images/text/newthread.png'></a></nobr></td>")."
		</tr>
	</table>";
	
	$threads = $sql->query("
	SELECT t.id, t.name, t.title, t.time, t.user, t.views, t.replies, t.sticky, t.closed, t.icon, t.ispoll, t.lastpostid, t.lastpostuser, t.lastposttime
	$where
	ORDER BY t.sticky DESC, t.lastposttime DESC
	LIMIT ".(filter_int($_GET['page'])*$loguser['tpp']).", ".$loguser['tpp']."");

	if (!$threads){
		if ($user) errorpage("There are no threads to show.", false);
		print "<table class='main w c'><tr><td class='light'>There are no threads in this forum.<br/>Come back later<small> (or create a new one)</small></td></tr></table>";
	}
	else{
		
		$pagectrl = dopagelist($forum['threads'], $loguser['tpp'], "forum", $user ? "user=$user" : "");

		print "
		$pagectrl<table class='main w'>
			<tr>
				<td class='head c br' >&nbsp;</td>
				<td colspan=2 class='head w c br' >Thread</td>
				<td class='head c br' ><nobr>Started by</nobr></td>
				<td class='head c br' >Replies</td>
				<td class='head c br' >Views</td>
				<td class='head c b' ><nobr>Last post</nobr></td>
			</tr>
		";
		
		$c = NULL;
		
		while ($thread = $sql->fetch($threads)){
			
			// Separator between sticked threads and not
			if ($c == 1 && $c != $thread['sticky']){
				$c = $thread['sticky'];
				print "<tr><td class='head fonts' colspan=7>&nbsp;</td></tr>";
			}
			$c = $thread['sticky'];
			

			if ($thread['lastpostid'])
				$lastpost = "<nobr>".printdate($thread['lastposttime'])."<br/><small><nobr> by ".makeuserlink($thread['lastpostuser'])." <a href='thread.php?pid=".$thread['lastpostid']."#".$thread['lastpostid']."'><img src='images/status/getlast.png'></a></nobr></small>";
			else
				$lastpost = "Nothing";

			
			$status = "";
			
			if($thread['replies']>20) 	$status.="hot";
			if($thread['closed']) 		$status.="off";
			if($thread['new'])			$status.="new";

			$status = $status ? "<img src='images/status/$status.gif'>".$thread['ncount'] : "&nbsp;";
			
			// Threads by user specific
			if ($user){
				if ($loguser['powerlevel']<$thread['powerlevel'] || (!$isadmin && !$thread['fid'])){
					print "<tr><td class='light c fonts' colspan='7'>(restricted)</td></tr>";
					continue;
				}
				$smalltext = "&nbsp;&nbsp;&nbsp;&nbsp;In <a href='forum.php?id=".$thread['forum']."'".($thread['fid'] ? ">".$thread['fname'] : "class='danger' style='background: #fff'>Invalid forum ID #".$thread['forum'])."</a>";
			}
			else{
				if ($thread['ispoll'])
					$thread['title'] = split_null($thread['title'], true);
				
				$smalltext = htmlspecialchars($thread['title']);
			}
			
			// new post link
			$new = $thread['new'] ? "<a href='thread.php?pid=".$thread['new']."'><img src='images/status/getnew.png'></a> " : "";
			
			print "<tr>
				<td class='light c'>$status</td>
				<td class='dim'>".($thread['icon'] ? "<img src='".$thread['icon']."'>" : "")."</td>
				<td class='dim w' >$new".($thread['ispoll'] ? "Poll: " : "")."<a href='thread.php?id=".$thread['id']."'>".htmlspecialchars($thread['name'])."</a><br/><small>$smalltext</small></td>
				<td class='dim c' >".makeuserlink($thread['user'])."<br/><small><nobr>".printdate($thread['time'])."</nobr></small></td>
				<td class='light c' >".$thread['replies']."</td>
				<td class='light c' >".$thread['views']."</td>
				<td class='dim c' >$lastpost</td>
			</tr>";
		
		}
		
		print "</table>$pagectrl";
	}
	
	if (!$user)
		print "<table><td class='w' style='text-align: left;'>".doforumjump($id)."</td><td><nobr><a href='new.php?act=newpoll&id=$id'><img src='images/text/newpoll.png'></a> - <a href='new.php?act=newthread&id=$id'><img src='images/text/newthread.png'></a></nobr></td></tr></table>";
	
	pagefooter();

?>