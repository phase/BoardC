<?php

	require "lib/function.php";

	$action 	= filter_string($_POST['action']);
	$isadmin 	= powlcheck(4);
	
	if (!$action){
		
		if ($loguser['id'] && !$isadmin)
			header("Location: index.php");
		
		pageheader("Register");
		
		if (!$isadmin){
			// Allow admins to rereggie as much as they want
			if ($miscdata['regmode'] == 1)
				errorpage("
				To register an account, you need to send a request to an administrator.<br/>
				<a href='memberlist.php?pow=4'>This</a> is the list of the current administrative team.
				", false);
			if ($miscdata['regmode'] == 3)
				errorpage("Registrations are currently disabled.", false);
			if ($miscdata['regmode'] == 2 && $miscdata['regkey'])
				$regkey = "	<tr>
								<td class='light'>
									<b>Registration key</b><br/>
									<small>
									Contact an admin to request this key.<br/>
									It's <b>NOT</b> a guarantee that you'll receive it.
									</small>
								</td>
								<td class='dim'>
									<input type='text' style='width:230px' name='regkey' autocomplete=off>
								</td>
							</tr>";
		}
		
		print "
		<form method='POST' action='register.php' autocomplete='OFF'>
		<input type='text' name='sinkfield' style='position: fixed; top: -300px;' autocomplete=off>
		<table class='main w'>
			<tr>
				<td class='head c' colspan=2>Register</td>
			</tr>
			<tr>
				<td class='light' style='width: 400px'>
					Username:<br/>
					<small>Maximum length: 32 Characters. Can contain only alphanumeric characters.</small>
				</td>
				<td class='dim'>
					<input type='text' style='width:230px' name='user' maxlength=32 autocomplete=off>
				</td>
			</tr>
			<tr>
				<td class='light'>
					Password:<br/>
					<small>Preferibly use passwords with at least 8 characters, with uppercase and lowercase letters, numbers and symbols.<br/>
					(the usual stuff, basically)</small>
				</td>
				<td class='dim'>
					<input type='password' style='width:230px' name='pass' autocomplete=off>
				</td>
			</tr>
			<tr>
				<td class='light'>
					Retype:<br/>
					<small>Retype the password again.</small>
				</td>
				<td class='dim'>
					<input type='password' style='width:230px' name='pass2' autocomplete=off>
				</td>
			</tr>
			$regkey
			<!--
			These are the standard special fields. <b>Do <div class='danger'>NOT</div> fill in these - you'll be IP Banned!</b>
			<tr>
				<td class='light'>
					Display Username:<br/>
					<small>Alternate username only an admin can add.<br/>
					Yes, you read that correctly. A description like this would sound fishy...</small>
				</td>
				<td class='dim'>
					<input type='text' name='akafield' autocomplete=off>
				</td>
			</tr>
			-->
			<tr style='display: none'>
				<td class='light'>
					Make me a local mod!<br/>
					FILL THIS IN TO GET IP BANNED
				</td>
				<td class='dim'>
					<input type='text' name='notreally' autocomplete=off>
				</td>
			</tr>
			<tr>
				<td class='dim' colspan=2>
					<input type='submit' name='action' value='Register'>
				</td>
			</tr>
			</table>
			</form>
		";
		
		
		
	}
	else if ($action == "Register"){
		
		if (filter_string($_POST['notreally']) || filter_string($_POST['akafield']) || filter_string($_POST['sinkfield'])){
			if ($isadmin) errorpage("<h1>It works!</h1>");
			ipban("", "Auto IP Banned ".$_SERVER['REMOTE_ADDR']." for filling in dummy registration fields.");
			errorpage("I don't think you know what a warning is.");
		}
		
		if ($miscdata['regmode'] == 1 || $miscdata['regmode'] == 3 && !$isadmin) // Enforce admin-only registration
			errorpage("Nice try, but only admin can register you.");
			
		$user = addslashes(filter_string($_POST['user']));
		$pass = filter_string($_POST['pass']);
		
		if (!$user || !$pass)
			errorpage("You have left either the username or password fields empty!");
		
		if ($pass != filter_string($_POST['pass2']))
			errorpage("The password and the retype don't match.");
		
		if (strlen($user)>32)
			errorpage("If you want to know, the 'name' field is varchar(32). Name too long.");
			
		$check = preg_replace('/[^\da-z]/i', '', $user);
		if ($check !== $user)
			errorpage("Your username contains non-alphanumeric characters.");
		
		if (strlen($pass)<8)
			errorpage("The password should be at least 8 characters long.");
		// 'password' is 8 characters
		if (stripos($pass, "password") !== false)
			errorpage("... (type a different password)");
		
		$rereggie = $sql->fetchq("SELECT id, name FROM users WHERE lastip = '".$_SERVER['REMOTE_ADDR']."'");
		if (filter_int($rereggie['id']) && !$config['allow-rereggie'] && !$isadmin){
			trigger_error("Rereggie attempt from ".$rereggie['name']." .", E_USER_NOTICE);
			errorpage("You've already registered as <a href='profile.php?id=".$rereggie['id']."'>".$rereggie['name']."</a>!");
		}
		
		$double = $sql->resultp("SELECT name FROM users WHERE name = ?", array($user));
		if ($double)
			errorpage("The username already exists. Please choose a different one.");
		
		if ($bot)
			errorpage("What are you doing?<br/>(<small>If you've been blocked in error, contact (me)</small>)");
		
		if ($fw->torcheck()){
			$sql->query("INSERT INTO tor (ip, time) VALUES ('".$_SERVER['REMOTE_ADDR']."', ".ctime().")");
			errorpage("You seem to be using Tor.<br/>For added security, Tor users are blocked from registering/logging in.");
		}
		
		// Other firewall checks here 
		
		/*
		this code is down here to prevent a loophole caused by sharing the regkey counter and failed logins in the same table
		(if you know the regkey and register again with the correct one, you would be blocked but the failed_logins counter would reset)
		
		to prevent the loophole also when $config['allow-rereggie'] is enabled, $rereggie is checked before erasing failed_logins
		
		basically, at this point the user registration is an almost guaranteed success
		*/
		if ($miscdata['regmode'] == 2 && $miscdata['regkey'] && !$isadmin){
			$regkey = filter_string($_POST['regkey']);
			if ($regkey != $miscdata['regkey']){
				// table recycling
				$attempts = $sql->resultq("SELECT attempt FROM failed_logins WHERE ip = '".$_SERVER['REMOTE_ADDR']."'");
				
				if (!$attempts){
					$sql->query("INSERT INTO failed_logins (ip, attempt) VALUES ('".$_SERVER['REMOTE_ADDR']."', 1)");
					trigger_error("Failed registration attempt #1 for IP ".$_SERVER['REMOTE_ADDR'], E_USER_NOTICE);
				}
				else if ($attempts<4){
					$sql->query("UPDATE failed_logins SET attempt=".($attempts+1)." WHERE ip = '".$_SERVER['REMOTE_ADDR']."'");
					trigger_error("Failed registration attempt #".($attempts+1)." for IP ".$_SERVER['REMOTE_ADDR'], E_USER_NOTICE);
				}
				else{
					ipban("Regkey", "Auto IP-Banned ".$_SERVER['REMOTE_ADDR']." for 5 failed registration attempts (Incorrect regkey).");
					errorpage("You have <i><u>not<u></i> been registered.<br/>Click <a href='?'>here</a> to know why.");
				}
				
				errorpage("Wrong registration key.");
			}
			else if (!$rereggie) $sql->query("DELETE from failed_logins WHERE ip = '".$_SERVER['REMOTE_ADDR']."'"); // prevent a loophole
			//else errorpage("No, don't think this will delete the failed_logins counter.");
		}
	
		// OK
		$sql->start();
		
		$newuser = $sql->prepare("INSERT INTO users (name, password, lastip, dateformat, timeformat, since) VALUES (?,?,?,?,?,?)");
		$c[] = $sql->execute($newuser, array($user, password_hash($pass, PASSWORD_DEFAULT), $_SERVER['REMOTE_ADDR'], $config['default-date-format'], $config['default-time-format'], ctime()) );
		
		if ($sql->finish($c)){
			$sql->query("INSERT INTO users_rpg () VALUES ()");
			$id = $sql->resultq("SELECT MAX(id) FROM users");
			$sql->query("ALTER TABLE new_posts ADD COLUMN user$id int(32) NOT NULL DEFAULT '1'");
			$sql->query("ALTER TABLE new_announcements ADD COLUMN user$id int(32) NOT NULL DEFAULT '1'");
			
			// Remove the "new" value from all past posts
			$sql->query("UPDATE new_posts SET user$id = 0");
			$sql->query("UPDATE new_announcements SET user$id = 0");
			
			
			mkdir("userpic/$id");
			trigger_error("New user: $user (".$config['board-url']."profile.php?id=$id)", E_USER_NOTICE);
			errorpage("You have been registered.<br/>Click <a href='login.php'>here</a> to log in.");
		}
		else errorpage("An unknown error occurred during registration.");
	}
	
	pagefooter();
?>