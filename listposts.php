<?php

	require "lib/function.php";
	
	/*
		due to the lack of a posts value in the database
		getting the post number requires to fetch all the posts
		and manually count all
	*/

	// General
	$page		= filter_int($_GET['page']);
	$id			= filter_int($_GET['id']);
	$forum 		= filter_int($_GET['forum']); // Forum for posts by forum mode
	if (!$id) errorpage("No user specified.");
	
	
	
	// Defaults
	if (!isset($_GET['time'])) 	$time 	= 86400;
	else 						$time 	= filter_int($_GET['time']);
	

	$user = $sql->fetchq("SELECT $userfields, u.posts FROM users u WHERE u.id = $id");
	if (!$user) errorpage("Invalid user selected.");
	
	$txt 		= "";
	
	
	
	
	// Posts by forums mode (main selection)
	if (isset($_GET['fmode'])){

		// Postcount for each forum
		$forums = $sql->query("
			SELECT t.forum, f.id, f.name, f.minpower, f.posts, COUNT(*) rcount
			FROM posts p
			LEFT JOIN threads t ON p.thread = t.id
			LEFT JOIN forums  f ON t.forum  = f.id
			WHERE p.user = $id
			GROUP BY t.forum
		", PDO::FETCH_GROUP | PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
		
		$invalid = 0;
		foreach($forums as $forum){
			
			if (!$forum['id']){
				// Invalid forum
				$invalid += $forum['rcount'];
				continue; 
			}
			if ($forum['minpower'] && $loguser['powerlevel'] < $forum['minpower']) {
				$link = "<i>(Restricted forum)</i>";
			} else {
				$link = "<a href='listposts.php?id=$id&forum={$forum['id']}'>{$forum['name']}</a>";
			}
			
			$txt .= "
				<tr>
					<td class='dim'>$link</td>
					<td class='light c'>{$forum['rcount']}</td>
					<td class='light c'>{$forum['posts']}</td>
				</tr>
			";
		}
		// Invalid posts, this time shown only if they exist
		if ($invalid){
			$txt .= "
			<tr>
				<td class='dim'>
					".($isadmin ? "<a href='listposts.php?id=$id&invalid' class='danger' style='background: #fff'><b>Invalid</b></a>" : "<i>(Restricted forum)</i>")."
				</td>
				<td class='light'>
					$invalid
				</td>
				<td class='light'>
					-
				</td>
			</tr>";
		}
		
		pageheader("Posts by forum");
		
		?>
		<br>
		<table class='main w c'>
		
			<tr>
				<td class='head' colspan=3>
					Posts by forum - <?php echo makeuserlink(false, $user) ?>
				</td>
			</tr>
			
			<tr>
				<td class='head'>Forum</td>
				<td class='head' style='width: 120px'>Posts</td>
				<td class='head' style='width: 120px'>Forum Total</td>
			</tr>
			<?php echo $txt ?>
		</table>
		<?php
		pagefooter();
	}
	
	
	
	
	// Date filtering for the query
	if ($time) 	$time_txt = "AND p.time>".(ctime()-$time);
	else 		$time_txt = "";
	
	// Special catch for quick search of invalid posts
	if (isset($_GET['invalid'])) {
		admincheck();
		$invalid = "AND (t.forum is NULL OR p.thread IS NULL)";
	} else {
		$invalid = "";
	}
	
	$new_check = $loguser['id'] ? "(p.time > n.user{$loguser['id']})" : "(p.time > ".(ctime()-300).")";
	
	$posts = $sql->query("
		SELECT 	p.id, p.time, t.name, f.minpower, t.id tid, f.id fid,
				p.thread, t.forum, f.name fname, $new_check new
		FROM posts p
		
		LEFT JOIN threads      t ON p.thread = t.id
		LEFT JOIN forums       f ON t.forum  = f.id
		LEFT JOIN threads_read n ON t.id     = n.id
		
		WHERE p.user = $id
		$invalid
		$time_txt
		".($forum && !$invalid ? "AND t.forum = $forum" : "")."
		ORDER BY p.id DESC
		
		LIMIT ".($page*50).", 50
	");
	

	pageheader("List posts");

	
	if (!$forum) {
		$i = $user['posts'] - $page * 50; //starting post
	} else {
		$i = "-";
	}
	
	
	
	
	while ($post = $sql->fetch($posts)){
		
		//if ($forum && $post['fid'] != $forum) continue; // Invalid forum
	
		if (($post['minpower'] && $loguser['powerlevel'] < $post['minpower']) || (!$isadmin && !$post['fid'])) {
			$link = "<i>(Restricted forum)</i>";
		} else {
			
			if (!$post['tid']) {
				$post['name'] 	 = "[Invalid thread ID #{$post['thread']}]";
			} else if (!$post['fid']) {
				$post['name'] 	.= "[Invalid forum ID #{$post['forum']}]";
			}
			
			$new = $post['new'] ? "<img src='{$IMG['statusfolder']}/new.gif'> " : "";
			
			// Link to the post with the name of the thread
			$link = "
				$new
				<a ".($post['tid'] && $post['fid'] ? "" : "class='danger' style='background: #fff'")." href='thread.php?pid={$post['id']}#{$post['id']}'>
					{$post['name']}
				</a>";
		}

		$txt .= "
			<tr>
				<td class='light'>{$post['id']}</td>
				<td class='light'>$i</td>
				<td class='light nobr'>".printdate($post['time'])."</td>
				<td class='dim' style='text-align: left'>$link</td>
			</tr>
			";
		if (!$forum) $i--;
		$fname = $post['fname']; // again
		
	}
	
	
	if (!$txt){ // again
		$txt = "<tr><td class='light' colspan=4>There are no posts to show.</td></tr>";
		$realcount = 0;
	} else {
		$realcount = $sql->resultq("
			SELECT COUNT(*) rcount
			FROM posts p
			LEFT JOIN threads t ON p.thread=t.id
			WHERE p.user = $id
			$invalid
			$time_txt
			".($forum ? " AND t.forum=$forum" : "")
		); 
	}
	
	
	
	
	// Page Counter
	$extra = "";
	if ($invalid)	$extra .= "&invalid";
	if ($forum) 	$extra .= "&forum=$forum";
	$notime = "id=$id$extra";
	$extra .= "&time=$time";
	$pagectrl = dopagelist($realcount, 50, "listposts", $extra);
	
	$forumtitle = $forum ? " in ".filter_string($fname) : "";
	$when 		= $time ?  " in the last ".choosetime($time) : " in total";
	
	?>
	<div class='fonts'>
	<a href='listposts.php?<?php echo $notime ?>&time=3600'>During last hour</a> |
	<a href='listposts.php?<?php echo $notime ?>&time=86400'>During last day</a> |
	<a href='listposts.php?<?php echo $notime ?>&time=604800'>During last week</a> |
	<a href='listposts.php?<?php echo $notime ?>&time=2592000'>During last 30 days</a> |
	<a href='listposts.php?<?php echo $notime ?>&time=0'>Total</a></div>
		
	<?php 
	echo "
	Posts by ".makeuserlink(false, $user)." on the board$forumtitle$when: ($realcount posts found)<br>
	$pagectrl";
	?>
	<table class='main w c fonts'>
	
		<tr>
			<td class='head' style='width: 50px'>#</td>
			<td class='head' style='width: 50px'>Post</td>
			<td class='head' style='width: 130px'>Date</td>
			<td class='head'>Thread</td>
		</tr>
		<?php echo $txt ?>
		</table>
	<?php
	print $pagectrl;
	
	pagefooter();
	
?>