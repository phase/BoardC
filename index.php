<?php
	require "lib/function.php";
	
	/*
		Marking (all) forums as read
	*/
	
	if ( isset($_GET['markforumread']) && $loguser['id']){
		if ( $loguser['id'] ){

			if (filter_int($_GET['forumid'])){
				$join 		= "LEFT JOIN threads t ON n.id = t.id";
				$where 		= "WHERE t.forum = ".intval($_GET['forumid']);
			}
			else{
				$join 		= "";
				$where 		= "";
			}
			
			$sql->query("UPDATE threads_read n $join SET n.user{$loguser['id']} = ".ctime()." $where");
		}
		
		//header("Location: ".(filter_int($_GET['forumid']) ? "forum.php?id=".$_GET['forumid'] : "index.php"));
		redirect("index.php");
	}	
	
	pageheader($config['board-name'], false);
	
	
	/*
		Index.php specific header
	*/

	$newuser 	= $sql->fetchq("SELECT COUNT(id) as count, MAX(id) as maxid FROM users");
	$d 			= ctime()-86400;
	
	// Birthday fluff
	
	$dayusers 		= $sql->resultq("SELECT COUNT(*) FROM (SELECT DISTINCT user FROM posts WHERE time>$d) AS N");
	$daythreads 	= $sql->resultq("SELECT COUNT(*) FROM (SELECT DISTINCT thread FROM posts WHERE time>$d) AS K");

	$p = ($dayusers==1)   ? "" : "s";
	$k = ($daythreads==1) ? "" : "s";
	
	
	$curday 	 = date("z", ctime()+$loguser['tzoff']*3600);
	$hasbirthday = $sql->resultq("SELECT 1 FROM users WHERE DAYOFYEAR(FROM_UNIXTIME(birthday)) = $curday");
	

	if ($hasbirthday){
		
		$birthdays = $sql->query("
			SELECT id, name, displayname, powerlevel, namecolor, sex, birthday
			FROM users
			WHERE DAYOFYEAR(FROM_UNIXTIME(birthday)) = $curday
		");
		
		$birthday_txt = "
		<tr>
			<td class='dim c' colspan=2>Birthdays for ".date("F j").": ";
			
		while ($x = $sql->fetch($birthdays)) {
			$birthday_txt .= makeuserlink(false, $x)." (".getyeardiff($x['birthday'],ctime()).")";
		}
		$birthday_txt .= "
			</td>
		</tr>";
		
	}
	else $birthday_txt = "";
	
	
	
	print "
	<br>
	<table class='main w fonts'>
		<tr>
			<td class='light' style='text-align: left; border-right: none;'>
				You are ".($loguser['id'] ? "logged in as ".makeuserlink($loguser['id']) : "not logged in").".</td>
			<td class='light' style='text-align: right'>
				{$newuser['count']} registered users<br>
				Latest registered user: ".makeuserlink($newuser['maxid'])."
			</td>
		</tr>
		$birthday_txt
		<tr>
			<td class='dim c' colspan=2>
				{$miscdata['threads']} threads and {$miscdata['posts']} posts in the board | $dayusers user$p active in $daythreads thread$k during the last day.
			</td>
		</tr>
		<tr>
			<td class='light c' colspan=2>".onlineusers()."</td>
		</tr>
	</table><br>
	";
	unset ($newuser, $dayusers, $daythreads, $p, $k, $d, $x, $curday, $hasbirthday);
	
	/*
		Wide PM Box for the index page
	*/
	if ($loguser['id']){
		
		
		$pmdata = $sql->fetchq("SELECT COUNT(p.id) pmcount, SUM(p.new) new FROM pms p WHERE p.userto = {$loguser['id']}");
		
		if ($pmdata['pmcount']){
			
			$pm =  $sql->fetchq("
				SELECT p.id pid, p.time, $userfields
				FROM pms p
				LEFT JOIN users u ON p.user = u.id
				WHERE p.userto = {$loguser['id']}
				ORDER by p.id DESC
			");
			
			$new 	= $pmdata['new'] ? "<img src='{$IMG['statusfolder']}/new.gif'>" : "";
			$pm_txt = "
				You have {$pmdata['pmcount']} private message".($pmdata['pmcount'] == 1 ? "" : "s")." ({$pmdata['new']} new). ".
				"<a href='private.php?act=view&id={$pm['pid']}'>Last message</a> from ".makeuserlink(false, $pm)." on ".printdate($pm['time']);
		}
		else{
			$new 	= "";
			$pm_txt = "You have no private messages.";
		}
		
		?>
		<table class='main w'>
			<tr><td class='head fonts c' colspan=2>Private messages</td></tr>
			
			<tr>
				<td class='light c'><?php echo $new ?></td>
				<td class='dim'>
					<a href='private.php'>Private messages</a> -- <?php echo $pm_txt ?>
				</td>
			</tr>
		</table><br>
		<?php
	}
	
	/*
		The query to get forums
	*/
	$hidden = $ismod ? "" : "AND hidden=0";
	$catsel = isset($_GET['cat']) ? "AND c.id=".filter_int($_GET['cat']) : "";

	// Normally I'd replace this with !f.minpower OR f.minpower <= $loguser['powerlevel']
	// But in this case it would make the WHERE clause bloated
	$querypowl = $isbanned ? 0 : $loguser['powerlevel'];
	
	// subquery replaced by a wall of LEFT JOINs, number of new posts returned
	$forums = $sql->query("
		SELECT f.id fid, f.name fname, f.title, f.hidden, f.threads, f.posts, f.category,
				f.lastpostid, f.lastpostuser, f.lastposttime,
			   c.name catname, $userfields
		FROM forums f
		
		LEFT JOIN categories   c ON f.category     = c.id
		LEFT JOIN users        u ON f.lastpostuser = u.id
		LEFT JOIN threads      t ON f.id           = t.forum
		LEFT JOIN posts        p ON p.thread       = t.id

		WHERE (f.minpower <= $querypowl AND c.minpower <= $querypowl $hidden $catsel)
		GROUP BY f.id ASC
		ORDER BY c.ord , f.ord, f.id
	");
	
	if (!$forums) {
		?>
		<table class='main w c'>
			<tr>
				<td class='dark'>
					The admin hasn't configured the forum list yet.<br>
					Please come back later.
				</td>
			</tr>
		</table>
		<?php
	} else {
		
		$new_check = $loguser['id'] ? "(t.lastposttime > n.user{$loguser['id']})" : "(t.lastposttime > ".(ctime()-300).")";
		// New posts
		$new_db = $sql->fetchq("
			SELECT t.forum, COUNT(t.id) new
			FROM threads t
			LEFT JOIN threads_read n ON t.id = n.id
			WHERE $new_check
			GROUP BY t.forum
		", true, PDO::FETCH_KEY_PAIR);
		
		$forummods = $sql->fetchq("
			SELECT f.fid, $userfields
			FROM forummods f
			LEFT JOIN users u ON f.uid = u.id
		", true, PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

		
		?>
		<table class='main w nb'>
			<?php echo doannbox() ?>
			<tr>
				<td class='head' style='width: 4%'>&nbsp;</td>
				<td class='head c'>Forum</td>
				<td class='head c' style='width: 80px'>Threads</td>
				<td class='head c' style='width: 80px'>Posts</td>
				<td class='head c nobr' style='width: 15%'>Last post</td>
			</tr>
		<?php
			
		$cat = NULL;
			
		while ($forum = $sql->fetch($forums)){
			
			/*
				Separator between categories
			*/
			if ($cat != $forum['category']){
				$cat = $forum['category'];
				print "<tr><td class='dark c b' colspan=5><a href='index.php?cat=$cat'>{$forum['catname']}</a></td></tr>";
			}
			
			/*
				Last post row
			*/
			if ($forum['lastpostid']) {
				$lastpost = "".
					printdate($forum['lastposttime'])."<br>
					<small> ".
					"by ".makeuserlink(false, $forum)." ".
					"<a href='thread.php?pid={$forum['lastpostid']}#{$forum['lastpostid']}'>
						<img src='{$IMG['getlast']}'>
					</a>
					</small>
				";
			} else {
				$lastpost = "None";
			}
			
			/*
				Get an array with a list of forum mods
			*/
			for ($i=0;isset($forummods[$forum['fid']][$i]); $i++){
				$mods[] = makeuserlink(false, $forummods[$forum['fid']][$i]);
			}
			unset($forummods[$forum['fid']]);
			
			// Number of threads with unread posts
			$new = isset($new_db[$forum['fid']]) ? "<img src='{$IMG['statusfolder']}/new.gif'><br><small>".numgfx($new_db[$forum['fid']])."</small>" : ""; 
			
			print "
			<tr>
				<td class='light c lh5'>
					$new
				</td>
				<td class='dim lh'>
					<a href='forum.php?id={$forum['fid']}'>
						{$forum['fname']}
					</a>
					<small>
						".($forum['title'] ? "<br>".$forum['title'] : "")."<br>
						".(isset($mods) ? "(Moderated by: ".implode(", ", $mods).")" : "")."
					</small>
				</td>
				<td class='light c'>
					{$forum['threads']}
				</td>
				<td class='light c'>
					{$forum['posts']}
				</td>
				<td class='dim c nobr lh'>
					$lastpost
				</td>
			</tr>";
			
			unset($mods);
		}
		
		print "</table>";
	}
	
	
	pagefooter();

?>