<?php

	require "lib/function.php";

	$action = filter_string($_POST['action']);
	
	if (!$action){
		
		if ($loguser['id'] && !powlcheck(4)){
			header("Location: index.php");
			die("<!--");
		}
		
		pageheader("Register");
		
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
					<small>Maximum length: 25 Characters. Can contain only alphanumeric characters.</small>
				</td>
				<td class='dim'>
					<input type='text' style='width:230px' name='user' maxlength=25 autocomplete=off>
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
		
		$isadmin = powlcheck(4);
		
		if (filter_string($_POST['notreally']) || filter_string($_POST['akafield']) || filter_string($_POST['sinkfield'])){
			if ($isadmin) errorpage("<h1>It works!</h1>");
			ipban("", "Auto IP Banned ".$_SERVER['REMOTE_ADDR']." for filling in dummy registration fields.");
			errorpage("I don't think you know what a warning is.");
		}
		
		$user = addslashes(filter_string($_POST['user']));
		$pass = filter_string($_POST['pass']);
		
		if (!$user || !$pass)
			errorpage("You have left either the username or password fields empty!");
		
		if ($pass != filter_string($_POST['pass2']))
			errorpage("The password and the retype don't match.");
		
		if (strlen($user)>25) // Just to make sure
			errorpage("No (the actual limit in the database is 32, but still).");
			
		$check = preg_replace('/[^\da-z]/i', '', $user);
		if ($check !== $user)
			errorpage("Your username contains non-alphanumeric characters.");
		
		if (strlen($pass)<8)
			errorpage("The password should be at least 8 characters long.");
		
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
		
		// OK
		$sql->start();
		
		$newuser = $sql->prepare("INSERT INTO users (name, password, lastip, dateformat, timeformat, since) VALUES (?,?,?,?,?,?)");
		$c[] = $sql->execute($newuser, array($user, password_hash($pass, PASSWORD_DEFAULT), $_SERVER['REMOTE_ADDR'], $config['default-date-format'], $config['default-time-format'], ctime()) );
		
		if ($sql->finish($c)){
			$id = $sql->resultq("SELECT MAX(id) FROM users");
			mkdir("userpic/$id");
			trigger_error("New user: $user (".$config['board-url']."profile.php?id=$id)");
			errorpage("You have been registered.<br/>Click <a href='login.php'>here</a> to log in.");
		}
		else errorpage("An unknown error occurred during registration.");
	}
	
	pagefooter();
?>