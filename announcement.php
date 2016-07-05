<?php

	// Based on private.php
	
	require "lib/function.php";
	
	$id 	= filter_int($_GET['id']); // Forum ID (0 for global)
	$action = filter_string($_GET['act']);
	$ismod 	= powlcheck(4) or ismod($id);
	
	if ($id)
		if (!canviewforum($id))
			errorpage("This forum doesn't exist.");

	$txt 	= "";
	
	if ($action == 'new'){
		/*
			New announcement
		*/
		if (!$ismod) errorpage("You're not allowed to do this!");
		
		$name 	= filter_string($_POST['name']);
		$title 	= filter_string($_POST['title']);
		$quote 	= filter_int($_GET['quote']);
		
		if ($quote){
			$quoted = $sql->fetchq("
				SELECT a.text, u.name, a.forum FROM announcements a
				LEFT JOIN users u ON a.user=u.id
				WHERE a.id = $quote
			");
			
			if ($quoted)
				if (canviewforum($quoted['forum']))
					$msg = "[quote=".$quoted['name']."]".$quoted['text']."[/quote]";
			else $msg = "";
		}
		else $msg = isset($_POST['message']) ? $_POST['message'] : "";
		

		
		
	
		if (isset($_POST['submit'])){
			
			if (!$msg)	errorpage("You've written an empty announcement!", false);
			if (!$name)	errorpage("The announcement name can't be blank!", false);
			
			
			$name 	= input_filters($name);
			$title 	= input_filters($title);
			$msg 	= input_filters($msg);
			
			
			$sql->start();
			
			$a 	  = $sql->prepare("INSERT INTO announcements (name, title, user, time, text, nohtml, nosmilies, nolayout, avatar, forum) VALUES (?,?,?,?,?,?,?,?,?,?)");
			$go[] = $sql->execute($a, array($name, $title, $loguser['id'], ctime(), $msg, filter_int($_POST['nohtml']),filter_int($_POST['nosmilies']),filter_int($_POST['nolayout']), filter_int($_POST['avatar']), $id));
			$sql->query("INSERT INTO announcements_read () VALUES ()");
			if ($sql->finish($go)) header("Location: announcement.php?id=$id");
			else errorpage("Couldn't create the announcement.");
		}
		
		pageheader("New announcement");
		
		if (isset($_POST['preview'])){
			
			$data = array(
				'id' 		=> $sql->resultq("SELECT MAX(id) FROM announcements")+1,
				'user' 		=> $loguser['id'],
				'ip' 		=> $loguser['lastip'],
				'deleted' 	=> 0,
				'text' 		=> $msg,
				'rev' 		=> 0,
				'time' 		=> ctime(),
				'nolayout' 	=> filter_int($_POST['nolayout']),
				'nosmilies' => filter_int($_POST['nosmilies']),
				'nohtml' 	=> filter_int($_POST['nohtml']),
				'avatar' 	=> filter_int($_POST['avatar']),
				'new'		=> 0,
				'noob'		=> 0
			);
			print "<table class='main w c'>
			<tr><td class='head' style='border-bottom: none;'>Announcement Preview</td></tr></table>".threadpost(array_merge($loguser,$data), false, false, true, false, true);
		}
		
		$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
		$nohtmlc 	= isset($_POST['nohtml']) 	 ? "checked" : "";
		$nolayoutc 	= isset($_POST['nolayout'])  ? "checked" : "";
		
		$fname = $id ? " <a href='forum.php?id=$id'>".$sql->resultq("SELECT name FROM forums WHERE id = $id")."</a> -" : "";
		
		// used to be maxlength='100'
		print "<a href='index.php'>".$config['board-name']."</a> -$fname <a href='announcement.php?id=$id'>Announcements</a> - New announcement
		<form action='announcement.php?act=new&id=$id' method='POST'>
			<table class='main w'>
				<tr><td class='head' width='150'>&nbsp;</td><td class='head'>&nbsp;</td></tr>
				<tr>
					<td class='light c'><b>Name:</b></td>
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
						<input type='submit' value='Create announcement' name='submit'>&nbsp;
						<input type='submit' value='Preview announcement' name='preview'>&nbsp;
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
	else if ($action == 'edit'){
		/*
			Edit announcement
		*/

		// Horray for recycling code
		
		if (!$ismod) errorpage("You're not allowed to do this!");
		if (!$id)	 errorpage("No announcement ID specified.");
		

		if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme'])-1;

		$post = $sql->fetchq("
			SELECT p.id, p.text, p.name aname, p.title atitle, p.time, p.rev, p.user, p.forum, p.nohtml, p.nosmilies, p.nolayout, p.avatar, o.time rtime, p.lastedited,
			u.head, u.sign, u.lastip ip, $userfields uid, u.title, u.posts, u.since, u.location, u.lastview, u.lastpost, 0 new
			FROM announcements AS p
			LEFT JOIN users AS u ON p.user = u.id
			LEFT JOIN announcements_old AS o ON p.id = (SELECT MAX('o.id') FROM announcements_old o WHERE o.aid = p.id)
			WHERE p.id = $id
		");
		
		if (!filter_int($post['id']))
			errorpage("This announcement doesn't exist", false);
		

		$name 	= isset($_POST['aname']) 	? $_POST['aname'] 	: $post['aname'];
		$title 	= isset($_POST['atitle']) 	? $_POST['atitle'] 	: $post['atitle'];
		$msg 	= isset($_POST['text']) 	? $_POST['text'] 	: $post['text'];
		

		
		if (isset($_POST['submit'])){
			
			if (!$msg) 	errorpage("You've edited the announcement to be blank!", false);
			if (!$name) errorpage("You've edited the name to be blank!", false);
			
			
			$msg 	= input_filters($msg);
			$name 	= input_filters($name);
			$title 	= input_filters($title);
			
			
			
			$sql->start();
			
			$bak  = $sql->prepare("INSERT INTO `announcements_old` (`aid` ,`name`, `title`, `text`, `time`, `rev`, `nohtml`, `nosmilies`, `nolayout`, `avatar`) VALUES (?,?,?,?,?,?,?,?,?,?)");
			$go[] = $sql->execute($bak, array($post['id'], $post['name'], $post['title'], $post['text'], $post['time'], $post['rev'], $post['nohtml'], $post['nosmilies'], $post['nolayout'], $post['avatar']));
			
			
			$a = $sql->prepare("
				UPDATE announcements
				SET name=?, title=?, text=? ,time=? ,rev=? ,nohtml=? ,nosmilies=? ,nolayout=?, lastedited=?, avatar=?
				WHERE id = $id
			");
			
			$_POST['nosmilies'] = filter_int($_POST['nosmilies']);
			$_POST['nohtml'] 	= filter_int($_POST['nohtml']);
			$_POST['nolayout'] 	= filter_int($_POST['nolayout']);
			$_POST['avatar'] 	= filter_int($_POST['avatar']);
			
			$go[] = $sql->execute($a, array($name, $title, $msg, ctime(), $post['rev']+1, $_POST['nohtml'], $_POST['nosmilies'], $_POST['nolayout'], $loguser['id'], $_POST['avatar']) );
			
			if ($sql->finish($go)) header("Location: announcement.php?id={$post['forum']}");//$msg = "The announcement has been edited successfully.";
			else errorpage("Couldn't edit the announcement.");
		}
		
		pageheader("Edit announcement");
		
		if (isset($_POST['preview'])){
			
			$postids = getpostcount($post['user'], true);
			
			$data = array(
				'rev' 		=> $post['rev']+1,
				'text' 		=> $msg,
				'nolayout' 	=> filter_int($_POST['nolayout']),
				'nosmilies' => filter_int($_POST['nosmilies']),
				'nohtml' 	=> filter_int($_POST['nohtml']),
				'postcur' 	=> array_search($post['id'], $postids[$post['user']])+1,
				'crev' 		=> $post['rev']+1,
				'time' 		=> ctime(),
				'rtime' 	=> $post['time'],
				'lastedited'=> $loguser['id'],
				'avatar'	=> filter_int($_POST['avatar']),
				'noob'		=> 0
			);
			print "<table class='main w'>
			<tr><td class='head c' style='border-bottom: none'>Announcement Preview</td></tr></table>".threadpost(array_merge($post, $data), false, false, true, false, false, true);
			

			$nsm = filter_int($_POST['nosmilies']);
			$nht = filter_int($_POST['nohtml']);
			$nly = filter_int($_POST['nolayout']);
			$cha = filter_int($_POST['avatar']);
			
			
		}
		else {
			$nsm = $post['nosmilies'];
			$nht = $post['nohtml'];
			$nly = $post['nolayout'];
			$cha = $post['avatar'];
			
		}
		
		$nosmiliesc = $nsm ? "checked" : "";
		$nohtmlc 	= $nht ? "checked" : "";
		$nolayoutc 	= $nly ? "checked" : "";

		$fname = $id ? " <a href='forum.php?id=$id'>".$sql->resultq("SELECT name FROM forums WHERE id = $id")."</a> -" : "";
		
		// used to be maxlength='100'
		print "<a href='index.php'>".$config['board-name']."</a> -$fname <a href='announcement.php?id=$id'>Announcements</a> - Edit announcement
		<form action='announcement.php?act=edit&id=$id'  method='POST'>
			<table class='main'>
				<tr>
					<td colspan=2 class='head c'>Edit announcement</td>
				</tr>
				<tr>
					<td class='light c'><b>Name:</b></td>
					<td class='dim'><input type='text' name='aname' value=\"".htmlspecialchars($name)."\" style='width: 300px'></td>
				</tr>
				<tr>
					<td class='light c'><b>Title:</b></td>
					<td class='dim'><input type='text' name='atitle' value=\"".htmlspecialchars($title)."\" style='width: 600px'></td>
				</tr>
				<tr>
					<td class='light c'><b>Message:</b></td>
					<td class='light' style='width: 806px; border-right: none'><textarea name='text' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'>".htmlspecialchars($msg)."</textarea></td>
				</tr>
				<tr>
					<td class='dim' colspan=2>
						<input type='submit' value='Submit' name='submit'>&nbsp;
						<input type='submit' value='Preview' name='preview'>&nbsp;
						<input type='checkbox' name='nohtml' value=1 $nohtmlc>Disable HTML&nbsp;
						<input type='checkbox' name='nolayout' value=1 $nolayoutc>Disable Layout&nbsp;
						<input type='checkbox' name='nosmilies' value=1 $nosmiliesc>Disable Smilies&nbsp;
						".getavatars($post['user'], $cha)."
					</td>
				</tr>
			</table>
		</form>";
		
		pagefooter();
	}
	
	
	$page = filter_int($_GET['page']);
	
	$new_check = $loguser['id'] ? "(a.time > n.user{$loguser['id']})" : "0";

	$ann = $sql->query("
	
		SELECT  a.id,a.name aname,a.title atitle,a.user,a.time,a.text,a.nohtml,a.nosmilies,a.nolayout,a.avatar,a.lastedited,a.rev,0 noob,o.time rtime,
				$userfields uid,u.title,u.head,u.sign,u.posts,u.since,u.location,u.lastview,u.lastip ip, u.lastpost, $new_check new
		FROM announcements a
		LEFT JOIN users u ON a.user = u.id
		LEFT JOIN announcements_old  o ON o.time = (SELECT MIN(o.time) FROM announcements_old o WHERE o.aid = a.id)
		LEFT JOIN announcements_read n ON a.id = n.id
		WHERE a.forum = $id
		GROUP BY a.id DESC
		LIMIT ".($page*$loguser['ppp']).", ".$loguser['ppp']."
		
	");
	
	if ($id){
		$fname 	= $sql->resultq("SELECT name FROM forums WHERE id = $id");
		$s  	= " - <a href='forum.php?id=$id'>$fname</a>";
	}
	else $fname = $s = "";
	
	/*
		Display announcements
	*/
	if ($ann){
		// Page number stuff
		$count 		= $sql->resultq("SELECT COUNT(id) FROM announcements WHERE forum = $id");
		$pagectrl 	= dopagelist($count, $loguser['ppp'], "announcement");
		
		while ($a = $sql->fetch($ann)){
			
			// Collect announcement IDs to update the last view table
			if ($a['new']){
				$id_list[] = $a['id'];
			}
			
			if ($ismod){
				if (filter_int($_GET['del']) == $a['id']){
					// *INSTANT* delete (not consistent with thread controls)
					$sql->query("DELETE FROM announcements WHERE id = ".$a['id']);
					continue;
				}
				
				if (isset($_GET['rev']) && filter_int($_GET['pin'])==$a['id'])
					$a = array_replace($a, $sql->fetchq("SELECT text,rev crev,time,nohtml,nosmilies,nolayout,avatar FROM announcements_old  WHERE aid = ".$a['id']." AND rev = ".filter_int($_GET['rev'])));
			}
			
			// Insert name and title at the top of the annoucement, along with a hr separator
			$a['text'] = "<center><b>{$a['aname']}</b><br><small>{$a['atitle']}</small></center><hr class='w'>{$a['text']}";
			
			$txt .= threadpost($a, false, false, $ismod ? false : true, false, false, true);

		}
		
		if (isset($id_list))
			$sql->query("UPDATE announcements_read SET user{$loguser['id']} = ".ctime()." WHERE id IN (".implode(",", $id_list).")");//$sql->query("UPDATE new_announcements SET user".$loguser['id']." = 0");//$sql->query("UPDATE new_announcements SET user".$loguser['id']." = 0 WHERE ".implode(" AND ", $set));
	}

	else{
		$txt .= "
	<table class='main w c'>
		<tr>
			<td class='light'>
				There are no announcements under this section.".($ismod ? "<br/>To create a new one, click <a href='announcement.php?act=new&id=$id'>here</a>." : "")."
			</td>
		</tr>
	</table>";
	$pagectrl = "";
	}
	

	$newann = $ismod ? "<table class='w'><tr><td style='text-align: right'><a href='announcement.php?act=new&id=$id'>New announcement</a></td></tr></table>" : "";
	
	pageheader("Announcements".($id ? " - $fname" : ""));
	
	print "
	<table class='w'><tr>
	<td><a href='index.php'>".$config['board-name']."</a>$s - Announcements</td>
	<td style='text-align: right'>$newann</td></tr></table>
	
	$pagectrl
	<table class='main w'>
		<tr>
			<td class='head c' style='width: 200px'>User</td>
			<td class='head c'>Announcement</td>
		</tr>
	</table>
	$txt
	
	<table class='w'><tr>
	<td>$pagectrl</td>
	<td style='text-align: right'>$newann</td></tr></table>
	";
	
	pagefooter();

?>