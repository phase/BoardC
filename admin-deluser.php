<?php
	
	require "lib/function.php";

	/*
		a del.php clone
	*/
	if (!$sysadmin){
		errorpage("You're not a sysadmin!");
	}
	
	$check = $sql->resultq("SELECT powerlevel FROM users WHERE id = ".$config['deleted-user-id']);
	if ($check != "-2"){
		errorpage("Deleted user ID not configured properly. (User missing or ID points to a normal user)");
	}
	
	if (isset($_POST['rip'])){
		
		checktoken();
		
		
		if (!isset($_POST['del'])){
			errorpage("No user selected.");
		}
		
		// Here we go

		$dest 		= $config['deleted-user-id'];
		$del 		= $_POST['del'];
		$deltext	= "";
		$i 			= 0;
		
		//errorpage("$del - Under construction!", false);	
		
		$sql->start();
		
		foreach($del as $id){
			
			$id = intval($id);
			
			// Sanity check
			if (in_array($id, [1, $loguser['id'], $dest])){
				$sql->undo();
				errorpage("Sorry, but you have selected a whitelisted user.");
			}
			
			// Will be appended to thread text
			$name = $sql->resultq("SELECT name FROM users WHERE id = $id");
			if ($name === false){
				// Prevent from deleting something that doesn't exist.
				errorpage("The user doesn't exist!");
			}
			$line = "<br><br>===================<br>[Posted by <b>$name</b>]<br>";
			
			// Deletions
			$c[] = $sql->query("DELETE FROM users        WHERE id   = $id");
			$c[] = $sql->query("DELETE FROM user_avatars WHERE user = $id");
			$c[] = $sql->query("DELETE FROM users_rpg    WHERE id   = $id");
			$c[] = $sql->query("DELETE FROM poll_votes   WHERE user = $id");
			$c[] = $sql->query("DELETE FROM radar  	  	 WHERE user = $id OR sel = $id");
			$c[] = $sql->query("DELETE FROM ratings      WHERE userfrom = $id OR userto = $id");

			
			// we have to take posts_old into consideration. sigh
			$posts = $sql->fetchq("SELECT id FROM posts WHERE user = 1", true, PDO::FETCH_COLUMN);
			$c[] = $sql->query("UPDATE posts_old SET avatar = 0 WHERE pid IN (".implode(",", $posts).")");
			unset($posts);
			
			// Replace reference of original user with the generic deleted user
			$c[] = $sql->query("UPDATE posts   SET user   = $dest, avatar = 0, text = CONCAT(text, '$line') WHERE user = $id");
			$c[] = $sql->query("UPDATE pms     SET user   = $dest, avatar = 0 WHERE user = $id");
			$c[] = $sql->query("UPDATE pms     SET userto = $dest WHERE userto = $id");
			$c[] = $sql->query("UPDATE threads SET user   = $dest WHERE user   = $id");
			
			// Update last post views
			$c[] = $sql->query("UPDATE threads SET lastpostuser = $dest WHERE lastpostuser = $id");
			$c[] = $sql->query("UPDATE forums  SET lastpostuser = $dest WHERE lastpostuser = $id");
			
			$c[] = $sql->query("ALTER TABLE threads_read DROP COLUMN user$id");
			$c[] = $sql->query("ALTER TABLE announcements_read DROP COLUMN user$id");
			
			$deltext .= "[$id] -> $name<br>";
		}
		if ($sql->finish($c)){
			// delete userpics too, but only if the SQL delete was successful to prevent any fun error
			foreach ($del as $id){
				foreach(glob("userpic/$id/*") as $f)
					unlink("$f");
				rmdir("userpic/$id");
				$i++;
			}
			// Refresh number of posts for the deleted user account
			$realposts 	= $sql->resultq("SELECT COUNT(id) FROM posts WHERE user = $dest");
			$c[] 		= $sql->query("UPDATE users SET posts = $realposts WHERE user = $dest");
			//$sql->end();
			
			errorpage("$i user(s) deleted!<br>$deltext");
		}
		else{
			errorpage("An error occurred while deleting users.");
		}
		
	}
	

	$ips 	= filter_string($_POST['ips']);
	$sname 	= filter_string($_POST['sname']);
	
	
	
	
	if (isset($_POST['switch'])){
		$show 		= filter_int($_POST['show']);
		$maxposts	= filter_int($_POST['maxposts']);
		$sort 		= filter_string($_POST['sort']);
		$sortdir 	= filter_int($_POST['sortdir']);
	}
	else{
		$show 		= 6;
		$sort 		= "since";
		$sortdir 	= 0;
		$maxposts	= 0;
	}
	
	// Sanity checks
	if (!in_array($sort, ['id', 'name', 'displayname', 'powerlevel', 'lastip', 'since', 'posts', 'ban_expire'])) errorpage("No.");
	
	
	
	$sel[$show] 	= "selected";
	$ssel[$sort] 	= "selected";
	$osel[$sortdir] = "checked";
	
	
	switch($show){
		case 6: { // All powerlevels
			$q_powl = "";
			break;
		}
		case 7: { // All banned
			$q_powl = "AND u.powerlevel < 0";
			break;
		}
		default:{ // Selected powerlevel
			$q_powl = "AND u.powerlevel = $show";
		}
	}
	
	$users = $sql->query("
		SELECT $userfields, u.ban_expire, u.posts, u.lastpost, u.lastview, u.since, u.lastip, h.page
		FROM users u
		LEFT JOIN hits h ON u.lastip = h.ip
		WHERE u.id != {$config['deleted-user-id']} AND u.id != {$loguser['id']}
		".($ips   ? "AND u.lastip LIKE '$ips%'" : "")."
		".($sname ? "AND u.name = '$sname'"     : "")."
		".($maxposts ? "AND u.posts < $maxposts" : "")."
		$q_powl
		ORDER BY u.$sort ".($sortdir ? "ASC" : "DESC")."
	");
	
	$list = "";
	
	while ($user = $sql->fetch($users)){
		$list .= "
		<tr class='c'>
			<td class='dim'><input type='checkbox' name='del[]' value='".$user['id']."'></td>
			<td class='light'>".makeuserlink(false, $user, true)."</td>
			<td class='dim'>".$user['posts']."</td>
			<td class='light'>".printdate($user['since'])."</td>
			<td class='dim'>".($user['lastpost'] ? printdate($user['lastpost']) : "None")."</td>
			<td class='light'>".$power_txt[$user['powerlevel']]."</td>
			<td class='dim'>".($user['powerlevel'] == '-1' && $user['ban_expire'] ? printdate($user['ban_expire']) : "-")."</td>
			<td class='light'>".printdate($user['lastview'])."</td>
			<td class='light'>".$user['page']."</td>
			<td class='light'>".$user['lastip']."</td>
		</tr>
		";
	}
	
	pageheader("Nuclear Bomb");
	
	print adminlinkbar()."
	<form method='POST' action='admin-deluser.php'>
	<input type='hidden' value='$token' name='auth'>
	
	<center>
	<table class='main w'>
	
	<tr><td class='head c' colspan=10>Delete User</td></tr>
	
	<tr>
		<td class='dim'></td>
		<td class='light c' style='width: 300px'><b>User Search:</b></td>
		<td class='dim' colspan=8><input type='text' name='sname' value=\"$sname\"></td>
	</tr>
	<tr>
		<td class='dim'></td>
		<td class='light c'><b>Search IP:</b></td>
		<td class='dim' colspan=8><input type='text' name='ips' value=\"$ips\"></td>
	</tr>
	<tr>
		<td class='dim'></td>
		<td class='light c'><b>Show users with less than:</b></td>
		<td class='dim' colspan=8><input type='text' style='width: 50px' name='maxposts' value=\"$maxposts\"> posts</td>
	</tr>	
	<tr>
		<td class='dim'></td>
		<td class='light c'><b>Powerlevel:</b></td>
		<td class='dim' colspan=8>
			<select name='show'>
				<option value='6' ".filter_string($sel[6]).">* Any powerlevel</option>	
				<option value='6' ".filter_string($sel[7]).">* All banned</option>					
				<option value='-2' ".filter_string($sel['-2']).">".$power_txt['-2']."</option>
				<option value='-1' ".filter_string($sel['-1']).">".$power_txt['-1']."</option>
				<option value='0' ".filter_string($sel[0]).">$power_txt[0]</option>
				<option value='1' ".filter_string($sel[1]).">$power_txt[1]</option>
				<option value='2' ".filter_string($sel[2]).">$power_txt[2]</option>
				<option value='3' ".filter_string($sel[3]).">$power_txt[3]</option>
				<option value='4' ".filter_string($sel[4]).">$power_txt[4]</option>
				<option value='5' ".filter_string($sel[5]).">$power_txt[5]</option>

			</select>
		</td>
	</tr>		
	<tr>
		<td class='dim'></td>
		<td class='light c'><b>Sort by:</b></td>
		<td class='dim' colspan=8>
			<select name='sort'>
				<option value='id' ".			filter_string($ssel['id'])			.">ID</option>
				<option value='name' ".			filter_string($ssel['name'])		.">Name</option>
				<option value='displayname' ".	filter_string($ssel['displayname'])	.">Display name</option>
				<option value='powerlevel' ".	filter_string($ssel['powerlevel'])	.">Powerlevel</option>
				<option value='lastip' ".		filter_string($ssel['lastip'])		.">IP Address</option>
				<option value='posts' ".		filter_string($ssel['posts'])		.">Posts</option>
				<option value='since' ".		filter_string($ssel['since'])		.">Registration date</option>
				<option value='ban_expire' ".	filter_string($ssel['ban_expire'])	.">Ban expiration</option>
			</select> 
			<input type='radio' name='sortdir' value='0' ".filter_string($osel[0])."> Descending
			<input type='radio' name='sortdir' value='1' ".filter_string($osel[1])."> Ascending 
		</td>
	</tr>

	<tr><td class='dark c' colspan=10><input type='submit' name='switch' value='Update query'></td></tr>
	<!-- deluser list starts here -->
	<tr class='c'>
		<td class='head'></td>
		<td class='head'>Name</td>
		<td class='head'>Posts</td>
		<td class='head'>Reg. Date</td>
		<td class='head'>Last post</td>
		<td class='head'>Powerlevel</td>
		<td class='head'>Banned until</td>
		<td class='head'>Last Activity</td>
		<td class='head'>Last View</td>
		<td class='head'>IP Address</td>
	</tr>
		$list
	<tr><td class='dark c' colspan=10><input type='submit' name='rip' value='Delete User'></td></tr>
	</table></center></form>
	";
	
	pagefooter();

?>