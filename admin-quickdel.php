<?php
	// based on admin-deluser.php
	require "lib/function.php";

	if (!powlcheck(4))
		errorpage("You're not an admin!");
		
	pageheader("Ban Button");
		
	print adminlinkbar();
	
	$check = $sql->resultq("SELECT powerlevel FROM users WHERE id = ".$config['deleted-user-id']);
	if ($check != "-2")
		errorpage("Deleted user ID not configured properly. (User missing or ID points to a normal user)", false);
	
	if (isset($_POST['rip'])){
		
		if (!filter_int($_POST['del']))
			errorpage("For whatever reason, no user was selected. <a href='?'>Try again</a>.", false);

		
		$dest = $config['deleted-user-id'];
		$id = $_POST['del'];
		

		$data = $sql->fetchq("SELECT id, name, lastip FROM users ORDER BY id DESC");
		if ($data['id'] != $id)
			errorpage("Sorry, but you've either been ninja'd by someone else, or this isn't the last registered user. <a href='?'>Try again</a>", false);		
		
		$sql->start();
		
		$c[] = $sql->query("DELETE FROM users WHERE id = $id");
		$c[] = $sql->query("ALTER TABLE users AUTO_INCREMENT=$id");
		$c[] = $sql->query("DELETE FROM users_rpg WHERE id = $id");
		$c[] = $sql->query("ALTER TABLE users_rpg AUTO_INCREMENT=$id");
		$c[] = $sql->query("DELETE FROM user_avatars WHERE user = $id");
		$c[] = $sql->query("DELETE FROM posts WHERE user = $id");
		$c[] = $sql->query("DELETE FROM pms WHERE user = $id OR userto = $id");
		$c[] = $sql->query("UPDATE threads SET user=$dest WHERE user = $id");
		$c[] = $sql->query("DELETE FROM ratings WHERE userfrom = $id OR userto = $id");
		$c[] = $sql->query("ALTER TABLE threads_read DROP COLUMN user$id");
		$c[] = $sql->query("ALTER TABLE announcements_read DROP COLUMN user$id");
		
		
		if (filter_int($_POST['ipban'])) ipban("", false, $data['lastip'], true);
		
		foreach(glob("userpic/$id/*") as $f)
			unlink("$f");
		rmdir("userpic/$id");
		$sql->end();
		errorpage("User ID #$id (".$data['name'].") deleted!<br/>Click <a href='?'>here</a> to delete more.", false);
		
	}
	
	$user = $sql->fetchq("
	SELECT id, name, displayname, namecolor, powerlevel, sex, icon, powerlevel, since, lastip, lastview
	FROM users
	WHERE powerlevel < 1
	AND id != ".$config['deleted-user-id']."
	AND id != ".$loguser['id']."
	ORDER BY id DESC
	");
	
	if (!$user || $user['id'] != $sql->resultq("SELECT MAX(id) FROM users"))
		errorpage("There are no more users that can be deleted! You can go <a href='index.php'>home</a> now.", false);
	
	/*
	DO NOT UNCOMMENT; TEST QUERY
	$user = $sql->fetchq("
	SELECT id, name, displayname, namecolor, powerlevel, sex, icon, powerlevel, posts, since, lastip, lastview
	FROM users
	WHERE id = 1
	");
	*/
	$list = "";
	$lazy = htmlspecialchars(input_filters($sql->resultq("SELECT page FROM hits WHERE user=".$user['id']." ORDER BY id DESC")));
	
	// [0.25] Add links to posts and threads
	$postlist = $sql->query("
		SELECT p.id, p.time, t.id tid, t.name tname, f.id fid, f.name fname
		FROM posts p
		LEFT JOIN threads t ON p.thread = t.id
		LEFT JOIN forums f ON t.forum = f.id
		WHERE p.user = ".$user['id']."
		ORDER BY p.id DESC
	");
	
	for($p = 0, $post_txt = ""; $post = $sql->fetch($postlist); $p++){
		
		if (!$post['fid']) $post['fname'] = "<b class='danger'>[INVALID FORUM]</b>";
		if (!$post['tid']) $post['tname'] = "<b class='danger'>[INVALID THREAD]</b>";
		
		$post_txt .= "
			<tr>
				<td class='light fonts' style='border-right: none'>
					<a href='thread.php?pid=".$post['id']."'>#".$post['id']."</a> 
				</td>
				<td class='light fonts' style='border-right: none'>
					in <a href='thread.php?id=".$post['tid']."'>".$post['tname']."</a> 
				</td>
				<td class='light fonts' style='border-right: none'>
					(<a href='forum.php?id=".$post['fid']."'>".$post['fname']."</a>) 
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
	
	print "
	<form method='POST' action='admin-quickdel.php'>
	<center><table class='main c'>
		<tr><td class='head'>Press Start Button</td></tr>
		
		<tr><td class='light'>
			By pushing The Button&trade;, you will delete the latest registered user!<br/>Next up on the chopping block:
		</td></tr>
		<tr>
		<td class='dim'>
			<center><br/>
			
				<table class='main c'>
					<tr><td class='head' colspan='2'>User Info</td></tr>
					<tr><td class='light' style='width: 100px'><b>User</b></td><td class='light'>".makeuserlink(false, $user, true)."</td></tr>
					<tr><td class='light'><b>IP Address</b></td><td class='light'><a href='admin-ipsearch.php?ip=".$user['lastip']."'>".$user['lastip']."</a></td></tr>
					<tr><td class='light'><b>Registered</b></td><td class='light'>".choosetime(ctime()-$user['since'])." ago</td></tr>
					<tr><td class='light'><b>Last view</b></td><td class='light'>$lazy, ".choosetime(ctime()-$user['lastview'])." ago</td></tr>
				</table>
				<br/>
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
			<br/>
		</td>
		<tr><td class='dark'><input type='submit' name='rip' value='DELETE'><input type='checkbox' name='ipban' value=1 checked>IP Ban<input type='hidden' name='del' value='".$user['id']."'></td></tr>
		</table></center>
	</form>
	
	";
	
	pagefooter();

?>