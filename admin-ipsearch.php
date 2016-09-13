<?php

	require "lib/function.php";
	
	admincheck();
	
	if (isset($_GET['ban'])){
		checkgettoken();
		$id = filter_int($_GET['ban']);
		userban($id, false, false, "", "Banned User ID #$id (admin-ipsearch.php ban)"); 
		setmessage("Added ban for user #$id");
		header("Location: ?");
		x_die();
	}
	else if (isset($_GET['unban'])){
		checkgettoken();
		$id = filter_int($_GET['unban']);
		$sql->query("
			UPDATE users
			SET powerlevel = 0, ban_expire = 0
			WHERE id = $id
		");
		setmessage("Removed ban for user #$id");
		header("Location: ?");
		x_die();
	}
	else if (isset($_GET['ipban'])){
		checkgettoken();
		$ip = filter_string($_GET['ipban']);
		ipban("online.php ban", "IP Banned $ip (admin-ipsearch.php ban)", $ip, 0, true); 
		setmessage("Added IP ban for $ip");
		header("Location: ?");
		x_die();
	}
		
	
	$ip		= filter_string($_POST['ip']);
	$ip1	= filter_string($_POST['ip1']);
	$ip2	= filter_string($_POST['ip2']);
	$ipm	= filter_string($_POST['ipm']);
	
	// Quick hack to allow linking other pages directly to an IP search here
	if (isset($_GET['ip'])){
		$_POST['dosearch'] 	= true;
		$ip 				= $_GET['ip'];
	}
	
	// Sorting options
	$usort = filter_string($_POST['su']);
	
	switch ($usort){
		case 'p': $qsort = "posts"; 	break;
		case 'r': $qsort = "since"; 	break;
		case 's': $qsort = "lastpost"; 	break;
		case 'a': $qsort = "lastview"; 	break;
		case 'i': $qsort = "lastip"; 	break;
		default:  $qsort = "id";
	}
	
	$usortdir = filter_int($_POST['sudir']);
		
	$user_txt = "<tr><td class='light w c' colspan=7>Select something to search</td></tr>";
	
	if (isset($_POST['dosearch'])){
		
		
		if (!$ip && !$ipm && (!$ip1 || !$ip2)){
			setmessage("You have left one of the required IP fields empty!");
			header("Location: ?");
			x_die();
		} else {
		
			$users = $sql->query("
				SELECT 	u.lastip, u.id, u.name, u.displayname, u.namecolor, u.powerlevel, u.sex,
						u.since, u.posts, u.lastpost, u.lastview, i.ip ipbanned
				FROM users u
				LEFT JOIN ipbans i ON u.lastip = i.ip
				".($ip ? "WHERE u.lastip LIKE '$ip%'" : "")."
				ORDER by u.$qsort ".($usortdir ? "ASC" : "DESC")."
			");

			$user_txt = $hits_txt = "";
			
			while ($user = $sql->fetch($users)){
				
				if ($ip1 && !iprange($ip1, $user['lastip'], $ip2)) continue;
				if ($ipm && !ipmask($ipm, $user['lastip'])) continue;
				
				$user_txt .= "
					<tr>
						<td class='dim c'>
							{$user['id']}
						</td>
						<td class='light'>
							".makeuserlink(false, $user)."
							<small>
							".(
								$user['powerlevel'] < 0 ?
								" - <a href='?unban={$user['id']}&auth=$token'><b>Unban</b></a>" :
								" - <a href='?ban={$user['id']}&auth=$token' class='danger'><b>Ban</b></a>"
							)."
							</small>
						</td>
						<td class='dim c'>
							".printdate($user['since'])."
						</td>
						<td class='light c'>
							".($user['lastpost'] ? printdate($user['lastpost']) : "None")."
						</td>
						<td class='light c'>
							".($user['lastview'] ? printdate($user['lastview']) : "None")."
						</td>				
						<td class='light c'>
							{$user['posts']}
						</td>						
						<td class='dim'>
							{$user['lastip']}
							<small>
								<a href='https://www.google.com/search?q={$user['lastip']}'>[G]</a> 
								<a href='https://en.wikipedia.org/wiki/User:{$user['lastip']}'>[W]</a> - 
								".(
									$user['ipbanned'] ? 
									"[<a href='admin-ipbans.php?ip={$user['lastip']}'>IP BANNED</a>]" : 
									"<a href='?ipban={$user['lastip']}&auth=$token' class='danger'><b>IP Ban</b></a>"
								)."
							</small>
						</td>
					</tr>
				";
			}
			
		}
		
	}
	
	$ch1[$usort] 		= "selected";
	$ch1dir[$usortdir] 	= "checked";
	
	
	pageheader("IP Search");
	print adminlinkbar().$message;
	print "
	<form method='POST' action='admin-ipsearch.php'>
	<center><table class='main'>
		<tr><td class='head c' colspan=2>IP Search</td></tr>
		
		<tr>
			<td class='light'><b>IP to search:</b></td>
			<td class='dim'><input type='text' name='ip' size=15 maxlength=15 value=\"$ip\"></td>
		</tr>
		
		<tr>
			<td class='light'><b>Search for an IP range:</b></td>
			<td class='dim'><input type='text' name='ip1' size=15 maxlength=15 value=\"$ip1\"> - <input type='text' name='ip2' size=15 maxlength=15 value=\"$ip2\"></td>
		</tr>
		
		<tr>
			<td class='light'><b>Search for an IP mask:</b></td>
			<td class='dim'><input type='text' name='ipm' size=15 maxlength=15 value=\"$ipm\"></td>
		</tr>
		
		<tr>
			<td class='light'><b>Sort users by:</b></td>
			<td class='dim'>
				<select name='su'>
					<option value='d' ".filter_string($ch1['d']).">ID</option>
					<option value='n' ".filter_string($ch1['n']).">Name</option>
					<option value='p' ".filter_string($ch1['p']).">Posts</option>
					<option value='r' ".filter_string($ch1['r']).">Registration</option>
					<option value='s' ".filter_string($ch1['s']).">Last post</option>
					<option value='a' ".filter_string($ch1['a']).">Last activity</option>
					<option value='i' ".filter_string($ch1['i']).">Last IP</option>
				</select>
				<input type='radio' name='sudir' value='0' ".filter_string($ch1dir[0])."><label for 'sudir'>Descending</label>
				<input type='radio' name='sudir' value='1' ".filter_string($ch1dir[1])."><label for 'sudir'>Ascending</label>
			</td>
		</tr>
			
		<tr>
			<td class='dark' colspan=2>
				<input type='submit' name='dosearch' value='Search'>
			</td>
		</tr>
	</table></center>
	</form>
	
	<center>
	<br>
	<table class='main w'>
		<tr><td class='head c' colspan=7>Users</td></tr>
		<tr>
			<td class='dark c'>ID</td>
			<td class='dark c'>Name</td>
			<td class='dark c'>Registered on</td>
			<td class='dark c'>Last post</td>
			<td class='dark c'>Activity</td>
			<td class='dark c'>Posts</td>
			<td class='dark c'>Last IP</td>
		</tr>
		$user_txt
	</table>
	</center>		
	";
	
	pagefooter();
	
	

?>