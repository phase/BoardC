<?php
	// based on postsbytime.php
	require "lib/function.php";
	
	/*
		Filter user input
	*/
	$id	= filter_int($_GET['id']);
	if (!$id) errorpage("No user specified.");
	
	if (!isset($_GET['time'])) $time = 86400; // 1 day
	else $time	= filter_int($_GET['time']);
	
	/*
		Check if the user does exist
	*/
	$user = $sql->fetchq("SELECT $userfields FROM users u WHERE u.id = $id");
	if (!$user)	errorpage("Invalid user.");
	
	/*
		Get (all) posts from this user grouped by thread
	*/
	if ($time) $time_txt = "AND p.time>".(ctime()-$time);
	else $time_txt = "";
	
	$new_check = $loguser['id'] ? "(p.time > n.user{$loguser['id']})" :  "(p.time > ".(ctime()-300).")";
	
	$posts = $sql->query("
		SELECT 	COUNT(p.id) pcount, p.thread, $new_check new,
				t.id tid, t.name tname, t.forum, t.replies,
				f.id fid, f.name fname, f.minpower
		FROM posts p
		
		LEFT JOIN threads      t ON p.thread = t.id
		LEFT JOIN forums       f ON t.forum  = f.id	
		LEFT JOIN threads_read n ON p.thread = n.id
		
		WHERE p.user = $id
		$time_txt
		GROUP BY p.thread
		ORDER BY p.time DESC
	");
	

	for ($i = 1, $txt = ""; $post = $sql->fetch($posts); $i++){
		// Skip restricted or broken threads (if you're not an admin)
		
		if ($loguser['powerlevel'] < $post['minpower'] || (!$isadmin && !$post['fid'])){
			$forum 		= "(restricted forum)";
			$thread 	= "(private thread)";
			$new		= "";
			$total 		= $post['replies'] + 1;
		}
		else {
			// This value isn't specified in case of invalid threads (it would appear as blank, breaking the url even more)
			$post['forum'] = (int) $post['forum'];
			
			// We use the value in 'forums' to check if the forum is valid or not
			if (!$post['fid']) {
				$forum = "
				<a class='danger' style='background: #fff' href='forum.php?id={$post['forum']}'>
					Invalid forum ID #{$post['forum']}
				</a>";
			} else {
				$forum = "<a href='{$post['forum']}'>".htmlspecialchars($post['fname'])."</a>";
			}
			
			$new = $post['new'] ? "<img src='{$IMG['statusfolder']}/new.gif'> " : "";
			
			if (!$post['tid']){
				// Posts referencing broken thread
				$thread = "
				<a class='danger' style='background: #fff' href='thread.php?id={$post['thread']}'>
					Invalid thread ID #{$post['thread']}
				</a>";
				$total 	= "-";
			} else {
				// Everything else
				$thread = "<a href='{$post['thread']}'>".htmlspecialchars($post['tname'])."</a>";
				$total 	= $post['replies'] + 1;
			}
		}
		
		$txt .= "
			<tr>
				<td class='light c'>$i</td>
				<td class='light c'>$forum</td>
				<td class='dim'>$new$thread</td>
				<td class='dim c'>{$post['pcount']}</td>
				<td class='dim c'>$total</td>
			</tr>
		";	
	}
	
	/*
		Layout options
	*/
	$when = $time ? " during the last ".choosetime($time) : " in total";
	
	pageheader("Posts by time of day");
	
	?>
	<div class='fonts'>
		Timeframe: 
		<a href='postsbythread.php?id=<?php echo $id ?>&time=86400'>During last day</a> | 
		<a href='postsbythread.php?id=<?php echo $id ?>&time=604800'>During last week</a> | 
		<a href='postsbythread.php?id=<?php echo $id ?>&time=2592000'>During last 30 days</a> | 
		<a href='postsbythread.php?id=<?php echo $id ?>&time=31536000'>During last year</a> | 
		<a href='postsbythread.php?id=<?php echo $id ?>&time=0'>Total</a>
	</div>
	
	Posts by <?php echo makeuserlink(false, $user) ?> in threads <?php echo $when ?>:
	
	<table class='main w'>
		<tr class='c'>
			<td class='head' style='width: 30px'>&nbsp;</td>
			<td class='head' style='width: 300px'>Forum</td>
			<td class='head'>Thread</td>
			<td class='head' style='width: 70px'>Posts</td>
			<td class='head' style='width: 90px'>Thread total</td>
		</tr>
		<?php echo $txt ?>
	</table>
	<?php
	
	pagefooter();
?>