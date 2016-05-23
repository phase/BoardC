<?php
	$meta['noindex'] = true;
	
	require "lib/function.php";
	
	if 		(!isset($_GET['act']))				errorpage("No action specified.");
	else if ($bot || $proxy || $tor)			errorpage("Fuck off.");
	else if (!$loguser['id'])					errorpage("You need to be logged in to do that.");
	else if ($loguser['powerlevel']<0)			errorpage("Banned users aren't allowed to do this.");
	else if ($loguser['editing_locked']==2)		errorpage("You aren't allowed to post.");
	else if (filter_bool($miscdata['noposts']))	errorpage("Posting has been disabled temporarily.");
	
	$id = filter_int($_GET['id']);
	
	if ($_GET['act'] == 'newreply'){

		if (!$id)
			errorpage("No thread ID specified");
		
		$lookup = $id;
		$pid 	= filter_int($_GET['pid']);
		$quote 	= filter_int($_GET['quote']);
		
		$tdata 	= getthreadinfo($lookup);
		
		$thread 	= $tdata[0];
		$forum 		= $tdata[1];
		$error_id 	= $tdata[2];
		if (!$pid)
			$pid 	= $tdata[3];
		
		unset($tdata);
		
		$ismod = ismod(isset($thread['forum']) ? $thread['forum'] : false);
		
		if ($error_id){
			
			// Copied from thread.php
			$username = ($loguser['id'] ? "User ID #".$loguser['id']."(".$loguser['name'].")" : "IP ".$_SERVER['REMOTE_ADDR']);
			
			$threadbug = array(
				1 => array("You're not allowed to view the thread","$username accessed restricted thread ID #$lookup", true),
				2 => array("The thread with ID #$lookup doesn't exist.", "$username accessed nonexisting thread ID #$lookup", true),
				3 => array(filter_int($_GET['pid']) ? "A post with ID #$pid does exist, but it's in an invalid thread. (ID #$lookup)" : "A thread with ID #$lookup doesn't exist, but there are posts associated with it.", "$username accessed valid posts in invalid thread ID #$lookup", false),
				4 => array("A thread with ID #$lookup does exist, but it's in an invalid forum. (ID #".$forum['id'].")", "$username accessed valid thread ID #$lookup in invalid forum ID #".$forum['id'], false),
				5 => array("There is no post in the database with ID #$pid", "$username accessed nonexisting post #$pid", true),
			);
			
			trigger_error($threadbug[$error_id][1], E_USER_NOTICE);
			

			if (!$ismod)
				errorpage("Couldn't enter the thread. Either it doesn't exist or you're not allowed to view it.");
			else if ($threadbug[$error_id][2])
				errorpage($threadbug[$error_id][0]);
			
			$threadbug_txt = "<div style='text-align: center; color: yellow; padding: 3px; border: 5px dotted yellow; background: #000;'><b>Thread error: ".$threadbug[$error_id][0]."</b></div>";
		}
		
		if ($thread['closed'] && !$ismod)
			errorpage("You know, this thread is <i>probably</i> closed for a reason.");
		
		if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme'])-1;
		
		$msg = isset($_POST['message']) ? $_POST['message'] : "";
		
		if ($quote){
			$quoted = $sql->fetchq("
				SELECT p.text, u.name, t.forum
				FROM posts p
				LEFT JOIN users   u ON p.user  = u.id
				LEFT JOIN threads t ON p.thread = t.id
				WHERE p.id=$quote
			");
			
			if ($quoted)
				if (canviewforum($quoted['forum']))
					$msg = "[quote=".$quoted['name']."]".$quoted['text']."[/quote]";
		}
		
		if (isset($_POST['submit'])){
			if (!filter_string($msg))
				errorpage("You've written an empty reply!");
			
			$filtered = input_filters($msg);
			
			$sql->start();
			
			$a = $sql->prepare("INSERT INTO posts (text, time, thread, user, rev, deleted, nohtml, nosmilies, nolayout, avatar) VALUES (?,?,?,?,?,?,?,?,?,?)");
			$go[] = $sql->execute($a, array($filtered, ctime(), $thread['id'], $loguser['id'], 0, 0, filter_int($_POST['nohtml']),filter_int($_POST['nosmilies']),filter_int($_POST['nolayout']), filter_int($_POST['avatar'])));
			if (!$error_id){
				$sql->query("UPDATE threads SET replies = ".($thread['replies']+1)." WHERE id = $lookup");
				$sql->query("UPDATE forums SET posts = (posts+1) WHERE id = ".$thread['forum']);
				$sql->query("UPDATE misc SET posts = posts+1");
			}
			
			$sql->query("UPDATE users SET posts = (posts+1), coins=coins+".rand($config['coins-rand-min'], $config['coins-rand-max'])." WHERE id = ".$loguser['id']);
			$sql->query("INSERT INTO new_posts () VALUES ()");	
			
			$pid = $sql->resultq("SELECT MAX(id) FROM posts");
			update_last_post($thread['id'], array('id' => $pid, 'user' => $loguser['id'], 'time' => ctime(), 'forum' => $forum['id']) );

			if ($sql->finish($go)) header("Location: thread.php?pid=$pid");//errorpage("Successfully posted the reply.", false);
			else errorpage("Couldn't post the reply.");
		}
		
		pageheader($thread['name']." - New Reply");
		
		if (isset($_POST['preview'])){
			
			$data = array(
				'id' 		=> $sql->resultq("SELECT MAX(id) FROM posts")+1,
				'user' 		=> $loguser['id'],
				'ip' 		=> $loguser['lastip'],
				'deleted' 	=> 0,
				'text' 		=> $msg,
				'rev' 		=> 0,
				'time' 		=> ctime(),
				'nolayout' 	=> filter_int($_POST['nolayout']),
				'nosmilies' => filter_int($_POST['nosmilies']),
				'nohtml' 	=> filter_int($_POST['nohtml']),
				'thread' 	=> $thread['id'],
				'postcur' 	=> $loguser['posts']+1,
				'posts' 	=> $loguser['posts']+1,
				'lastpost' 	=> ctime(),
				'lastview' 	=> ctime(),
				'avatar' 	=> filter_int($_POST['avatar']),
				'new'		=> 0,
				'noob'		=> 0
			);
			print "<table class='main w c'>
			<tr><td class='head' style='border-bottom: none;'>Post Preview</td></tr></table>".threadpost(array_merge($loguser,$data), false, false, true);
		}
		
		$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
		$nohtmlc 	= isset($_POST['nohtml']) 	 ? "checked" : "";
		$nolayoutc 	= isset($_POST['nolayout'])  ? "checked" : "";

		print "<a href='forum.php?id=".$forum['id']."'>".htmlspecialchars($forum['name'])."</a> - <a href='thread.php?id=".htmlspecialchars($thread['id'])."'>".htmlspecialchars($thread['name'])."</a><br>
		<form action='new.php?act=newreply&id=".$thread['id']."'  method='POST'>
			<table class='main'>
				<tr>
					<td colspan=2 class='head c'>New Reply</td>
				</tr>
				<tr>
					<td class='light' style='width: 806px; border-right: none'><textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'>".htmlspecialchars($msg)."</textarea></td>
				</tr>
				<tr>
					<td class='dim' colspan=2>
						<input type='submit' value='Submit' name='submit'>&nbsp;
						<input type='submit' value='Preview' name='preview'>&nbsp;
						<input type='checkbox' name='nohtml' value=1 $nohtmlc>Disable HTML&nbsp;
						<input type='checkbox' name='nolayout' value=1 $nolayoutc>Disable Layout&nbsp;
						<input type='checkbox' name='nosmilies' value=1 $nosmiliesc>Disable Smilies&nbsp;
						".getavatars($loguser['id'], filter_int($_POST['avatar']))."
					</td>
				</tr>
			</table>
		</form>";
		
		print minipostlist($lookup);
	
	}
	
	else if ($_GET['act'] == "newthread"){
		

		if (!$id)
			errorpage("No forum ID specified");
		
		$forum = $sql->fetchq("
			SELECT f.id, f.name, f.powerlevel, f.theme
			FROM forums AS f
			WHERE f.id = $id
		");
		
		if (!isset($forum['id']))				errorpage("Invalid forum ID");
		if (!powlcheck($forum['powerlevel']))	errorpage("You're not allowed to create threads in this restricted forum");
		if ($config['trash-id'] == $id)			errorpage("What are you doing? Stop that!");
		
		if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme'])-1;
		
		// Load previously sent or defaults		
		$name 	= isset($_POST['name']) 	? $_POST['name'] 	: "";
		$title 	= isset($_POST['title']) 	? $_POST['title'] 	: "";
		$msg 	= isset($_POST['message']) 	? $_POST['message'] : "";
		
		if (filter_string($_POST['icon_c'])) 	$icon = $_POST['icon_c'];
		else if (filter_string($_POST['icon'])) $icon = $_POST['icon'];
		else 									$icon = 0;

		
		if (isset($_POST['submit'])){
			
			if (!filter_string($name))
				errorpage("You have left the thread name empty!");
			
			if (!filter_string($msg))
				errorpage("You've left the message blank!");
			
			$sql->start();
			
			$newthread = $sql->prepare("INSERT INTO threads (name, title, time, forum, user, icon) VALUES (?,?,?,?,?,?)");
			
			$c[] = $sql->execute($newthread, array(input_filters($name), input_filters($title), ctime(), $forum['id'], $loguser['id'], input_filters($icon) ));
			$fid = $sql->resultq("SELECT MAX(id) FROM threads");
			
			$addreply = $sql->prepare("INSERT INTO `posts` (`text`, `time`, `thread`, `user`, `rev`, `deleted`, `nohtml`, `nosmilies`, `nolayout`, `avatar`) VALUES (?,?,?,?,?,?,?,?,?,?)");
			
			$c[] = $sql->execute($addreply, array(input_filters($msg), ctime(), $fid, $loguser['id'], 0, 0, filter_int($_POST['nohtml']), filter_int($_POST['nosmilies']), filter_int($_POST['nolayout']), filter_int($_POST['avatar']) ));

			$sql->query("UPDATE forums SET threads = (threads+1), posts=(posts+1) WHERE id = $id");
			$sql->query("UPDATE misc SET threads = threads+1, posts=(posts+1)");
			$sql->query("UPDATE users SET threads = (threads+1) WHERE id = ".$loguser['id']);
			$sql->query("UPDATE users SET posts = (posts+1), coins = coins+".$config['coins-bonus-newthread']."+".rand($config['coins-rand-min'], $config['coins-rand-max'])." WHERE id = ".$loguser['id']);
			$sql->query("INSERT INTO new_posts () VALUES ()");
			
			$pid = $sql->resultq("SELECT MAX(id) FROM posts");
			update_last_post($fid, array('id' => $pid, 'user' => $loguser['id'], 'time' => ctime(), 'forum' => $id) );

			
			if ($sql->finish($c)) header("Location: thread.php?id=$fid");//errorpage("The thread has been created.", false);
			else errorpage("Couldn't create the thread. An error occured.");
			
			
		}
		
		pageheader($forum['name']." - New Thread");
		
		if (isset($_POST['preview'])){
					
			$data = array(
				'id' 		=> $sql->resultq("SELECT MAX(id) FROM posts")+1,
				'user' 		=> $loguser['id'],
				'ip' 		=> $loguser['lastip'],
				'deleted' 	=> 0,
				'rev' 		=> 0,
				'text' 		=> $msg,
				'time' 		=> ctime(),
				'nolayout' 	=> filter_int($_POST['nolayout']),
				'nosmilies' => filter_int($_POST['nosmilies']),
				'nohtml' 	=> filter_int($_POST['nohtml']),
				'thread' 	=> $id,
				'postcur' 	=> $loguser['posts']+1,
				'posts' 	=> $loguser['posts']+1,
				'lastpost' 	=> ctime(),
				'lastview' 	=> ctime(),
				'avatar' 	=> filter_int($_POST['avatar']),
				'new'		=> 0,
				'noob'		=> 0
			);
			print "<table class='main w'>
			<tr><td class='head c' colspan=2>Thread Preview</td></tr>
			<tr><td class='light c' style='border-bottom: none'>".($icon ? "<img src='$icon'>" : "&nbsp;")."</td><td class='dim w' style='border-bottom: none'>$name".($title ? "<br><small>$title</small>" : "")."</td></tr></table>
			".threadpost(array_merge($loguser,$data), false, false, true);
		}
		
		$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
		$nohtmlc 	= isset($_POST['nohtml']) 	 ? "checked" : "";
		$nolayoutc 	= isset($_POST['nolayout'])  ? "checked" : "";
		
		
		$icons 				= getthreadicons();
		$icon_sel[$icon] 	= "checked";
		
		$icon_txt 			= "";
		$i 					= 0;

		foreach($icons as $link){
			if ($i == 10){
				$i = 0;
				$icon_txt .= "<br>";
			}
			$link 		= trim($link);
			$icon_txt  .= "<nobr><input type='radio' name='icon' value=\"$link\" ".filter_string($icon_sel[$link])."><img src='$link'></nobr>&nbsp;&nbsp;&nbsp;&nbsp;";
			$i++;
		}
		$icon_txt .= "<br>
		<nobr><input type='radio' name='icon' value=0 ".filter_string($icon_sel[0])."> None&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Custom: <input type='text' name='icon_c' value=\"".filter_string($_POST['icon_c'])."\"></nobr>";
		
		
		print "
		<a href='forum.php?id=".$forum['id']."'>".htmlspecialchars($forum['name'])."</a> - New Thread<br>
		<form action='new.php?act=newthread&id=$id'  method='POST'>
		
			<table class='main'>
				<tr>
					<td colspan=3 class='head c'>
						New Thread
					</td>
				</tr>
				
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Thread icon:</b>
					</td>
					<td class='dim'>
						$icon_txt
					</td>
				</tr>
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Name:</b>
					</td>
					<td class='dim'>
						<input style='width: 400px;' type='text' name='name' value=\"".htmlspecialchars($name)."\">
					</td>
				</tr>
				
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Title:</b>
					</td>
					<td class='dim'>
						<input style='width: 400px;' type='text' name='title' value=\"".htmlspecialchars($title)."\">
					</td>
				</tr>
				
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Post:</b>
					</td>
					<td class='light' style='border-right: none'>
						<textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'>".htmlspecialchars($msg)."</textarea>
					</td>
				</tr>
				<tr>
					<td colspan=3 class='dim'>
						<input type='submit' value='Submit' name='submit'>&nbsp;
						<input type='submit' value='Preview' name='preview'>&nbsp;
						<input type='checkbox' name='nohtml' value=1 $nohtmlc>Disable HTML&nbsp;
						<input type='checkbox' name='nolayout' value=1 $nolayoutc>Disable Layout&nbsp;
						<input type='checkbox' name='nosmilies' value=1 $nosmiliesc>Disable Smilies&nbsp;
						".getavatars($loguser['id'], filter_int($_POST['avatar']))."
				</td></tr>
			</table>
		</form>
		";
	}
	
	else if ($_GET['act'] == "newpoll"){
		
		// here it goes
		// the first time \0 is used as a separator

		if (!$id)
			errorpage("No forum ID specified");
		
		
		
		$forum = $sql->fetchq("
			SELECT f.id, f.name, f.powerlevel, f.theme
			FROM forums AS f
			WHERE f.id = $id
		");
		
		if (!isset($forum['id']))
			errorpage("Invalid forum ID");
		if (!powlcheck($forum['powerlevel']))
			errorpage("You're not allowed to create polls in this restricted forum");
		
		if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme'])-1;
		
		// Load previously sent or defaults		
		$name 		= isset($_POST['name']) 		? $_POST['name'] 			: "";
		$title 		= isset($_POST['title']) 		? $_POST['title'] 			: "";
		$msg 		= isset($_POST['message']) 		? $_POST['message'] 		: "";
		$briefing 	= isset($_POST['briefing']) 	? $_POST['briefing'] 		: "";
		$addopt 	= filter_int($_POST['addopt']) 	? intval($_POST['addopt']) 	: 1;
		
		
		
		if (filter_string($_POST['icon_c'])) 	$icon = $_POST['icon_c'];
		else if (filter_string($_POST['icon'])) $icon = $_POST['icon'];
		else 									$icon = 0;
		

		if (isset($_POST['submit'])){
			
			if (!filter_string($name))		errorpage("You have left the thread name empty!");
			if (!filter_string($title))		errorpage("You have left the question empty!");
			if (!filter_string($msg))		errorpage("You've left the message blank!");
			if (!isset($_POST['chtext']))	errorpage("You haven't specified the options!");
			
			
			$sql->start();
			
			// strike #1 for concatenation of options with the NULL value
			$title  = input_filters($title);
			$title .= "\0".input_filters($briefing);
			$title .= "\0".filter_int($_POST['multivote']);
			
			foreach($_POST['chtext'] as $i => $chtext){
				if (isset($_POST['remove'][$i]) || !$chtext) continue;
				$title .= "\0".input_filters($chtext)."\0".input_filters($_POST['chcolor'][$i]);
			}
			//errorpage("Unfinished", false);
			
			$newthread = $sql->prepare("INSERT INTO threads (name, title, time, forum, user, icon, ispoll) VALUES (?,?,?,?,?,?,1)");

			$c[] = $sql->execute($newthread, array(input_filters($name), $title, ctime(), $forum['id'], $loguser['id'], input_filters($icon) ));
			$fid = $sql->resultq("SELECT MAX(id) FROM threads");
			
			$addreply = $sql->prepare("INSERT INTO `posts` (`text`, `time`, `thread`, `user`, `rev`, `deleted`, `nohtml`, `nosmilies`, `nolayout`, `avatar`) VALUES (?,?,?,?,?,?,?,?,?,?)");
			$c[] = $sql->execute($addreply, array(input_filters($msg), ctime(), $fid, $loguser['id'], 0, 0, filter_int($_POST['nohtml']), filter_int($_POST['nosmilies']), filter_int($_POST['nolayout']), filter_int($_POST['avatar']) ));

			$sql->query("UPDATE forums SET threads = (threads+1), posts=(posts+1) WHERE id = $id");
			$sql->query("UPDATE misc SET threads = threads+1, posts=(posts+1)");
			$sql->query("UPDATE users SET threads = (threads+1) WHERE id = ".$loguser['id']);
			$sql->query("UPDATE users SET posts = (posts+1), coins = coins+".$config['coins-bonus-newthread']."+".rand($config['coins-rand-min'], $config['coins-rand-max'])." WHERE id = ".$loguser['id']);
			$sql->query("INSERT INTO new_posts () VALUES ()");
			
			$pid = $sql->resultq("SELECT MAX(id) FROM posts");
			update_last_post($fid, array('id' => $pid, 'user' => $loguser['id'], 'time' => ctime(), 'forum' => $id) );
			
			if ($sql->finish($c)) header("Location: thread.php?id=$fid");//errorpage("The poll has been created.", false);
			else errorpage("Couldn't create the poll. An error occured.");
			
			
		}
		
		$choice_txt = "";
		$choice_out = ""; // this is actually for the preview page, but might as well build this here

		$n = 1;

		if (isset($_POST['chtext'])){
			// build options from array, delete too
			foreach($_POST['chtext'] as $i => $chtext){
				if (isset($_POST['remove'][$i]) || !$chtext) continue;
				$choice_txt .= "
				Choice $n: <input name='chtext[$n]' size='30' maxlength='255' value=\"".htmlspecialchars($chtext)."\" type='text'> &nbsp;
				Color: <input name='chcolor[$n]' size='7' maxlength='25' value=\"".htmlspecialchars($_POST['chcolor'][$i])."\" type='text'> &nbsp;
				<input name='remove[$n]' value=1 type='checkbox'> Remove<br>";
				
				$choice_out .= "
				<tr>
					<td class='light' width='20%'>$chtext</td>
					<td class='dim' width='60%'><table bgcolor='".$_POST['chcolor'][$i]."' cellpadding='0' cellspacing='0' width='50%'><tr><td>&nbsp;</td></tr></table></td>
					<td class='light c' width='20%'>? votes, ??.?%</td>
				</tr>
				";
				
				$n++;
			}
		}
		
		if (isset($_POST['changeopt'])){
			// add set option number
			for ($n;$n<$addopt;$n++)
				$choice_txt .= "
				Choice $n: <input name='chtext[$n]' size='30' maxlength='255' value='' type='text'> &nbsp;
				Color: <input name='chcolor[$n]' size='7' maxlength='25' value='' type='text'> &nbsp;
				<input name='remove[$n]' value=1 type='checkbox'> Remove<br>";
		}
		$choice_txt .= "
			Choice $n: <input name='chtext[$n]' size='30' maxlength='255' value='' type='text'> &nbsp;
			Color: <input name='chcolor[$n]' size='7' maxlength='25' value='' type='text'> &nbsp;
			<input name='remove[$n]' value=1 type='checkbox'> Remove<br>";
		
		pageheader($forum['name']." - New Poll");
		
		if (isset($_POST['preview'])){

			$data = array(
				'id' 		=> $sql->resultq("SELECT MAX(id) FROM posts")+1,
				'user' 		=> $loguser['id'],
				'ip' 		=> $loguser['lastip'],
				'deleted' 	=> 0,
				'rev' 		=> 0,
				'text' 		=> $msg,
				'time' 		=> ctime(),
				'nolayout' 	=> filter_int($_POST['nolayout']),
				'nosmilies' => filter_int($_POST['nosmilies']),
				'nohtml' 	=> filter_int($_POST['nohtml']),
				'thread' 	=> $id,
				'postcur' 	=> $loguser['posts']+1,
				'posts' 	=> $loguser['posts']+1,
				'lastpost' 	=> ctime(),
				'lastview' 	=> ctime(),
				'avatar' 	=> filter_int($_POST['avatar']),
				'new'		=> 0,
				'noob'		=> 0
			);
			print "
			<table class='main w'>
				<tr><td class='head c' colspan=3>Poll Preview</td></tr>
				
				<tr><td colspan='3' class='dark c'><b>$title</b></td></tr>
				
				<tr><td class='dim fonts' colspan='3'>$briefing</td></tr>
				
				$choice_out
				
				<tr>
					<td class='dim fonts' colspan='3'>Multi-voting is ".(filter_int($_POST['multivote']) ? "enabled" : "disabled").".</td>
				</tr>
			</table>
			
			<br>
			<table class='main w'>
			<tr><td class='head c' colspan=3>Post Preview</td></tr>
			<tr><td class='light c' style='border-bottom: none'>".($icon ? "<img src='$icon'>" : "&nbsp;")."</td><td class='dim w' style='border-bottom: none' colspan=2>$name</td></tr></table>
			".threadpost(array_merge($loguser,$data), false, false, true);

		}
		
		$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
		$nohtmlc 	= isset($_POST['nohtml']) 	 ? "checked" : "";
		$nolayoutc 	= isset($_POST['nolayout'])  ? "checked" : "";
		
		
		$vote_sel[filter_int($_POST['multivote'])] = "checked";
		
		
		
		$icons = getthreadicons();
		$icon_sel[$icon] = "checked";
		
		$icon_txt 	= "";
		$i 			= 0;

		foreach($icons as $link){
			if ($i == 10){
				$i = 0;
				$icon_txt .= "<br>";
			}
			$link 		= trim($link);
			$icon_txt  .= "<nobr><input type='radio' name='icon' value=\"$link\" ".filter_string($icon_sel[$link])."><img src='$link'></nobr>&nbsp;&nbsp;&nbsp;&nbsp;";
			$i++;
		}
		$icon_txt .= "<br>
		<nobr><input type='radio' name='icon' value=0 ".filter_string($icon_sel[0])."> None&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Custom: <input type='text' name='icon_c' value=\"".filter_string($_POST['icon_c'])."\"></nobr>";
		
		
		print "
		<a href='forum.php?id=".$forum['id']."'>".htmlspecialchars($forum['name'])."</a> - New Thread<br>
		<form action='new.php?act=newpoll&id=$id'  method='POST'>
		
			<table class='main'>
				<tr>
					<td colspan=2 class='head c'>
						New Poll
					</td>
				</tr>
				
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Poll icon:</b>
					</td>
					<td class='dim'>
						$icon_txt
					</td>
				</tr>
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Poll title:</b>
					</td>
					<td class='dim'>
						<input style='width: 400px;' type='text' name='name' value=\"".htmlspecialchars($name)."\">
					</td>
				</tr>
				
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Question:</b>
					</td>
					<td class='dim'>
						<input style='width: 400px;' type='text' name='title' value=\"".htmlspecialchars($title)."\">
					</td>
				</tr>
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Briefing:</b>
					</td>
					<td class='dim'>
						<textarea name='briefing' rows='2' cols='80' wrap='virtual'>".htmlspecialchars($briefing)."</textarea>
					</td>
				</tr>
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Multi-voting:</b>
					</td>
					<td class='dim'>
						<input type='radio' name='multivote' value=0 ".filter_string($vote_sel[0]).">Disabled&nbsp;&nbsp;&nbsp;&nbsp;
						<input type='radio' name='multivote' value=1 ".filter_string($vote_sel[1]).">Enabled
					</td>
				</tr>
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Choices:</b>
					</td>
					<td class='dim'>
						$choice_txt
						<input type='submit' name='changeopt' value='Submit changes'> and show <input type='text' name='addopt' value='$addopt' size='4' maxlength='1'> options
					</td>
				</tr>
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Post:</b>
					</td>
					<td class='light' style='border-right: none'>
						<textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'>".htmlspecialchars($msg)."</textarea>
					</td>
				</tr>
				<tr>
					<td colspan=3 class='dim'>
						<input type='submit' value='Submit poll' name='submit'>&nbsp;
						<input type='submit' value='Preview poll' name='preview'>&nbsp;
						<input type='checkbox' name='nohtml' value=1 $nohtmlc>Disable HTML&nbsp;
						<input type='checkbox' name='nolayout' value=1 $nolayoutc>Disable Layout&nbsp;
						<input type='checkbox' name='nosmilies' value=1 $nosmiliesc>Disable Smilies&nbsp;
						".getavatars($loguser['id'], filter_int($_POST['avatar']))."
				</td></tr>
			</table>
		</form>
		";
	}

	else if ($_GET['act'] == 'editpost'){

		if ($loguser['editing_locked']==1)
			errorpage("Sorry, but you're not allowed to edit posts.");
		
		if (!$id)
			errorpage("No post ID specified");
		
		$pid 	= $id;
		$lookup = getthreadfrompost($pid);
		
		$tdata 	= getthreadinfo($lookup, $pid);
		
		$thread 	= $tdata[0];
		$forum 		= $tdata[1];
		$error_id 	= $tdata[2];
		
		unset($tdata);
		
		$ismod = ismod(isset($thread['forum']) ? $thread['forum'] : false);
		
		if ($error_id){
			
			// Copied from thread.php
			$username = ($loguser['id'] ? "User ID #".$loguser['id']."(".$loguser['name'].")" : "IP ".$_SERVER['REMOTE_ADDR']);
			
			$threadbug = array(
				1 => array("You're not allowed to view the thread","$username accessed restricted thread ID #$lookup", true),
				2 => array("The thread with ID #$lookup doesn't exist.", "$username accessed nonexisting thread ID #$lookup", true),
				3 => array(filter_int($_GET['pid']) ? "A post with ID #$pid does exist, but it's in an invalid thread. (ID #$lookup)" : "A thread with ID #$lookup doesn't exist, but there are posts associated with it.", "$username accessed valid posts in invalid thread ID #$lookup", false),
				4 => array("A thread with ID #$lookup does exist, but it's in an invalid forum. (ID #".$forum['id'].")", "$username accessed valid thread ID #$lookup in invalid forum ID #".$forum['id'], false),
				5 => array("There is no post in the database with ID #$pid", "$username accessed nonexisting post #$pid", true),
			);
			
			trigger_error($threadbug[$error_id][1], E_USER_NOTICE);
			

			if (!$ismod)
				errorpage("Couldn't edit the post. Either it doesn't exist or you're not allowed to view it.");
			else if ($threadbug[$error_id][2])
				errorpage($threadbug[$error_id][0]);
			
			$threadbug_txt = "<div style='text-align: center; color: yellow; padding: 3px; border: 5px dotted yellow; background: #000;'><b>Thread error: ".$threadbug[$error_id][0]."</b></div>";
		}
		
		if ($thread['closed'] && !$ismod)
			errorpage("Nyet.");
		
		if (isset($forum['theme'])) $loguser['theme'] = filter_int($forum['theme'])-1;

		
		// A copy of a massive query to fetch almost everything threadpost needs
		$post = $sql->fetchq("
			SELECT p.id, p.text, p.time, p.rev, p.user, p.deleted, p.thread, p.nohtml, p.nosmilies, p.nolayout, p.avatar, o.time rtime, p.lastedited, p.noob,
			u.head, u.sign, u.lastip ip, u.name, u.displayname, u.title, u.namecolor, u.sex, u.powerlevel, u.posts, u.lastpost, u.since, u.location, u.lastview
			FROM posts p
			LEFT JOIN users u     ON p.user = u.id
			LEFT JOIN posts_old o ON p.id = (SELECT MAX('o.id') FROM posts_old o WHERE o.pid = p.id)
			WHERE p.id = $pid
		");
		
		if (!filter_int($post['id'])) // just to make sure
			errorpage("Bad post ID. (something happened, you shouldn't see this message)");
		
		if (!$ismod){
			if ($post['user'] !== $loguser['id']) errorpage("You're not allowed to edit other people's posts.");
			if ($post['deleted']) errorpage("You can't edit deleted posts.");
			
		}
			
		$msg = isset($_POST['message']) ? $_POST['message'] : $post['text'];
		

		
		if (isset($_POST['submit'])){
			
			if (!filter_string($_POST['message']))
				errorpage("You've edited the reply to be blank!");
			
			$filtered = input_filters($msg);
			
			$sql->start();
			// Xkeeper once said about backing up posts in a different table. this bit of code does that
			$bak  = $sql->prepare("INSERT INTO `posts_old` (`pid` ,`text`, `time`, `rev`, `nohtml`, `nosmilies`, `nolayout`, `avatar`) VALUES (?,?,?,?,?,?,?,?)");
			$go[] = $sql->execute($bak, array($post['id'], $post['text'], $post['time'], $post['rev'], $post['nohtml'], $post['nosmilies'], $post['nolayout'], $post['avatar']) );
			
			// ...and THEN edit the original
			$a = $sql->prepare("
				UPDATE posts
				SET text=? ,time=? ,rev=? ,nohtml=? ,nosmilies=? ,nolayout=?, lastedited=?, avatar=?
				WHERE id = $pid
			");
			
			$_POST['nosmilies'] = filter_int($_POST['nosmilies']);
			$_POST['nohtml'] 	= filter_int($_POST['nohtml']);
			$_POST['nolayout'] 	= filter_int($_POST['nolayout']);
			$_POST['avatar'] 	= filter_int($_POST['avatar']);
			
			$go[] = $sql->execute($a, array($filtered, ctime(), $post['rev']+1, $_POST['nohtml'], $_POST['nosmilies'], $_POST['nolayout'], $loguser['id'], $_POST['avatar']) );
			
			if ($sql->finish($go)) header("Location: thread.php?pid=$pid");
			else errorpage("Couldn't edit the post.");
		}
		
		pageheader($thread['name']." - Edit Post");		
		
		if (isset($_POST['preview'])){
			
			$postids = getpostcount($post['user'], true);
			
			$data = array(
				'deleted' 	=> 0,
				'rev' 		=> $post['rev']+1,
				'text' 		=> filter_string($_POST['message']),
				'nolayout' 	=> filter_int($_POST['nolayout']),
				'nosmilies' => filter_int($_POST['nosmilies']),
				'nohtml' 	=> filter_int($_POST['nohtml']),
				'thread' 	=> $thread['id'],
				'postcur' 	=> array_search($post['id'], $postids[$post['user']])+1,
				'crev' 		=> $post['rev']+1,
				'rtime' 	=> ctime(),
				'lastedited'=> $loguser['id'],
				'avatar'	=> filter_int($_POST['avatar']),
				'new'		=> 0
			);
			print "<table class='main w'>
			<tr><td class='head c' style='border-bottom: none'>Post Preview</td></tr></table>".threadpost(array_merge($post,$data), false, false, true);
			
			// Moved here [0.06]
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

		print "
		<a href='forum.php?id=".$forum['id']."'>".htmlspecialchars($forum['name'])."</a> - <a href='thread.php?id=".htmlspecialchars($thread['id'])."'>".htmlspecialchars($thread['name'])."</a><br>
		<form action='new.php?act=editpost&id=$pid'  method='POST'>
			<table class='main'>
				<tr>
					<td colspan=2 class='head c'>New Reply</td>
				</tr>
				<tr>
					<td class='light' style='width: 806px; border-right: none'><textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'>".htmlspecialchars($msg)."</textarea></td>
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
		
		
		print minipostlist($lookup);
		
	}
	
	else{
		// A suspicious action
		trigger_error("User ID #".$loguser['id']." accessed new.php with an invalid action (".$_GET['act'].") and id ($id)", E_USER_NOTICE);
		errorpage("Invalid action.");
	}

		pagefooter();
	
	
?>
	