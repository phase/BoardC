<?php

/*
	[0.20] Unified PM page
	featuring more HTML adapted from Jul to mimic the UI (again)
*/
	
	require "lib/function.php";
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to read/send private messages!");
	
	$action = filter_string($_GET['act']);
	$id 	= filter_int($_GET['id']);
	
	if ($action == 'sent' || !$action){
		if (!$id){
			$id = $loguser['id'];
			$id_txt = ""; // hide this if you're viewing your own pms
		}
		else $id_txt = "&id=$id";
	}
	
	$isadmin= powlcheck(4);
	
	$txt 	= "";
	$pmcount= 0;
	
	if ($action == 'send'){
		
		if ($id)
			$sendto = $sql->resultq("SELECT name FROM users WHERE id = $id");
		
		$quote = filter_int($_GET['quote']);

		pageheader("Compose PM");
		
		if ($quote){
			$quoted = $sql->fetchq("
			SELECT p.text, p.title, p.name, u.name username
			FROM pms p
			LEFT JOIN users u
			ON p.user=u.id
			WHERE p.id=$quote");
			
			$sendto = $quoted['username'];
			$msg = "[quote=".$quoted['username']."]".$quoted['text']."[/quote]";
			$name = "Re: ".$quoted['name'];
			$title = $quoted['title'];
		}
		else{
			if (!$id)
				$sendto = isset($_POST['sendto']) ? $_POST['sendto'] : "";
			$msg = isset($_POST['message']) ? $_POST['message'] : "";
			$name = isset($_POST['name']) ? $_POST['name'] : "";
			$title = isset($_POST['title']) ? $_POST['title'] : "";
		}
		
		if (isset($_POST['submit'])){
			
			if (!$msg)
				errorpage("You've written an empty message!", false);
			if (!$name)
				errorpage("You've written an empty title!", false);
			
			$userto = $sql->resultp("SELECT id FROM users WHERE name = ?", array(filter_string($_POST['sendto'])));
			if (!$userto)
				errorpage("This user doesn't exist!", false);
			
			
			$msg = input_filters($msg);
			$name = input_filters($name);
			$title = input_filters($title);
			
			
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
		SELECT  p.id,p.name pmname,p.user,p.userto,p.time,p.text,p.nohtml,p.nosmilies,p.nolayout,p.avatar,
				u.name,u.displayname,u.title,u.sex,u.powerlevel,u.namecolor,u.head,u.sign,u.posts,u.since,u.location,u.lastview
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
	
	$pms = $sql->query("
	
		SELECT  p.id pid,p.name pmname,p.title pmtitle,p.user,p.userto,p.time,
				u.id,u.name,u.displayname,u.title,u.sex,u.powerlevel,u.namecolor
		FROM pms p
		LEFT JOIN users u ON $userlink = u.id
		WHERE $inorout = $id
		ORDER by p.id DESC
		
	");
	
	while ($pm = $sql->fetch($pms)){
		$new = ""; // TEMP!
		
		$txt .= "
		<tr>
			<td class='light c'>$new</td>
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