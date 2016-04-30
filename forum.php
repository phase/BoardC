<?php

	if (isset($_POST['fjumpgo']))
		header("Location: forum.php?id=".$_POST['forumjump']);
	
	require "lib/function.php";
	
	$id = filter_int($_GET['id']);
	$user = filter_int($_GET['user']);
	$isadmin = powlcheck(4);
	
	if ($user){
		update_hits();
		$where = ",t.forum , f.id fid, f.name fname, f.powerlevel
		FROM threads t
		LEFT JOIN forums f ON t.forum = f.id
		WHERE t.user = $user";
		
		$userdata = $sql->fetchq("
			SELECT u.name, u.displayname
			FROM users u
			LEFT JOIN posts p ON u.id = p.user
			WHERE u.id = $user
			ORDER BY p.time DESC
		");
		
		if (!$userdata)
			errorpage("This user doesn't exist!");
		
		// hack hack hack
		$forum['name'] = "Threads by ".($userdata['displayname'] ? $userdata['displayname'] : $userdata['name']);
		$forum['threads'] = $loguser['threads'];
		pageheader($forum['name']);
		
	}
	else if ($id)	{
		$where = "FROM threads t WHERE t.forum = $id";
	
		$forum = $sql->fetchq("SELECT name, powerlevel, threads, theme FROM forums WHERE id = $id");
		
		if ((!$forum && !$isadmin) || $loguser['powerlevel']<$forum['powerlevel'])
			errorpage("Couldn't enter this restricted forum.");
		else if (!$forum){
			errorpage("This forum ID doesn't exist.");
		}
		// online update, revised
		update_hits($id);

		if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme']);
		pageheader($forum['name']);
	}
	else errorpage("No forum ID specified.");

	print "<table class='main w fonts'><tr><td class='light c'>".onlineusers($id)."</td></tr></table>
	<table class='w'>
		<tr>
			<td class='w'>
				<a href='index.php'>".$config['board-name']."</a> - ".$forum['name']."</td>
			<td>&nbsp;</td>
			".($user ? "" : "<td style='text align: right'><a href='new.php?act=newthread&id=$id'><img src='images/text/newthread.png'></a></td>")."
		</tr>
	</table>";
	
	$threads = $sql->query("
	SELECT t.id, t.name, t.title, t.time, t.user, t.views, t.replies, t.sticky, t.closed, t.icon
	$where
	ORDER BY t.sticky DESC, t.id DESC
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
			
			// Temp
			/*
			$new = "&nbsp;"; 
			
			if ($thread['sticky']) $new .= "[S]"; 
			if ($thread['closed']) $new .= "[C]"; */
			// Thread status conditions
			$thread['new'] = 0; // TO-DO:
			
			$status = "";
			
			if($thread['replies']>20) 	$status.="hot";
			if($thread['closed']) 		$status.="off";
			if($thread['new']) 			$status.="new";

			$status = $status ? "<img src='images/status/$status.gif'>" : "&nbsp;";
			
			// Separator between sticked threads and not
			if ($c == 1 && $c != $thread['sticky']){
				$c = $thread['sticky'];
				print "<tr><td class='head fonts' colspan=7>&nbsp;</td></tr>";
			}
			$c = $thread['sticky'];
			
			$postdata = $sql->fetchq("
				SELECT `id`, `time`, `user`
				FROM `posts`
				WHERE `thread` = ".$thread['id']."
				ORDER BY `time` DESC
				LIMIT 1");
			if (!empty($postdata))
				$lastpost = "<nobr>".printdate($postdata['time'])."<br/><small><nobr> by ".makeuserlink($postdata['user'])." <a href='thread.php?pid=".$postdata['id']."#".$postdata['id']."'><img src='images/status/getlast.png'></a></nobr></small>";
			else $lastpost = "Nothing"; // [0.09]
			
			// Threads by user specific
			if ($user){
				if ($loguser['powerlevel']<$thread['powerlevel'] || (!$isadmin && !$thread['fid'])){
					print "<tr><td class='light c fonts' colspan='7'>(restricted)</td></tr>";
					continue;
				}
				$smalltext = "&nbsp;&nbsp;&nbsp;&nbsp;In <a href='forum.php?id=".$thread['forum']."'".($thread['fid'] ? ">".$thread['fname'] : "class='danger' style='background: #fff'>Invalid forum ID #".$thread['forum'])."</a>";
			}
			else{
				$smalltext = htmlspecialchars($thread['title']);
			}
			
			print "<tr>
				<td class='light'>$status</td>
				<td class='dim'>".($thread['icon'] ? "<img src='".$thread['icon']."'>" : "")."</td>
				<td class='dim w' ><a href='thread.php?id=".$thread['id']."'>".htmlspecialchars($thread['name'])."</a><br/><small>$smalltext</small></td>
				<td class='dim c' >".makeuserlink($thread['user'])."<br/><small><nobr>".printdate($thread['time'])."</nobr></small></td>
				<td class='light c' >".$thread['replies']."</td>
				<td class='light c' >".$thread['views']."</td>
				<td class='dim c' >$lastpost</td>
			</tr>";
		
		}
		
		print "</table>$pagectrl";
	}
	
	if (!$user)
		print "<table><td class='w' style='text-align: left;'>".doforumjump($id)."</td><td><a href='new.php?act=newthread&id=$id'><img src='images/text/newthread.png'></a></td></tr></table>";
	
	pagefooter();

?>