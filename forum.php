<?php
	
	// Workaround for forum jump with Javascript disabled
	if (isset($_POST['fjumpgo'])){
		header("Location: forum.php?id={$_POST['forumjump']}");
		die;
	}
	
	require "lib/function.php";
	
	$id 	 		= filter_int($_GET['id']);
	$user 	 		= filter_int($_GET['user']);
	
	if ($user) {
		/*
			Threads by user
		*/
		update_hits();

		$where = "
			, t.forum, f.id fid, f.name fname, f.minpower
			FROM threads t
			LEFT JOIN forums f ON t.forum  = f.id
			LEFT JOIN posts p  ON p.thread = t.id
			WHERE t.user = $user
			GROUP BY t.id DESC
		";
		
		$userdata = $sql->fetchq("SELECT name, displayname, threads, lastpost FROM users WHERE id = $user");
		
		if (!$userdata)
			errorpage("This user doesn't exist!");
		
		// Patch out title
		$forum['name'] 		= "Threads by ".($userdata['displayname'] ? $userdata['displayname'] : $userdata['name']);
		$forum['threads'] 	= $userdata['threads'];
		$announce = $newthread = "";
		pageheader($forum['name']);
		
	}
	else if ($id) {
		/*
			Normal forum view
		*/
		
		$where = "
			FROM threads t
			LEFT JOIN posts p ON p.thread = t.id
			WHERE t.forum = $id
			GROUP BY t.id DESC
		";
	
		$forum 	= $sql->fetchq("
			SELECT 	name, minpower, minpowerthread, minpowerreply,
					pollstyle, threads, theme
			FROM forums
			WHERE id = $id
		");

		if (!$isadmin){
			$outname = $loguser['id'] ? $loguser['name'] : $_SERVER['REMOTE_ADDR'];
			
			if (!$forum) {
				$error = "$outname tried to view nonexistent forum id $id";
			} else if ($forum['minpower'] && $loguser['powerlevel'] < $forum['minpower']) {
				$error = "$outname tried to view restricted forum id $id ({$forum['name']})";		
			}
			
			if (isset($error)){
				irc_reporter($error, 1);
				errorpage("Couldn't view the forum.<br>Either it doesn't exist or you don't have access to it.");
			}
		}
		
		if (!$forum) errorpage("This forum doesn't exist.");
		
		// Update online users
		update_hits($id);

		// Load special forum theme
		if (isset($forum['theme'])) $loguser['theme'] = (int) $forum['theme'];
		
		pageheader($forum['name'], true, $id);
		
		$announce 	= doannbox($id);
		// Local mod things
		$ismod 		= ismod($id);
		
		// Forum """permissions"""
		// Returns $canreply, $canthread and $canpoll (see below)
		doforumperm($forum);
		
		if ($loguser['id']) {
			$newthread = "
				<nobr>".
					($ismod 	? "<a href='announcement.php?act=new&id=$id'>New announcement</a> - " : "").
					($canpoll 	? "<a href='new.php?act=newthread&id=$id&ispoll'>{$IMG['newpoll']}</a> - ": "").
					($canthread ? "<a href='new.php?act=newthread&id=$id'>{$IMG['newthread']}</a>" : "")."
				</nobr>";
		} else {
			$newthread = "";
		}
		
	}
	else {
		errorpage("No forum ID specified.");
	}
	
	/*
		Online users and New Thread controls
	*/
	?>
	<table class='main w fonts'>
		<tr>
			<td class='light c'>
				<?php echo onlineusers($id) ?>
			</td>
		</tr>
	</table>
	
	<table class='w'>
		<tr>
			<td class='w'>
				<a href='index.php'><?php echo $config['board-name'] ?></a> - <?php echo $forum['name'] ?>
			</td>
			<td>&nbsp;</td>
			<td style='text align: right'>
				<?php echo $newthread ?>
			</td>
		</tr>
	</table>
	<?php

	/*
		List of threads
	*/
	
	$threads = $sql->query("
		SELECT 	t.id, t.name, t.title, t.time, t.user, t.views, t.replies,
				t.sticky, t.closed, t.icon, t.ispoll, t.lastpostid,
				t.lastpostuser, t.lastposttime
		$where
		
		ORDER BY t.sticky DESC, t.lastposttime DESC
		
		LIMIT ".(filter_int($_GET['page'])*$loguser['tpp']).", {$loguser['tpp']}
	");

	if (!$threads){
		if ($user) errorpage("There are no threads to show.", false);
		?>
		<table class='main w c'>
			<tr>
				<td class='light'>
					There are no threads in this forum.<br>
					Come back later
					<small><?php echo ($loguser['id'] ? " (or create a new one)" : "") ?></small>
				</td>
			</tr>
		</table>
		<?php
	}
	else{
		
		$pagectrl = dopagelist($forum['threads'], $loguser['tpp'], "forum", $user ? "user=$user" : "");
		
		// Get new posts by date
		$new_check = $loguser['id'] ? "(p.time > n.user{$loguser['id']})" : "(p.time > ".(ctime()-300).")";
		
		$new_db = $sql->fetchq("
			SELECT t.id tid, MIN(p.id) pid, SUM(p.time > user{$loguser['id']}) ncount
			FROM posts p
			
			LEFT JOIN threads      t ON p.thread = t.id
			LEFT JOIN threads_read n ON t.id     = n.id
			
			WHERE $new_check
			GROUP BY t.id
		", true, PDO::FETCH_UNIQUE | PDO::FETCH_NUM);


		print $pagectrl;
		?>
		<table class='main w'>
		<?php echo $announce ?>
			<tr>
				<td class='head c' style='width: 30px'>&nbsp;</td>
				<td colspan=2 class='head c'>Thread</td>
				<td class='head c nobr' style='width: 14%'>Started by</td>
				<td class='head c' style='width: 60px'>Replies</td>
				<td class='head c' style='width: 60px'>Views</td>
				<td class='head c nobr' style='width: 150px'>Last post</td>
			</tr>
		<?php
		
		// To hold previous value of sticky
		$c = NULL;
		
		while ($thread = $sql->fetch($threads)){
			
			// Separator between sticked threads and not
			if ($c == 1 && $c != $thread['sticky']){
				$c = $thread['sticky'];
				?><tr><td class='head fonts' colspan=7>&nbsp;</td></tr><?php
			}
			$c = $thread['sticky'];
			

			if ($thread['lastpostid']){
				$lastpost = "
					<nobr>".printdate($thread['lastposttime'])."<br>
					<small>
						<nobr> ".
							"by ".makeuserlink($thread['lastpostuser'])." ".
							"<a href='thread.php?pid={$thread['lastpostid']}#{$thread['lastpostid']}'>
								<img src='{$IMG['getlast']}'>
							</a>
						</nobr>
					</small>
				";
			} else {
				$lastpost = "None";
			}
			
			/*
				Thread status icons
			*/
			$status_name = "";
			
			if($thread['replies'] > $miscdata['threshold'])	$status_name.="hot";
			if($thread['closed']) 							$status_name.="off";
			//if($thread['new']) 								$status_name.="new";

			if (isset($new_db[$thread['id']])){
				$status_name .= "new";
				$unread_txt   = $new_db[$thread['id']][1]; // Add number of unread posts to status column
				$new = "
					<a href='thread.php?pid={$new_db[$thread['id']][0]}#{$new_db[$thread['id']][0]}'>
						<img src='{$IMG['statusfolder']}/getnew.png'>
					</a> "; // Link to newest unread post
			} else {
				$new = $unread_txt = "";
			}
			
			
			$status = $status_name ? "<img src='{$IMG['statusfolder']}/$status_name.gif'><br>".numgfx($unread_txt) : "&nbsp;";
			
			
			/*
				Powerlevel check in threads by user mode
			*/
			if ($user) {
				if (($thread['minpower'] && $loguser['powerlevel'] < $thread['minpower']) || (!$isadmin && !$thread['fid'])){
					print "<tr><td class='light c fonts' colspan='7'>(restricted)</td></tr>";
					continue;
				}
				// Text appended to the bottom of the thread title, to show the original forum
				$smalltext = "
					&nbsp;&nbsp;&nbsp;&nbsp;In ".
					"<a href='forum.php?id={$thread['forum']}'".(
						$thread['fid'] ?
						">{$thread['fname']}" :
						"class='danger' style='background: #fff'>Invalid forum ID #{$thread['forum']}").
					"</a>";
			} else {
				$smalltext = htmlspecialchars($thread['title']);
			}
			
			
			// Thread page list
			$tpagectrl = dopagelist($thread['replies']+1, $loguser['ppp'], "thread", "", $thread['id']);
			
			print "
			<tr>
				<td class='light c lh5'>
					$status
				</td>
				<td class='dim c' style='width: 40px'>
					".($thread['icon'] ? "<img src='{$thread['icon']}'>" : "")."
				</td>
				<td class='dim lh'>
					$new".($thread['ispoll'] ? "Poll: " : "")."
					<a href='thread.php?id={$thread['id']}'>
						".htmlspecialchars($thread['name'])."
					</a> $tpagectrl<br>
					<small>
						$smalltext
					</small>
				</td>
				<td class='dim c nobr lh'>
					".makeuserlink($thread['user'])."<!--<br>
					<small>
						".printdate($thread['time'])."
					</small>-->
				</td>
				<td class='light c'>
					{$thread['replies']}
				</td>
				<td class='light c'>
					{$thread['views']}
				</td>
				<td class='dim c lh'>
					$lastpost
				</td>
			</tr>";
		
		}
		
		print "</table>$pagectrl";
	}
	
	if (!$user) {
		print "<table><tr><td class='w' style='text-align: left;'>".doforumjump($id)."</td><td>$newthread</td></tr></table>";
	}
	
	pagefooter();

?>