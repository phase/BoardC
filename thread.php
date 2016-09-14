<?php

	require "lib/function.php";
	

	$pid = filter_int($_GET['pid']);
	
	/*
		Get the thread ID
	*/
	if (filter_int($_GET['id'])) {
		$lookup = intval($_GET['id']);
	} else if ($pid){
		$lookup = getthreadfrompost($pid);
		if ($lookup){
			// Even shorter method to get current page
			$posts = $sql->resultq("
				SELECT COUNT(id)
				FROM posts
				WHERE thread = $lookup AND id <= $pid
			");
			$_GET['page'] = floor($posts / $loguser['ppp']);
			unset($posts);
		}
	} else {
		errorpage("No thread selected.");
	}
	
	/*
		Fetch thread data
	*/
	
	$tdata = getthreadinfo($lookup, $_GET['pid']);
	
	$thread 	= $tdata[0];
	$forum 		= $tdata[1];
	if (!$pid)
		$pid	= $tdata[2];
	
	// Point the thread ID to the value in the posts table
	// Normally this doesn't make any difference, except when the thread is invalid
	if ($thread['rthread']){
		$lookup = $_GET['id'] = $thread['rthread'];
	}
	
	unset($tdata);
	
	$ismod = ismod($thread['forum']);
	
	// online update, revised
	update_hits($thread['forum'], $lookup);
	

	if (!$isproxy) $sql->query("UPDATE threads SET views = views + 1 WHERE id = $lookup");
	
	$mergewhere			= "";
	$showmergecheckbox 	= false;
	
	if (isset($forum['theme'])) $loguser['theme'] = (int) $forum['theme'];
	
	/*
		Vote to a poll
	*/
	if (filter_int($_GET['vote'])){

		if ($thread['ispoll'] && $loguser['id']){
			
			$vote = (int) $_GET['vote'];
			
			$done = $sql->resultq("SELECT id FROM poll_votes WHERE user = {$loguser['id']} AND thread = $lookup AND vote = $vote");
			
			if ($done){ // delete your vote when clicking on something you already voted on
				$sql->query("DELETE from poll_votes WHERE id = $done");
				redirect("thread.php?id=$lookup");
			} else if (!$thread['polldata'][2]) {// multiple votes flag
				$sql->query("DELETE from poll_votes WHERE user = {$loguser['id']} AND thread = $lookup");
			}
			
			$sql->query("INSERT INTO poll_votes (user, thread, vote) VALUES ({$loguser['id']}, $lookup, $vote)");
			
		}

		redirect("thread.php?id=$lookup");
	}
	/*
		View poll votes
	*/
	else if (isset($_GET['votes'])){
		
		if (!$isadmin || !$thread['ispoll']){
			redirect("thread.php?id=$lookup");
		}
		
		$votes = $sql->query("
			SELECT p.vote, $userfields, u.icon
			FROM poll_votes p
			LEFT JOIN users u ON p.user = u.id
			WHERE p.thread = $lookup
		");
		
		$txt 	= "";
		$total 	= 0;
		$votedb = array(0);
		$txtdb 	= array("");
		
		
		while ($vote = $sql->fetch($votes)){
			$votedb[$vote['vote']] 	= filter_int($votedb[$vote['vote']]) + 1;
			$txtdb[$vote['vote']][] = makeuserlink(false, $vote, true);
			$total++;
		}
		
		// Create rows with choices and votes
		for($i=3,$n=1;isset($thread['polldata'][$i]);$i+=2,$n++)
			$txt .= "
				<tr>
					<td class='light c'><b>{$thread['polldata'][$i]}</b></td>
					<td class='dim c'>".filter_int($votedb[$n])."</td>
					<td class='dim c'>".(isset($txtdb[$n]) ? implode(", ", $txtdb[$n]) : "None")."</td>
				</tr>
			";
		
		pageheader($thread['title']." - Poll votes");
		
		?>
		<br>
		<center>
		<table class='main'>
		
			<tr><td class='head c' colspan=3>Poll votes for <b><?php echo $thread['title'] ?></b></td></tr>
			
			<tr>
				<td class='head c'>Option</td>
				<td class='head c'>Votes</td>
				<td class='head c'>Users</td>
			</tr>
			
			<?php echo $txt ?>
			
			<tr>
				<td class='dark c'><i><b>Total votes<b></i></td>
				<td class='dark c'>$total</td>
				<td class='dark c'>&nbsp;</td>
			</tr>
			
			<tr>
				<td class='light c' colspan=3>
					<a href='thread.php?id=<?php echo $lookup ?>'>
						Return to the poll
					</a>
				</td>
			</tr>
		
		</table>
		</center>
		<?php
		
		pagefooter();
		
	}

	else if ($ismod){
		
		if (isset($_GET['tmerge'])){
			/*
				This is the first part of the thread merge function
				This (currently) modifies the threadpost list so we can't move it to edithread.php
			*/
			$showmergecheckbox = true;
			$mergewhere = "
			<form method='POST' action='editthread.php?id=$lookup&tmerge'>
			<table class='main w'>
				<tr>
					<td class='head c' colspan=2>
						Check the posts you want to move, then enter the ID of the destination thread.<br>
						NOTE: Selecting another page will cancel the current selections!
					</td>
				</tr>
				
				<tr>
					<td class='light nobr' style='min-width: 200px'>
						<input type='checkbox' name='a_merge' value=1><label for='a_merge'>Move All Posts</label>
					</td>
					<td class='dim w'>
						Destination thread ID:<input type='text' name='moveto'> <input type='submit' name='domerge' value='Merge Posts'>
					</td>
				</tr>
			</table>
			";
		}
		
		// Stop this nonsense. No more delete/undelete refresh cycles.
		// Apply post actions that actually alter the db here and header() away
		
		else if (isset($_GET['noob']) && !$thread['noob']){ // If the thread is forced in noob mode, don't do anything
			checktoken(true);
			$sanityCheck = $sql->resultq("SELECT id FROM posts WHERE id = ".intval($_GET['noob'])." AND thread = $lookup");
			if ($sanityCheck){
				$sql->query("UPDATE posts SET noob = NOT noob WHERE id = ".intval($_GET['noob']));
			}
			redirect("?pid={$_GET['noob']}#{$_GET['noob']}");
		}				
		else if (isset($_GET['hide'])){ // Hide is actually delete. Delete is actually erase. CONSISTENCY!
			checktoken(true);
			$sanityCheck = $sql->resultq("SELECT id FROM posts WHERE id = ".intval($_GET['hide'])." AND thread = $lookup");
			if ($sanityCheck){
				$sql->query("UPDATE posts SET deleted = NOT deleted WHERE id = ".intval($_GET['hide']));
			}
			redirect("?pid={$_GET['hide']}#{$_GET['hide']}");
		}

		else if ($sysadmin && filter_int($_GET['del'])){
			
			$del = (int) $_GET['del'];
			
			$postdata = $sql->fetchq("SELECT id, text, user FROM posts WHERE id = $del");
			if (!$postdata) errorpage("This post doesn't exist!");
			
			// Deleting posts from the database by accident a good thing does not make. Added a confirmation prompt.
			if (isset($_POST['dokill'])){
				checktoken();
				
				$sql->start();
				
				/*
					Updates for each set:
					- replies in threads
					- posts in forums
					- posts in misc
					- posts in user
				*/
				
				$c[] = $sql->query("DELETE FROM posts WHERE id = $del");
				$c[] = $sql->query("DELETE FROM posts_old WHERE pid = $del");
				
				// Note: Negative replies can't be reached here even if we delete the starting post, as a thread with zero real posts is automatically deleted.
				$c[] = $sql->query("UPDATE threads SET replies = replies - 1 WHERE id = $lookup");
				if (!defined('E_BADFORUM')){
					$c[] = $sql->query("UPDATE forums SET posts = posts - 1 WHERE id = {$forum['id']}");
				}
				$c[] = $sql->query("UPDATE misc  SET posts = posts - 1");
				$c[] = $sql->query("UPDATE users SET posts = posts-1 WHERE id = {$postdata['user']}");
				
				if (!defined('E_BADTHREAD')){
					// As deleting threads by accident is a BAD thing, actually count the real number of posts before doing anything fancy
					$realposts = $sql->resultq("SELECT COUNT(id) FROM posts WHERE thread = $lookup");
					if (!$realposts){// we have deleted the last post, delete the thread too
						$c[] = $sql->query("DELETE FROM threads WHERE id = $lookup");
						if (!defined('E_BADFORUM')){
							$c[] = $sql->query("UPDATE forums SET threads = threads - 1 WHERE id = {$forum['id']}");
							update_last_post($forum['id'], false, true);
						}
						$c[] = $sql->query("UPDATE misc  SET threads = threads - 1");
						$c[] = $sql->query("UPDATE users SET threads = threads - 1 WHERE id = {$thread['user']}");

						$redirect = "forum.php?id={$forum['id']}";
					} else {
						update_last_post($lookup);
						update_last_post($forum['id'], false, true);
						
						$redirect = "thread.php?id=$lookup";
					}
				}
				
				$c[] = update_user_post($postdata['user']);
				
				if ($sql->finish($c)){
					setmessage("The post has been deleted.");
					redirect($redirect);
				} else {
					errorpage("An unknown error occurred while deleting the post.");
				}
			}
			
			pageheader($thread['name']." - Delete Post");
			?>
			<center>
			<form method='POST' action='thread.php?pid=<?php echo $del ?>&del=<?php echo $del ?>'>
			<input type='hidden' name='auth' value='<?php echo $token ?>'>
			
			<table class='main c'>
			
				<tr><td class='head'>Delete Post</td></tr>
				
				<tr>
					<td class='light'>
						You are about to erase this post with ID #<?php echo $del ?>.<br>
						This action will <B>PERMANENTLY DELETE</B> it from the database.<br>
						<br>
						Are you sure you want to continue?
					</td>
				</tr>
				
				<tr>
					<td class='dark'>
						<input type='submit' name='dokill' value='Yes'>&nbsp;-&nbsp;
						<a href='?pid=<?php echo "$del#$del" ?>'>No</a>
					</td>
				</tr>
			</table>
			<br>
			<table class='main w'>
				<tr><td class='dark c'>The post</td></tr>
				
				<tr>
					<td class='light'>
						<?php echo output_filters($postdata['text']) ?>
					</td>
				</tr>
			</table>
			
			</form>
			</center>
			<?php
			
			pagefooter();
		}	
	}
	
	pageheader($thread['name'], true, $forum['id']);
	
	// Returns $canreply, $canthread, $canpoll
	doforumperm($forum);
	
	// New XYZ links
	if ($thread['closed']){
		$newreply_txt = $IMG['threadclosed'].($ismod ? " - " : "");
	} else {
		$newreply_txt = "";
	}
	if ($ismod || !$thread['closed']){
		$newreply_txt .= "".
			($canpoll   ? "<a href='new.php?act=newthread&id={$forum['id']}&ispoll'>{$IMG['newpoll']}</a> - " : "").
			($canthread ? "<a href='new.php?act=newthread&id={$forum['id']}'>{$IMG['newthread']}</a> - " : "").
			($canreply  ? "<a href='new.php?act=newreply&id=$lookup'>{$IMG['newreply']}</a>" : "");
	}
	
	?>
	<table class='main w fonts'>
		<tr>
			<td class='light c'>
				<?php echo onlineusers(false, $lookup) ?>
			</td>
		</tr>
	</table>
	
	<table class='w'>
		<tr>
			<td class='w'>
				<a href='index.php'>
					<?php echo $config['board-name'] ?>
				</a> - <a href='forum.php?id=<?php echo $forum['id'] ?>'>
					<?php echo $forum['name'] ?>
				</a> - <?php echo htmlspecialchars($thread['name']) ?>
			</td>
			
			<td>&nbsp;</td>
			
			<td class='nobr' style='text align: right'>
				<?php echo $newreply_txt ?>
			</td>
		</tr>
	</table>
	<?php

	
	// Mod Thread Controls
	
	//move to editthread
	//$killthread = $sysadmin ? " | <a class='danger' href='editthread.php?id=$lookup&tkill'>Erase</a>" : "";

	
	
	if ($ismod) {
		// Disable certain links when there are valid posts in broken threads
		$w = defined('E_BADTHREAD') ? "s" : "a";
		print "
		<table class='main w fonts'>
			<tr>
				<td class='dark'>
					Moderating options: ".
					"<$w href='editthread.php?id=$lookup&act=editthread'>Edit thread</$w> | ".
					"<$w href='editthread.php?id=$lookup&tstick&auth=$token'>".($thread['sticky'] ? "Uns"  : "S")."tick</$w> | ".
					"<$w href='editthread.php?id=$lookup&tclose&auth=$token'>".($thread['closed'] ? "Open" : "Close")."</$w> | ".
					"<$w href='editthread.php?id=$lookup&tnoob&auth=$token'>". ($thread['noob']   ? "Un"   : "N")."00b</$w> | ".
					($forum['id'] == $config['trash-id'] ? "" : "<$w href='editthread.php?id=$lookup&ttrash'>Trash</$w> | ").
					"<a href='thread.php?id=$lookup&tmerge'>Merge</a>
				</td>
			</tr>
		</table>$mergewhere
		";
	} else if ($loguser['id'] == $thread['user']) {
		?>
		<table class='main w fonts'>
			<tr>
				<td class='dark'>
					Thread options:
					<a href='editthread.php?id=<?php echo $lookup ?>&tren'>Edit thread</a>
				</td>
			</tr>
		</table>
		<?php
	}
	
	
	$new_check 	= $loguser['id'] ? "(p.time > n.user{$loguser['id']})" : "(p.time > ".(ctime()-300).")";
	$noob_check = $thread['noob'] ? "1 " : "p."; // A thread marked as noob has all of its posts noobed
	
	$posts = $sql->query("
		SELECT 	p.id, p.text, p.time, p.rev, p.user, p.deleted, p.thread, p.nohtml,
				p.nosmilies, p.nolayout, p.avatar, o.time rtime, p.lastedited,
				{$noob_check}noob, $new_check new,
				u.lastip ip, u.title, $userfields temp, u.posts, u.since,
				u.location, u.lastview, u.lastpost, u.rankset, u.class
		FROM posts AS p
		
		LEFT JOIN users        u ON p.user   = u.id
		LEFT JOIN posts_old    o ON o.time   = (SELECT MIN(o.time) FROM posts_old o WHERE o.pid = p.id)
		LEFT JOIN threads_read n ON p.thread = n.id
		
		WHERE p.thread = $lookup
		ORDER BY p.id ASC
		
		LIMIT ".(filter_int($_GET['page'])*$loguser['ppp']).", {$loguser['ppp']}
	");// offset, limit
	

	if ($posts){
		
		// IDs, layouts and ranks for the current page
		$postids 	= getpostcount($lookup);
		$ranks		= doranks($lookup);
		$layouts	= loadlayouts($lookup);
			
		// Page numbers
		$pagectrl = dopagelist($thread['replies']+1, $loguser['ppp'], "thread", $showmergecheckbox ? "&tmerge" : "");

		print $pagectrl;
		 
		if ($thread['ispoll']) print poll_print($thread['polldata']);
		
		/*
			Cycle though the posts in this page
		*/
		while ($post = $sql->fetch($posts)){
			
			$post['postcur'] = array_search($post['id'], $postids[$post['user']])+1;
			
			if ($ismod){
				// Mod post actions with no alteration to the database, making them refresh-safe.
				
				// Pin post (unhides)
				if (filter_int($_GET['pin']) == $post['id']){
					$post['deleted'] = false;
				}
				
				// Get old version of post by patching extra data on top of $post
				if (isset($_GET['rev']) && filter_int($_GET['pid']) == $post['id']) {
					$oldpost = $sql->fetchq("
						SELECT text, rev crev, time, nohtml, nosmilies, nolayout, avatar
						FROM posts_old
						WHERE pid = {$post['id']} AND rev = ".filter_int($_GET['rev'])."
					");
					$post = array_replace($post, $oldpost);
					unset($oldpost);
				}
			}
			
			// Status messages
			if ($post['id'] == $pid){
				print "<span id=$pid></span>$message";
			}
			
			// To enable updating the last view date
			if ($loguser['id'] && $post['new']) $set = true;

			print threadpost($post, false, $showmergecheckbox);

		}
		print $pagectrl;
		
		// werpl
		if ($showmergecheckbox)
			print "</form>";
		
		// Update last view data
		if (isset($set)) {
			$sql->query("
				UPDATE threads_read SET
				user{$loguser['id']} = ".ctime()."
				WHERE id = $lookup
			");
		}
	}
	

	?>
	<table class='w'>
		<tr>
			<td>
				<?php echo doforumjump($forum['id']) ?>
			</td>
			<td style='text-align: right;'>
				<?php echo $newreply_txt ?>
			</td>
		</tr>
	</table>
	<?php
	
	pagefooter();

?>