<?php

	require "lib/function.php";
	
	if (!powlcheck(4))
		errorpage("You're not an admin!");
	
	if (isset($_GET['act'])){
		// Do appropriate action
		$id = filter_int($_GET['id']);
		if ($id){
			
			// Sanity check - No invalid users
			$data	= $sql->fetchq("SELECT * FROM pendingusers WHERE id = $id");
			if (!is_array($data))
				errorpage("This user doesn't exist. (ID #$id)");
			
			if ($_GET['act'] == 'accept'){
				/*
					TODO: A register user function
				*/
				
				$sql->start();
				$newuser 	= $sql->prepare("INSERT INTO users (name, password, lastip, since) VALUES (?,?,?,?)");
				$c[] 		= $sql->execute($newuser, [$data['name'], $data['password'], $data['lastip'], $data['since']]);
				
				if ($sql->finish($c)){
					$sql->query("DELETE FROM pendingusers WHERE id = $id");
					$sql->query("DELETE FROM ipbans WHERE ip = '{$data['lastip']}'");
					
					$sql->query("INSERT INTO users_rpg () VALUES ()");
					$id = $sql->resultq("SELECT MAX(id) FROM users");
					$sql->query("ALTER TABLE threads_read ADD COLUMN user$id int(32) NOT NULL DEFAULT '0'");
					$sql->query("ALTER TABLE announcements_read ADD COLUMN user$id int(32) NOT NULL DEFAULT '0'");
					
					// Remove the "new" value from all past threads and announcements
					$newtime = ctime();
					$sql->query("UPDATE threads_read SET user$id = '$newtime'");
					$sql->query("UPDATE announcements_read SET user$id = '$newtime'");
					
					
					mkdir("userpic/$id");
					trigger_error("User approved: {$data['name']} ({$config['board-url']}profile.php?id=$id) IP: {$data['lastip']}", E_USER_NOTICE);
					errorpage("User approved!");
				}
				else errorpage("An unknown error occurred while accepting the user.");
				
				
			}
			else if ($_GET['act'] == 'reject'){
				$sql->query("DELETE FROM pendingusers WHERE id = $id");
				$sql->query("DELETE FROM ipbans WHERE ip = '{$data['lastip']}'");
				trigger_error("User rejected: {$data['name']} (IP: {$data['lastip']})", E_USER_NOTICE);
				errorpage("User rejected!");
			}
			else if ($_GET['act'] == 'ipban'){
				$sql->query("DELETE FROM pendingusers WHERE id = $id");
				$sql->query("UPDATE ipbans SET reason = 'Spam' WHERE ip = '{$data['lastip']}'");
				trigger_error("Added IP Ban to pending user {$data['name']} (IP: {$data['lastip']})", E_USER_NOTICE);
				errorpage("User blocked!");
			}
			else errorpage("Invalid action.");
			
		}
	}
	
	$users 	= $sql->query("SELECT * FROM pendingusers ORDER BY id DESC");
	$txt 	= "";
	
	if ($users)
		while ($u = $sql->fetch($users))
			$txt .= "
				<tr>
					<td class='light c'>{$u['id']}</td>
					<td class='dim c'>{$u['name']}</td>
					<td class='dim c'>".printdate($u['since'])."</td>
					<td class='light c'>{$u['lastip']}</td>
					<td class='dim c'><a class='notice' href='?id={$u['id']}&act=accept'>Accept</a> - <a href='?id={$u['id']}&act=reject'>Reject</a> - <a class='danger' href='?id={$u['id']}&act=ban'>IP Ban</a></td>
				</tr>
			";
			
	else $txt = "<tr><td class='light c' colspan='5'>There are no pending users.</td></tr>";
	
		
	pageheader("Pending users");
	print adminlinkbar()."
	<form method='POST' action='?'>
		<table class='main w'>
		
			<tr><td class='head c' colspan='5'>Pending users</td></tr>
			
			<tr>
				<td class='head c' style='width: 50px'>#</td>
				<td class='head c'>Name</td>
				<td class='head c' style='width: 250px'>Date</td>
				<td class='head c' style='width: 200px'>IP</td>
				<td class='head c' style='width: 230px'>Action</td>
			</tr>
			$txt
		</table>
	</form>
	";
	pagefooter();


?>