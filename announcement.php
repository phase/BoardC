<?php

	// Based on private.php
	
	require "lib/function.php";
	
	$id = filter_int($_GET['id']); // Forum ID (0 for global)
	$action = filter_string($_GET['act']);
	
	$ismod = powlcheck(4) or ismod($id);
	
	errorpage("Under construction!");
	
	$txt = "";
	
	if ($action == 'send'){
		
		if (!$ismod) errorpage("You're not allowed to do this!");
		
		if ($id)
			$sendto = $sql->resultq("SELECT name FROM users WHERE id = $id");
		
		$quote = filter_int($_GET['quote']);

		pageheader("New announcement");
		
		if ($quote){
			$quoted = $sql->fetchq("
			SELECT a.text, u.name FROM announcements a
			LEFT JOIN users u ON a.user=u.id
			WHERE p.id=$quote");
			
			$msg = "[quote=".$quoted['username']."]".$quoted['text']."[/quote]";
		}
		else $msg = isset($_POST['message']) ? $_POST['message'] : "";
		
	
		if (isset($_POST['submit'])){
			
			if (!$msg)	errorpage("You've written an empty announcement!", false);
			if (!$name)	errorpage("The announcement name can't be blank!", false);
			
			
			$name = input_filters($name);
			$title = input_filters($title);
			$msg = input_filters($msg);
			
			
			$sql->start();
			
			$a = $sql->prepare("INSERT INTO pms (name, title, user, userto, time, text, nohtml, nosmilies, nolayout, avatar) VALUES (?,?,?,?,?,?,?,?,?,?)");
			$go[] = $sql->execute($a, array($name, $title, $loguser['id'], $userto, ctime(), $msg, filter_int($_POST['nohtml']),filter_int($_POST['nosmilies']),filter_int($_POST['nolayout']), filter_int($_POST['avatar'])));
			if ($sql->finish($go)) errorpage("PM Sent!", false);
			else errorpage("Couldn't send the PM.", false);
		}
		
		if (isset($_POST['preview'])){
			
			$data = array(
				'id' => $sql->resultq("SELECT MAX(id) FROM pms")+1,
				'user' => $loguser['id'],
				'ip' => $loguser['lastip'],
				'deleted' => 0,
				'text' => $msg,
				'rev' => 0,
				'time' => ctime(),
				'nolayout' => filter_int($_POST['nolayout']),
				'nosmilies' => filter_int($_POST['nosmilies']),
				'nohtml' => filter_int($_POST['nohtml']),
				'lastpost' => $sql->resultq("SELECT MAX(time) FROM posts WHERE user = ".$loguser['id']),
				'avatar' => filter_int($_POST['avatar']),
				
			);
			print "<table class='main w c'>
			<tr><td class='head' style='border-bottom: none;'>PM Preview</td></tr></table>".threadpost(array_merge($loguser,$data), false, false, true, false, true);
		}
		
		$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
		$nohtmlc = isset($_POST['nohtml']) ? "checked" : "";
		$nolayoutc = isset($_POST['nolayout']) ? "checked" : "";
		// used to be maxlength='100'
		print "<a href='index.php'>".$config['board-name']."</a> - <a href='private.php'>Private messages</a> - Compose PM
		<form action='private.php?act=send' method='POST'>
			<table class='main w'>
				<tr><td class='head' width='150'>&nbsp;</td><td class='head'>&nbsp;</td></tr>
				<tr>
					<td class='light c'><b>Send to:</b><br/><small>Enter the real handle</small></td>
					<td class='dim'><input name='sendto' value=\"".htmlspecialchars($sendto)."\" size='30' maxlength='25' type='text'></td>
				</tr>
				<tr>
					<td class='light c'><b>Subject:</b></td>
					<td class='dim'><input name='name' value=\"".htmlspecialchars($name)."\" size='60' type='text'></td>
				</tr>
				<tr>
					<td class='light c'><b>Title:</b></td>
					<td class='dim'><input name='title' value=\"".htmlspecialchars($title)."\" size='60' type='text'></td>
				</tr>			
				<tr>
					<td class='light c'><b>Message:</b></td>
					<td class='dim' style='width: 806px; border-right: none'><textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'>".htmlspecialchars($msg)."</textarea></td>
				</tr>
				<tr>
					<td class='light'></td>
					<td class='dim'>
						<input type='submit' value='Send message' name='submit'>&nbsp;
						<input type='submit' value='Preview message' name='preview'>&nbsp;
						<input type='checkbox' name='nohtml' value=1 $nohtmlc>Disable HTML&nbsp;
						<input type='checkbox' name='nolayout' value=1 $nolayoutc>Disable Layout&nbsp;
						<input type='checkbox' name='nosmilies' value=1 $nosmiliesc>Disable Smilies&nbsp;
						".getavatars($loguser['id'], filter_int($_POST['avatar']))."
					</td>
				</tr>
			</table>
		</form>";
		
		pagefooter();
	}
	else if ($action == 'view'){
		
		if (!$id)
			errorpage("No PM specified.");
		
		//errorpage("Under construction!");
		$post = $sql->fetchq("
		SELECT  p.id,p.name pmname,p.user,p.userto,p.time,p.text,p.nohtml,p.nosmilies,p.nolayout,p.avatar,p.new,
				$userfields,u.title,u.head,u.sign,u.posts,u.since,u.location,u.lastview
		FROM pms p
		LEFT JOIN users u ON p.user = u.id
		WHERE p.id = $id 
		");
		
		if (!$isadmin && (($post['userto'] != $loguser['id'] && $post['user'] != $loguser['id']) || !$post['id']))
			errorpage("This private message isn't for you!");
		
		if (!$post['id'])
			errorpage("A private message with ID #$id doesn't exist.");
		
		if (($post['userto'] == 1 || $post['user'] == 1) && !in_array($loguser['id'],array($post['user'],$post['userto']) ))
			errorpage("No.");

		$s = "<a href='index.php'>".$config['board-name']."</a> - <a href='private.php'>Private messages</a> - ".htmlspecialchars($post['pmname']);
		
		$data = array(
			'ip' => $loguser['lastip'],
			'deleted' => 0,
			'rev' => 0,
			'lastpost' => $sql->resultq("SELECT MAX(time) FROM posts WHERE user = ".$loguser['id']),
		);
		
		if ($post['new'] && $post['userto'] == $loguser['id'])
			$sql->query("UPDATE pms SET new = 0 WHERE id = $id");
		
		pageheader("Private Messages: ".htmlspecialchars($post['pmname']));
		print $s.threadpost(array_merge($post,$data), false, false, false, false, true).$s;
		
		pagefooter();
	}
	else if ($action == 'sent'){
		$pmtype = "Outbox";
		$from = "To";
		$linkswitch = "<a href='private.php?$id_txt'>View received messages</a>";
		$inorout = "p.user";
		$userlink = "p.userto";
	}
	else{
		$pmtype = "Inbox";
		$from = "From";
		$linkswitch = "<a href='private.php?act=sent$id_txt'>View sent messages</a>";
		$inorout = "p.userto";
		$userlink = "p.user";
	}
	
	if ( (!$isadmin && $loguser['id'] != $id) || ($id == 1 && $loguser['id'] != 1) )
		errorpage("No.");
	
	$ann = $sql->query("
	
		SELECT  a.id,a.name aname,a.title atitle,a.user,a.time,a.text,a.nohtml,a.nosmilies,a.nolayout,a.avatar,
				$userfields,u.title,u.head,u.sign,u.posts,u.since,u.location,u.lastview
		FROM pms p
		LEFT JOIN users u ON a.user = u.id
		WHERE a.forum = $id 
		
	");
	
	while ($pm = $sql->fetch($pms)){
		$txt .= "
		<tr>
			<td class='light c'>".($pm['new'] && $pmtype == "Inbox" ? "<img src='images/status/new.gif'>" : "")."</td>
			<td class='dim'><a href='private.php?act=view&id=".$pm['pid']."'>".htmlspecialchars($pm['pmname'])."</a>".($pm['pmtitle'] ? "<br/><small>".htmlspecialchars($pm['pmtitle'])."</small>" : "")."</td>
			<td class='dim c'>".makeuserlink(false, $pm)."</td>
			<td class='dim c'>".printdate($pm['time'])."</td>
		</tr>";
		
		$pmcount++;
		
	}
	
	
	pageheader("Private Messages");
	
	print "
	<table class='w'>
		<tr>
			<td>
				<a href='index.php'>".$config['board-name']."</a> - Private messages ".($id_txt ? "for ".makeuserlink($id)." " : "")."- $pmtype: $pmcount
			</td>
			<td class='fonts' align='right'>
				$linkswitch | <a href='private.php?act=send$id_txt'>Send new message".($id_txt ? " to this user" : "")."</a>
			</td>
		</tr>
	</table>

	<table class='main w'>
		<tr class='c'>
			<td class='head' style='width:50px'>&nbsp;</td>
			<td class='head'>Subject</td>
			<td class='head' style='width:15%'>$from</td>
			<td class='head' style='width:180px'>Sent on</td>
		</tr>
		$txt
	</table>
	
	";
	
	pagefooter();
?>