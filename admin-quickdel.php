<?php
	// forked from admin-deluser.php
	require "lib/function.php";

	admincheck();

	if (isset($_POST['rip'])){
		
		if (!filter_int($_POST['del'])){
			errorpage("For whatever reason, no user was selected.<br><a href='?'>Try again</a>.", false);
		}
		
		checktoken();
		
		// Here we go

		$id 		= $_POST['del'];
		
		$sql->start();
		
		// Sanity check
		$data = $sql->fetchq("SELECT id, name, lastip FROM users ORDER BY id DESC");
		if ($data['id'] != $id){
			errorpage("
				Sorry, but someone else has already killed this user. Better luck next time.<br>
				Do you want to <a href='?'>try again</a>?", false
			);	
		}
		// Deletions. ALL OF THEM THIS TIME
		$c[] = $sql->query("DELETE FROM users        WHERE id   = $id");
		$c[] = $sql->query("DELETE FROM user_avatars WHERE user = $id");
		$c[] = $sql->query("DELETE FROM users_rpg    WHERE id   = $id");
		$c[] = $sql->query("DELETE FROM poll_votes   WHERE user = $id");
		$c[] = $sql->query("DELETE FROM radar  	  WHERE user = $id OR sel = $id");
		$c[] = $sql->query("DELETE FROM ratings      WHERE userfrom = $id OR userto = $id");

		// we have to take posts_old into consideration (again). sigh
		$posts = $sql->query("SELECT id FROM posts WHERE user = $id");
		while($x = $sql->fetch($posts)){
			$c[] = $sql->query("DELETE FROM posts_old WHERE pid = {$x['id']}");
		}
		
		$c[] = $sql->query("DELETE FROM posts   WHERE user = $id");
		$c[] = $sql->query("DELETE FROM pms     WHERE user = $id OR userto = $id");
		$c[] = $sql->query("DELETE FROM threads WHERE user = $id");
		
		$c[] = $sql->query("ALTER TABLE threads_read       DROP COLUMN user$id");
		$c[] = $sql->query("ALTER TABLE announcements_read DROP COLUMN user$id");

		// Delete those invalid threads
		$chk = $sql->query("SELECT t.id, COUNT(p.id) i FROM threads t LEFT JOIN posts p ON t.id = p.thread GROUP BY t.id HAVING COUNT(p.id) = 0");
		while($x = $sql->fetch($chk)){
			$c[] = $sql->query("DELETE FROM threads WHERE id = {$x['id']}");
		}
		
		// Remove userpics
		if ($sql->finish($c)){
			foreach(glob("userpic/$id/*") as $f)
				unlink("$f");
			rmdir("userpic/$id");

			
			// And IP Ban to boot
			if (filter_int($_POST['ipban'])){
				ipban("Thanks for playing!", "Nuked latest user {$data['name']} (#$id) (IP Banned and posts deleted)", $data['lastip'], 0, true);
			} else {
				irc_reporter("Nuked latest user {$data['name']} (#$id) (Posts deleted, *NOT* IP Banned)", 1);
			}
			
			errorpage("
				User ID #$id (".$data['name'].") deleted!<br>
				Click <a href='?'>here</a> to delete more.<br>
				<br>
				When you've finished, run the two thread fix things to fix phantom pages/broken reply counts.
			");
		} else {
			errorpage("An error occured while deleting {$data['name']}");
		}
		
	}
	

	// Sanity check
	$user = $sql->fetchq("
		SELECT $userfields, u.since, u.lastip, u.lastview
		FROM users u
		WHERE u.powerlevel < 1 AND u.id != {$config['deleted-user-id']}
		ORDER BY u.id DESC
	");
	
	if (!$user || $user['id'] != $sql->resultq("SELECT MAX(id) FROM users")){
		pageheader("QuickBan");
		print adminlinkbar();
		errorpage("There are no more users that can be deleted!
		<br>If you have already deleted an user, you should use the two thread fix things <i>right now</i>.", false);
	}
	
	$list = "";
	
	// [0.25] Add links to posts and threads
	$postlist = $sql->query("
		SELECT p.id, p.time, t.id tid, t.name tname, f.id fid, f.name fname
		FROM posts p
		LEFT JOIN threads t ON p.thread = t.id
		LEFT JOIN forums  f ON t.forum  = f.id
		WHERE p.user = ".$user['id']."
		ORDER BY p.id DESC
	");
	
	for($p = 0, $post_txt = ""; $post = $sql->fetch($postlist); $p++){
		
		if (!$post['fid']) $post['fname'] = "<b class='danger'>[INVALID FORUM]</b>";
		if (!$post['tid']) $post['tname'] = "<b class='danger'>[INVALID THREAD]</b>";
		
		$post_txt .= "
			<tr>
				<td class='light fonts' style='border-right: none'>
					<a href='thread.php?pid={$post['id']}'>#{$post['id']}</a> 
				</td>
				<td class='light fonts' style='border-right: none'>
					in <a href='thread.php?id={$post['tid']}'>{$post['tname']}</a> 
				</td>
				<td class='light fonts' style='border-right: none'>
					(<a href='forum.php?id={$post['fid']}'>{$post['fname']}</a>) 
				</td>
				<td class='light fonts'>
					at ".printdate($post['time'])."
				</td>
			</tr>";
	}

	$threadlist = $sql->query("
		SELECT t.id, t.name, t.time, f.id fid, f.name fname
		FROM threads t
		LEFT JOIN forums f ON t.forum = f.id
		WHERE t.user = ".$user['id']."
		ORDER BY t.id DESC
	");
	
	for($t = 0, $thread_txt = ""; $thread = $sql->fetch($threadlist); $t++){
		
		if (!$thread['fid']) $thread['fname'] = "<b class='danger'>[INVALID FORUM]</b>";
		
		$thread_txt .= "
			<tr>
				<td class='light fonts' style='border-right: none'>
					<a href='thread.php?id=".$thread['id']."'>#".$thread['id']."</a>
				</td>
				<td class='light fonts' style='border-right: none'>
					 - <a href='thread.php?id=".$thread['id']."'>".$thread['name']."</a> 
				</td>
				<td class='light fonts' style='border-right: none'>
					(<a href='forum.php?id=".$thread['fid']."'>".$thread['fname']."</a>) 
				</td>
				<td class='light fonts'>
					at ".printdate($thread['time'])."
				</td>
			</tr>";
	}
	
	// Get last viewed page
	
	if (!$user['lastview']){
		$lastview_txt = "None";
	}
	else{
		$lazy 			= htmlspecialchars(input_filters($sql->resultq("SELECT page FROM hits WHERE user = {$user['id']}")));
		$lastview_txt 	= "$lazy, ".choosetime(ctime()-$user['lastview'])." ago";
	}
	
	
	//pageheader("GOTTA BAN FAST"); // Shoot me
	pageheader("Quick Ban Button");
	
	print adminlinkbar()."
	<form method='POST' action='admin-quickdel.php'>
	<input type='hidden' value='$token' name='auth'>
	<center><table class='main c'>
		<tr><td class='head'>Press Start Button</td></tr>
		
		<tr><td class='light'>
			By pushing The Button&trade;, you will delete the latest registered user!<br>Next up on the chopping block:
		</td></tr>
		<tr>
		<td class='dim'>
			<center><br>
			
				<table class='main c'>
					<tr>
						<td class='head' colspan='2'>User Info</td></tr>
					<tr><td class='light' style='width: 100px'><b>User</b></td><td class='light'>".makeuserlink(false, $user, true)."</td></tr>
					<tr><td class='light'><b>IP Address</b></td><td class='light'><a href='admin-ipsearch.php?ip=".$user['lastip']."'>".$user['lastip']."</a></td></tr>
					<tr><td class='light'><b>Registered</b></td><td class='light'>".choosetime(ctime()-$user['since'])." ago</td></tr>
					<tr><td class='light'><b>Last view</b></td><td class='light'>$lastview_txt</td></tr>
				</table>
				<br>
				<table>
					<tr>
						<td valign='top'>
							<table class='main'>
								<tr><td class='head c' colspan='4'>We have $p posts by this user:</td></tr>
								$post_txt
							</table>
						</td>
						<td valign='top'>
							<table class='main'>
								<tr><td class='head c' colspan='4'>We have $t threads by this user:</td></tr>
								$thread_txt
							</table>
						</td>
					</tr>
				</table>
			<br>
		</td>
		<tr><td class='dark'><input type='submit' name='rip' value='DELETE'><input type='checkbox' name='ipban' value=1 checked>IP Ban<input type='hidden' name='del' value='".$user['id']."'></td></tr>
		</table></center>
	</form>
	";
	
	pagefooter();

?>