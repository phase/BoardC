<?php

	if (isset($_POST['fjumpgo']))
		header("Location: forum.php?id=".$_POST['forumjump']);
	
	require "lib/function.php";
	
	$id = filter_int($_GET['id']);
	
	if (!$id)
		errorpage("No forum ID specified.");
	
	$forum = $sql->fetchq("SELECT name, powerlevel, threads, theme FROM forums WHERE id = $id");
	
	if ((!$forum && !powlcheck(4)) || $loguser['powerlevel']<$forum['powerlevel'])
		errorpage("Couldn't enter this restricted forum.");
	else if (!$forum){
		errorpage("This forum ID doesn't exist.");
	}
	// online update, revised
	update_hits($id);
	//$sql->query("UPDATE hits SET forum=$id WHERE ip='".$_SERVER['REMOTE_ADDR']."' ORDER BY time DESC LIMIT 1");
	
	if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme']);
	pageheader($forum['name']);

	print "<table class='main w fonts'><tr><td class='light c'>".onlineusers($id)."</td></tr></table>
	<table class='w'><tr><td class='w'><a href='index.php'>".$config['board-name']."</a> - ".$forum['name']."</td><td>&nbsp;</td><td style='text align: right'><a href='new.php?act=newthread&id=$id'><img src='images/text/newthread.png'></a></td></tr></table>";
	
	$threads = $sql->query("
	SELECT id, name, title, time, user, views, replies, sticky, closed, icon
	FROM threads
	WHERE forum = $id
	ORDER BY sticky DESC, id DESC
	LIMIT ".(filter_int($_GET['page'])*$loguser['tpp']).", ".$loguser['tpp']."");

	if (!$threads)
		print "<table class='main w c'><tr><td class='light'>There are no threads in this forum.<br/>Come back later<small> (or create a new one)</small></td></tr></table>";
	
	else{
		
		$pagectrl = dopagelist($forum['threads'], $loguser['tpp'], "forum");

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
			
			
			print "<tr>
				<td class='light'>$status</td>
				<td class='dim'>".($thread['icon'] ? "<img src='".$thread['icon']."'>" : "")."</td>
				<td class='dim w' ><a href='thread.php?id=".$thread['id']."'>".htmlspecialchars($thread['name'])."</a><br/><small>".htmlspecialchars($thread['title'])."</small></td>
				<td class='dim c' >".makeuserlink($thread['user'])."<br/><small><nobr>".printdate($thread['time'])."</nobr></small></td>
				<td class='light c' >".$thread['replies']."</td>
				<td class='light c' >".$thread['views']."</td>
				<td class='dim c' >$lastpost</td>
			</tr>";
		
		}
		
		print "</table>$pagectrl";
	}
	
	print "<table><td class='w' style='text-align: left;'>".doforumjump($id)."</td><td><a href='new.php?act=newthread&id=$id'><img src='images/text/newthread.png'></a></td></tr></table>";
	
	pagefooter();

?>