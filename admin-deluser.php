<?php
	
	require "lib/function.php";

	/*
	del.php clone
	
	$sql->finish() check doesn't work, the function itself is somewhat broken
	*/
	if (!powlcheck(5))
		errorpage("You're not a sysadmin!");
		
	pageheader("Nuclear Bomb");
		
	print adminlinkbar();
	
	$check = $sql->resultq("SELECT powerlevel FROM users WHERE id = ".$config['deleted-user-id']);
	if ($check != "-2")
		errorpage("Deleted user ID not configured properly. (User missing or ID points to a normal user)", false);
	
	if (isset($_POST['rip'])){
		
		if (!isset($_POST['del']))
			errorpage("No user selected.", false);
		
		// Here we go

		
		$dest = $config['deleted-user-id'];
		$del = $_POST['del'];
		
		//errorpage("$del - Under construction!", false);	
		
		$sql->start();
		foreach($del as $id){
			
			$c[] = $sql->query("DELETE FROM users WHERE id = $id");
			$c[] = $sql->query("DELETE FROM user_avatars WHERE user = $id");
			$c[] = $sql->query("DELETE FROM users_rpg WHERE user = $id");
			
			// doesn't take into consideration avatars in posts_old, but who cares
			$c[] = $sql->query("UPDATE posts SET user=$dest,avatar=0 WHERE user = $id");
			$c[] = $sql->query("UPDATE pms SET user=$dest,avatar=0 WHERE user = $id");
			$c[] = $sql->query("UPDATE pms SET userto=$dest WHERE userto = $id");
			$c[] = $sql->query("UPDATE threads SET user=$dest WHERE user = $id");
			
			$c[] = $sql->query("DELETE FROM ratings WHERE userfrom = $id OR userto = $id");
			$c[] = $sql->query("ALTER TABLE new_posts DROP COLUMN user$id");
			$c[] = $sql->query("ALTER TABLE new_announcements DROP COLUMN user$id");
		}
		//if ($sql->finish($c)){
			// delete userpics too, but only if the SQL delete was successful to prevent any fun error
			foreach ($del as $id){
				foreach(glob("userpic/$id/*") as $f)
					unlink("$f");
				rmdir("userpic/$id");
			}
			$sql->end();
			errorpage("User IDs ".implode(", ", $del)." deleted!", false);
		//}
		/*else{
			errorpage("An error occurred while deleting this user.", false);
		}*/
		
	}
	
	$where = filter_string($_POST['cwhere']);
	$ips = 	 filter_string($_POST['ips']);
	$sname = filter_string($_POST['sname']);
	
	
	
	
	if (isset($_POST['switch'])){
		$show = filter_int($_POST['show']);
		$sort = filter_string($_POST['sort']);
		$sortdir = filter_string($_POST['sortdir']);
	}
	else{
		$show = 6;
		$sort = "id";
		$sortdir = "ASC";
	}
	
	$sel[$show] = "selected";
	$ssel[$sort] = "selected";
	$osel[$sortdir] = "checked";
	
	

	
	
	$users = $sql->query("
	SELECT $userfields, u.ban_expire, u.posts, u.lastpost, u.lastview, u.since, u.lastip, h.page
	FROM users u
	LEFT JOIN hits h
	ON h.id = (SELECT MAX(h.id) FROM hits h WHERE h.ip = u.lastip)
	WHERE u.id != ".$config['deleted-user-id']."
	AND u.id != ".$loguser['id']."
	".($ips ? "AND u.lastip LIKE '$ips%'" : "")."
	".($sname ? "AND u.name = '$sname'" : "")."
	".($where ? "AND $where" : "")."
	".($show != 6 ? "AND u.powerlevel = $show" : "")."
	ORDER BY u.$sort $sortdir
	");
	
	$list = "";
	
	$powl_table = array(
		'-2'=> "Permabanned",
		'-1'=> "Banned",
		0	=> "Normal",
		1 	=> "Privileged",
		2 	=> "Local Moderator",
		3 	=> "Global Moderator",
		4 	=> "Administrator",
		5 	=> "Sysadmin",
	);
	
	while ($user = $sql->fetch($users)){
		$list .= "
		<tr class='c'>
			<td class='dim'><input type='checkbox' name='del[]' value='".$user['id']."'></td>
			<td class='light'>".makeuserlink(false, $user, true)."</td>
			<td class='light'>".$powl_table[$user['powerlevel']]."</td>
			<td class='dim'>".($user['powerlevel'] == '-1' && $user['ban_expire'] ? printdate($user['ban_expire']) : "-")."</td>
			<td class='dim'>".$user['posts']."</td>
			<td class='dim'>".($user['lastpost'] ? printdate($user['lastpost']) : "None")."</td>
			<td class='light'>".printdate($user['since'])."</td>
			<td class='light'>".$user['lastip']."</td>
			<td class='light'>".printdate($user['lastview'])."</td>
			<td class='light'>".$user['page']."</td>
		</tr>
		";
	}
	
	
	print "<form method='POST' action='admin-deluser.php'>
	<center><table class='main w'>
	<tr><td class='head c' colspan=10>Delete User</td></tr>
	
	
	<tr>
		<td class='dim'></td>
		<td class='light c' style='width: 200px'><b>Show:</b></td>
		<td class='dim' colspan=8>
			<select name='show'>
				<option value='-2' ".filter_string($sel['-2']).">".$powl_table['-2']."</option>
				<option value='-1' ".filter_string($sel['-1']).">".$powl_table['-1']."</option>
				<option value='0' ".filter_string($sel[0]).">$powl_table[0]</option>
				<option value='1' ".filter_string($sel[1]).">$powl_table[1]</option>
				<option value='2' ".filter_string($sel[2]).">$powl_table[2]</option>
				<option value='3' ".filter_string($sel[3]).">$powl_table[3]</option>
				<option value='4' ".filter_string($sel[4]).">$powl_table[4]</option>
				<option value='5' ".filter_string($sel[5]).">$powl_table[5]</option>
				<option value='6' ".filter_string($sel[6]).">All</option>
			</select>
		</td>
	</tr>		
	<tr>
		<td class='dim'></td>
		<td class='light c'><b>Sort:</b></td>
		<td class='dim' colspan=8>
			<select name='sort'>
				<option value='id' ".filter_string($ssel['id']).">ID</option>
				<option value='name' ".filter_string($ssel['name']).">Name</option>
				<option value='displayname' ".filter_string($ssel['displayname']).">Display name</option>
				<option value='powerlevel' ".filter_string($ssel['powerlevel']).">Powerlevel</option>
				<option value='posts' ".filter_string($ssel['posts']).">Posts</option>
				<option value='since' ".filter_string($ssel['since']).">Registration date</option>
				<option value='ban_expire' ".filter_string($ssel['ban_expire']).">Ban expiration</option>
			</select> <input type='radio' name='sortdir' value='ASC' ".filter_string($osel['ASC'])."> ASC <input type='radio' name='sortdir' value='DESC' ".filter_string($osel['DESC'])."> DESC
		</td>
	</tr>
	<tr>
		<td class='dim'></td>
		<td class='light c'><b>Search IP:</b></td>
		<td class='dim' colspan=8><input type='text' name='ips' value=\"$ips\"></td>
	</tr>
	<tr>
		<td class='dim'></td>
		<td class='light c'><b>Search Name:</b></td>
		<td class='dim' colspan=8><input type='text' name='sname' value=\"$sname\"></td>
	</tr>
	<tr>
		<td class='dim'></td>
		<td class='light c'><b>Custom WHERE:</b></td>
		<td class='dim' colspan=8><input type='text' style='width: 500px' name='cwhere' value=\"$where\"></td>
	</tr>
	<tr><td class='dark c' colspan=10><input type='submit' name='switch' value='Update query'></td></tr>
	<!-- deluser list starts here -->
	<tr class='c'>
		<td class='head'></td>
		<td class='head'>User</td>
		<td class='head'>Powerlevel</td>
		<td class='head'>Banned until</td>
		<td class='head'>Posts</td>
		<td class='head'>Last post</td>
		<td class='head'>Registered on</td>
		<td class='head'>IP Address</td>
		<td class='head'>Last Activity</td>
		<td class='head'>Last View</td>
	</tr>
		$list
	<tr><td class='dark c' colspan=10><input type='submit' name='rip' value='Delete User'></td></tr>
	</table></center></form>
	";
	
	pagefooter();

?>