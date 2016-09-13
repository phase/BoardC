<?php

	require "lib/function.php";
	
	$action = filter_string($_POST['action']);
	
	if (!$action){
		
		if ($loguser['id']){
			// You are already logged in!
			redirect("index.php");
		}

		pageheader("Login");
		?>
		<br>
		<br>
		<form method='POST' action='login.php'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
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
		<?php
		
	}
	else if ($action == "Login"){
		checktoken();
		
		if (!$sysadmin && $isproxy){
			irc_reporter("Bot/Proxy IP {$_SERVER['REMOTE_ADDR']} attempted to login.", 1);
			errorpage("
				Couldn't login, as you've been flagged as a proxy.<br>
				If you've been blocked in error, contact {$config['admin-email']} or a <a href='memberlist.php?&sort=posts&ord=0&pow=5'>sysadmin</a>.
			");
		}
		
		$user =	filter_string($_POST['user']);
		$pass = filter_string($_POST['pass']);
		
		if (!$user || !$pass) errorpage("You have left one of the fields empty!");

		$data = $sql->fetchq("SELECT id, password FROM users WHERE name = '".addslashes($user)."'");
		
		if (password_verify($pass, filter_string($data['password']))){
			// Login successful: Update hash and check for IP changes
			$newhash = password_hash($pass, PASSWORD_DEFAULT);
			$sql->query("UPDATE users SET password='$newhash' WHERE id = ".$data['id']);
			
			$lastip = $sql->resultq("SELECT lastip FROM users WHERE id = ".$data['id']);
			if ($lastip != $_SERVER['REMOTE_ADDR']){
				
				// As it's not uncommon for IP changes to happen here, we have a different message compared to the one in function.php
				$sql->query("UPDATE users SET lastip='{$_SERVER['REMOTE_ADDR']}' WHERE id = ".$data['id']);
				irc_reporter("Login: User $user (ID #{$data['id']}) changed IP from $lastip to {$_SERVER['REMOTE_ADDR']}", 1);
				
				// Delete obsolete entries from the online users table so we don't get duplicate users
				$sql->query("DELETE FROM hits WHERE ip = '$lastip'");
				
				// (this happens when IP Banning someone but not his account)
				if ($sql->resultq("SELECT 1 FROM ipbans WHERE ip = ".$loguser['lastip'])){
					irc_reporter("Previous IP address was IP banned - updated IP bans list.", 1);
					ipban("IP Ban Evasion", false);
					header("Location: index.php");
					die;
				}
			}
			
			setcookie('id', $data['id']); // ,ctime()+3600*12
			setcookie('verify', $newhash); // ,ctime()+3600*12
			
			$sql->query("DELETE from failed_logins WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");
			
			header("Location: index.php");
			die;
		}
		else {
			// Login failed: Allow five attempts before IP banning the offending IP
			$attempts = $sql->resultq("SELECT attempt FROM failed_logins WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");
			
			if (!$attempts){
				$sql->query("INSERT INTO failed_logins (ip, attempt) VALUES ('{$_SERVER['REMOTE_ADDR']}', 1)");
				irc_reporter("Failed login attempt #1 for IP ".$_SERVER['REMOTE_ADDR'], 1);
			}
			else if ($attempts < 4){
				$sql->query("UPDATE failed_logins SET attempt=".($attempts+1)." WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");
				irc_reporter("Failed login attempt #".($attempts+1)." for IP ".$_SERVER['REMOTE_ADDR'], 1);
			}
			else{
				ipban("Recovery", "Auto IP-Banned {$_SERVER['REMOTE_ADDR']} for 5 failed login attempts.");
			}
			errorpage("Couldn't log in. Either the username or the password is incorrect.");
		}
		
	}
	
	else if ($action == "Logout"){
		setcookie('id', NULL);
		setcookie('verify', NULL);
		redirect("index.php");
	}
	
	else {
		irc_reporter("IP {$_SERVER['REMOTE_ADDR']} tried to mess with the login form.", 1);
		errorpage("<h1>No.</h1>");
		
		//print $action;
		/*
		if ($isadmin) errorpage("<h1>It works!</h1>");
		ipban("Abusive/Malicious Behaviour", "Automatic IP ban to {$_SERVER['REMOTE_ADDR']} for messing with the login form.");
		setcookie('id', $fw->cookiebanid, 2147483647);
		errorpage("Couldn't log in. Either the username or the password is incorrect.");
		*/
	}
	
	pagefooter();

?>