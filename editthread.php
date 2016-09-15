<?php

	require "lib/function.php";
	
	$lookup = filter_int($_GET['id']);
	if (!$lookup) errorpage("No thread selected");
	
	$tdata 	= getthreadinfo($lookup);
	$thread	= $tdata[0];
	$forum 	= $tdata[1];
	$pid	= $tdata[2];
	unset($tdata);
	
	
	// Point the thread ID to the value in the posts table
	// Normally this doesn't make any difference, except when the thread is invalid
	if ($thread['rthread']){
		$lookup = $_GET['id'] = $thread['rthread'];
	}
	
	$ismod = ismod($thread['forum']);
	
	if (isset($forum['theme'])) $loguser['theme'] = (int) $forum['theme'];
	
	
	/*
		Quickmod
	*/
	if ($ismod && !defined('E_BADFORUM')){
		
		if 		(isset($_GET['tstick'])) $field = "sticky";
		else if (isset($_GET['tclose'])) $field = "closed";
		else if (isset($_GET['tnoob' ])) $field = "noob";
		else $field = "";
		
		if ($field){
			checktoken(true);
			$sql->query("UPDATE threads SET $field = NOT $field WHERE id = $lookup");
			redirect("thread.php?id=$lookup");
		}
	}
	
	/*
		Merge thread (after selecting posts)
	*/
	//c_merge[]
	
	if (isset($_POST['domerge'])){
		
		if (!$ismod) errorpage("You aren't allowed to do this!");
		
		if (isset($_GET['return'])){
			redirect("thread.php?id=$lookup");
		}
		
		$dest 		= filter_int($_POST['moveto']);
		$c_merge 	= filter_array($_POST['c_merge']);
		$all		= filter_bool($_POST['a_merge']);
		
		// This action is quite risky. Check these things to prevent "fun" stuff from happening
		if (!$dest)				errorpage("No thread ID selected.");
		if (!$c_merge && !$all)	errorpage("No posts selected.");
		
		$destforum = $sql->resultq("SELECT forum FROM threads WHERE id = $dest");
		if (!$destforum)				errorpage("The thread ID you have typed doesn't exist.");
		if (!canviewforum($destforum)) 	errorpage("The thread you have chosen is in a restricted forum!");
		
		/*
			Actually merge threads and make an attempt to fix all the broken counters
			(the only one that isn't being updated is the last post time
			for the users)
		*/
		if(isset($_POST['domerge2'])){
			checktoken();
			
			$sql->start();
			
			if ($all) {
				$x = ""; //query addendum
			} else {
				// Make sure that all values in the array are integers.
				array_map('intval', $c_merge);
				$x = "id IN (".implode(", ", $c_merge).") AND";
			}
			
			$c[] = $upd = $sql->query("UPDATE posts SET thread = $dest WHERE $x thread = $lookup");
			$affected 	= $sql->num_rows($upd);
			$remaining 	= $sql->resultq("SELECT COUNT(id) FROM posts WHERE thread = $lookup");
			update_last_post($dest);
			// we're forced to update twice to take into account how we're not updating the last post
			// like everywhere else
			update_last_post($destforum, false, true);
		
		
			// Update forum counters
			if ($all || !$remaining){
				// The thread has 0 posts: delete it
				$c[] = $sql->query("DELETE FROM threads WHERE id = $lookup");
				$c[] = $sql->query("UPDATE forums SET threads = threads - 1, posts = posts - $affected WHERE id = {$thread['forum']}");
				$c[] = $sql->query("UPDATE users  SET threads = threads - 1 WHERE id = {$thread['user']}");
				$c[] = $sql->query("UPDATE misc   SET threads = threads - 1");
				if ($thread['ispoll']){
					$c[] = $sql->query("DELETE FROM poll_votes WHERE thread = $lookup");
				}
				update_last_post($thread['forum'], false, true);
				
			} else {
				update_last_post($lookup);
				update_last_post($thread['forum'], false, true);
				// Only update the thread reply count when you don't move all the posts
				$c[] = $sql->query("UPDATE threads SET replies = replies - $affected WHERE id = $lookup");
				$c[] = $sql->query("UPDATE forums  SET posts   = posts   - $affected WHERE id = {$thread['forum']}");				
			}
			$c[] = $sql->query("UPDATE threads SET replies = replies + $affected WHERE id = $dest");
			$c[] = $sql->query("UPDATE forums  SET posts   = posts   + $affected WHERE id = $destforum");
			
			if ($sql->finish($c)){
				setmessage("$affected posts moved!");
				redirect("thread.php?id=$dest");
			} else {
				errorpage("The posts could not be moved.");
			}
		}
		
		$phide = "";
		/*
			Display the confirmation prompt
		*/
		if ($all){
			$whatposts 	= "all the posts";
			$phide 		= "<input type='hidden' name='a_merge' value=1>";
			$showposts 	= "";
			$txt_mini 	= "";
		} else {
			
			$whatposts = "these posts";
			
			
			foreach ($c_merge as $onevar){
				
				if (!is_numeric($onevar)){
					// What are you trying to do
					irc_reporter("Thread merge -- Post ID from '{$loguser['name']}' contained non int $onevar", 1);
					redirect("?sec=1");
					//userban($loguser['id'], false , false, "", "The user was banned.");
					//errorpage("Uh, nope");
				}
				// Resend the post IDs as hidden fields
				$phide .= "<input type='hidden' name='c_merge[]' value='$onevar'>";
				$filteredstuff[] = $onevar;
				
			}
			
			/*
				Display a list of posts you're about to move
			*/
			$posts = $sql->query("
				SELECT 	p.id, p.text, p.time, p.rev, p.user, p.deleted, p.thread, u.lastip ip,
						1 nolayout, p.nohtml, p.nosmilies, p.lastedited, 0 noob,
						o.time rtime, NULL title, $userfields tmp, (p.time > n.user{$loguser['id']}) new
				FROM posts p
				
				LEFT JOIN users        u ON p.user   = u.id
				LEFT JOIN threads_read n ON p.thread = n.id
				LEFT JOIN posts_old    o ON o.time   = (SELECT MIN(o.time) FROM posts_old o WHERE o.pid = p.id)
				
				WHERE p.id IN (".implode(", ", $filteredstuff).") AND thread = $lookup
			");
			
			if (!$posts){ // Assume someone has edited all the checkboxes to point to posts IDs not in the thread
				errorpage("No.");
			}
			
			$txt_mini = "
				<br>
				<table class='main w'>
					<tr>
						<td colspan=2 class='dark'>
							Posts to move:
						</td>
					</tr>
				";
			
			while($post = $sql->fetch($posts)) {
				$txt_mini .= threadpost($post, true);
			}
				
			$txt_mini .= "
				</table>
			";
			//end of copy
		}
		
		$threadname = $sql->resultq("SELECT name FROM threads WHERE id = $dest");
		pageheader("Move posts");
		
		?>
		<br>
		<center>
		<form method='POST' action='editthread.php?id=<?php echo $lookup ?>&tmerge'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
		<table class='main'>
		
			<tr><td class='head c'>Move Posts</td></tr>
			
			<tr>
				<td class='light c'>
					Are you sure you want to move <?php echo $whatposts ?> to the thread '<?php echo $threadname ?>' (ID #<?php echo $dest ?>)?
				</td>
			</tr>
			
			<tr>
				<td class='dim c'>
					<input type='hidden' name='domerge'>
					<input type='hidden' name='moveto' value='<?php echo $_POST['moveto'] ?>'>
					<?php echo $phide ?>
					<input type='submit' name='domerge2' value='Yes'>&nbsp;-&nbsp;
					<a href='thread.php?id=<?php echo $lookup ?>'>No</a>
				</td>
			</tr>
		</table>
		
		</form>
		</center>
		<?php
		print $txt_mini;
		
		pagefooter();			
	}
	
	/*
		Trash thread
	*/
	
	if (isset($_GET['ttrash'])){
		
		checktoken(true);
		
		if (!$ismod)				errorpage("You have no permission to do this!");
		if (defined('E_BADTHREAD'))	errorpage("You can't trash invalid threads.");
		
		if (!$sql->resultq("SELECT 1 FROM forums WHERE id = ".$config['trash-id'])) {
			errorpage("The trash forum id (config.php - \$config['trash-id']) is not configured properly. The forum id referenced doesn't exist.");
		}
		
		$sql->start();
		
		$c[] = $sql->query("UPDATE threads SET forum = {$config['trash-id']} WHERE id = $lookup");
		
		// Fixes for thread counters
		if (!defined('E_BADFORUM')){
			$c[] = $sql->query("
				UPDATE forums SET
					threads = threads - 1,
					posts 	= posts - ".($thread['replies']+1)."
				WHERE id = ".$thread['forum']);
			update_last_post($thread['forum'], false, true);
		}
		$c[] = $sql->query("
			UPDATE forums SET
				threads = threads + 1,
				posts = posts + ".($thread['replies']+1)."
			WHERE id = {$config['trash-id']}
		");
		update_last_post($config['trash-id'], false, true);
				
		if ($sql->finish($c)){
			setmessage("Thread trashed successfully!");
			redirect("thread.php?id=$lookup");
		} else {
			errorpage("Couldn't trash the thread.");
		}
		
	}
	
	/*
		Erase thread (from edit thread page)
	*/
	if (isset($_POST['deletethread']) && isset($_POST['submit'])){
		
		if (!$sysadmin)						errorpage("Don't you know you shouldn't play with nuclear bombs?");
		if (!$config['allow-thread-erase']) errorpage("This feature has been disabled.");
		
		
		if (isset($_POST['dokill'])){
			checktoken();
			
			$sql->start();
			
			$postcount = $sql->query("
				SELECT user, COUNT(id) diff
				FROM posts
				WHERE thread = $lookup
				GROUP BY user
			");
			
			// Fix the postcounts for each user
			$diff = 0;
			$fixc = $sql->prepare("UPDATE users SET posts = posts - ? WHERE id = ?");
			foreach ($postcount as $cnt){
				$c[] = $sql->execute($fixc, [$cnt['diff'], $cnt['user']]);
				$c[] = update_user_post($cnt['user']);
				$diff += $cnt['diff'];
			}
			
			// Delete the posts and the old revisions
			$c[] = $sql->query("
				DELETE posts, posts_old
				FROM posts
				LEFT JOIN posts_old ON posts.id = posts_old.pid
				WHERE posts.thread = $lookup
			");
			
			// Account for invalid threads / forums
			if (!defined('E_BADTHREAD')){
				$c[] = $sql->query("DELETE FROM threads WHERE id = $lookup");
				$c[] = $sql->query("UPDATE users SET threads = threads - 1 WHERE id = {$thread['user']}");
			}
			if (!defined('E_BADFORUM')){
				$c[] = $sql->query("UPDATE forums SET posts = posts - $diff, threads = threads - 1 WHERE id = ".$thread['forum']);
			}
			// and update global counters
			$c[] = $sql->query("UPDATE misc SET posts = posts - $diff, threads = threads - 1");
			
			update_last_post($forum['id'], false, true);
			
			if ($thread['ispoll']){
				$c[] = $sql->query("DELETE FROM poll_choices WHERE thread = $lookup");
				$c[] = $sql->query("DELETE FROM poll_votes WHERE thread = $lookup");
				$c[] = $sql->query("DELETE FROM polls WHERE thread = $lookup");
			}
			
			if ($sql->finish($c)){
				setmessage("The thread has been deleted");
				redirect("forum.php?id=".$forum['id']);
			} else {
				errorpage("Couldn't delete the thread.");
			}
		}
		
		pageheader($thread['name']." - Delete Thread");
		
		?>
		<center>
		<form method='POST' action='editthread.php?id=<?php echo $lookup ?>'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		<input type='hidden' name='deletethread' value='Y'>
		<input type='hidden' name='submit' value='Y'>
		
		<table class='main c'>
		
			<tr><td class='head'>Delete Thread</td></tr>
			
			<tr>
				<td class='light'>
					You are about to delete the thread '<?php echo $thread['name'] ?>'<br>
					This will <B>PERMANENTLY DELETE</B> the thread and all the posts in it.<br>
					<br>
					Are you sure you want to continue?
				</td>
			</tr>
			
			<tr>
				<td class='dark'>
					<input type='submit' name='dokill' value='Yes'>&nbsp;-&nbsp;
					<a href='?id=<?php echo $lookup ?>'>No</a>
				</td>
			</tr>
		</table>
		
		</form>
		</center>
		<?php
		pagefooter();		
	}
	
	/*
		Edit thread
	*/
	
	$id = $lookup;
	
	if (!$ismod && $loguser['id'] != $thread['user']) errorpage("You're not allowed to do this!");
	
	// Special theme
	if (isset($thread['theme'])) $loguser['theme'] = (int) $thread['theme'];
	
	
	// Load previously sent or defaults	
	$name 		= isset($_POST['name'])       ? $_POST['name']       : $thread['name'];
	$sticky 	= isset($_POST['sticky'])     ? $_POST['sticky']     : $thread['sticky'];
	$closed 	= isset($_POST['closed'])     ? $_POST['closed']     : $thread['closed'];
	$title 		= isset($_POST['title'])      ? $_POST['title']      : $thread['title'];
	$destforum 	= isset($_POST['forumjump2']) ? $_POST['forumjump2'] : $thread['forum'];
	
	if ($thread['ispoll']) {	
		$polldata   = getpollinfo($id);
		
		// The defaults for poll-specific options
		$question 	= isset($_POST['question'])   ? $_POST['question'] 	 : $polldata['question'];
		$briefing 	= isset($_POST['briefing'])   ? $_POST['briefing'] 	 : $polldata['briefing'];
		$multivote 	= isset($_POST['multivote'])  ? $_POST['multivote']  : $polldata['multivote'];
		$pollclosed	= isset($_POST['pollclosed']) ? $_POST['pollclosed'] : $polldata['closed'];
		
		$addopt 	= filter_int($_POST['addopt']) 	? intval($_POST['addopt']) 	: 1;
		
		// Count votes for poll preview
		$votes	= $sql->query("SELECT vote FROM poll_votes WHERE thread = $id");
		$total	= 0;
		while ($vote = $sql->fetch($votes)){
			$votedb[$vote['vote']] = filter_int($votedb[$vote['vote']]) + 1;
			$total++;
		}
	
		if (isset($_POST['chtext'])) {
			// Choice text and color counter
			$chtext 	= filter_array($_POST['chtext']);
			$chcolor 	= filter_array($_POST['chcolor']);
			
			/*
				This specific check is to skip over the last entry, but only if it is blank and the form has been previewed / posted.
				In this case, it always belongs to the extra option, which is then shown as a blank one with the "removed" attribute.
			*/
			
			$maxval  = max(array_keys($chtext)); // The extra option has the highest chtext ID
			if (!$chtext[$maxval]){
				unset($chtext[$maxval]);
			}
		} else {
			// Get the existing choices and group them
			$choicelist = getpollchoices($id);
			foreach($choicelist as $i => $x){
				$chtext[$i] 	= $x['name'];
				$chcolor[$i] 	= $x['color'];
			}
			
			unset($choicelist);
		}
		
		$chlist  = array_keys($chtext);
	}
	
	
	//	Get correct thread icon
	if (filter_string($_POST['icon_c'])) 	$icon = $_POST['icon_c'];
	else if (filter_string($_POST['icon'])) $icon = $_POST['icon'];
	else 									$icon = $thread['icon'];
	
	
	
	/*
		Save the changes
	*/

	if (isset($_POST['submit'])){
		
		checktoken();
		
		if (!filter_string($name, true)) 	errorpage("You have left the thread name empty!");
		
		$sql->start();
		/*
			Considering we have to handle multiple powerlevels,
			the query is built progressively
		*/
		
		// We start from the fields everybody can change
		$query = "UPDATE threads SET name = ?, title = ?, icon = ?";
		$q_values = [
			input_filters($name),
			prepare_string($title),
			prepare_string($icon)
		];
		// Only a mod can edit polls to prevent "funny" changes to the poll question
		if ($ismod && $thread['ispoll']) {
			/*
				Poll specific query
			*/
			
			if (!isset($_POST['chtext']))		errorpage("You haven't specified the options!");
			
			foreach ($chlist as $i){
				if (isset($_POST['remove'][$i]) || !$chtext[$i]){
					// Remove all the votes associated with this, then the actual choice
					$c[] = $sql->query("DELETE FROM poll_votes WHERE thread = $id AND vote = $i");
					$c[] = $sql->query("DELETE FROM poll_choices WHERE id = $i");
				} else {
					// Update and insert in a single query
					$c[] = $sql->queryp("
						INSERT INTO poll_choices (id, thread, name, color) VALUES ($i,$id,?,?)
						ON DUPLICATE KEY UPDATE name = ?, color = ?",
						[prepare_string($chtext[$i]), prepare_string($chcolor[$i]), prepare_string($chtext[$i]), prepare_string($chcolor[$i])]
					);
				}
			}
			
			$c[] = $sql->queryp("
				UPDATE polls SET
					question  = ?,
					briefing  = ?,
					multivote = ".intval($multivote).",
					closed    = ".intval($pollclosed)."
				WHERE thread = $id
			", [prepare_string($question), prepare_string($briefing)]
			);
			
		} else if ($thread['ispoll']) {
			// I guess normal users should be allowed to do this
			$c[] = $sql->query("UPDATE polls SET closed = ".intval($pollclosed)." WHERE thread = $id");
		}
		
		if ($ismod){
			
			// Are we moving the thread?
			if ($destforum != $thread['forum']){
				
				// Check if the dest thread exists and we have access to it
				$valid = $sql->resultq("
					SELECT 1 FROM forums
					WHERE id = $destforum AND minpower <= {$loguser['powerlevel']}
				");
				
				if (!$valid) errorpage("You have selected an invalid forum!");
				
				// The fix for thread counters requires this query to be separate
				$c[] = $sql->query("UPDATE threads SET forum = $destforum WHERE id = $id");
				
				// Fixes for thread counters
				if (!defined('E_BADFORUM')){
					$c[] = $sql->query("
						UPDATE forums SET
							threads = threads - 1,
							posts 	= posts - ".($thread['replies']+1)."
						WHERE id = ".$thread['forum']);
					update_last_post($thread['forum'], false, true);
				}
				$c[] = $sql->query("
					UPDATE forums SET
						threads = threads + 1,
						posts = posts + ".($thread['replies']+1)."
					WHERE id = $destforum
				");
				update_last_post($destforum, false, true);	
			}
			
			$query .= ",
			sticky = ".filter_int($_POST['sticky']).",
			closed = ".filter_int($_POST['closed']);


		}

		
		$query .= " WHERE id = $id";
		
		$c[] = $sql->queryp($query, $q_values);

		if ($sql->finish($c)){
			setmessage("Thread edited successfully!");
			redirect("thread.php?id=$id");
		} else {
			errorpage("Couldn't edit the thread. An unknown error occured.");
		}
		
	}
	
	
	/*
		Poll choices
	*/
	if ($thread['ispoll']){
		
		$choice_txt = "";
		$choice_out = ""; // this is actually for the preview page, but might as well build this here

		$i = 1;
		foreach($chlist as $n){
			
			/*
				Here we can't delete entries marked as deleted
				Instead, just remove the flag
			*/
			if (isset($_POST['remove'][$n]) || !$chtext[$n]) {
				$deleted = true;
			} else {
				$deleted = false;
			}
			
			$choice_txt .= "
			Choice $i: <input name='chtext[$n]' size='30' maxlength='255' value=\"".htmlspecialchars($chtext[$n])."\" type='text'> &nbsp;
			Color: <input name='chcolor[$n]' size='7' maxlength='25' value=\"".htmlspecialchars($chcolor[$n])."\" type='text'> &nbsp;
			<input name='remove[$n]' value=1 type='checkbox' ".($deleted ? "checked" : "")."><label for='remove[$n]'>Remove</label><br>
			";
			
			// Preview
			if (!$deleted) {
				$votes = filter_int($votedb[$n]);
				$width = $total ? sprintf("%.1f", $votes / $total * 100) : '0.0';
				$choice_out .= "
				<tr>
					<td class='light' width='20%'>
						{$chtext[$n]}
					</td>
					<td class='dim' width='60%'>
						<table bgcolor='{$chcolor[$n]}' cellpadding='0' cellspacing='0' width='$width%'>
							<tr><td>&nbsp;</td></tr>
						</table>
					</td>
					<td class='light c' width='20%'>
						$votes votes, $width%
					</td>
				</tr>
				";
			}
			
			$i++;

		}
		
		//$n++;
		// dwoiwfjeatjsekjgkjrd,hd
		$n = $sql->resultq("SELECT MAX(id) FROM poll_choices") + 1;
		
		if (isset($_POST['changeopt'])){
			// add set option number
			for (;$i < $addopt; $i++, $n++) {
				$choice_txt .= "
					Choice $i: <input name='chtext[$n]' size='30' maxlength='255' value='' type='text'> &nbsp;
					Color: <input name='chcolor[$n]' size='7' maxlength='25' value='' type='text'> &nbsp;
					<input name='remove[$n]' value=1 type='checkbox'><label for='remove[$n]'>Remove</label><br>
				";
			}
		}
		
		$choice_txt .= "
			Choice $i: <input name='chtext[$n]' size='30' maxlength='255' value='' type='text'> &nbsp;
			Color: <input name='chcolor[$n]' size='7' maxlength='25' value='' type='text'> &nbsp;
			<input name='remove[$n]' value=1 type='checkbox'><label for='remove[$n]'>Remove</label><br>
		";
	}
	
	
	pageheader($forum['name']." - Edit Thread");
	
	if (isset($_POST['preview'])){
		
		if ($thread['ispoll']) {
			// Only show the poll data here
			?>
			<br>
			<table class='main w'>
			
				<tr><td class='head c' colspan=3>Poll Preview</td></tr>
				
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
						Multi-voting is <?php echo ($multivote ? "enabled" : "disabled") ?>.
					</td>
				</tr>
			</table>
			<?php
		}
		
		$icon_txt	= ($icon ? "<img src='$icon'>" : "&nbsp;");
		$name_txt 	= $name.($title ? "<br><small>$title</small>" : "");
		
		?>
		<br>
		<table class='main w'>
		
			<tr><td class='head c' colspan=2>Thread Preview</td></tr>
			
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

	}
	
	/*
		Layout
	*/
	
	
	// Selected thread options
	$closed_sel[$closed] = "checked";
	$sticky_sel[$sticky] = "checked";
	$del_sel 		= filter_bool($_POST['deletethread']) ? "checked" : "";
	$closepoll_sel 	= filter_bool($_POST['pollclosed'])   ? "checked" : "";
	
	// Create icon list
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
		Page layout
	*/
	print "
	<a href='forum.php?id={$thread['forum']}'>
		".htmlspecialchars($forum['name'])."
	</a> - <a href='thread.php?id=$id'>
		".htmlspecialchars($thread['name'])."
	</a> - Edit Thread
	";
	?>
	<br>
	<center>
	<form action='editthread.php?&id=<?php echo $id ?>' method='POST'>
	<input type='hidden' name='auth' value='<?php echo $token ?>'>
	
		<table class='main'>
		
			<tr><td colspan=2 class='head c'>Edit Thread</td></tr>
			
			<tr>
				<td class='light c' style='width: 150px'><b>Thread name:</b></td>
				<td class='dim'>
					<input style='width: 400px;' type='text' name='name' value="<?php echo htmlspecialchars($name) ?>">
				</td>
			</tr>
			<tr>
				<td class='light c'><b>Thread title:</b></td>
				<td class='dim'>
					<input style='width: 400px;' type='text' name='title' value="<?php echo htmlspecialchars($title) ?>">
				</td>
			</tr>				
			<tr>
				<td class='light c'><b>Thread icon:</b></td>
				<td class='dim'>
					<?php echo $icon_txt ?>
				</td>
			</tr>
				
				<?php
			if ($ismod) {
					?>
			<tr>
				<td class='light c' rowspan=2>&nbsp;</td>
				<td class='dim'>
					<input type='radio' name='closed' value=0 <?php echo filter_string($closed_sel[0]) ?>> Open&nbsp; &nbsp;
					<input type='radio' name='closed' value=1 <?php echo filter_string($closed_sel[1]) ?>> Closed
				</td>
			</tr>
			<tr>
				<td class='dim'>
					<input type='radio' name='sticky' value=0 <?php echo filter_string($sticky_sel[0]) ?>> Normal&nbsp; &nbsp;
					<input type='radio' name='sticky' value=1 <?php echo filter_string($sticky_sel[1]) ?>> Sticky
				</td>
			</tr>
			
			<tr>
				<td class='light c'><b>Forum:</b></td>
				<td class='dim'>
					<?php
					echo doforumjump($destforum, true);
					if ($sysadmin && $config['allow-thread-erase']){
						?>
					<input type='checkbox' name='deletethread' value=1 <?php echo $del_sel ?>><label for='deletethread'>Delete thread</label>
						<?php
					}
					?>
				</td>
			</tr>	
				<?php
		}	
		/*
			Poll-only settings
		*/
		if ($ismod && $thread['ispoll']) {
			
			$vote_sel[$multivote] = "checked";
			// Only a mod can change poll settings
			?>
			<tr><td class='head c' colspan=2>Poll options</td></tr>
			
			<tr>
				<td class='light c'><b>Question:</b></td>
				<td class='dim'>
					<input style='width: 600px;' type='text' name='question' value="<?php echo htmlspecialchars($question) ?>">
				</td>
			</tr>
			
			<tr>
				<td class='light c'><b>Briefing:</b></td>
				<td class='dim'>
					<textarea name='briefing' rows='2' cols='80' wrap='virtual'><?php
						echo htmlspecialchars($briefing)
					?></textarea>
				</td>
			</tr>
			
			<tr>
				<td class='light c'><b>Multi-voting:</b></td>
				<td class='dim'>
					<input type='radio' name='multivote' value=0 <?php echo filter_string($vote_sel[0]) ?>>Disabled&nbsp;&nbsp;&nbsp;&nbsp;
					<input type='radio' name='multivote' value=1 <?php echo filter_string($vote_sel[1]) ?>>Enabled
				</td>
			</tr>
			
			<tr>
				<td class='light c'><b>Choices:</b></td>
				<td class='dim'>
					<?php echo $choice_txt ?>
					<input type='submit' name='changeopt' value='Submit changes'>&nbsp;and show
					&nbsp;<input type='text' name='addopt' value='<?php echo $addopt ?>' size='4' maxlength='1'>&nbsp;options
				</td>
			</tr>
		<?php
		}
			?>
			<tr>
				<td class='light c'><b>Close poll:</b></td>
				<td class='dim'>
					<input type='checkbox' name='pollclosed' value=1 <?php echo $closepoll_sel ?>><label for='pollclosed'>Close Poll</label>
				</td>
			</tr>
			<tr>
				<td colspan=2 class='dark'>
					<input type='submit' value='Edit thread' name='submit'>&nbsp;
					<input type='submit' value='Preview' name='preview'>&nbsp;
				</td>
			</tr>
		</table>
	</form>
	</center>
	<?php
		
	pagefooter();


