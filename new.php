<?php
	$meta['noindex'] = true;
	
	require "lib/function.php";
	
	if 		(!isset($_GET['act']))				errorpage("No action specified.");
	else if ($isproxy)							errorpage("Fuck off.");
	else if (!$loguser['id'])					errorpage("You need to be logged in to do that.");
	else if ($isbanned)							errorpage("Banned users aren't allowed to do this.");
	else if ($loguser['editing_locked'] == 2)	errorpage("You aren't allowed to post.");
	else if (!$isadmin && $miscdata['noposts'])	errorpage("Posting has been disabled temporarily.");
	
	$id = filter_int($_GET['id']);
	
	if ($_GET['act'] == 'newreply'){

		if (!$id) errorpage("No thread ID specified");
		
		// Fetch thread data
		$lookup = $id;
		$pid 	= filter_int($_GET['pid']);
		$quote 	= filter_int($_GET['quote']);
		
		$tdata 	= getthreadinfo($lookup);
		
		$thread 	= $tdata[0];
		$forum 		= $tdata[1];
		if (!$pid)
			$pid 	= $tdata[2];
		
		unset($tdata);
		
		// Check permissions
		if ($loguser['powerlevel'] < $forum['minpowerreply']) {
			errorpage("You aren't allowed to do this!");
		}
		
		$ismod = ismod($thread['forum']);
		
		if ($thread['closed'] && !$ismod) {
			errorpage("You know, this thread is <i>probably</i> closed for a reason.");
		}
		
		if (isset($forum['theme'])) $loguser['theme'] = (int) $forum['theme'];
		
		
		// Are we quoting another post?
		if ($quote){
			$quoted = $sql->fetchq("
				SELECT p.text, u.name, t.forum
				FROM posts p
				LEFT JOIN users   u ON p.user  = u.id
				LEFT JOIN threads t ON p.thread = t.id
				WHERE p.id = $quote
			");
			
			if ($quoted && canviewforum($quoted['forum'])) {
				$msg = "[quote={$quoted['name']}]{$quoted['text']}[/quote]";
			} else {
				// errorpage("Fuck off and don't even think about trying this stunt again.");
				$msg = "";
			}
		} else {
			$msg = isset($_POST['message']) ? $_POST['message'] : "";
		}
		
		update_hits($thread['forum'], $id);
		
		/*
			Post that reply!
		*/
		if (isset($_POST['submit'])) {
			checktoken();
			
			if ($loguser['lastpost'] > ctime() - $config['post-break']) errorpage("You are posting too fast!");
			
			if (!$msg) errorpage("You've written an empty reply!");
			
			$sql->start();
			$c[] = createpost($msg, $id, $_POST['nohtml'], $_POST['nosmilies'], $_POST['nolayout'], $_POST['avatar']);
			$pid = $sql->resultq("SELECT LAST_INSERT_ID()");
			$coins = update_postcount($forum['id'], $id);

			
			update_last_post($thread['id'], [
				'id' 	=> $pid,
				'user' 	=> $loguser['id'],
				'time' 	=> ctime(),
				'forum' => $forum['id']
			]);
			
			if ($sql->finish($c)){
				setmessage("Successfully posted the reply. Gained $coins coins.");
				redirect("thread.php?pid=$pid#$pid");
			} else {
				errorpage("Couldn't post the reply.");
			}
		}
		
		pageheader($thread['name']." - New Reply");
		
		?>
		<table class='main w fonts'>
			<tr>
				<td class='light c'>
					<?php echo onlineusers(false, $id) ?>
				</td>
			</tr>
		</table>
		<?php
		
		if (isset($_POST['preview'])){
			
			$data = array(
				'id' 		=> $sql->resultq("SELECT MAX(id) FROM posts") + 1,
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
			
			$ranks 		= doranks($loguser['id'], true);
			$layouts	= loadlayouts($loguser['id'], true);
			
			?>
			<table class='main w c'>
				<tr>
					<td class='head' style='border-bottom: none;'>
						Post Preview
					</td>
				</tr>
			</table>
			<?php
			print threadpost(array_merge($loguser,$data), false, false, true);
		}
		
		$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
		$nohtmlc 	= isset($_POST['nohtml']) 	 ? "checked" : "";
		$nolayoutc 	= isset($_POST['nolayout'])  ? "checked" : "";

		
		echo "
			<a href='forum.php?id={$forum['id']}'>
				".htmlspecialchars($forum['name'])."
			</a> - <a href='thread.php?id=$id'>
				".htmlspecialchars($thread['name'])."
			</a>";
		?>
		<br>
		<form action='<?php echo "new.php?act=newreply&id=$id" ?>'  method='POST'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
			<table class='main'>
			
				<tr>
					<td colspan=2 class='head c'>
						New Reply
					</td>
				</tr>
				
				<tr>
					<td class='light' style='width: 806px; border-right: none'>
						<textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'><?php
							echo htmlspecialchars($msg)
						?></textarea>
					</td>
				</tr>
				
				<tr>
					<td class='dim' colspan=2>
						<input type='submit' value='Submit' name='submit'>&nbsp;
						<input type='submit' value='Preview' name='preview'>&nbsp;
						<input type='checkbox' name='nohtml' 	value=1 <?php echo $nohtmlc 	?>>Disable HTML&nbsp;
						<input type='checkbox' name='nolayout' 	value=1 <?php echo $nolayoutc 	?>>Disable Layout&nbsp;
						<input type='checkbox' name='nosmilies' value=1 <?php echo $nosmiliesc 	?>>Disable Smilies&nbsp;
						<?php echo getavatars($loguser['id'], filter_int($_POST['avatar'])) ?>
					</td>
				</tr>
				
			</table>
		</form>
		<?php
		
		print minipostlist($lookup);
	
	}
	
	
	else if ($_GET['act'] == "newthread"){
		
		
		if (!$id) errorpage("No forum ID specified");
		
		$forum = $sql->fetchq("
			SELECT id, name, minpowerthread, theme, pollstyle
			FROM forums
			WHERE id = $id
		");
		
		$ispoll = isset($_GET['ispoll']);
		
		// NOTE: The second line does allow to create threads "blindly". "Blindly" is in quotes as you still get a list of the previous posts.
		// So, watch out and set the correct powerlevels
		if (!isset($forum['id']))								errorpage("Invalid forum ID");
		if ($loguser['powerlevel'] < $forum['minpowerthread'])	errorpage("You're not allowed to create threads in this restricted forum.");
		if ($config['trash-id'] == $id)							errorpage("What are you doing? Stop that!");
		if ($ispoll && $forum['pollstyle'] == 1)				errorpage("No polls are allowed in this forum. Thanks for still trying though.");
		
		if (isset($forum['theme'])) $loguser['theme'] = (int) $forum['theme'];
		
		// _POST data or defaults	
		$name 		= filter_string($_POST['name']);
		$title 		= filter_string($_POST['title']);
		$msg 		= filter_string($_POST['message']);
		
		if ($ispoll) {
			$question	= filter_string($_POST['question']);
			$briefing 	= filter_string($_POST['briefing']);
			$addopt 	= filter_int($_POST['addopt']) 	? ((int) $_POST['addopt']) 	: 1;
			$vote_sel[filter_int($_POST['multivote'])] = "checked";
		}
		
		if (filter_string($_POST['icon_c'])) 	$icon = $_POST['icon_c'];
		else if (filter_string($_POST['icon'])) $icon = $_POST['icon'];
		else 									$icon = 0;

		update_hits($id);
		
		if (isset($_POST['submit'])){
			checktoken();
			
			if ($loguser['lastpost'] > ctime() - $config['post-break']) errorpage("You are posting threads too fast!");
			
			if (!$name)	errorpage("You have left the thread name empty!");
			if (!$msg)	errorpage("You've left the message blank!");
			
			if ($ispoll) {
				if (!is_array($_POST['chtext'])) errorpage("You haven't specified the options!");
			}
			
			/*
				Add thread info to the database
			*/
			$sql->start();
			
			$c[] = createthread($name, $title, $id, $icon, $ispoll);
			$tid = $sql->resultq("SELECT LAST_INSERT_ID()");
			$c[] = createpost($msg, $tid, $_POST['nohtml'], $_POST['nosmilies'], $_POST['nolayout'], $_POST['avatar']);
			$pid = $sql->resultq("SELECT LAST_INSERT_ID()");
			$c[] = $sql->queryp("
				INSERT INTO polls (thread, question, briefing, multivote)
				VALUES ($tid, ?, ?, ".filter_int($_POST['multivote']).")
			", [prepare_string($question), prepare_string($briefing)]);
			// Add choices in a loop
			$list = array_keys($_POST['chtext']);
			foreach ($list as $i){
				if (!filter_string($_POST['chtext'][$i]) || filter_int($_POST['remove'][$i])) continue;
				
				$c[] = $sql->queryp("
					INSERT INTO poll_choices (thread, name, color)
					VALUES ($tid,?,?)
				",[prepare_string($_POST['chtext'][$i]), prepare_string($_POST['chcolor'][$i])]
				);
			}
			$coins = update_postcount($id);
			
			
			update_last_post($tid, [
				'id' => $pid,
				'user' => $loguser['id'],
				'time' => ctime(),
				'forum' => $id
			]);

			
			if ($sql->finish($c)){
				setmessage("The thread has been created. Gained $coins coins.");
				redirect("thread.php?id=$tid");
			}
			else errorpage("Couldn't create the thread. An error occured.");
			
			
		}
		
		
		if ($ispoll) {
			
			/*
				Create the poll choices (and remove the blank ones)
				Note that unlike editpoll, we can safely remove the blank / removed options
			*/
			$choice_txt = "";
			$choice_out = ""; // this is actually for the preview page, but might as well build this here

			$n = 1;

			if (isset($_POST['chtext'])){
				
				foreach($_POST['chtext'] as $i => $chtext){
					
					// Delete blank options or those with "Remove" checked
					if (isset($_POST['remove'][$i]) || !$chtext) continue;
					
					// Editable option
					$choice_txt .= "
						Choice $n: <input name='chtext[$n]' size='30' maxlength='255' value=\"".htmlspecialchars($chtext)."\" type='text'> &nbsp;
						Color: <input name='chcolor[$n]' size='7' maxlength='25' value=\"".htmlspecialchars($_POST['chcolor'][$i])."\" type='text'> &nbsp;
						<input name='remove[$n]' value=1 type='checkbox'><label for='remove[$n]'>Remove</label><br>
					";
					
					// Preview of the poll option
					$choice_out .= "
					<tr>
						<td class='light' width='20%'>$chtext</td>
						<td class='dim' width='60%'>
							<table bgcolor='{$_POST['chcolor'][$i]}' cellpadding='0' cellspacing='0' width='50%'>
								<tr><td>&nbsp;</td></tr>
							</table>
						</td>
						<td class='light c' width='20%'>? votes, ??.?%</td>
					</tr>
					";
					
					$n++;
				}
			}
			
			if (isset($_POST['changeopt'])){
				// add set option number
				for ($n; $n < $addopt; $n++)
					$choice_txt .= "
					Choice $n: <input name='chtext[$n]' size='30' maxlength='255' value='' type='text'> &nbsp;
					Color: <input name='chcolor[$n]' size='7' maxlength='25' value='' type='text'> &nbsp;
					<input name='remove[$n]' value=1 type='checkbox'><label for='remove[$n]'>Remove</label><br>
				";
			}
			$choice_txt .= "
				Choice $n: <input name='chtext[$n]' size='30' maxlength='255' value='' type='text'> &nbsp;
				Color: <input name='chcolor[$n]' size='7' maxlength='25' value='' type='text'> &nbsp;
				<input name='remove[$n]' value=1 type='checkbox'><label for='remove[$n]'>Remove</label><br>
			";
				
		}
		pageheader($forum['name']." - New Thread");
		
		?>
		<table class='main w fonts'>
			<tr>
				<td class='light c'>
					<?php echo onlineusers($id) ?>
				</td>
			</tr>
		</table>
		<?php		
		
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
			
			$ranks 		= doranks($loguser['id'], true);
			$layouts	= loadlayouts($loguser['id'], true);
			
			$icon_txt	= ($icon ? "<img src='$icon'>" : "&nbsp;");
			$name_txt 	= $name.($title ? "<br><small>$title</small>" : "");
			
			if ($ispoll){
			?>
			<table class='main w'>
				<tr>
					<td class='head c' colspan=3>
						Poll Preview
					</td>
				</tr>
				
				<tr>
					<td colspan='3' class='dark c'>
						<b><?php echo $question ?></b>
					</td>
				</tr>
				
				<tr>
					<td class='dim fonts' colspan='3'>
						<?php echo $briefing ?>
					</td>
				</tr>
				
				<?php echo $choice_out ?>
				
				<tr>
					<td class='dim fonts' colspan='3'>
						<?php echo "Multi-voting is ".(filter_int($_POST['multivote']) ? "enabled" : "disabled") ?>
					</td>
				</tr>
			</table>
			<?php
			
			}
			
			?>
			<br>
			<table class='main w'>
				<tr>
					<td class='head c' colspan=2>
						Thread Preview
					</td>
				</tr>
				
				<tr>
					<td class='light c' style='border-bottom: none'>
						<?php echo $icon_txt ?>
					</td>
					<td class='dim w lh' style='border-bottom: none'>
						<?php echo $name_txt ?>
					</td>
				</tr>
			</table>
			<?php
			
			print threadpost(array_merge($loguser,$data), false, false, true);
		}
		
		// Post options
		$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
		$nohtmlc 	= isset($_POST['nohtml']) 	 ? "checked" : "";
		$nolayoutc 	= isset($_POST['nolayout'])  ? "checked" : "";
		
		// Get selected icon
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
			$icon_txt  .= "
				<nobr>
					<input type='radio' name='icon' value=\"$link\" ".filter_string($icon_sel[$link]).">
					<img src='$link'>
				</nobr>&nbsp;&nbsp;&nbsp;&nbsp;
				";
			$i++;
		}
		$icon_txt .= "
			<br>
			<nobr>
				<input type='radio' name='icon' value=0 ".filter_string($icon_sel[0])."> None&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				Custom: <input type='text' name='icon_c' style='width: 500px' value=\"".filter_string($_POST['icon_c'])."\">
			</nobr>
		";
		
		
		/*
			Do the layout
		*/
		
		// Thread type specific words
		if ($ispoll) {
			$threadtype = "poll";
			$pollurl 	= "&ispoll";
			$title_txt 	= "Question";
		} else {
			$threadtype = "thread";
			$pollurl	= "";
			$title_txt 	= "Title";
		}
		
		print "<a href='forum.php?id=$id'>".htmlspecialchars($forum['name'])."</a> - New $threadtype";
		
		?>
		<br>
		<form action='new.php?act=newthread&id=<?php echo $id.$pollurl ?>'  method='POST'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
		<table class='main'>
		
			<tr>
				<td colspan=3 class='head c'>
					New <?php echo $threadtype ?>
				</td>
			</tr>
			
			<tr>
				<td class='light c' style='width: 150px'>
					<b>Thread icon:</b>
				</td>
				<td class='dim'>
					<?php echo $icon_txt ?>
				</td>
			</tr>
			
			<tr>
				<td class='light c' style='width: 150px'>
					<b>Name:</b>
				</td>
				<td class='dim'>
					<input style='width: 400px;' type='text' name='name' value="<?php echo htmlspecialchars($name) ?>">
				</td>
			</tr>
			
			<tr>
				<td class='light c' style='width: 150px'>
					<b>Title:</b>
				</td>
				<td class='dim'>
					<input style='width: 400px;' type='text' name='title' value="<?php echo htmlspecialchars($title) ?>">
				</td>
			</tr>
			<?php
			/*
				Poll-specific fields start here
			*/
			if ($ispoll) {
				?>
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Question:</b>
					</td>
					<td class='dim'>
						<input style='width: 400px;' type='text' name='question' value="<?php echo htmlspecialchars($question) ?>">
					</td>
				</tr>
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Briefing:</b>
					</td>
					<td class='dim'>
						<textarea name='briefing' rows='2' cols='80' wrap='virtual'><?php
							echo htmlspecialchars($briefing)
						?></textarea>
					</td>
				</tr>
				
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Multi-voting:</b>
					</td>
					<td class='dim'>
						<input type='radio' name='multivote' value=0 <?php echo filter_string($vote_sel[0]) ?>>Disabled&nbsp;&nbsp;&nbsp;&nbsp;
						<input type='radio' name='multivote' value=1 <?php echo filter_string($vote_sel[1]) ?>>Enabled
					</td>
				</tr>
				
				<tr>
					<td class='light c' style='width: 150px'>
						<b>Choices:</b>
					</td>
					<td class='dim'>
						<?php echo $choice_txt ?>
						<input type='submit' name='changeopt' value='Submit changes'>&nbsp;and show
						&nbsp;<input type='text' name='addopt' value='<?php echo $addopt ?>' size='4' maxlength='1'>&nbsp;options
					</td>
				</tr>
				<?php
				
			}
			/*
				Poll-specific fields end here
			*/			
			?>
			<tr>
				<td class='light c' style='width: 150px'>
					<b>Post:</b>
				</td>
				<td class='light' style='border-right: none'>
					<textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'><?php
						echo htmlspecialchars($msg)
					?></textarea>
				</td>
			</tr>
			
			<tr>
				<td colspan=3 class='dim'>
					<input type='submit' value='Submit'  name='submit'>&nbsp;
					<input type='submit' value='Preview' name='preview'>&nbsp;
					<input type='checkbox' name='nohtml'    value=1 <?php echo $nohtmlc    ?>>Disable HTML&nbsp;
					<input type='checkbox' name='nolayout'  value=1 <?php echo $nolayoutc  ?>>Disable Layout&nbsp;
					<input type='checkbox' name='nosmilies' value=1 <?php echo $nosmiliesc ?>>Disable Smilies&nbsp;
					<?php echo getavatars($loguser['id'], filter_int($_POST['avatar'])) ?>
				</td>
			</tr>
		</table>
		
		</form>
		<?php
	}
	else if ($_GET['act'] == 'editpost'){

		if ($loguser['editing_locked'] == 1) errorpage("Sorry, but you're not allowed to edit posts.");
		if (!$id)							 errorpage("No post ID specified");
		
		/*
			Fetch thread data
		*/
		$pid 	= $id;
		$lookup = getthreadfrompost($pid);
		
		$tdata 	= getthreadinfo($lookup, $pid);
		$thread = $tdata[0];
		$forum 	= $tdata[1];
		
		unset($tdata);
		
		$ismod = ismod($thread['forum']);
		
		if ($thread['closed'] && !$ismod) errorpage("Nyet.");
		
		if (isset($forum['theme'])) $loguser['theme'] = (int) $forum['theme'];

		
		// Get everything needed to view the post
		$post = $sql->fetchq("
			SELECT 	p.id, p.text, p.time, p.rev, p.user, p.deleted, p.thread, p.nohtml,
					p.nosmilies, p.nolayout, p.avatar, o.time rtime, p.lastedited, p.noob,
					u.lastip ip, u.name, u.displayname, u.title, u.namecolor, u.sex,
					u.powerlevel, u.posts, u.lastpost, u.since, u.location, u.lastview,
					u.rankset, u.class
			FROM posts p
			LEFT JOIN users u     ON p.user = u.id
			LEFT JOIN posts_old o ON p.id = (SELECT MAX('o.id') FROM posts_old o WHERE o.pid = p.id)
			WHERE p.id = $pid
		");
		
		// Permission checks
		if (!$ismod) {
			if ($post['user'] !== $loguser['id']) 	errorpage("You're not allowed to edit other people's posts.");
			if ($post['deleted']) 					errorpage("You can't edit deleted posts.");
		}
		
		// Load sent text or defaults
		$msg = isset($_POST['message']) ? $_POST['message'] : $post['text'];
		
		update_hits($thread['forum'], $lookup);

		
		if (isset($_POST['submit'])){
			checktoken();
			
			$msg = prepare_string($msg); // NOTE: We do this here to make the comparision work between this message and the original.
			if (!$msg) 					errorpage("You've edited the reply to be blank!");
			if ($msg == $post['text']) 	errorpage("You haven't changed the message of the post!");
			
			$sql->start();
			// Xkeeper once said about backing up posts in a different table. this bit of code does that
			$bak  = $sql->prepare("
				INSERT INTO posts_old (pid, text, time, rev, nohtml, nosmilies, nolayout, avatar) VALUES 
				(
					{$post['id']},
					?,
					{$post['time']},
					{$post['rev']},
					{$post['nohtml']},
					{$post['nosmilies']},
					{$post['nolayout']},
					{$post['avatar']}
				)
			");
			$c[] = $sql->execute($bak, [$post['text']]);
			
			// ...and THEN edit the original
			$a = $sql->prepare("
				UPDATE posts SET
					text 		= ?,
					time 		= ".ctime().",
					rev			= rev + 1,
					nohtml		= ".filter_int($_POST['nohtml']).",
					nosmilies	= ".filter_int($_POST['nosmilies']).",
					nolayout	= ".filter_int($_POST['nolayout']).",
					lastedited	= {$loguser['id']},
					avatar		= ".filter_int($_POST['avatar'])."
				WHERE id = $pid
			");
			$c[] = $sql->execute($a, [prepare_string($msg)]);
			
			if ($sql->finish($c)){
				setmessage("The post has been edited successfully!");
				redirect("thread.php?pid=$pid");
			} else {
				errorpage("Couldn't edit the post.");
			}
		}
		
		pageheader($thread['name']." - Edit Post");
		
		?>
		<table class='main w fonts'>
			<tr>
				<td class='light c'>
					<?php echo onlineusers(false, $lookup) ?>
				</td>
			</tr>
		</table>
		<?php
		
		if (isset($_POST['preview'])){
			
			$postids = getpostcount($post['user'], true);
			
			$data = array(
				'deleted' 	=> 0,
				'rev' 		=> $post['rev'] + 1,
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
			
			$ranks 		= doranks($post['user'], true);
			$layouts	= loadlayouts($post['user'], true);
			
			?>
			<table class='main w'>
				<tr>
					<td class='head c' style='border-bottom: none'>
						Post Preview
					</td>
				</tr>
			</table>
			<?php
			
			print threadpost(array_merge($post,$data), false, false, true);
			
			// Edited post options
			$nsm = filter_int($_POST['nosmilies']);
			$nht = filter_int($_POST['nohtml']);
			$nly = filter_int($_POST['nolayout']);
			$cha = filter_int($_POST['avatar']);
			
		}
		else {
			// Default post options
			$nsm = $post['nosmilies'];
			$nht = $post['nohtml'];
			$nly = $post['nolayout'];
			$cha = $post['avatar'];
		}
		
		$nosmiliesc = $nsm ? "checked" : "";
		$nohtmlc 	= $nht ? "checked" : "";
		$nolayoutc 	= $nly ? "checked" : "";

		print "
		<a href='forum.php?id={$forum['id']}'>
			".htmlspecialchars($forum['name'])."
		</a> - <a href='thread.php?id=".htmlspecialchars($thread['id'])."'>
			".htmlspecialchars($thread['name'])."
		</a>
		";
		?>
		<br>
		<form action='new.php?act=editpost&id=<?php echo $pid ?>'  method='POST'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
		<table class='main'>
			<tr>
				<td colspan=2 class='head c'>
					New Reply
				</td>
			</tr>
			
			<tr>
				<td class='light' style='width: 806px; border-right: none'>
					<textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'><?php
						echo htmlspecialchars($msg)
					?></textarea>
				</td>
			</tr>
			
			<tr>
				<td class='dim' colspan=2>
					<input type='submit' value='Submit'  name='submit'>&nbsp;
					<input type='submit' value='Preview' name='preview'>&nbsp;
					<input type='checkbox' name='nohtml'    value=1 <?php echo $nohtmlc    ?>>Disable HTML&nbsp;
					<input type='checkbox' name='nolayout'  value=1 <?php echo $nolayoutc  ?>>Disable Layout&nbsp;
					<input type='checkbox' name='nosmilies' value=1 <?php echo $nosmiliesc ?>>Disable Smilies&nbsp;
					<?php echo getavatars($post['user'], $cha) ?>
				</td>
			</tr>
		</table>
		
		</form>
		<?php
		
		
		print minipostlist($lookup);
		
	}
	
	else{
		// A suspicious action
		irc_reporter("User '{$loguser['name']}' accessed new.php with an invalid action ({$_GET['act']}) and id ($id)", 1);
		errorpage("Invalid action.");
	}

	pagefooter();
	
	
?>
	