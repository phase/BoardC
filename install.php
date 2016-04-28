<?php

	require "lib/config.php";
	
	function filter_bool(&$bool){
		if (!isset($bool)) return false;
		else return (bool) $bool;
	}
	
	function filter_int(&$int){
		if (!isset($int)) return 0;
		else return (int) $int;
	}
	
	function filter_string(&$string){
		if (!isset($string)) return "";
		else return (string) $string;
	}
	
	function ctime(){return time()+$GLOBALS['config']['default-time-zone'];}
	
	function query($q){
		// it's almost like $fw->banflags()
		global $sql, $errors, $q_errors, $ok;
		print "$q";
		$res = $sql->exec($q);
		if ($res === false) {$errors++; $q_errors[] = $q; print " NG!\n";}
		else {$ok++; print " OK!\n";}
	}
	
	function dialog($desc, $contents, $buttons, $title="Installer"){
		die("<!doctype html>
		<head>
			<title>BoardC Installer</title>
			<style type='text/css'>
			body {
				background: #999;
				font-family: Verdana, Geneva, sans-serif;
				font-size: 13px;
				color: #fff;
			}
			a{
				text-decoration: none;
				font-weight: bold;
			}
			table.special{
				border: solid 1px #000;
				color: #000;
			}
			
			.c{
				text-align: center;
			}
			.w{
				width: 100%;
			}
			.head{
				background: #BBB;
			}
			.dim{
				background: #EEE;
			}
			.light{
				background: #FFF;
			}
			.dark{
				background: #DDD;
			}
			</style>
			<body>
			<center><div style='height: 30vh'>PRE-RELEASE VERSION</div><form method='POST' action='install.php'><table class='special'>
				<tr>
					<td class='head c'><center><b>$title</b></center></td>
				</tr>
				<tr><td class='light c'>$desc</td></tr>
				<tr><td class='dim'><center>$contents</center></td></tr>
				<tr><td class='dark c'>$buttons</td></tr>
			</table></form></center>
			</body>
		</head>");
	}
	
	$step = filter_int($_POST['step']);
	
	if ($step){
		require "lib/mysql.php";
		$sql = new mysql;
		$connection = $sql->connect($sqlhost,$sqluser,$sqlpass,$sqlpersist);
	}
	if (!$step){
		dialog(	"This will setup BoardC Pre-Release v0.17a",
				"BoardC will be configured under these settings:<br/><br/>

					<table class='special head'>
					<tr><td class='light'>SQL Host:</td><td class='light'>$sqlhost</td></tr>
					<tr><td class='light'>SQL User:</td><td class='light'>$sqluser</td></tr>
					<tr><td class='light'>SQL Password:</td><td class='light'>$sqlpass</td></tr>
					<tr><td class='light'>SQL Database:</td><td class='light'>$sqldb</td></tr>
					<tr><td class='light'>Deleted User ID:</td><td class='light'>".$config['deleted-user-id']."</td></tr>
					</table><br/>
					
				If these are correct, click 'Continue'. Otherwise, edit lib/config.php",
				"<input type='submit' name='start' value='Continue'><input type='hidden' name='step' value=1>");
	}				
	else if ($step == 1){
		
		dialog(	"Enter User ID #1 Login info",
		"This will be used to login to the board.<br/><br/>

			<table class='special head'>
			<tr><td class='light'>Username:</td><td class='light'><input type='text' name='username'></td></tr>
			<tr><td class='light'>Password:</td><td class='light'><input type='text' name='pass1'></td></tr>
			<tr><td class='light'>Retype Password:</td><td class='light'><input type='text' name='pass2'></td></tr>
			</table><br/>
			
		Click continue to start executing the SQL commands. This may take more than 30 seconds.<br/>WARNING: This will drop the specified database!",
		"<input type='submit' name='start' value='Continue'><input type='hidden' name='step' value=2>");
	}
	else if ($step == 2){
		$name = filter_string($_POST['username']);
		$pass1 = filter_string($_POST['pass1']);
		$pass2 = filter_string($_POST['pass2']);
		
		$return = "<input type='submit' name='start' value='Return'><input type='hidden' name='step' value=1>";
		
		if (!$name) dialog("", "You have left the username field empty!", $return, "Error");
		if (!$pass1) dialog("", "You have left the password field empty!", $return, "Error");
		if ($pass1 != $pass2) dialog("", "The passwords you entered don't match.", $return, "Error");
		
		// Here we go
		print "<!doctype html><title>Installer</title><body style='background: #008; color: #fff;'>
		<pre><b style='background: #fff; color: #008'>BoardC Installer</b>\n\n";

		$sql->query("DROP DATABASE IF EXISTS `$sqldb`");	
		$sql->start();
		
		$errors = 0;
		$q_errors = array();
		$ok = 0;
		
		set_time_limit(0); //Fatal error:  Maximum execution time of 30 seconds exceeded in C:\xampp\htdocs\boardx\lib\mysql.php on line 128
		
query("
CREATE DATABASE `$sqldb`; DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;");
$sql->selectdb($sqldb);
query("
CREATE TABLE `categories` (
  `id` int(32) NOT NULL,
  `name` varchar(128) NOT NULL,
  `powerlevel` int(1) NOT NULL DEFAULT '0',
  `ord` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
INSERT INTO `categories` (`id`, `name`, `powerlevel`, `ord`) VALUES (1, 'Main', 0, 1);");
query("
CREATE TABLE `failed_logins` (
  `id` int(32) NOT NULL,
  `ip` varchar(32) NOT NULL,
  `attempt` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
CREATE TABLE `forummods` (
  `id` int(32) NOT NULL,
  `fid` int(32) NOT NULL,
  `uid` int(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
CREATE TABLE `forums` (
  `id` int(32) NOT NULL,
  `name` varchar(256) NOT NULL,
  `title` varchar(256) NOT NULL,
  `powerlevel` int(1) NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `threads` int(32) NOT NULL DEFAULT '0',
  `posts` int(32) NOT NULL DEFAULT '0',
  `category` int(32) NOT NULL DEFAULT '0',
  `ord` int(32) NOT NULL DEFAULT '0',
  `theme` int(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
INSERT INTO `forums` (`id`, `name`, `title`, `powerlevel`, `hidden`, `threads`, `posts`, `category`, `ord`) VALUES
(1, 'General forum', 'For everybody!', 0, 0, 0, 0, 1, 1),
(2, 'General staff forum', 'Not for everybody!', 2, 0, 0, 0, 1, 0);");
query("
CREATE TABLE `ipbans` (
  `id` int(32) NOT NULL,
  `ip` varchar(32) DEFAULT NULL,
  `time` int(32) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
CREATE TABLE `jstrap` (
  `id` int(32) NOT NULL,
  `user` int(32) NOT NULL,
  `ip` varchar(32) NOT NULL,
  `source` text NOT NULL,
  `filtered` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
CREATE TABLE `misc` (
  `disable` tinyint(1) NOT NULL,
  `views` int(32) NOT NULL,
  `theme` int(32) DEFAULT NULL,
  `threads` int(32) NOT NULL DEFAULT '0',
  `posts` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `misc` (`disable`, `views`, `theme`, `threads`, `posts`) VALUES ('0', '0', NULL, '0', '0');");
query("
CREATE TABLE `hits` (
  `id` int(32) NOT NULL,
  `ip` varchar(32) NOT NULL,
  `time` int(32) NOT NULL,
  `page` text NOT NULL,
  `useragent` text NOT NULL,
  `user` int(32) NOT NULL DEFAULT '0',
  `forum` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
query("
CREATE TABLE `posts` (
  `id` int(32) NOT NULL,
  `text` text NOT NULL,
  `time` int(32) NOT NULL,
  `thread` int(32) NOT NULL,
  `user` int(32) NOT NULL,
  `rev` int(4) NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `nohtml` tinyint(1) NOT NULL DEFAULT '0',
  `nosmilies` tinyint(1) NOT NULL DEFAULT '0',
  `nolayout` tinyint(1) NOT NULL DEFAULT '0',
  `lastedited` int(32) NOT NULL DEFAULT '0',
  `avatar` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
CREATE TABLE `posts_old` (
  `id` int(32) NOT NULL,
  `pid` int(32) NOT NULL,
  `text` text NOT NULL,
  `time` int(32) NOT NULL,
  `rev` int(4) NOT NULL,
  `nohtml` tinyint(1) NOT NULL DEFAULT '0',
  `nosmilies` tinyint(1) NOT NULL DEFAULT '0',
  `nolayout` tinyint(1) NOT NULL DEFAULT '0',
  `avatar` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
CREATE TABLE `ratings` (
  `id` int(32) NOT NULL,
  `userfrom` int(32) NOT NULL,
  `userto` int(32) NOT NULL,
  `rating` int(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
query("
CREATE TABLE `shop_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `title` varchar(128) NOT NULL,
  `ord` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
query("
INSERT INTO `shop_categories` (`id`, `name`, `title`, `ord`) VALUES
(1, 'Sample category', 'This is a sample description', 0);");
query("
CREATE TABLE `shop_items` (
  `id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `title` text NOT NULL,
  `cat` int(32) NOT NULL,
  `hp` varchar(32) NOT NULL,
  `mp` varchar(32) NOT NULL,
  `atk` varchar(32) NOT NULL,
  `def` varchar(32) NOT NULL,
  `intl` varchar(32) NOT NULL,
  `mdf` varchar(32) NOT NULL,
  `dex` varchar(32) NOT NULL,
  `lck` varchar(32) NOT NULL,
  `spd` varchar(32) NOT NULL,
  `coins` varchar(32) NOT NULL DEFAULT '0',
  `gcoins` varchar(32) NOT NULL DEFAULT '0',
  `special` int(32) NOT NULL DEFAULT '0',
  `ord` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
query("
INSERT INTO `shop_items` (`id`, `name`, `title`, `cat`, `hp`, `mp`, `atk`, `def`, `intl`, `mdf`, `dex`, `lck`, `spd`, `coins`, `gcoins`, `special`, `ord`) VALUES
(1, 'Test item?', 'It does not actually do anything! (or is it?)', 1, '+1000', '-10', 'x45', '/2', '+2', '+0', '+56', '+9999', '+1', '0', '0', 1, 0);");
query("
CREATE TABLE `themes` (
  `id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `file` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
query("
INSERT INTO `themes` (`id`, `name`, `file`) VALUES
(0, 'Default', 'default.css'),
(1, 'Night (Jul)', 'night.css');");
query("
CREATE TABLE `threads` (
  `id` int(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `time` int(32) NOT NULL,
  `forum` int(32) NOT NULL,
  `user` int(32) NOT NULL,
  `sticky` tinyint(1) NOT NULL,
  `closed` tinyint(1) NOT NULL,
  `views` int(32) NOT NULL DEFAULT '0',
  `replies` int(32) NOT NULL DEFAULT '0',
  `icon` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
CREATE TABLE `tor` (
  `id` int(32) NOT NULL,
  `ip` varchar(32) NOT NULL,
  `time` int(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
CREATE TABLE `users` (
  `id` int(32) NOT NULL,
  `name` varchar(32) NOT NULL,
  `displayname` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `powerlevel` int(1) NOT NULL DEFAULT '0',
  `sex` int(1) NOT NULL DEFAULT '2',
  `namecolor` varchar(6) DEFAULT NULL,
  `lastip` varchar(32) DEFAULT NULL,
  `ban_expire` int(32) DEFAULT '0',
  `since` int(32) NOT NULL DEFAULT '0',
  `ppp` int(3) NOT NULL DEFAULT '25',
  `tpp` int(3) NOT NULL DEFAULT '25',
  `head` text,
  `sign` text,
  `dateformat` varchar(32) DEFAULT NULL,
  `timeformat` varchar(32) DEFAULT NULL,
  `lastview` int(32) NOT NULL DEFAULT '0',
  `lastforum` int(32) NOT NULL DEFAULT '0',
  `bio` text,
  `posts` int(32) NOT NULL DEFAULT '0',
  `threads` int(32) NOT NULL DEFAULT '0',
  `email` varchar(64) NOT NULL,
  `homepage` varchar(64) NOT NULL,
  `youtube` varchar(64) NOT NULL,
  `twitter` varchar(64) NOT NULL,
  `facebook` varchar(64) NOT NULL,
  `homepage_name` varchar(64) NOT NULL,
  `tzoff` int(2) NOT NULL DEFAULT '0',
  `realname` varchar(64) NOT NULL,
  `location` varchar(64) NOT NULL,
  `birthday` int(32) DEFAULT NULL,
  `theme` int(8) NOT NULL DEFAULT '0',
  `showhead` tinyint(1) NOT NULL DEFAULT '1',
  `signsep` int(3) NOT NULL DEFAULT '1',
  `icon` text,
  `coins` int(32) NOT NULL DEFAULT '0',
  `gcoins` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
query("
INSERT INTO `users` (`id`, `name`, `password`, `lastip`, `dateformat`, `timeformat`, `since`, `powerlevel`) VALUES
('1', '$name','".password_hash($pass1, PASSWORD_DEFAULT)."','".$_SERVER['REMOTE_ADDR']."','".$config['default-date-format']."','".$config['default-time-format']."','".ctime()."', '5'),
('".$config['deleted-user-id']."', 'Deleted user', 'rip','".$_SERVER['REMOTE_ADDR']."','".$config['default-date-format']."','".$config['default-time-format']."','".ctime()."', '-2');
");
query("
CREATE TABLE `users_rpg` (
  `id` int(11) NOT NULL,
  `hp` int(32) NOT NULL DEFAULT '1',
  `mp` int(32) NOT NULL DEFAULT '1',
  `atk` int(32) NOT NULL DEFAULT '1',
  `def` int(32) NOT NULL DEFAULT '1',
  `intl` int(32) NOT NULL DEFAULT '1',
  `dex` int(32) NOT NULL DEFAULT '1',
  `lck` int(32) NOT NULL DEFAULT '1',
  `spd` int(32) NOT NULL DEFAULT '1',
  `mdf` int(32) NOT NULL DEFAULT '1',
  `item1` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
query("
INSERT INTO users_rpg (`id`, `hp`, `mp`, `atk`, `def`, `intl`, `dex`, `lck`, `spd`, `mdf`) VALUES
('1', '1', '1', '1', '1', '1', '1', '1', '1', '1'),
('".$config['deleted-user-id']."', '0', '0', '0', '0', '0', '0', '0', '0', '0');");
query("
CREATE TABLE `user_avatars` (
  `id` int(32) NOT NULL,
  `user` int(32) NOT NULL,
  `file` int(16) NOT NULL,
  `title` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
");
query("
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `failed_logins`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `forummods`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `forums`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `ipbans`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `jstrap`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `hits`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `posts_old`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `threads`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `tor`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `user_avatars`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `shop_categories`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `shop_items`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `users_rpg`
  ADD PRIMARY KEY (`id`);
");
query("
ALTER TABLE `categories`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `failed_logins`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT; 
ALTER TABLE `forummods`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
ALTER TABLE `forums`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `ipbans`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
ALTER TABLE `jstrap`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
ALTER TABLE `hits`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
ALTER TABLE `posts`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `posts_old`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ratings`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
ALTER TABLE `threads`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `tor`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=".($config['deleted-user-id']+1).";
ALTER TABLE `user_avatars`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
ALTER TABLE `shop_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `shop_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `users_rpg`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=".($config['deleted-user-id']+1).";
");
		
		print "\n\nQueries: ".($ok+$errors)." | Errors: $errors\n";
		
		if (!$errors){
			$c = $sql->end();
			if ($c !== false){
				if (!file_exists("userpic")) mkdir("userpic");
				die("Operation completed successfully.\nYou can (and <i>should</i>) delete this file and login <a href='login.php' style='background: #fff'>here</a>.");
			}
			else die("An unknown error occurred while closing the transaction.");
		}
		else{
			$sql->undo();
			die("Installation failed.\n\n<small>Failed queries: ".implode("\n", $q_errors)."</small>");
		}
		
	}
	
		
		
	
	
?>
