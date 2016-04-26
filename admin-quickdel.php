<?php
	// based on admin-deluser.php
	require "lib/function.php";

	if (!powlcheck(4))
		errorpage("You're not an admin!");
		
	pageheader("?");
		
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
			errorpage("Sorry, but you've been ninja'd by someone else. <a href='?'>Try again</a>", false);		
		
		$sql->start();
		
		$c[] = $sql->query("DELETE FROM users WHERE id = $id");
		$c[] = $sql->query("ALTER TABLE `users` AUTO_INCREMENT=$id");
		$c[] = $sql->query("DELETE FROM user_avatars WHERE user = $id");
		$c[] = $sql->query("UPDATE posts SET user=$dest,avatar=0 WHERE user = $id");
		$c[] = $sql->query("UPDATE threads SET user=$dest WHERE user = $id");
		$c[] = $sql->query("DELETE FROM ratings WHERE userfrom = $id OR userto = $id");
		if (filter_int($_POST['ipban'])) ipban("", false, $data['lastip']);
		
		foreach(glob("userpic/$id/*") as $f)
			unlink("$f");
		rmdir("userpic/$id");
		$sql->end();
		errorpage("User ID #$id (".$data['name'].") deleted!<br/>Click <a href='?'>here</a> to delete more.", false);
		
	}
	
	
	$user = $sql->fetchq("
	SELECT id, name, displayname, namecolor, powerlevel, sex, icon, powerlevel, posts, since, lastip
	FROM users
	WHERE powerlevel < 1
	AND id != ".$config['deleted-user-id']."
	AND id != ".$loguser['id']."
	ORDER BY id DESC
	");
	
	if (!$user)
		errorpage("There are no more users to delete! You can go <a href='index.php'>home</a> now.", false);
	
	$list = "";
	
	print "
	<form method='POST' action='admin-quickdel.php'>
	<center><table class='main c'>
		<tr><td class='head'>?</td></tr>
		
		<tr><td class='light'>
			This will delete the latest registered user.<br/>(better description coming soon)
		</td></tr>
		<tr>
		<td class='dim'>
			<br/><center>
			<table class='main c'>
			<tr><td class='light'>User:</td><td class='light'>".makeuserlink(false, $user, true)."</td></tr>
			<tr><td class='light'>Posts:</td><td class='light'>".$user['posts']."</td></tr>
			<tr><td class='light'>IP Address:</td><td class='light'>".$user['lastip']."</td></tr>
			<tr><td class='light'>Registered:</td><td class='light'>".choosetime(ctime()-$user['since'])." ago</td></tr>
			</table></center><br/>
			
		</td>
		<tr><td class='dark'><input type='submit' name='rip' value='DELETE'><input type='checkbox' name='ipban' value=1 checked>IP Ban<input type='hidden' name='del' value='".$user['id']."'></td></tr>
		</table></center>
	</form>
	
	";
	
	pagefooter();

?>