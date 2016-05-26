<?php

	// Main functions are stored here
	
	// Variable filters
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
	
	function sgfilter(&$source){
		
		if (is_array($source)) return $source; // todo?
		
		if (filter_bool($GLOBALS['pmeta']['noreplace']))
			return $source;
	
		$result = $source;
		
		// Control Codes
		$result = str_replace("\x00", "", $result); //always remove \x00 as it's internally used as a separator for other stuff (ie: poll data)
		$result = preg_replace("'[\x01-\x09\x0B-\x1F\x7F]'", "", $result); // Don't think about adding \x0A (the newline character)

		//Unicode Control Codes
		$result = str_replace("\xC2\xA0","\x20", $result);
		$result = preg_loop($result, "\xC2+[\x80-\x9F]");
		
		// Entities
		$result = html_entity_decode($result, ENT_NOQUOTES, 'UTF-8'); //No standard HTML entities
		$result = preg_replace("'(&#x?([0-9]|[a-f])+[;>])'si", "<img src='images/coin.gif'>", $result);// Remove this entirely, potential attack vector
		
		return $result;
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
		
		$string = preg_replace("'<(.*?)script'","&lt;script", $string);
		$string = str_ireplace("iframe", "i<z>frame", $string); 
		$string = str_ireplace("meta", "me<z>ta", $string);			
//		$string = str_ireplace("<script","&lt;scr<z>ipt", $string);
//		$string = str_ireplace("<meta", "&lt;fail", $string);
		
		if ($string != $source)
			$sql->queryp("INSERT INTO jstrap (user, ip, source, filtered) VALUES (?,?,?,?)", array($loguser['id'], $_SERVER['REMOTE_ADDR'], $source, $string));

		$string = str_ireplace("javascript", "javas<z>cript", $string);


		
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
	
	// Ban stuff
	
	function userban($id, $expire = false, $permanent = false, $reason = "", $ircreason = true){
		global $sql;

		$expire_query	= ($expire && !$permanent) ? ",`ban_expire` = '".(ctime()+3600*intval($expire))."'" /*counts by hours*/ : "";
		$new_powl		= $permanent ? "-2" : "-1";
		$whatisthis		= is_numeric($id) ? "id" : "name";
				
		$res = $sql->queryp("UPDATE users SET powerlevel = ?, title = ? $expire_query WHERE $whatisthis = ?", array($new_powl, $reason, $id));
		
		if (!$res)
			trigger_error("Query failure: couldn't ban user $whatisthis $id", E_USER_ERROR);
		
		else if ($ircreason !== false && $reason){
			if ($ircreason === true) $ircreason = $reason;
			trigger_error($ircreason, E_USER_WARNING);
		}
			
		return $res;
	}
	
	function ipban($reason = "", $ircreason = true, $ip = false, $manual = false){
		global $sql, $loguser;
		// Have to do it here to account for PHP being stupid
		if (!$ip) $ip = $_SERVER['REMOTE_ADDR'];
		
		$res = $sql->query("INSERT INTO `ipbans` (`ip`, `time`, `reason`, `userfrom`) VALUES ('$ip', '".ctime()."', '$reason', '".($manual ? $loguser['id'] : 0)."')");
		
		if (!$res)
			trigger_error("Query failure: couldn't IP Ban $id", E_USER_WARNING);
		
		else if ($ircreason !== false && $reason){
			if ($ircreason === true) $ircreason = $reason;
			trigger_error($ircreason, E_USER_NOTICE);
		}
		
		return $res;
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
	
	//

	function update_hits($forum = 0){
		global $loguser, $sql;
		$sql->queryp("INSERT INTO hits (user, ip, time, page, useragent, forum, referer) VALUES (?,?,?,?,?,?,?)", array($loguser['id'], $_SERVER['REMOTE_ADDR'], ctime(), $_SERVER['REQUEST_URI'], $_SERVER['HTTP_USER_AGENT'], $forum, $_SERVER['HTTP_REFERER']));
		if ($loguser['id'])	$sql->query("UPDATE users SET lastview = ".ctime()." WHERE id = ".$loguser['id']);
	}
	
	function update_last_post($id, $newdata = false, $fmode = false){
		
		// Modeled after makeuserlink, so you can directly send an array of values to enter

		global $sql, $loguser;
		
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
				
				$sql->query("UPDATE users SET lastpost = ".$newdata['time']." WHERE id = ".$loguser['id']);
			}
		}
	
	}
	
	
	// Permissions
	
	function powlcheck($minpowl, $test = NULL){
		global $loguser, $config;
		
		if (isset($test)) return ($test<$minpowl) ? false : true;
		else if ($loguser['powerlevel']<$minpowl /*&& !$config['admin-board'] */)
			return false;
		else return true;
		
	}
	
	function canviewforum($fid){
		global $sql, $loguser;
		
		$minpower = $sql->resultq("SELECT powerlevel FROM forums WHERE id = ".filter_int($fid));
		$powl = $loguser['powerlevel'] < 0 ? 0 : $loguser['powerlevel']; // Allow banned users to read normal forums
		if ($minpower === false) return false;
		else return powlcheck($minpower, $powl);
	}
	
	function ismod($forum = false){
		global $sql, $loguser;
		
		if ($forum !== false) // Account for forum mods
			$makemealocalmod = $sql->query("SELECT 1 FROM forummods WHERE (fid = $forum AND uid = ".$loguser['id'].")");
		else $makemealocalmod = false;
		
		return (powlcheck(2) || $makemealocalmod) ? true : false;
	}
	
	
	// Load/apply stuff
	
	function dotags($string){
		
		static $tags = array(
			"&test&" 	=> "TEST TAG!",
		);
		
		return strtr($string, $tags);
	}
	
	function dosmilies($string, $return_array = NULL){
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
		
		if (!isset($return_array)) return strtr($string, $smilies);
		else return $smilies;
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
	
	function findthemes($mode = false, $all = false){
		global $sql;
		// Due to editprofile limitations I won't change, all the special themes need to be last in the list
		//static $themes;
		//if (!$themes)
			$themes = $sql->fetchq("SELECT * FROM themes".($all ? "" : " WHERE special = 0"), true, PDO::FETCH_ASSOC);

		if ($mode) // editprofile mode
			return implode("|", array_extract($themes, "name"));
		else
			return $themes;
		
	}
	
	// Thread.php helpers
	
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
		
		$getusers = $sql->query("
			SELECT id, user
			FROM posts 
			WHERE user ".($single ? "= $id" : "IN (SELECT user FROM posts WHERE thread = $id)")."
		");
		
		if (!$getusers) return array(0);
		for($x=0; $x=$sql->fetch($getusers); $list[$x['user']][] = $x['id']);
		return $list;
	}
	
	/*
	function getforumfromurl($url){
		
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
	
	// File uploads
	
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
	
	// Mini functions
	
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
		else if ($t<3600)	return floor($t/60)." minute".(floor($t/60)==1 ? "" : "s");
		else if ($t<86400)	return floor($t/3600)." hour".(floor($t/7200)==1 ? "" : "s");
		else 				return floor($t/86400)." day".(floor($t/172800)==1 ? "" : "s");
	}
	function getyeardiff($a, $b){
		$a = new DateTime(date("Y-m-d", $a));
		$b = new DateTime(date("Y-m-d", $b));
		return $b->diff($a)->y;
	}
	
	function getfilename(){
		$path = explode("/", $_SERVER['PHP_SELF']);
		return $path[count($path)-1];
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
	
	function preg_loop($before, $remove){
		$after = NULL;
		while ($before != $after){
			if ($after === NULL) $after = $before;
			else $before = $after;
			$after = preg_replace("'$remove'", "", $after);//trim($after, $remove);
		}
		return $after;
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
	
	function prime_num($max){
		$res = array_fill(2, $max, true);
		for ($i=2; $i<$max; $i++)
			for($j=2; $i*$j<$max; $j++)
				$res[($i*$j)] = false;	
		foreach($res as $i => $key)
			if ($key)
				print "$i ";
	}
	
	// Error reporting
	
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
	

?>