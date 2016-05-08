<?php
	
	// Common board actions are now done here
	error_reporting(0);	// Suppress everything, including fatal errors (the integrated error handler will be used instead)
	ini_set("default_charset", "UTF-8");

	
	$startingtime = microtime(true);
	$errors = array();
	$userfields = "u.name, u.displayname, u.sex, u.powerlevel, u.namecolor, u.icon, u.id"; // consistency is god
	
	require "lib/config.php";
	require "lib/mysql.php";
	require "lib/helpers.php";
	require "lib/rpg.php";
	require "lib/layout.php";
	require "lib/threadpost.php";
	
	
	// Database connection. It handles the give up message by itself.
	$sql = new mysql;
	$connection = $sql->connect($sqlhost,$sqluser,$sqlpass,$sqlpersist);
	$sql->selectdb($sqldb);
	unset($sqlhost,$sqluser,$sqlpass,$sqldb,$sqlpersist);
	

	set_error_handler('error_reporter');

	
	if (ini_get("register_globals"))
		die("Please update your PHP version.");
	
	if (get_magic_quotes_gpc())
		die("If the magic quotes are turned on, it's likely the PHP version you're using is too low for the board to run.");
	
	
	// placeholder functions for compatibility
	if (!function_exists("password_hash")){
		function password_hash($source, $dumb="insecure"){return sha1($source);}
	}
	
	//cache is bad
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Pragma: no-cache");
	
	// Stop this nonsense, leave $loguser available from here
	$loguser = array(
		'id' => 0,
		'password' => NULL,
		'powerlevel' => 0,
		'ppp' => 25,
		'tpp' => 25,
		'dateformat' => $config['default-date-format'],
		'timeformat' => $config['default-time-format'],	
		'tzoff'		 => 0,
		'theme'		 => 1,
	);
	
	/* Do not uncomment yet
	if ((int) date('Gi')<5)
		errorpage("Backup in progress...");	
	*/
	
	//update timed bans
	$sql->query("
	UPDATE users
	SET ban_expire=0,powerlevel=0
	WHERE ban_expire <> 0
	AND powerlevel='-1'
	AND ban_expire-".ctime()."<0");

	$ipbanned = $sql->fetchq("SELECT id, reason FROM `ipbans` WHERE `ip` = '".$_SERVER['REMOTE_ADDR']."'");
	if (filter_int($ipbanned['id'])){
		
		$reason = filter_string($ipbanned['reason']);
		
		if ($reason == "Password")
			errorpage("It seems you have failed 5 login attempts.<br/><br/>If you have lost your password, send an email at ".$config['admin-email']." for password recovery.");
		else if (!$reason)
			$reason = "Unspecified reason";
		
		$views = $sql->resultq("SELECT views FROM misc");
		
		errorpage("You have been banned from the board for the following reason:<br/>$reason");
	}

	$tor = $proxy = $bot = 0;
	$banflags = $request = array();
	
	if (!filter_bool($pmeta['nofw'])){
		if (file_exists("lib/firewall_new.php") && $config['enable-firewall']){
			require "lib/firewall_new.php";
			$fw_error = "";
		}
		else{
			$fw_error = "<div style=\"text-align: center; color: #f00; padding: 3px; border: 5px dashed red; background: #000;\"><b>HEY YOU! STOP RESTING AND REWRITE THE FIREWALL!<!--WARNING: Firewall missing or disabled--></b></div>";
			class firewall{public function __call($a=null,$b=null){return false;} public function __get($a=null){return false;}}
		}
		
		$fw = new firewall;
	
	if ($fw->dead || isset($_GET['sec']))
		$fw->minilog();
	
	}
	
	if (strpos(strtolower(filter_string($_SERVER['HTTP_X_REQUESTED_WITH'])),'xmlhttprequest')!== false)
		$misc['ajax_request'] = true;
	else $misc['ajax_request'] = false;
	

	// saved from the old firewall, remove this bit when the new one is finished
	if (count($_FILES))
		if (!$config['enable-file-uploads'])
			errorpage("File uploads are disabled. Now kindly go away.");
	
	

	
	foreach ($_POST as $sgname => $sgval)
		$_POST[$sgname] = sgfilter($sgval);
		
	unset($sgname, $sgval);
	
	$_SERVER['QUERY_STRING'] 	= sgfilter($_SERVER['QUERY_STRING']);
	$_SERVER['HTTP_REFERER'] 	= sgfilter($_SERVER['HTTP_REFERER']);
	$_SERVER['HTTP_USER_AGENT'] = sgfilter($_SERVER['HTTP_USER_AGENT']);

	# Load user
	
	// Guest perms moved up
	$miscdata = $sql->fetchq("SELECT * FROM misc");
		
	
	if (!($bot || $proxy || $tor)){
		$views=$miscdata['views']+1;
		$sql->query("UPDATE misc SET views=$views");
	}
	
	if (filter_int($_COOKIE['id']) && filter_string($_COOKIE['verify'])){
		
		if ($bot || $tor || $proxy){
			setcookie('id', NULL);
			setcookie('verify', NULL);
			setcookie('fid', filter_int($_COOKIE['fid'])+1);
			errorpage("What do you think you're doing?");
		}
		
		$userdata = $sql->fetchq("
		SELECT u.*,r.*
		FROM users u
		LEFT JOIN users_rpg r
		ON u.id = r.id
		WHERE u.id = ".intval($_COOKIE['id']), true)[0];
		
		if ($_COOKIE['verify'] == $userdata['password']){
			$loguser = $userdata;
			if ($loguser['lastip'] != $_SERVER['REMOTE_ADDR']){
				
				trigger_error("User ".$loguser['name']." (ID #".$loguser['id']." changed IP from ".$loguser['lastip']." to ".$_SERVER['REMOTE_ADDR'], E_USER_NOTICE);
				if ($sql->resultq("SELECT 1 FROM ipbans WHERE ip = ".$loguser['lastip'])){ // Just in case
					trigger_error("Previous IP address was IP banned - updated IP bans list.", E_USER_NOTICE);
					ipban("Auto - IP ban evasion", false);
					header("Location: index.php");
				}
				$loguser['lastip'] = $_SERVER['REMOTE_ADDR'];
			}
		}
		else {
			setcookie('id', NULL);
			setcookie('verify', NULL);
		//	$fw->minilog();
		}
		
		unset($userdata);
	}


	if ($config['force-userid'])
		$loguser = $sql->fetchq("SELECT * FROM users WHERE id = ".$config['force-userid'], true)[0];
	
	if ($config['admin-board'])
		$loguser['powerlevel'] = 5;
	
	if ($loguser['powerlevel'] == 5)
		$adminips[] = $_SERVER['REMOTE_ADDR'];
	
	else if (in_array($_SERVER['REMOTE_ADDR'], $adminips))
		$loguser['powerlevel'] = 5;
	
	if ($loguser['powerlevel'] == 5)
		$config['show-comments'] = true;
	
	// with the powerlevels set up, register now the shutdown function
	register_shutdown_function('error_printer', false, powlcheck(5), $GLOBALS['errors']);
	
	if (!$loguser['timeformat'])
		$loguser['timeformat'] = $config['default-time-format'];
	if (!$loguser['dateformat'])
		$loguser['dateformat'] = $config['default-date-format'];

	if ($miscdata['disable']){
		if (powlcheck(4))
			$fw_error = "<div style=\"text-align: center; color: #0f0; padding: 3px; border: 5px dotted #0f0; background: #000;\"><b>Notice: This board has been disabled.</b></div>$fw_error";
		
		else
			dialog(
			"The board is offline",
			"The board is (currently) offline",
			"The board is under manteniance, likely due to one of the following reasons.
				<ul>
					<li> Preventing something wrong from happening
					<li> Codebase upgrade
					<li> It's fun to disable the board (???)
					<small><li> Testing if the ACP works properly</small>
				</ul>
				
				<center>In the mean time, join <b>#nktest</b> on <b>irc.badnik.net</b>.</center>"
			);
	}	
	
	// RPG Stuff
	$q = getuseritems($loguser);
	
	if (!empty($q)){
		$itemdb = $sql->query("
		SELECT hp, mp, atk, def, intl, mdf, dex, lck, spd, special
		FROM shop_items
		WHERE id IN (".implode(", ", $q).")
		");
		
		while ($item = $sql->fetch($itemdb))
			if ($item['special'] == 3) $config['show-comments'] = true;
		
		unset($itemdb, $q);
	}

	
	// First character is always /
	if (!stripos($_SERVER['PHP_SELF'], "forum.php") && !stripos($_SERVER['PHP_SELF'], "thread.php"))
		update_hits();

	
	if (isset($_GET['pupd'])){
		
		$i = $sql->query("SELECT id FROM posts");
		
		while ($id = $sql->fetch($i))
			$sql->query("INSERT INTO new_posts (id) VALUES (".$id['id'].")");

		$i = $sql->query("SELECT id FROM announcements");
		
		while ($id = $sql->fetch($i))
			$sql->query("INSERT INTO new_announcements (id) VALUES (".$id['id'].")");
		
		x_die("Done.");
		
	}

	
	
?>