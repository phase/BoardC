<?php

	require "lib/function.php";
	
	$action = filter_string($_POST['action']);
	
	if (isset($_GET['logout']))
		$action = "Logout";
	
	if (!$action){
		
		if ($loguser['id'])
			errorpage("You are already logged in.");
		
		pageheader("Login");
		

		
		print "<br/><br/>
		<form method='POST' action='login.php'>
		<table class='main w'>
			<tr>
				<td colspan=2 class='head c'>
					Login
				</td>
			</tr>
			<tr>
				<td class='dim' style='width: 100px;'>
					Username:
				</td>
				<td class='light'>
					<input type='text' name='user'>
				</td>
			</tr>
			<td class='dim'>
					Password:
				</td>
				<td class='light'>
					<input type='password' name='pass'>
				</td>
			</tr>
			<tr>
				<td colspan=2 style='text-align: left' class='dark c'>
					<input type='submit' value='Login' name='action'>
				</td>
			</tr>
		</table>
		</form>
		";
	}
	else if ($action == "Login"){
		
		if ($bot || $proxy || $tor)
			errorpage("Denied.");
		
		$user =	filter_string($_POST['user']);
		$pass = filter_string($_POST['pass']);
		
		// Detailed descriptions for simple mistakes
		if (!$_POST['user'] && !$_POST['pass']) errorpage("You have left both fields empty!");
		else if (!$_POST['user']) errorpage("You have left the username field empty!");
		else if (!$_POST['pass']) errorpage("You have left the password field empty!");
		
		// But not for this
		$data = $sql->fetchq("SELECT id, password FROM users WHERE name = '".addslashes($user)."'");
		
		if (password_verify($pass, filter_string($data['password']))){
			// Update hash
			$newhash = password_hash($pass, PASSWORD_DEFAULT);
			$sql->query("UPDATE users SET password='$newhash' WHERE id = ".$data['id']);
			
			setcookie('id', $data['id'], ctime()+3600*12);
			setcookie('verify', $newhash, ctime()+3600*12);
			$sql->query("DELETE from failed_logins WHERE ip = '".$_SERVER['REMOTE_ADDR']."'");
			
			errorpage("Successfully logged in.<br>Click <a href='index.php'>here</a> to return to the index.");
		}
		else {
			$attempts = $sql->resultq("SELECT attempt FROM failed_logins WHERE ip = '".$_SERVER['REMOTE_ADDR']."'");
			
			if (!$attempts){
				$sql->query("INSERT INTO failed_logins (ip, attempt) VALUES ('".$_SERVER['REMOTE_ADDR']."', 1)");
				trigger_error("Failed login attempt #1 for IP ".$_SERVER['REMOTE_ADDR']);
			}
			else if ($attempts<4){
				$sql->query("UPDATE failed_logins SET attempt=".($attempts+1)." WHERE ip = '".$_SERVER['REMOTE_ADDR']."'");
				trigger_error("Failed login attempt #".($attempts+1)." for IP ".$_SERVER['REMOTE_ADDR']);
			}
			else
				ipban("Password", "Auto IP-Banned ".$_SERVER['REMOTE_ADDR']." for 5 failed login attempts.");
			
			errorpage("Couldn't log in. Either the username or the password is incorrect.");
		}
		
	}
	
	else if ($action == "Logout"){
		setcookie('id', NULL);
		setcookie('verify', NULL);
		errorpage("Successfully logged out.");
	}
	
	else{
		//print $action;
		if (powlcheck(5)) errorpage("<h1>It works!</h1>");
		ipban("Abusive/Malicious Behaviour", "Automatic IP ban to ".$_SERVER['REMOTE_ADDR']." for messing with the login form.");
		errorpage("Couldn't log in. Either the username or the password is incorrect.<img src='cookieban.php' width=1 height=1>");
	}
	
	pagefooter();

	?>