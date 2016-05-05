<?php

	/*
	this file could be possibly split
	
	currently this contains all the functions, except for the mysql and firewall classes
	
	possible post-split files:
	function.php
	threadpost.php
	layout.php
	helpers.php
	rpg.php
	*/
	
	// Suppress everything, including fatal errors (the integrated error handler will be used instead)
	
	error_reporting(0);	
	ini_set("default_charset", "UTF-8");

	
	$startingtime = microtime(true);
	$errors = array();
	$userfields = "u.name, u.displayname, u.sex, u.powerlevel, u.namecolor, u.icon, u.id"; // consistency is god
	
	require "lib/config.php";
	require "lib/mysql.php";
		
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
	
	
	function sgfilter(&$source){
		
		if (is_array($source)) return $source; // todo?
		
		if (filter_bool($GLOBALS['pmeta']['noreplace']))
			return $source;
	
		$result = $source;
		
		// Control Codes
		$result = trim($result, "\x00..\x1F"); //always remove \x00 as it's internally used as a separator for other stuff (ie: poll data)
		$result = trim($result, "\x7F");		
		
		//Unicode Control Codes
		$result = str_replace("\xC2\xA0","\x20", $result);
		$result = trim_loop($result, "\xC2\x80..\xC2\x9F");
		
		// Entities
		$result = html_entity_decode($result, ENT_NOQUOTES, 'UTF-8'); //No standard HTML entities
		$result = preg_replace("'(&#x?([0-9]|[a-f])+[;>])'si", "<img src='images/coin.gif'>", $result);// Remove this entirely, potential attack vector
		
		return $result;
	}
	
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
		
		else{
			x_die ("
		<!doctype html>
		<head>
			<title>The board is offline</title>
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
			
			table.c, td.c{
				text-align: center;
			}
			table.w, td.w{
				width: 100%;
			}
			td.head{
				background: #BBB;
			}
			td.dim{
				background: #EEE;
			}
			td.light{
				background: #FFF;
			}
			td.dark{
				background: #DDD;
			}
			</style>
			<body>
			<center><div style='height: 32vh'></div><table class='special'>
				<tr>
					<td class='head'><center><b>The board is (currently) offline</b></center></td>
				</tr>
				<tr>
					<td class='dim'>
					The board is under manteniance, likely due to one of the following reasons.
						<ul>
						<li> Preventing something wrong from happening
						<li> Codebase upgrade
						<li> It's fun to disable the board (???)
						<small><li> Testing if the ACP works properly</small>
					</ul>
					
					<center>In the mean time, join <b>#nktest</b> on <b>irc.badnik.net</b>.</center>
					</td>
				</tr>
			</table></center>
			</body>
		</head>
		");
		}
	}	
	
	// RPG stuff
	function dorpgstatus($user){
		return "
			<table class='main' style='width: 256px;'>
				<tr><td class='head c'>RPG status TEMP</td></tr>
				<tr><td class='light c' style='height: 212px;'>

					<img src='images/coin.gif'> - ".$user['coins']." | 
					<img src='images/coin2.gif'> - ".$user['gcoins']."<br/>
					HP: ".$user['hp']."<br/>
					MP: ".$user['mp']."<br/>
					Atk: ".$user['atk']."<br/>
					Def: ".$user['def']."<br/>
					Int: ".$user['intl']."<br/>
					MDf: ".$user['mdf']."<br/>
					Dex: ".$user['dex']."<br/>
					Lck: ".$user['lck']."<br/>
					Spd: ".$user['spd']."<br/>
					
					<font color=red>Image not implemented</font>
					
				</td></tr>
			</table>";
	}
	
	function getuseritems($user){
		global $sql;
		for($i=0, $max=$sql->resultq("SELECT MAX(id) FROM shop_categories"); $i<$max+1; $i++)
			if (filter_int($user["item$i"]))
				$q[] = $user["item$i"];
		return isset($q) ? $q : false;
	}

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
	
	// Online users update
	function update_hits($forum = 0){
		global $loguser, $sql;
		$sql->queryp("INSERT INTO hits (user, ip, time, page, useragent, forum, referer) VALUES (?,?,?,?,?,?,?)", array($loguser['id'], $_SERVER['REMOTE_ADDR'], ctime(), $_SERVER['REQUEST_URI'], $_SERVER['HTTP_USER_AGENT'], $forum, $_SERVER['HTTP_REFERER']));
		if ($loguser['id'])	$sql->query("UPDATE users SET lastview = ".ctime()." WHERE id = ".$loguser['id']);
	}
	
	// First character is always /
	if (!stripos($_SERVER['PHP_SELF'], "forum.php") && !stripos($_SERVER['PHP_SELF'], "thread.php"))
		update_hits();
	
	
	// Powerlevel Checking
	
	function powlcheck($minpowl){
		global $loguser, $config;
		
		if ($loguser['powerlevel']<$minpowl /*&& !$config['admin-board'] */)
			return false;
		else return true;
		
	}
	
	function canviewforum($fid){
		global $sql;
		
		$minpower = $sql->resultq("SELECT powerlevel FROM forums WHERE id = ".filter_int($fid));
		if ($minpower === false) return false;
		else return powlcheck($minpower);
	}
	
	function ismod($forum = false){
		global $sql, $loguser;
		
		if ($forum !== false) // Account for forum mods
			$makemealocalmod = $sql->query("SELECT 1 FROM forummods WHERE (fid = $forum AND uid = ".$loguser['id'].")");
		else $makemealocalmod = false;
		
		return (powlcheck(2) || $makemealocalmod) ? true : false;
	}

	function donamecolor($powl, $sex, $usercolor = false){
		
		static $colors;
				
		if (!$usercolor){
			// User colors now defined by CSS, hardcoded stuff not needed anymore
			if ($powl>4) $powl = 4;
			//if ($powl<0) $powl = '-1';
			return "class='nmcol$powl$sex'";
			/*
			// 0   -> color
			// powl -> sex
			if (!$colors)
				$colors = array(
					'-2' => array("666666","666666","666666"),
					'-1' => array("888888","888888","888888"),
					0 => array("97ACEF","F185C9","7C60B0"), //Normal
					1 => array("D8E8FE","FFB3F3","EEB9BA"), //Privileged
					2 => array("D8E8FE","FFB3F3","EEB9BA"), //Staff
					3 => array("AFFABE","C762F2","7C60B0"), //Global Mod
					4 => array("FFEA95","C53A9E","F0C413"), //Admin
				);
			

			
			
			$usercolor = $colors[$powl][$sex];*/
			
		}
		return "style='color:#$usercolor; !important'";
	}
	
	function makeuserlink($uid, $u = NULL, $showicon = false){
		global $sql, $loguser, $userfields;
		static $udb = array();
		
		if (!$u){
			if (!isset($udb[$uid])){
				$u = ($uid == $loguser['id']) ?	$loguser : $sql->fetchq("SELECT $userfields FROM users u WHERE u.id = ".intval($uid));
				$udb[$uid] = $u;
			}
			else $u = $udb[$uid];
		}
		
		if ($uid) $u['id'] = $uid; // hack for compatibility, allows to remove useless code

		$icon = isset($u['icon']) && $showicon ? "<img src='".$u['icon']."'>" : "";
		
		if (!$u) return "<a class='danger'>(Invalid Userlink)</a>";
		
		if ($u['displayname']){
			$name = htmlspecialchars($u['displayname']);
			$title = "title='Also known as: ".htmlspecialchars($u['name'])."'";
		}
		else{
			$name = $u['name'];
			$title = "";
		}
		// 0 male, 1 female, 2 unspec
		
		$linkcolor = donamecolor($u['powerlevel'], $u['sex'], $u['namecolor']);
		
		return "<a href='profile.php?id=".$u['id']."' $linkcolor $title>$icon $name</a>";
	}

	function onlineusers($forum = false){
		global $sql, $bot, $proxy, $tor, $userfields;
		
		$bots = "[NUM]"; // TEMP
		


		// TODO: in online db specify if it's a bot, proxy or tor
		
		$online = $sql->query("
			SELECT h.forum, h.ip, f.id fid, f.name fname, $userfields
			FROM hits h
			
			LEFT JOIN users u ON h.user = u.id
			LEFT JOIN forums as f ON h.forum=f.id
			
			WHERE h.time>".(ctime()-300)."
			".($forum ? "AND h.forum = $forum" : "")."
			ORDER BY h.time DESC
		");
		$txt = "";
		
		$users = 0;
		$guests = 0;
		$fname = NULL;
		$txt = $ipdb = $udb = array();

		while($x = $sql->fetch($online)){
			/*
			a separate check is needed for users and guests
			
			as using an unified IP check would make show up twice
			users who for some reason have their IP changed
			*/
			
			if ($x['id']){ // user
				if (filter_bool($udb[$x['id']])) continue; // don't count same users twice
				else $udb[$x['id']] = true;
					
				$txt[] = makeuserlink(false, $x, true);
				$users++;
			}
			else{
				if (filter_bool($ipdb[$x['ip']])) continue; // also don't count same guests twice
				else $ipdb[$x['ip']] = true;
				
				$guests++;
			}
			
			if (!isset($fname))	$fname = $x['fname'];
		}
		
		$txt = implode(", ", $txt);

		
		$extra = powlcheck(2) ? "<!-- ([NUM] bots | [NUM] proxies | [NUM] tor users) -->" : "";
		$where = $forum ? "in $fname" : "online";
		$p = ($users==1) ? "" : "s";
		$k = ($guests==1) ? "" : "s";
		$txt = $txt ? ": $txt" : "";
		
		return "$users user$p currently $where$txt | $guests guest$k $extra";
		
	}
	
	function doforumjump($id = 0, $welp = false){
		global $sql, $loguser;
		
		$txt = "";
		$cat = NULL;
		
		$select[$id] = "selected";
		
		$hidden = powlcheck(3) ? "" : "AND (f.hidden=0 OR f.id = $id)";
		
		$forums = $sql->query("
		SELECT f.id, f.name, f.category, c.name catname
		FROM forums f
		LEFT JOIN categories c
		ON f.category = c.id
		WHERE (f.powerlevel<=".$loguser['powerlevel']." AND c.powerlevel<=".$loguser['powerlevel']." $hidden)
		ORDER BY c.ord , f.ord, f.id
		");
		
		while ($forum = $sql->fetch($forums)){
			if ($forum['category'] != $cat){
				$cat = $forum['category'];
				$txt .= "</optgroup><optgroup label='".$forum['catname']."'>";
			}
			
			$txt .= "<option value=".$forum['id']." ".filter_string($select[$forum['id']]).">".$forum['name']."</option>";
		}
		
		// onselect code directly from Jul because JavaScript&trade;
		if (!$welp) return "<form method='POST' action='forum.php'>Forum jump:
			<select name='forumjump' onChange='parent.location=\"forum.php?id=\"+this.options[this.selectedIndex].value'>$txt</optgroup></select> <input type='submit' value='Go' name='fjumpgo'>
		</form>";
		
		else return "<select name='forumjump2'>$txt</select>";
	}
	
	function dopagelist($total, $limit, $script, $extra=""){
		

		if ($total<=$limit)
			return "";
		
		$page	= filter_int($_GET['page']);
		$id		= filter_int($_GET['id']);
		
		for($txt="",$n=0;$total>0;$total-=$limit){
			$type = ($page == $n) ? "z" : "a";
			$txt .= "<$type href='$script.php?id=$id&page=$n$extra'>".($n+1)."</$type> ";
			$n++;
		}
		
		return "<small>Pages: $txt</small>";

	}
	
	function dosmilies($string){
		static $smilies = NULL;
		
		// as of now, this is intentionally not run on header and signature
		
		if (!$smilies){// Load Smilies
			$handle = fopen("smilies.dat","r");
			if ($handle !== false){
				while (($x = fgetcsv($handle, 128, ",")) !== false)
					if (isset($x[0]))
						$smilies[$x[0]] = "<img src='$x[1]'>";
				
				fclose($handle);
			}

			else {
				trigger_error("Couldn't open smilies.dat. Smilies will not be processed. ", E_USER_WARNING);
				$smilies = array(0);
			}
			
		}
		
		return strtr($string, $smilies);
	}
	
	function getthreadicons(){
		static $icons = NULL;
		
		if (!$icons){
			$iconlist = file_get_contents("posticons.dat");
			
			if (!$iconlist){
				trigger_error("Couldn't open posticons.dat. Thread icons will not be processed. ", E_USER_WARNING);
				$icons = array(0);
			}
			else $icons = explode("\n", $iconlist);
		}
		
		return $icons;
	}

	# Extra fun Misc functions! (and Helpers)
	
	function getthreadfrompost($pid){
		global $sql;
		if (!$pid) return false;
		$res = $sql->resultq("SELECT thread FROM posts WHERE id = ".intval($pid));
		return $res;
	}
	
	function getforumfrompost($pid){
		global $sql;
		if (!$pid) return false;
		$res = $sql->resultq("
		SELECT threads.forum FROM threads
		LEFT JOIN posts	ON posts.thread = thread.id
		");
		return $res;
	}
	
	function getpostcount($id, $single=false){
		global $sql;
		
		// also get time for threadpost
		$getusers = $sql->query("
		SELECT id, user, time
		FROM posts 
		WHERE user ".($single ? "= $id" : "IN (SELECT user FROM posts WHERE thread = $id)")."
		");
		
		if (!$getusers)
			return array(0, 0);
		
		for($x=0; $x=$sql->fetch($getusers); $list[$x['user']][] = $x['id'], $time[$x['user']][] = $x['time']);
		
		return array($list, $time);
	}
	
/*	function getforumfromurl($url){
		
		// Post ID
		if ($x = stripos($url, "pid="))
			return getforumfrompost(explode("&", substr($url, $x+4), 1));
		
		else if ($x = stripos($url, "id=")){
			// Thread ID
			if (stripos($url, "thread")){
				return $sql->resultq("SELECT forum FROM thread WHERE id = ".explode("&", substr($url, $x+3), 1) );
			
			// Forum ID
			else return explode("&", substr($url, $x+3), 1)
		}
	}
*/	
	function findthemes($mode = false){
		global $sql;
		
		static $themes;
		if (!$themes)
			$themes = $sql->fetchq("SELECT * FROM themes", true, PDO::FETCH_ASSOC);

		if ($mode) // editprofile mode
			return implode("|", array_extract($themes, "name"));
		else
			return $themes;
		
	}
	function in_range($val, $min, $max, $equal = false){return ($equal ? ($min<=$val && $val<=$max) : ($min<$val && $val<$max));}
	function ctime(){return time()+$GLOBALS['config']['default-time-zone'];}
	function reverse_implode($separator, $string) {return implode($separator,array_reverse(explode($separator, $string)));}
	function x_unquote($x){return str_replace("\"","",$x);}
	function x_die($msg=""){error_printer(true, false, ""); die($msg);}
	function printdate($t, $x=true, $y=true){return date(($x?$GLOBALS['loguser']['dateformat']:"")." ".($y?$GLOBALS['loguser']['timeformat']:""), $t+$GLOBALS['loguser']['tzoff']*3600);}
	function array_extract($a,$i){foreach($a as $j => $c){$r[$j]=$c[$i];}return $r;}
	function d($x){print "<title>VDS - BoardC</title><body style='background: #000; color: #fff'><pre>";var_dump($x); x_die();}
	function choosetime($t){
		if 		($t<60) 	return "$t second".($t==1 ? "" : "s");
		else if ($t<3600)	return sprintf("%d minute".($t<120 ? "" : "s"),$t/60);
		else if ($t<86400)	return sprintf("%d hour".($t<7200 ? "" : "s"),$t/3600);
		else 				return sprintf("%d day".($t<172800 ? "" : "s"),$t/86400);
	}
	function getyeardiff($a, $b){
		$a = new DateTime(date("Y-m-d", $a));
		$b = new DateTime(date("Y-m-d", $b));
		return $b->diff($a)->y;
	}
	function split_null($s, $one = false){
		$c = strlen($s);
		for($i=0,$b="";$i<$c;$i++){
			if ($s[$i] == "\0"){
				if ($one) return $b;
				$r[] = $b;
				$b = "";
			}
			else $b .= $s[$i];
		}
		$r[] = $b;
		return $r;
	}
	// math related functions
		
	function calcexp($since, $posts){
		/*
		$time = ctime()-$since;
		$a = floor(log($time)*$posts);
		$b = "";

		print $a;
		*/
		return "<font color=red>Not implemented</font>";
	}
	
	function dec_rom($num){

		$txt = "IVXLCDM";
		for($res="", $p=6, $d=1000; $d>=1; $d = $d/10, $p-=2){
			$m = floor($num/$d);
			if ($m){
				if ($d!=1000){
					$f = ($m>=5) ? 1 : 0;
					$x = $f ? $m-5 : $m;
					if ($x==4) $res.=$txt[$p].$txt[$p+$f+1];
					else {
						if ($f) $res.=$txt[$p+$f];
						for($i=0; $i<$x; $i++) $res.=$txt[$p];
					}
				}
				else for($i=0; $i<$m; $i++) $res.=$txt[$p];
				$num -= ($m*$d);
			}
		}
		
		return $res;

	}
	
	function update_last_post($id, $newdata = false, $fmode = false){
		
		// Modeled after makeuserlink, so you can directly send an array of values to enter

		global $sql;
		
		if ($fmode){ 
			if (!$newdata)
				$newdata = $sql->fetchq("
					SELECT p.id, p.user, p.time
					FROM posts p
					LEFT JOIN threads t ON p.thread = t.id
					WHERE t.forum = $id
					ORDER BY p.time DESC");
					
			if (!$newdata){
				trigger_error("Attempted to update last post time for an invalid forum ID", E_NOTICE);
				return false;
			}
			
			if (!filter_int($newdata['id']))
				$sql->query("UPDATE forums SET lastpostid = NULL WHERE id = $id");
			else
				$sql->query("
					UPDATE forums SET lastpostid = ".$newdata['id'].", lastpostuser = ".$newdata['user'].", lastposttime = ".$newdata['time']."
					WHERE id = $id");			
			
		}
		
		else{ // update thread + forum
			
			if (!$newdata)					
				$newdata = $sql->fetchq("SELECT p.id, p.user, p.time, t.forum
					FROM posts p
					LEFT JOIN threads t ON p.thread = t.id
					WHERE p.thread = $id
					ORDER BY p.time DESC");
			
			if (!$newdata){
				trigger_error("Attempted to update last post time for an invalid thread ID", E_NOTICE);
				return false;
			}
			
			if (!filter_int($newdata['id'])){
				$sql->query("UPDATE threads SET lastpostid = NULL WHERE id = $id");
				$sql->query("UPDATE forums SET lastpostid = NULL WHERE id = ".$newdata['forum']);
			}
				
			else{
				
				$sql->query("
					UPDATE threads SET
						lastpostid = ".$newdata['id'].",
						lastpostuser = ".$newdata['user'].",
						lastposttime = ".$newdata['time']."
					WHERE id = $id
				");
				
				$sql->query("
					UPDATE forums SET
						lastpostid = ".$newdata['id'].",
						lastpostuser = ".$newdata['user'].",
						lastposttime = ".$newdata['time']."
					WHERE id = ".$newdata['forum']."
				");
			}
		}
	
	}
	
	function getfilename(){
		$path = explode("/", $_SERVER['PHP_SELF']);
		return $path[count($path)-1];
	}
	
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
	

	function iprange($left, $in, $right){
		$start = explode(".", $left);
		$end = explode(".", $right);
		$ip = explode(".", $in);
		$cnt = max(count($ip), count($start), count($end));
		
		for ($i=0; $i<$cnt; $i+=1){
			if (in_range(filter_int($ip[$i]), filter_int($start[$i]), filter_int($end[$i]), true)) continue;//if ($start[$i] < $ip[$i] && $ip[$i] < $end[$i])
			else return false;
		}
		
		return true;
	}
		
	function safe_fopen($file, $mode = "r"){
		if (!file_exists($file)){
			trigger_error("Couldn't find $filename",E_USER_WARNING);
			return false;
		}
		if (($fhandle = fopen($file, $mode) === false)){
			trigger_error("Couldn't open $filename",E_USER_WARNING);
			return false;
		}
		
		return $fhandle;
	}
	
	function trim_loop($before, $remove){
		$after = NULL;
		while ($before != $after){
			if ($after === NULL) $after = $before;
			else $before = $after;
			$after = trim($after, $remove);
		}
		return $after;
	}
	
	// Hidden audio player!
	function audio_play($path, $message = "Error.", $volume = 100){
		if (!file_exists($path))
			return "<small>An mp3 track was supposed to play here, but some doofus linked to a nonexisting file</small>";
		
		else return "
		<object type='application/x-shockwave-flash' data='ext/audioPlayer.swf' id='audioplayer'>
			<param name='movie' value='ext/audioPlayer.swf'>
			<param name='FlashVars' value='playerID=audioplayer&amp;autostart=yes&amp;initialvolume=$volume&amp;soundFile=$path'>
			<param name='quality' value='high'>
			<param name='menu' value='false'>
			<param name='wmode' value='transparent'>
			$message
		</object>";
	}
	
	function imageupload($file, $maxsize, $x, $y, $dest = false){
		
			if (!$file['tmp_name'])
				errorpage("No file selected.");
		
			if ($file['size'] > $maxsize)
				errorpage("File size limit exceeded.");
			
			list($width, $height) = getimagesize($file['tmp_name']);
			
			if (!$width || !$height)
				errorpage("This isn't a supported image type.");
			
			if ($width > $x || $height > $y)
				errorpage("Maximum image size exceeded (Your image: $width*$height | Expected: $x*$y).");
			
			if (!$dest)	return "data:".$file['type'].";base64,".base64_encode(file_get_contents($file['tmp_name']));
			else return move_uploaded_file($file['tmp_name'], $dest);
	}
	
	function getavatars($id, $use = NULL){
		global $sql;
		
		if (!$id){
			trigger_error("getavatars() with invalid ID", E_NOTICE);
			return "";
		}
		
		$moods = $sql->query("
		SELECT id, file, title
		FROM user_avatars
		WHERE user = $id
		AND file != 0
		ORDER by id ASC"
		);
		
		//if (!$moods) return "";
		
		if (isset($use)) $sel[$use] = "selected";
		
		$txt = "Avatar: <select name='avatar'>
					<option value='0'>-Normal avatar-</option>";
		if ($moods)
			while ($mood = $sql->fetch($moods))
				$txt .= "<option value='".$mood['file']."' ".filter_string($sel[$mood['file']]).">".$mood['title']."</option>";
		
		return "$txt</select>";
	}
	
	function dotags($string){
		
		static $tags = array(
			"&test&" 	=> "TEST TAG!",
		);
		
		return strtr($string, $tags);
	}
	
	function input_filters($source){
		// Javascript fun
		global $loguser, $sql;
		
		$string = $source;

		static $badjs = array(
			"click",
			"context",
			"mouse",
			"key",
			"abort",
			"open",
			"error",
			"hashchange",
			"load",
			"page",
			"scroll",
			"unload",
			"drag",
			"drop",
			"touch",
			"activate",
			"after",
			"before",
			"begin",
			"blur",
			"cellchange",
			"change",
			"control",
			"copy",
			"cut",
			"deactivate",
			"end",
			"filter",
			"focus",
			"hash",
			"help",
			"input",
			"layout",
			"lose",
			"media",
			"move",
			"offline",
			"online",
			"outofsync",
			"paste",
			"popstate",
			"progress",
			"property",
			"ready",
			"redo",
			"repeat",
			"reset",
			"resize",
			"resume",
			"reverse",
			"row",
			"seek",
			"select",
			"start",
			"stop",
			"storage",
			"syncrestored",
			"submit",
			"timeerror",
			"undo",
		);
		
		foreach ($badjs as $event)
			$string = str_ireplace("on$event", "on<z>$event", $string);
			
		$string = str_ireplace("FSCommand","FS<z>Command", $string);
		$string = str_ireplace("execcommand","exec<z>command", $string);
		
			
//		$string = str_ireplace("<script","&lt;scr<z>ipt", $string);
//		$string = str_ireplace("<meta", "&lt;fail", $string);
		
		if ($string != $source)
			$sql->queryp("INSERT INTO jstrap (user, ip, source, filtered) VALUES (?,?,?,?)", array($loguser['id'], $_SERVER['REMOTE_ADDR'], $source, $string));

		$string = str_ireplace("javascript", "javas<z>cript", $string);
		$string = preg_replace("'(<|=|\'|\")(.*?)script(.*?)='si","<z>", $string);
		$string = str_ireplace("iframe", "i<z>frame", $string); 
		$string = str_ireplace("meta", "me<z>ta", $string);
		
		$string = str_ireplace("object", "obj<z>ect", $string);
		$string = str_ireplace("data:image/svg", "data:image/png", $string);
		
		//$string = str_ireplace(".php", ".p<z>hp", $string); // oh god no why
		$source = $string;
		$string = preg_replace("'<(.*?)(src|rel)(.*?).php(.*?)>'si", "<img src='images/no.png'>", $source);
		if ($string != $source)
			trigger_error($loguser['name']." (ID #".$loguser['id']." | IP: ".$loguser['lastip'].") attempted to use a php file as image.", E_USER_NOTICE);

		return $string;
	}
	
	function output_filters($source, $forcesm = false){

		global $post, $sql, $config;
		
		$string = $source;
		
		$string = dotags($string);
		
		if ($forcesm) $string = dosmilies($string); // threadpost handles smilies by itself, but not profile.php
		
		if ($config['show-comments']) $string = preg_replace("'<!--(.*?)-->'si", "<font style='color: #0f0'>&lt;!--$1--&gt;</font>", $string);
		
		$string = preg_replace("'FILTER_TeSt'si", "[Filtered]", $string);
		
		/*
		nested quotes are broken with this, should find a replacement one day
		$string = preg_replace("'\[quote=(.*?)\](.*?)\[/quote\]'si", "<blockquote><div class='fonts'><i>Originally posted by $1</i></div><hr/>$2<hr/></blockquote>", $string);
		$string = preg_replace("'\[quote\](.*?)\[/quote\]'si", "<blockquote>$1</blockquote>", $string);
		*/
		$string = preg_replace("'\[quote=(.*?)\]'si", "<blockquote><div class='fonts'><i>Originally posted by $1</i></div><hr/>$2", $string);
		$string = str_ireplace("[quote]", "<blockquote><hr/>", $string);
		$string = str_ireplace("[/quote]", "<hr/></blockquote>", $string);
		
		

		return $string;
	}
	
	function userban($id, $expire = false, $permanent = false, $reason = "", $ircreason = true){
		global $sql;
		
		$expire_query = ($expire && !$permanent) ? ",`ban_expire` = '".(ctime()+3600*intval($expire))."'" /*counts by hours*/ : "";
		$new_powl = $permanent ? "-2" : "-1";
		$whatisthis = is_int($id) ? "id" : "name";
				
		$res = $sql->queryp("UPDATE `users` SET `powerlevel` = '?',`title` = '?'$expire_query WHERE $whatisthis = '?'", array($new_powl, $reason, $id));
		
		if (!$res)
			trigger_error("Query failure: couldn't ban user $whatisthis $id", E_USER_ERROR);
		
		else if ($ircreason !== false && $reason){
			if ($ircreason === true) $ircreason = $reason;
			trigger_error($ircreason, E_USER_NOTICE);
		}
			
		return $res;
	}
	
	function ipban($reason = "", $ircreason = true, $ip = false){
		global $sql;
		// Have to do it here to account for PHP being stupid
		if (!$ip) $ip = $_SERVER['REMOTE_ADDR'];
		
		$res = $sql->query("INSERT INTO `ipbans` (`ip`, `time`, `reason`) VALUES ('$ip', '".ctime()."', '$reason')");
		
		if (!$res)
			trigger_error("Query failure: couldn't IP Ban $id", E_USER_ERROR);
		
		else if ($ircreason !== false && $reason){
			if ($ircreason === true) $ircreason = $reason;
			trigger_error($ircreason, E_USER_NOTICE);
		}
		
		return $res;
	}
	
	// Layout functions
	function dopmbox(){
		
		global $sql, $loguser, $userfields;
		
		// index page handles this by itself while printing this isn't necessary in private.php for obvious reasons
		$file = getfilename();
		if ($file == 'index.php' || $file == 'private.php')
			return "";

		$newpm = $sql->fetchq("
			SELECT p.id pid, p.user, p.time, p.new, COUNT(p.new) count, $userfields
			FROM pms p
			LEFT JOIN users u ON p.user = u.id
			WHERE p.userto = ".$loguser['id']."
			AND p.new = 1
			ORDER BY p.id DESC
		");
		
		if ($newpm['pid'])
			return "<br/>
			<table class='main w c'>
				<tr>
					<td class='dark'>
						You have ".$newpm['count']." new private message".($newpm['count']==1 ? "" : "s").", <a href='private.php?act=view&id=".$newpm['pid']."'>last</a> by ".makeuserlink(false, $newpm)." at ".printdate($newpm['time'])."
					</td>
				</tr>
			</table>";
				
		else return "";
		
	}
	
	function pageheader($title, $show = true, $forum = 0){
		global $config, $hacks, $fw_error, $loguser, $views, $miscdata, $meta;
		
		if ($show)
			$title .= " - ".$config['board-name'];
		
		$meta_txt = "";
		
		if (filter_bool($meta['noindex'])){
			$meta_txt = "<meta name='robots' content='noindex, nofollow, noarchive'>";
			header('X-Robots-Tag: noindex, nofollow, noarchive', true);
		}
		
		$links = "";
		
		if (!powlcheck(5))
			$fw_error = "";
		
		if (powlcheck(1))
			$links .= "<a href='shoped.php'>Shop Editor</a> - ";		
		
		if (powlcheck(4))
			$links .= "<a href='admin.php'>Admin</a> - <a href='/phpmyadmin'>PMA</a> - <a href='register.php'>Rereggie</a> - ";
		/*
		if (isset($_GET['id'])){
			if (getfilename()=='private.php' && (!$_GET['act'] || $_GET['act'] == 'sent') && ($_GET['id'] == 1 && $loguser['id'] != 1)){
				ipban("Nice try.", false);
				header("Location: index.php");
			}
		}*/
		
		if (!$loguser['id'])
			$links .= "<a href='login.php'>Login</a> - <a href='register.php'>Register</a>";
		else
			$links .= "<a href='login.php?logout'>Logout</a> - <a href='editprofile.php'>Edit profile</a> - <a href='editavatars.php'>Edit avatars</a> - <a href='shop.php'>Item shop</a>";
		
		
		if ($loguser['id']){
			if (getfilename() == 'index.php') // mark all posts read
				$links .= " - <a href='index.php?markforumread'>Mark all forums read</a> - <a class='danger' href='index.php?markforumread&r'>Reverse</a>";
			else if ($forum) // mark all posts in forum read
				$links .= " - <a href='index.php?markforumread&forumid=$forum'>Mark forum read</a> - <a class='danger' href='index.php?markforumread&forumid=$forum&r'>Reverse II</a>";
		}
		
		$links2 = "
		<a href='index.php'>Main</a> - 
		<a href='memberlist.php'>Memberlist</a> -
		<a href='calendar.php'>Calendar</a> -
		<a href='online.php'>Online users</a>
		<br/>
		<a href='latestposts.php'>Latest posts</a> 
		";
		
		if (isset($miscdata['theme'])) $loguser['theme'] = $miscdata['theme'];
		
		$css = file_get_contents("css/".findthemes()[$loguser['theme']]['file']);
		
		if (!$css)
			$css = "";
		
		$ctime = ctime();
		
		if ($hacks['replace-image-before-login'] && !$loguser['id'])
			$config['board-title'] = "<h1>(?)</h1>";
			
		
		print "
		<!doctype html>
		<html>
			<head>
				<title>$title</title>
				<style type='text/css'>$css</style>
				<link rel='icon' type='image/png' href='images/favicon.png'>
				$meta_txt
			</head>
			<body>
			$fw_error
			".($hacks['test-ext'] ? audio_play("ext/sample.mp3") : "")."
			<table class='main c w fonts'>
				<tr>
					<td colspan=3 class='light b'><a href='".$config['board-url']."'>".$config['board-title']."</a><br/>$links</td>
				</tr>
				<tr>
					<td class='dim' style='width: 120px'>
						<nobr>Views: $views</nobr>
					</td>
					<td class='dim'>
						$links2
					</td>
					<td class='dim' style='width: 120px'>
						<nobr>".printdate($ctime)."</nobr>
					</td>
					
				</tr>			
				<tr><td colspan=3 class='dim'></td></tr>
			</table>";
			
		if ($loguser['id']) print dopmbox();
		
		unset ($GLOBALS['fw_error']);
	}
	
//	$sql->query("no this isn't a valid query and it will go into the error table");
	
	function pagefooter(){
		global $config, $sql, $hacks;
		$GLOBALS['fw'] = null;
		
		$errorlog = error_printer(true, powlcheck(5), $GLOBALS['errors']);

		if ($errorlog){
			$errorprint = "
			<table class='main'>
				<tr>
					<td class='head c' colspan=4>Errors</td>
				</tr>
				<tr>
					<td class='dark c'>Type</td>
					<td class='dark c'>Message</td>
					<td class='dark c'>File</td>
					<td class='dark c'>Line</td>
				</tr>
				$errorlog
			</table><br/>";
		}
		else $errorprint = "";// "(No errors or no permission)";
		unset($errorlog);
		
		$querylist = "";
		if(powlcheck(5)){
			if (!isset($_GET['debug']) && !$config['force-sql-debug-on'])
				$querylist = "<br/><small><a href='".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."&debug'>SQL debugging or something</a></small>";
			else{
				foreach($sql->querylist as $i => $query) //querylist[1] = 1 if pquery 
					$querylist .= "
								<tr>
									<td class='".($query[1] ? "dark" : "light")."'>
										".htmlspecialchars($query[0])."
									</td>
								</tr>";
								
				$querylist = "<br/><table class='main'><tr><td class='head c'>SQL Query Debugging</td></tr>$querylist</table>";
			}
		}
		
		$endtime = microtime(true) - $GLOBALS['startingtime'];
		// why
		if (!$hacks['correct-board-name'])
			$boardinfo = "
			<table class='main c fonts'><tr><td class='light'>
			BoardC ".$config['board-version']."<br/>
			&copy; 2016 Kak
			</td></tr></table>";
			
		else $boardinfo = "<table><tr>
		<td><img src='images/poweredbyacmlm.gif'></td>
		<td class='fonts'>Acmlmboard C - ".$config['board-version']."<br/>&copy; 2016 Kak
		</td></tr></table>
		";
		
		die("<br/>
		<center>$errorprint<small>$boardinfo
		Queries: ".$sql->queries." - PQueries: ".$sql->pqueries." | Total: ".($sql->queries+$sql->pqueries)."<br/>
		Query Execution Time: ".(number_format($sql->querytime, 6))." seconds<br/>
		Script Execution Time: ".(number_format($endtime - $sql->querytime, 6))." seconds<br/>
		Total Execution Time: ".(number_format($endtime, 6))." seconds</small>
		$querylist</center>
		</body>
		</html>
		");
	}
	
	function errorpage($err, $show = true){
		global $config;
		if ($show) pageheader($config['board-name'], false);
		print "<br/><table class='main c w'><tr><td class='light'>$err</td></tr></table><br/>";
		pagefooter();
	}

	function error_reporter($type, $string, $file, $line){
		global $loguser, $errors;
		
		static $type_txt = array(
			1 		=> "Error",
			2 		=> "Warning",
			8 		=> "Notice",
			256 	=> "User Error",
			512 	=> "User Warning",
			1024 	=> "User Notice",
			2048 	=> "Strict",
			4096 	=> "Recoverable Error",
			8192 	=> "Deprecated",
			16384 	=> "User Deprecated",
		);
		
		if (in_array($type, array(256,512,16384)) && strpos($file,"mysql.php") !== false){ //Errors in mysql.php are a complete lie
			$error = debug_backtrace();
			for($i=1; isset($error[$i]) && strpos(($error[$i]['file']),"mysql.php")!==false; $i++);
			$file = $error[$i]['file'];
			$line = $error[$i]['line'];
		}
		
		/* TODO: Irc Reporting
			if ($loguser['powerlevel'] < 4){
				$msg = $type_txt[$type]." - $string in $file at line $line";
		*/
		
		$errors[] = array($type_txt[$type], $string, $file, $line);
		return $errors;
		
	}

	function error_printer($trigger, $report, $errors){
		static $called = false;
		
		if (!$called){
			
			$called = true;
			
			// Correct behaviour based on how the function's called (returning "" after a fatal error is a bad idea)
			if (!$report || ($trigger && empty($errors))) return ($trigger ? "" : true);
			
			if ($trigger != false){ // called by pagefooter()
			
				$list = "";
				foreach ($errors as $error)
					$list .= "<tr>
								<td class='light'><nobr>".htmlspecialchars($error[0])."</nobr></td>
								<td class='dim'>".htmlspecialchars($error[1])."</td>
								<td class='light'>".htmlspecialchars($error[2])."</td>
								<td class='dim'>".htmlspecialchars($error[3])."</td>
							</tr>";
					
				return $list;
				
			}
			else{
					extract(error_get_last());
					$ok = error_reporter($type, $message, $file, $line)[0];
					die("<pre>Fatal Error!\n\nType: $ok[0]\nMessage: $ok[1]\nFile: $ok[2]\nLine: $ok[3]</pre>");				
			}
		}
		
		return true;
	}
	
	function threadpost($post, $mini = false, $merge = false, $nocontrols = false, $extra = "", $pmmode = false){
		global $ismod, $loguser, $config, $error_id;
		
		static $theme = false;
		// Reverse post color scheme
		$theme = ($theme == "light") ? "dim" : "light";
		
		if ((!isset($ismod) || isset($ismod_a)) && !$pmmode){
			static $ismod_a;
			if (!isset($ismod_a[$post['thread']])) $ismod_a[$post['thread']] = ismod($post['thread']); //useful when printing posts from different threads (ie: list posts)
		
			$ismod = $ismod_a[$post['thread']];
		}
		
		$controls = "";
		$uid = $post['user'];
		
		if ($pmmode){
			// snip
			if ($post['nohtml'])
				$post['text'] = htmlspecialchars($post['text']);
			if (!$post['nosmilies'])
				$post['text'] = dosmilies($post['text']);
			
			$post['text'] = output_filters($post['text']);
			
			if ($post['nolayout'] || !$loguser['showhead'])
				$post['head'] = $post['sign'] = "";
			else{
				
				switch ($loguser['signsep']){
					case 1: $sep = "</br>--------------------<br/>"; break;
					case 2: $sep = "</br>____________________</br>"; break;
					case 3: $sep = "</br><hr/><br/>"; break;
					default: $sep = "";
				}
				
				$post['head'] = output_filters($post['head']);
				$post['sign'] = $sep.output_filters($post['sign']);
			}
			
			if (isset($post['avatar']) && is_file("userpic/$uid/".$post['avatar'])) $avatar = "<img src='userpic/$uid/".$post['avatar']."'>";
			else $avatar = "";
			// end snip
			$controls = "<a href='private.php?act=send&quote=".$post['id']."'>Reply</a>";
			
			$sidebar = "<small>
			".($post['title'] ? $post['title']."<br/>" : "")."
			".($avatar ? "$avatar<br/>" : "")."
			Posts: ".$post['posts']."<br/>
			EXP: [NUM]<br/>
			For Next: [NUM]<br/>
			<br/>
			Since: ".printdate($post['since'], true, false)."<br/>
			".($post['location'] ? "From: ".$post['location']."<br/>" : "")."
			<br/>
			Since last post: ".choosetime(ctime()-$post['lastpost'])."<br/>
			Last activity: ".choosetime(ctime()-$post['lastview'])."</small>";
			
			$post['text'] = nl2br($post['text']);
			$height = "style='height: 220px'";
		}
		else if ($post['deleted']){
			$post['text'] = "(Post deleted)";
			$post['head'] = $post['sign'] = "";
			
			// Topbar
			if ($ismod)
				$controls = "
			<a href=\"thread.php?id=".$post['thread']."&pin=".$post['id']."\">Peek</a>
			<a href=\"thread.php?id=".$post['thread']."&unhide=".$post['id']."\">Undelete</a>
			";
			
			$sidebar = "&nbsp;";
			
			$height = "";
			$avatar = "";
		}
		else {
			if ($post['nohtml'])
				$post['text'] = htmlspecialchars($post['text']);
			if (!$post['nosmilies'])
				$post['text'] = dosmilies($post['text']);
			
			$post['text'] = output_filters($post['text']);
			
			if ($post['nolayout'] || !$loguser['showhead'])
				$post['head'] = $post['sign'] = "";
			else{
				
				switch ($loguser['signsep']){
					case 1: $sep = "</br>--------------------<br/>"; break;
					case 2: $sep = "</br>____________________</br>"; break;
					case 3: $sep = "</br><hr/><br/>"; break;
					default: $sep = "";
				}
				
				$post['head'] = output_filters($post['head']);
				$post['sign'] = $sep.output_filters($post['sign']);
			}
			
			if (isset($post['avatar']) && is_file("userpic/$uid/".$post['avatar'])) $avatar = "<img src='userpic/$uid/".$post['avatar']."'>";
			else $avatar = "";
			
			$controls .= "<a href=\"thread.php?pid=".$post['id']."#".$post['id']."\">Link</a> | <a href=\"new.php?act=newreply&id=".$post['thread']."&quote=".$post['id']."\">Quote</a>";
			
			if (($ismod || $post['user'] == $loguser['id']) && powlcheck(0))
				$controls .= " | <a href=\"new.php?act=editpost&id=".$post['id']."\">Edit</a>";
			
			if ($ismod)
				$controls .= " | <a href=\"thread.php?id=".$post['thread']."&hide=".$post['id']."\">Delete</a>";
			
			
			if (!$mini)
				$sidebar = "<small>
				".($post['title'] ? $post['title']."<br/>" : "")."
				".($avatar ? "$avatar<br/>" : "")."
				Posts: ".$post['postcur']."/".$post['posts']."<br/>
				EXP: [NUM]<br/>
				For Next: [NUM]<br/>
				<br/>
				Since: ".printdate($post['since'], true, false)."<br/>
				".($post['location'] ? "From: ".$post['location']."<br/>" : "")."
				<br/>
				Since last post: ".choosetime(ctime()-$post['lastpost'])."<br/>
				Last activity: ".choosetime(ctime()-$post['lastview'])."</small>";
			else $sidebar = "";
			
			$post['text'] = nl2br($post['text']);
			$height = "style='height: 220px'";
		}
		
		
		
		if (powlcheck(5) && !$pmmode)
			$controls .= " | <a class='danger' href=\"thread.php?id=".$post['thread']."&del=".$post['id']."\">Erase post</a>";
		
		if (powlcheck(4))
			$controls .= " | IP: <a href='admin-ipsearch.php?ip=".$post['ip']."'>".$post['ip']."</a>";
		
		$controls .= " | ID: ".$post['id'];
		
		/*
		before:
		trev - total revision
		rev - current revision (used to be replaced with this)
		
		now:
		rev - total revision
		crev - current revision (doesn't replace rev anymore)
		*/
		
		if (filter_int($post['rev'])){
			
			if (!isset($post['crev'])) $post['crev'] = $post['rev']; // imply max revision if it isn't set
			
			// post revision jump
			if ($ismod){
				for($i=0, $revjump="Revision: ";$i<$post['rev'];$i++){
					$a = ($post['crev'] == $i) ? "z" : "a"; 
					$revjump .= "<$a href='thread.php?pid=".$post['id']."&pin=".$post['id']."&rev=$i#".$post['id']."'>".($i+1)."</$a> ";
				}
				$revjump .= "<a href='thread.php?pid=".$post['id']."#".$post['id']."'>".($i+1)."</a>";
			}
			else $revjump = "";
			
			$datetxt = "Posted on ".printdate($post['rtime'])."$extra Revision ".($post['crev']+1)." (Last edited by ".makeuserlink($post['lastedited']).": ".printdate($post['time']).") $revjump";
		}
		else $datetxt = "Posted on ".printdate($post['time']).$extra;
		
		$inputmerge = $merge ? "<input type='checkbox' name='c_merge[]' value=".$post['id'].">" : "";
		
		if ($nocontrols) $controls = "";
		
		$new = $post['new'] ? "<img src='images/status/new.gif'> - " : "";
		
		if (isset($_GET['lol'])){
			return "<table class='main'><tr><td class='$theme'>
			USER: ".makeuserlink($uid, $post)."<br/>
			MESSAGE: ".$post['text']."<br/>
			</td></tr></table>
			";
		}
		else if (!$mini)
		return "
		<table id='".$post['id']."' class='main content_$uid'>
			<tr>
				<td class='topbar1_$uid $theme' style='min-width: 200px; border-bottom: none'>$inputmerge".makeuserlink($uid, $post)."</td>
				<td class='topbar2_$uid $theme w fonts' style='text-align: right'>
				<table class='fonts' style='margin: 0px; border-spacing: 0px;'><tr><td><nobr>$new$datetxt</nobr></td><td class='w'></td><td><nobr>$controls</nobr></td></tr></table></td>
			</tr>
			<tr>
				<td class='sidebar_$uid $theme' valign='top'>$sidebar</td>
				<td class='mainbar_$uid $theme' valign='top' $height>".$post['head'].$post['text'].$post['sign']."</td>
			</tr>
		</table>
		";
		
		else return "
			<tr id='".$post['id']."'>
				<td class='head $theme' style='min-width: 200px;'>".makeuserlink($uid, $post)."</td>
				<td class='head $theme w' style='text-align: right'>
				<table class='fonts' style='margin: 0px; border-spacing: 0px;'><tr><td><nobr>$new$datetxt</nobr></td><td class='w'></td><td><nobr>$controls</nobr></td></tr></table></td>
			</tr>
			<tr>
				<td colspan=2 class='$theme'>".$post['text']."</td>
			</tr>
		";	
	}

	if (isset($_GET['pupd'])){
		
		$i = $sql->query("SELECT id FROM posts");
		
		while ($id = $sql->fetch($i))
			$sql->query("INSERT INTO new_posts (id) VALUES (".$id['id'].")");
		
		x_die("Done.");
		
	}
	
	function minipostlist($thread_id){
		global $loguser, $sql, $userfields;
		
		$posts = $sql->query("
		SELECT p.id, p.text, p.time, p.rev, p.user, p.deleted, p.thread, u.lastip as ip, 1 nolayout, p.nohtml, p.nosmilies, p.lastedited, o.time rtime,
		NULL title, $userfields welpwelp, n.user".$loguser['id']." new
		FROM posts p
		LEFT JOIN users u ON p.user = u.id
		LEFT JOIN posts_old o ON o.time = (SELECT MIN(o.time) FROM posts_old o WHERE o.pid = p.id)
		LEFT JOIN new_posts n ON p.id = n.id
		WHERE p.thread = $thread_id
		ORDER BY p.id DESC
		LIMIT ".$loguser['ppp']."
		");// offset, limit
		
		$txt = "<br/><table class='main w'><tr><td colspan=2 class='dark c'>Latest posts in the thread:</td></tr>";
		
		
		if ($posts)
			while($post = $sql->fetch($posts))
				$txt .= threadpost($post, true);
			
		else $txt .= "<tr><td class='light'>There are no posts in this thread</td></tr>";
		
		return $txt."</table>";
	}
		
	function getthreadinfo($lookup, $pid = false){
		global $sql;
		
		if ($lookup){
			
			
			// I don't know why there were three different queries here
			// Query redone to start from post id and allow thread controls to work on invalid threads
			// (which can have no valid thread id, with the invalid one is post.thread)
			
			$data = $sql->fetchq("
				SELECT p.id pid, p.thread rthread,
				t.id, t.name, t.title, t.time, t.forum, t.user, t.views, t.replies, t.sticky, t.closed, t.icon, t.ispoll,
				t.lastpostid, t.lastpostuser, t.lastposttime,
				f.id fid, f.name fname, f.powerlevel fpowl, f.theme
				FROM posts p
				
				LEFT JOIN threads t	ON p.thread = t.id
				LEFT JOIN forums f ON t.forum = f.id
				
				WHERE p.thread = $lookup
				".($pid ? "OR p.id = $pid" : "")."
			");
			
			if (!$pid)
				$pid = $sql->resultq("SELECT id FROM posts WHERE thread = $lookup");

			if (!$pid) $pid = filter_int($data['pid']);
			
			
			if (filter_int($data['ispoll'])){
				$poll = split_null($data['title']);
				$data['title'] = $poll[0];
			}
			
			
			
			// Rebuild $forum and $thread since everything expects it this way
			
			$forum = array(
				'id'		=> filter_int($data['fid']),
				'name' 		=> filter_string($data['fname']),
				'powerlevel'=> filter_int($data['fpowl']),
				'theme' 	=> &$data['theme'],
				
			);
			$thread = array(
				'id'			=> filter_int($data['id']),
				'name'			=> filter_string($data['name']),
				'title'			=> filter_string($data['title']),
				'time'			=> filter_int($data['time']),
				'forum'			=> filter_int($data['forum']),
				'user'			=> filter_int($data['user']),
				'views'			=> filter_int($data['views']),
				'replies'		=> filter_int($data['replies']),
				'sticky'		=> filter_int($data['sticky']),
				'closed'		=> filter_int($data['closed']),
				'rthread'		=> filter_int($data['rthread']),
				'icon'			=> filter_string($data['icon']),
				'lastpostid'	=> filter_int($data['lastpostid']),
				'lastpostuser'	=> filter_int($data['lastpostuser']),
				'lastposttime'	=> filter_int($data['lastposttime']),
				'ispoll'		=> filter_bool($data['ispoll']),
				'polldata' 		=> isset($poll) ? $poll : false
			);

			// Error Handling
			
			if 		($thread['id'] && !$forum['id'])				$error_id = 4; # Thread in bad forum
			else if (!$thread['id'] && $pid)						$error_id = 3; # post in bad thread
			else if (!$thread['id'])								$error_id = 2; # Thread doesn't exist
			else if ($forum['id'] && !canviewforum($forum['id']))	$error_id = 1; # minpower check
			else $error_id = 0;
		}
		else{
			// Account for error id 5
			$error_id = 5;
			$thread = false;
			$forum = false;
		}
		
		if ($error_id){
				switch ($error_id){
					case 3:{
						$thread['id'] = $lookup;
						$thread['name'] = "Invalid thread #$lookup";
						$forum['name'] = "(No forum)";
						
						break;
					}
					case 4:{
						$forum['id'] = $thread['forum'];
						$forum['name'] = "Invalid forum #".$thread['forum'];
						break;
					}
//					case 1:
					case 2:{
						$thread['id'] = $lookup;
						break;
					}
//					case 5:
					default:
				}
		}
		
		return array($thread, $forum, $error_id, filter_int($pid));
	
	}
	
	function poll_print($p){
		
		global $loguser, $sql, $lookup;
		

		$votes = $sql->query("SELECT vote FROM poll_votes WHERE thread = $lookup");
		
		$total = 0;
		$votedb = array(0);
		
		while ($vote = $sql->fetch($votes)){
			$votedb[$vote['vote']] = filter_int($votedb[$vote['vote']]) + 1;
			$total++;
		}
		
		//d($votedb);
		
		$max = max($votedb);
		if ($max != 0) $mul = 100/$max;
		else $mul = 0;


		$title = $p[0];
		$briefing = $p[1];
		$multivote = $p[2];
		
		for($i=3,$n=1,$choice_out="";isset($p[$i]);$i+=2,$n++){
			
			if (!$loguser['id']) $name = $p[$i];
			else $name = "<a href='thread.php?".$_SERVER['QUERY_STRING']."&vote=$n'>".$p[$i]."</a>";
			
			$votes_num = filter_int($votedb[$n]);
			$width = sprintf("%.1f", $votes_num/$total*100);
			
			$choice_out .= "
			<tr>
				<td class='light' width='20%'>$name</td>
				<td class='dim' width='60%'><table bgcolor='".$p[$i+1]."' cellpadding='0' cellspacing='0' width='$width%'><tr><td>&nbsp;</td></tr></table></td>
				<td class='light c' width='20%'>$votes_num vote".($votes_num==1 ? "" : "s").", $width%</td>
			</tr>
			";
		}
		
		return "
			<table class='main w'>
				<tr><td colspan='3' class='dark c'><b>$title</b></td></tr>
				
				<tr><td class='dim fonts' colspan='3'>$briefing</td></tr>
				
				$choice_out
				
				<tr>
					<td class='dim fonts' colspan='3'>Multi-voting is ".($multivote ? "enabled" : "disabled")." - $total votes in total. ".(powlcheck(4) ? "<a href='thread.php?".$_SERVER['QUERY_STRING']."&votes'>(View votes)</a>" : "")."</td>
				</tr>
			</table><br/>";
	}
	
	function adminlinkbar(){
		
		$adminpages = array(
			"admin.php"				=> "Main ACP",
			"admin-updatethemes.php"=> "Update Themes",
			"admin-threadfix.php" 	=> "Thread Fix",
			"admin-threadfix2.php" 	=> "Thread Fix 2",
			"admin-userfix.php" 	=> "User Fix",
			"admin-editforums.php" 	=> "Edit Forums",
			"admin-editmods.php" 	=> "Edit Mods",
			"admin-ipsearch.php" 	=> "IP Search",
			"admin-quickdel.php" 	=> "The (Ban) Button&trade;",
		);
		if (powlcheck(5)) $adminpages["admin-deluser.php"] = "Delete User";
			
		$page = getfilename();	
		$cnt = count($adminpages);
		$span = ($cnt > 4) ? 4 : $cnt;
		
		
		$txt = "<br/>
		<table class='main w c'><tr><td class='head' colspan=$span>Administration bells and whistles</td></tr>";

		$i = 4;
		foreach ($adminpages as $link => $title){
			if ($i == 4){
				$i = 0;
				$txt .= "</tr><tr>";
			}
			
			$txt .= ($link == $page ? "<td class='dark'><a class='notice' href='$link'>$title</a>" : "<td class='light'><a href='$link'>$title</a>");
			$i++;
		}
		
		for ($i; $i<4; $i++)
			$txt .= "<td class='dim'>&nbsp;</td>";
		
		return $txt."</tr></table><br/>";
	}
?>