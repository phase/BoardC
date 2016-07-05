<?php
	
	// Workaround for forum jump with Javascript disabled
	if (isset($_POST['fjumpgo']))
		header("Location: forum.php?id=".filter_int($_POST['forumjump']));
	
	require "lib/function.php";
	
	$id 	 		= filter_int($_GET['id']);
	$user 	 		= filter_int($_GET['user']);
	$isadmin 		= powlcheck(4);
	
	if ($user){
		
		update_hits();

		$where = "
			, t.forum, f.id fid, f.name fname, f.powerlevel
			FROM threads t
			LEFT JOIN forums f ON t.forum  = f.id
			LEFT JOIN posts p  ON p.thread = t.id
			WHERE t.user = $user
			GROUP BY t.id DESC
		";
		
		$userdata = $sql->fetchq("SELECT name, displayname, threads, lastpost FROM users WHERE id = $user");
		
		if (!$userdata)
			errorpage("This user doesn't exist!");
		
		// hack hack hack
		$forum['name'] 		= "Threads by ".($userdata['displayname'] ? $userdata['displayname'] : $userdata['name']);
		$forum['threads'] 	= $userdata['threads'];
		$announce 	= "";
		pageheader($forum['name']);
		
	}
	else if ($id)	{
			
		$where = "
			FROM threads t
			LEFT JOIN posts p ON p.thread = t.id
			WHERE t.forum = $id
			GROUP BY t.id DESC
		";
	
		$forum 		= $sql->fetchq("SELECT name, powerlevel, threads, theme FROM forums WHERE id = $id");
		$viewpowl 	= $loguser['powerlevel'] < 0 ? 0 : $loguser['powerlevel'];
		
		if ((!$forum && !$isadmin) || $viewpowl<$forum['powerlevel']) 	errorpage("Couldn't enter this restricted forum.");
		else if (!$forum)												errorpage("This forum ID doesn't exist.");
		
		// online update, revised
		update_hits($id);

		if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme'])-1;
		pageheader($forum['name'], true, $id);
		
		$announce = doannbox($id);
	}
	else errorpage("No forum ID specified.");

	
	$ismod = ismod($id);
	
	$newthread = (!$user && $loguser['id'] && $loguser['powerlevel']>=0 && (!$miscdata['noposts'] || powlcheck(4))) ? "<nobr>".($ismod ? "<a href='announcement.php?act=new&id=$id'>New announcement</a> - " : "")."<a href='new.php?act=newpoll&id=$id'><img src='images/text/newpoll.png'></a> - <a href='new.php?act=newthread&id=$id'><img src='images/text/newthread.png'></a></nobr>" : "";
	
	print "<table class='main w fonts'><tr><td class='light c'>".onlineusers($id)."</td></tr></table>
	<table class='w'>
		<tr>
			<td class='w'>
				<a href='index.php'>{$config['board-name']}</a> - {$forum['name']}</td>
			<td>&nbsp;</td>
			<td style='text align: right'>$newthread</td>
		</tr>
	</table>";

	$threads = $sql->query("
	SELECT t.id, t.name, t.title, t.time, t.user, t.views, t.replies, t.sticky, t.closed, t.icon, t.ispoll, t.lastpostid, t.lastpostuser, t.lastposttime
	$where
	ORDER BY t.sticky DESC, t.lastposttime DESC
	LIMIT ".(filter_int($_GET['page'])*$loguser['tpp']).", {$loguser['tpp']}");

	if (!$threads){
		if ($user) errorpage("There are no threads to show.", false);
		print "<table class='main w c'><tr><td class='light'>There are no threads in this forum.<br/>Come back later<small>".($loguser['id'] ? " (or create a new one)" : "")."</small></td></tr></table>";
	}
	else{
		
		$pagectrl = dopagelist($forum['threads'], $loguser['tpp'], "forum", $user ? "user=$user" : "");
		
		// Get new posts by date
		if ($loguser['id']){
			$newposts = $sql->query("
				SELECT t.id tid, MIN(p.id) pid, SUM(p.time > user{$loguser['id']}) ncount
				FROM posts p
				LEFT JOIN threads      t ON p.thread = t.id
				LEFT JOIN threads_read n ON t.id     = n.id
				WHERE p.time > n.user{$loguser['id']} 
				GROUP BY t.id

				");
			while ($newpost = $sql->fetch($newposts)){
				$new_db[$newpost['tid']] = array($newpost['pid'], $newpost['ncount']);
			}
			//d($new_db);
		}

		print "
		$pagectrl<table class='main w'>
		$announce
			<tr>
				<td class='head c'>&nbsp;</td>
				<td colspan=2 class='head w c'>Thread</td>
				<td class='head c'><nobr>Started by</nobr></td>
				<td class='head c'>Replies</td>
				<td class='head c'>Views</td>
				<td class='head c'><nobr>Last post</nobr></td>
			</tr>
		";
		
		// To hold previous value of sticky
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

			
			$status_name = "";
			
			if($thread['replies'] > $miscdata['threshold'])	$status_name.="hot";
			if($thread['closed']) 							$status_name.="off";
			//if($thread['new']) 								$status_name.="new";

			
			if (isset($new_db[$thread['id']])){
				$status_name .= "new";
				$unread_txt   = $new_db[$thread['id']][1]; // Add number of unread posts to status column
				$new = "<a href='thread.php?pid={$new_db[$thread['id']][0]}#{$new_db[$thread['id']][0]}'><img src='images/status/getnew.png'></a> "; // Link to newest unread post
			}
			else $new = $unread_txt = "";
			
			$status = $status_name ? "<img src='images/status/$status_name.gif'>$unread_txt" : "&nbsp;";
			
			// Threads by user specific
			if ($user){
				if (($thread['powerlevel'] && $loguser['powerlevel'] < $thread['powerlevel']) || (!$isadmin && !$thread['fid'])){
					print "<tr><td class='light c fonts' colspan='7'>(restricted)</td></tr>";
					continue;
				}
				$smalltext = "&nbsp;&nbsp;&nbsp;&nbsp;In <a href='forum.php?id={$thread['forum']}'".($thread['fid'] ? ">{$thread['fname']}" : "class='danger' style='background: #fff'>Invalid forum ID #{$thread['forum']}")."</a>";
			}
			else{
				if ($thread['ispoll'])
					$thread['title'] = split_null($thread['title'], true);
				
				$smalltext = htmlspecialchars($thread['title']);
			}
			
			
			// Thread page list
			$tpagectrl = dopagelist($thread['replies']+1, $loguser['ppp'], "thread", "", $thread['id']);
			
			print "<tr>
				<td class='light c'>$status</td>
				<td class='dim'>".($thread['icon'] ? "<img src='{$thread['icon']}'>" : "")."</td>
				<td class='dim w'>$new".($thread['ispoll'] ? "Poll: " : "")."<a href='thread.php?id={$thread['id']}'>".htmlspecialchars($thread['name'])."</a> $tpagectrl<br/><small>$smalltext</small></td>
				<td class='dim c'>".makeuserlink($thread['user'])."<br/><small><nobr>".printdate($thread['time'])."</nobr></small></td>
				<td class='light c'>{$thread['replies']}</td>
				<td class='light c'>{$thread['views']}</td>
				<td class='dim c'>$lastpost</td>
			</tr>";
		
		}
		
		print "</table>$pagectrl";
	}
	
	if (!$user)
		print "<table><td class='w' style='text-align: left;'>".doforumjump($id)."</td><td>$newthread</td></tr></table>";
	
	pagefooter();

?>