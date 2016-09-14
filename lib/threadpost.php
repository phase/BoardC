<?php
	
	function threadpost($post, $mini = false, $merge = false, $nocontrols = false, $extra = "", $pmmode = false, $annmode = false){
		global $sql, $loguser, $config, $hacks, $sep, $ranks, $layouts, $IMG, $token, $bonusexp;
		global $isbanned, $ismod, $isadmin, $sysadmin;
		
		// Reverse post color scheme
		static $theme = false;
		$theme = ($theme == "light") ? "dim" : "light";
		
		$controls 	= "";
		$uid 		= $post['user'];
		if (!$mini)
			$postcount  = $post['posts'];
		
		if ($post['deleted']){
			/*
				Deleted post actions
			*/
			$script 		= "thread";
			$post['text'] 	= "(Post deleted)";
			$post['head'] 	= $post['sign'] = $post['noob'] = $height = $avatar = "";
			
			// Topbar
			if ($ismod)
				$controls = "
					<a href='thread.php?id={$post['thread']}&pin={$post['id']}'>Peek</a> |
					<a href='thread.php?id={$post['thread']}&hide={$post['id']}&auth=$token'>Undelete</a>
				";
		}
		else {
			/*
				Normal post actions (+ PM/announcement modes)
			*/
			if ($post['nohtml'])	 $post['text'] = htmlspecialchars($post['text']);
			if (!$post['nosmilies']) $post['text'] = dosmilies($post['text']);
			
			$post['text'] = output_filters($post['text'], false, $uid);
			
			if ($post['nolayout'] || !$loguser['showhead']) {
				$post['head'] = $post['sign'] = "";
			} else{
				$post['head'] = $layouts[$uid]['head'];
				$post['sign'] = $sep.$layouts[$uid]['sign'];
			}
			
			if (isset($post['avatar']) && is_file("userpic/$uid/".$post['avatar']))
				 $avatar = "<img src='userpic/$uid/{$post['avatar']}'>";
			else $avatar = "";
			
			// RPG Elements
			if (!$mini) {
				
				// RPG Classes can give out an exp bonus
				if (!isset($bonusexp)) {
					$bonusexp    = $sql->fetchq("SELECT id, bonus_exp FROM rpg_classes", true, PDO::FETCH_KEY_PAIR);
					$bonusexp[0] = 0;
				}
				
				$days 		= ((ctime() - $post['since']) / 86400);
				$exp 		= calcexp($postcount, $days, $bonusexp[$post['class']]);
				$level 		= calclvl($exp);
				$expleft 	= calcexpleft($exp);
				$levelbar	= drawlevelbar($level, $expleft);
			}
			/*
				Specific mode actions. Also use it to store the $script name for later
			*/
			if ($pmmode){
				$script 	= "private";
				$controls 	= "<a href='private.php?act=send&quote={$post['id']}'>Reply</a>";
			}
			else if ($annmode){
				$script 	= "announcement";
				$controls 	= "
					<a href='announcement.php?act=new&id=".filter_int($_GET['id'])."&quote={$post['id']}'>Reply</a> -
					<a href='announcement.php?act=edit&id={$post['id']}'>Edit</a>
				";
			}
			else{
				$script 	= "thread";
				
				if (!$mini){
					$postcount 	= $post['postcur']."/$postcount";
				}
				$controls  .= "<a href='thread.php?pid={$post['id']}#{$post['id']}'>Link</a> | <a href='new.php?act=newreply&id={$post['thread']}&quote={$post['id']}'>Quote</a>";
			
				if (($ismod || $post['user'] == $loguser['id']) && !$isbanned)
					$controls .= " | <a href='new.php?act=editpost&id={$post['id']}'>Edit</a>";
				
				if ($ismod){
					$controls .= "".
					" | <a href='thread.php?id={$post['thread']}&noob={$post['id']}&auth=$token'>".($post['noob'] ? "un" : "")."n00b</a>".
					" | <a href='thread.php?id={$post['thread']}&hide={$post['id']}&auth=$token'>Delete</a>";
				}
			}
			
			$height = "style='height: 220px'";
		}
		
		/*
			(mostly) Common actions
		*/
		if ($sysadmin && !$pmmode){
			if ($annmode) 	$controls .= " - <a class='danger' href='announcement.php?id=".filter_int($_GET['id'])."&del={$post['id']}'>Delete</a>";
			else			$controls .= " | <a class='danger' href='thread.php?id={$post['thread']}&del={$post['id']}'>Erase post</a>";
		}
		
		if ($isadmin) {
			$controls .= " | IP: <a href='admin-ipsearch.php?ip={$post['ip']}'>{$post['ip']}</a>";
		}
		
		$controls .= " | ID: ".$post['id'];
		
		/*
			Date/Revision text
			($extra is the currently used for adding the thread name text in Threads by User)
		*/
		if (filter_int($post['rev'])){
			
			if (!isset($post['crev'])) $post['crev'] = $post['rev']; // imply max revision if it isn't set
			
			$annoucement_fid = $annmode ? "&id=".filter_int($_GET['id']) : "";

			/*
				post revision jump
			*/
			if ($ismod){
				for($i = 0, $revjump = "Revision: "; $i < $post['rev']; $i++){
					$a 		  = ($post['crev'] == $i) ? "z" : "a"; 
					$revjump .= "<$a href='$script.php?pid={$post['id']}&pin={$post['id']}$annoucement_fid&rev=$i#{$post['id']}'>".($i+1)."</$a> ";
				}
				// ...
				$a 		  = ($post['crev'] == $i) ? "z" : "a"; 
				$revjump .= "<a href='$script.php?pid={$post['id']}$annoucement_fid#{$post['id']}'>".($i+1)."</a>";
			}
			else $revjump = "";
			
			$datetxt = "Posted on ".printdate($post['rtime'])."$extra Revision ".($post['crev']+1)." (Last edited by ".makeuserlink($post['lastedited']).": ".printdate($post['time']).") $revjump";
		}
		else $datetxt = "Posted on ".printdate($post['time']).$extra;
		
		/*
			Misc stuff
		*/
		
		// Checkboxes for merge thread function
		$inputmerge = $merge ? "<input type='checkbox' name='c_merge[]' value={$post['id']}>" : "";
		
		// Dirty way of clearing out controls
		if ($nocontrols) $controls = "";
		
		// 'new' status indicator
		$new = $post['new'] ? "<img src='{$IMG['statusfolder']}/new.gif'> - " : "";
		
		// Noobify post (implemented for absolutely no reason at all other than feature++). Also, more HTML from Jul.
		$noobdiv = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
		
		// Print posts
		if (isset($_GET['lol']))
			return "
				<table class='main w' style='border-top: none'><tr><td class='$theme'>
					<table style='border-spacing: 0'>
						<tr><td><b>USER:</b></td><td style='width: 10px' rowspan='10000'></td><td>".makeuserlink($uid, $post)."</td></tr>
						<tr><td valign='top'><b>MESSAGE:</b></td><td class='w'>".$post['text']."</td></tr>
					</table>
				</td></tr></table>
			";
		else if (isset($_GET['lol2']) || $hacks['failed-attempt-at-irc'])
			return "
				<table class='w' cellspacing='0'><tr><td>
				&lt;".makeuserlink($uid, $post)."&gt; {$post['text']}
				</td></tr></table>
			";
		
		else if (!$mini){
			
			// Horrible hack
			if ($post['user'] == $config['deleted-user-id']){
				return "<table class='main' id='{$post['id']}'>
					<tr>
						<td rowspan=2 class='dim c' style='vertical-align: top; background: #181818; font-size: 14px; color: #bbbbbb; padding-top: .5em;".($post['deleted'] ? "" : " min-width: 200px")."'>
							$inputmerge
							$noobdiv
								<a class='nmcol-12' href='profile.php?id={$post['user']}'>
									Deleted User
								</a>
								<br>
								<span style='letter-spacing: 0px; color: #555555; font-size: 10px;'>
									Collection of nobodies
								</span>
								
							</span>
						</td>
						<td class='dim w r fonts' style='background: #181818'>
							<table class='fonts nobr' style='margin: 0px; border-spacing: 0px;'>
								<tr>
									<td>$new$datetxt</td>
									<td class='w'></td>
									<td>$controls</td>
								</tr>
							</table>
						</td>
					</tr>
					
					<tr>
						<td class='dim' style='background: #181818;".($post['deleted'] ? "" : " height: 220px")."' valign='top' colspan='2'>
							{$post['text']}
						</td>
					</tr>
				</table>
				";
			}
			
			if (!$post['deleted']) {
				$sidebar = "
					".($post['rankset'] ? $ranks[$post['user']] : "")."<br>
					".($post['title'] ? $post['title']."<br>" : "")."
					Level: $level<br>
					$levelbar<br>
					".($avatar ? "$avatar<br>" : "")."
					Posts: $postcount<br>
					EXP: $exp<br>
					For Next: $expleft<br>
					<br>
					Since: ".printdate($post['since'], true, false)."<br>
					".($post['location'] ? "From: {$post['location']}<br>" : "")."
					<br>
					Since last post: ".($post['lastpost'] ? choosetime(ctime()-$post['lastpost']) : "None")."<br>
					Last activity: ".choosetime(ctime()-$post['lastview'])."
				";
			} else {
				$sidebar = "";
			}
			
			return "
				<table id='{$post['id']}' class='main content_$uid'>
					<tr>
						<td class='topbar1_$uid $theme' style='min-width: 200px; border-bottom: none'>
							$inputmerge$noobdiv".makeuserlink($uid, $post)."</span>
						</td>
						<td class='topbar2_$uid $theme w fonts' style='text-align: right'>
						
							<table class='fonts nobr' style='margin: 0px; border-spacing: 0px;'>
								<tr>
									<td>
										$new$datetxt
									</td>
									
									<td class='w'></td>
									
									<td>
										$controls
									</td>
								</tr>
							</table>
							
						</td>
					</tr>
					
					<tr>
						<td class='sidebar_$uid $theme fonts' valign='top'>
							$sidebar
						</td>
						<td class='mainbar_$uid $theme' valign='top' $height colspan='2'>
							".$post['head'].$post['text'].$post['sign']."
						</td>
					</tr>
				</table>
			";
		}
		else {
			return "
				<tr id='{$post['id']}'>
					<td class='head $theme' style='min-width: 200px;'>
						".makeuserlink($uid, $post)."
					</td>
					<td class='head $theme w' style='text-align: right'>
					
						<table class='fonts nobr' style='margin: 0px; border-spacing: 0px;'>
							<tr>
								<td>
									$new$datetxt
								</td>
								
								<td class='w'></td>
								
								<td>
									$controls
								</td>
							</tr>
						</table>
						
					</td>
				</tr>
				
				<tr>
					<td colspan=2 class='$theme'>
						{$post['text']}
					</td>
				</tr>
			";
		}
	}
	
	/*
		Used to display a list posts (ie: list of previous post new replies)
	*/
	function minipostlist($thread_id){
		global $loguser, $sql, $userfields;
		
		$new_check = $loguser['id'] ? "(p.time > n.user{$loguser['id']})" : "0";
		
		$posts = $sql->query("
			SELECT 	p.id, p.text, p.time, p.rev, p.user, p.deleted, p.thread, u.lastip ip,
					1 nolayout, p.nohtml, p.nosmilies, p.lastedited, p.noob,
					o.time rtime, NULL title, $userfields welpwelp, $new_check new
			
			FROM posts p
			
			LEFT JOIN users        u ON p.user   = u.id
			LEFT JOIN posts_old    o ON o.time   = (SELECT MIN(o.time) FROM posts_old o WHERE o.pid = p.id)
			LEFT JOIN threads_read n ON p.thread = n.id
			
			WHERE p.thread = $thread_id
			ORDER BY p.id DESC
			
			LIMIT {$loguser['ppp']}
		");
		
		$txt = "
			<br>
			<table class='main w'>
				<tr>
					<td colspan=2 class='dark c'>
						Latest posts in the thread:
					</td>
				</tr>";
		
		
		if ($posts) {
			for($i = 0; $post = $sql->fetch($posts); $i++) {
				$txt .= threadpost($post, true);
			}
			
			if ($i == $loguser['ppp']){
				$txt .= "
					<tr>
						<td colspan=2 class='light'>
							This is a long thread. Click <a href='thread.php?id=$thread_id'>here</a> to view it.
						</td>
					</tr>
				";
			}
		}
		else $txt .= "<tr><td class='light'>There are no posts in this thread</td></tr>";
		
		return $txt."</table>";
	}
		
	function getthreadinfo($lookup, $pid = false){
		global $sql, $ismod, $loguser;
		
		if ($lookup){
			
			
			// I don't know why there were three different queries here
			// Query redone to start from post id and allow thread controls to work on invalid threads
			// (which can have no valid thread id, with the invalid one is post.thread)
			
			$data = $sql->fetchq("
				SELECT 	p.id pid, p.thread rthread,
						t.id, t.name, t.title, t.time, t.forum, t.user, t.views, t.replies,
						t.sticky, t.closed, t.icon, t.ispoll, t.noob,
						t.lastpostid, t.lastpostuser, t.lastposttime,
						f.id fid, f.name fname, f.minpower, f.minpowerthread, f.minpowerreply,
						f.theme, f.pollstyle
				FROM posts p
				
				LEFT JOIN threads t	ON p.thread = t.id
				LEFT JOIN forums f ON t.forum = f.id
				
				WHERE p.thread = $lookup
				".($pid ? "OR p.id = $pid" : "")."
			");
			
			if (!$pid)
				$pid = $sql->resultq("SELECT id FROM posts WHERE thread = $lookup");

			if (!$pid) $pid = filter_int($data['pid']);
			
			
			if (filter_int($data['ispoll'])){
				$poll = split_null($data['title']);
				$data['title'] = $poll[0];
			}
			
			
			
			// Rebuild $forum and $thread since everything expects it this way
			
			$forum = array(
				'id'			=> filter_int($data['fid']),
				'name' 			=> filter_string($data['fname']),
				'minpower'		=> filter_int($data['minpower']),
				'minpowerthread'=> filter_int($data['minpowerthread']),
				'minpowerreply'	=> filter_int($data['minpowerreply']),
				'theme' 		=> &$data['theme'],
				'pollstyle'		=> filter_int($data['pollstyle'])
			);
			$thread = array(
				'id'			=> filter_int($data['id']),
				'name'			=> filter_string($data['name']),
				'title'			=> filter_string($data['title']),
				'time'			=> filter_int($data['time']),
				'forum'			=> filter_int($data['forum']),
				'user'			=> filter_int($data['user']),
				'views'			=> filter_int($data['views']),
				'replies'		=> filter_int($data['replies']),
				'sticky'		=> filter_int($data['sticky']),
				'closed'		=> filter_int($data['closed']),
				'rthread'		=> filter_int($data['rthread']),
				'icon'			=> filter_string($data['icon']),
				'lastpostid'	=> filter_int($data['lastpostid']),
				'lastpostuser'	=> filter_int($data['lastpostuser']),
				'lastposttime'	=> filter_int($data['lastposttime']),
				'noob'			=> filter_int($data['noob']),
				'ispoll'		=> filter_bool($data['ispoll']),
				'polldata' 		=> isset($poll) ? $poll : false
			);

			// Error Handling
			
			if 		($thread['id'] && !$forum['id'])				$error_id = 4; # Thread in bad forum
			else if (!$thread['id'] && $pid)						$error_id = 3; # post in bad thread
			else if (!$thread['id'])								$error_id = 2; # Thread doesn't exist
			else if ($forum['minpower'] > $loguser['powerlevel'])	$error_id = 1; # minpower check
			else $error_id = 0;
		}
		else{
			// Account for error id 5 (post doesn't exist)
			$error_id 	= 5;
			$thread 	= false;
			$forum 		= false;
		}
		
		if ($error_id){
				switch ($error_id){
					case 3:{
						$thread['id'] = $lookup;
						$thread['name'] = "Invalid thread #$lookup";
						$forum['name'] = "(No forum)";
						DEFINE('E_BADTHREAD', true);
						DEFINE('E_BADFORUM', true);
						
						break;
					}
					case 4:{
						$forum['id'] = $thread['forum'];
						$forum['name'] = "Invalid forum #".$thread['forum'];
						DEFINE('E_BADFORUM', true);
						
						break;
					}
//					case 1:
					case 2:{
						$thread['id'] = $lookup;
						break;
					}
//					case 5:
					default:
				}

			/*
				Thread error handler
				
				threadbug[<error id>] = [ <message shown to the user>, <message in the error log / shown to IRC>, <stop script> ];
			*/
			$username = $loguser['id'] ? "User '{$loguser['name']}' (ID #{$loguser['id']})" : "IP ".$_SERVER['REMOTE_ADDR'];
			
			$threadbug = array(
				1 => ["You're not allowed to view the thread","$username accessed restricted thread ID #$lookup", true],
				2 => ["The thread with ID #$lookup doesn't exist.", "$username accessed nonexisting thread ID #$lookup", true],
				3 => [filter_int($_GET['pid']) ? "A post with ID #$pid does exist, but it's in an invalid thread. (ID #$lookup)" : "A thread with ID #$lookup doesn't exist, but there are posts associated with it.", "$username accessed valid posts in invalid thread ID #$lookup", false],
				4 => ["A thread with ID #$lookup does exist, but it's in an invalid forum. (ID #".$forum['id'].")", "$username accessed valid thread ID #$lookup in invalid forum ID #".$forum['id'], false],
				5 => ["There is no post in the database with ID #$pid", "$username accessed nonexisting post #$pid", true]
			);
			
			trigger_error($threadbug[$error_id][1], E_USER_NOTICE);
			

			if (!$ismod){
				errorpage("Couldn't enter the thread. Either it doesn't exist or you're not allowed to view it.");
			} else if ($threadbug[$error_id][2]) {
				errorpage($threadbug[$error_id][0]);
			}
			
			// A global mod and up can view broken threads / threads in bad forums
			$GLOBALS['threadbug_txt'] = "
				<div style='text-align: center; color: yellow; padding: 3px; border: 5px dotted yellow; background: #000;'>
					<b>Thread error: {$threadbug[$error_id][0]}</b>
				</div>
			";
			
		}
		
		return array($thread, $forum, (int) $pid);
	
	}
	/*
		print a poll from the polldata data
	*/
	function poll_print($p){
		
		global $loguser, $sql, $lookup, $thread, $isadmin;
		
		// TODO: Replace this with one fetchall query using grouping?
		$votes = $sql->query("SELECT vote, user FROM poll_votes WHERE thread = $lookup");
		
		$total 	= 0;
		$votedb = array(0);
		
		while ($vote = $sql->fetch($votes)){
			$votedb[$vote['vote']] = filter_int($votedb[$vote['vote']]) + 1;
			if ($vote['user'] == $loguser['id']) $voted[$vote['vote']] = true;
			$total++;
		}
		
		//d($votedb);
		
		$max = max($votedb);
		if ($max != 0) $mul = 100/$max;
		else $mul = 0;


		$title 		= $p[0];
		$briefing 	= $p[1];
		$multivote 	= $p[2];
		
		// The elements in $p follow this order
		// $p[3] = <name of choice> $p[4] = <color> and then it loops
		// This is why you increase $i by two
		for($i=3, $n=1, $choice_out=""; isset($p[$i]); $i+=2, $n++){
			
			 // You can't vote if you're not logged in
			if (!$loguser['id']) $name = $p[$i];
			else $name = "<a href='thread.php?id={$thread['id']}&page=".filter_int($_GET['page'])."&vote=$n'>{$p[$i]}</a>";
			
			// Have we voted on this option?
			$marker = isset($voted[$n]) ? "*" : "&nbsp;";
			
			$votes_num = filter_int($votedb[$n]);
			// Division by 0
			$width = $total ? sprintf("%.1f", $votes_num / $total * 100) : '0.0';
			
			// Row with the current choice
			$choice_out .= "
			<tr>
				<td class='light'>$marker</td>
				<td class='light' width='20%'>$name</td>
				<td class='dim' width='60%'>
					<table bgcolor='{$p[$i+1]}' cellpadding='0' cellspacing='0' width='$width%'>
						<tr><td>&nbsp;</td></tr>
					</table>
				</td>
				<td class='light c' width='20%'>
					$votes_num vote".($votes_num==1 ? "" : "s").", $width%
				</td>
			</tr>
			";
		}
		
		return "
			<table class='main w'>
			
				<tr>
					<td colspan='4' class='dark c'>
						<b>$title</b>
					</td>
				</tr>
				
				<tr>
					<td class='dim fonts' colspan='4'>
						$briefing
					</td>
				</tr>
				
				$choice_out
				
				<tr>
					<td class='dim fonts' colspan='4'>
						Multi-voting is ".($multivote ? "enabled" : "disabled").
						" - $total votes in total. ".
						($isadmin ? "<a href='thread.php?id={$thread['id']}&page=".filter_int($_GET['page'])."&votes'>(View votes)</a>" : "")."
					</td>
				</tr>
				
			</table>
			<br>";
	}
	
	// I got tired of the duplicated code in new.php :|
	/*
		NOTE:
		The returned value should always be added to $c[]
	*/
	function createpost($message, $thread, &$nohtml, &$nosmilies, &$nolayout, &$avatar){
		
		global $sql, $loguser;
		
		$addreply = $sql->prepare("
			INSERT INTO posts (text, time, thread, user, rev, deleted, nohtml, nosmilies, nolayout, avatar) VALUES
			(
				?,
				".ctime().",
				".((int) $thread).",
				{$loguser['id']},
				0,
				0,
				".((int) $nohtml).",
				".((int) $nosmilies).",
				".((int) $nolayout).",
				".((int) $avatar)."
			)
		");
			
		return $sql->execute($addreply, [prepare_string($message)]);
	}
	
	function createthread($name, &$title, $forum, &$icon, $ispoll = 0){
		
		global $sql, $loguser;
		
		$sql->query("INSERT INTO threads_read () VALUES ()");
		
		$newthread = $sql->prepare("
			INSERT INTO threads (name, title, time, forum, user, icon, ispoll) VALUES
			(
				?,
				?,
				".ctime().",
				".((int) $forum).",
				{$loguser['id']},
				?,
				".((int) $ispoll)."
			)
		");
		
		return $sql->execute($newthread,[
				prepare_string($name),
				($ispoll ? $title : prepare_string($title)),
				prepare_string($icon)
			]);
	}
	
	/*
		Update forum / post / user post counts
		id 		- forum ID you want to update
		thread 	- thread ID. use it if you've created a new post, otherwise leave 0 (for new thread)
	*/
	function update_postcount($id, $thread = false){
		global $sql, $config, $loguser;
		
		$tplus = $thread ? "" : "threads = threads + 1,";

		if ($thread && !defined('E_BADTHREAD')) {
			$sql->query("UPDATE threads SET replies = replies + 1 WHERE id = $thread");
		}
		if (!defined('E_BADFORUM')) {
			$sql->query("UPDATE forums SET $tplus posts = posts + 1 WHERE id = $id");
		}
		$sql->query("UPDATE misc   SET $tplus posts = posts + 1");
		
		/*
			Update stats, then check the difference in coins
		*/
		$sql->query("
			UPDATE users SET
				$tplus 
				posts = posts + 1
			WHERE id = {$loguser['id']}
		");
		
		// Recalculate coins for difference
		return coins($loguser['posts'] + 1, (ctime() - $loguser['since']) / 86400) - $loguser['coins'];
	}
?>