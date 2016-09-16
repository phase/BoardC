<?php

	// Main functions are stored here
	
	// Variable filters
	function filter_bool  (&$bool)	{return (bool)   $bool;}
	function filter_int   (&$int)	{return (int)    $int;}
	function filter_array (&$arr)	{return (array)  $arr;}
	function filter_string(&$string, $removecontrols = false){
		/*
			Remove special characters:
			1 - Controls codes (these are either used internally or are a waste of precious bytes >_>)
			2 - Entities, as they can get in the way of the XSS filter.
				I should find a better way to deal with them instead of disallowing them completely.
		*/
		if ($removecontrols){
			// Control Codes
			$string = str_replace("\x00", "", $string); //always remove \x00 just in case
			$string = preg_replace("'[\x01-\x09\x0B-\x1F\x7F]'", "", $string); // Don't think about adding \x0A (the newline character)

			//Unicode Control Codes
			$string = str_replace("\xC2\xA0","\x20", $string);
			$string = preg_loop($string, "\xC2+[\x80-\x9F]");
			
			// Entities
			$string = html_entity_decode($string, ENT_NOQUOTES, 'UTF-8'); //No standard HTML entities
			$string = preg_replace("'(&#x?([0-9]|[a-f])+[;>])'si", "<img src='images/coin.gif'>", $string);// Remove this entirely, potential attack vector			
		}
		return (string) $string;
	}
	
	function sgfilter(&$source){
		trigger_error("Used deprecated sgfilter() function. Use filter_string() with second argument set as true.", E_USER_DEPRECATED);
		return filter_string($source, true);
	}
	
	// Filters applied when storing posts on the database
	function input_filters($source){
		// Javascript fun
		global $loguser, $sql;
		
		$string = $source;
			
		$string = str_ireplace("FSCommand","BS<z>Command", $string);
		$string = str_ireplace("execcommand","hex<z>het", $string);
		
		$string = preg_replace("'on\w+( *?)=( *?)(\'|\")'si", "jscrap=$3", $string);
		$string = preg_replace("'<(/?)(script|meta|iframe|object|svg|form|textarea|xml|title|input|xmp|plaintext|base|!doctype|html|head|body)'i", "&lt;$1$2", $string);
		$string = preg_replace("'<embed(.*?)>'si", "<h1>HI, I'M A FUCKING IDIOT AND I DESERVE TO DIE IN A PIT OF STACKED MUNCHERS</h1>", $string);		
		
		if ($string != $source) {
			$sql->queryp("INSERT INTO jstrap (user, ip, source, filtered) VALUES (?,?,?,?)", array($loguser['id'], $_SERVER['REMOTE_ADDR'], $source, $string));
		}
		return $string;
	}
	
	// Filters applied when viewing posts
	function output_filters($source, $forcesm = false, $id = 0){
		global $post, $sql, $config;
	
		$string = $source;
		
		// A simple method to skip tag loading
		if ($id && strpos($string, "&") !== false){
			$string = dotags($string, $id);
		}
		
		if ($forcesm) $string = dosmilies($string); // threadpost handles smilies by itself, but not profile.php
		if ($config['show-comments']) $string = preg_replace("'<!--(.*?)-->'si", "<span style='color: #0f0'>&lt;!--$1--&gt;</span>", $string);
		
		// Another simple method to skip [tags]
		if (strpos($string, "[") !== false){
			
			// Quoting
			$string = preg_replace("'\[quote=(.*?)\]'si", "<blockquote><div class='fonts'><i>Originally posted by $1</i></div><hr/>$2", $string);
			$string = str_ireplace("[quote]", "<blockquote><hr/>", $string);
			$string = str_ireplace("[/quote]", "<hr/></blockquote>", $string);
			
			// Standard BBCode (who the fuck even uses it?!)
			$string = preg_replace("'\[(b|i|s|u)\](.*)\[/(.*)\]'si", '<$1>$2</$1>', $string);
			$string = preg_replace("'\[img\](.*)\[/img\]'si", "<img src='$1'>", $string);
			$string = preg_replace("'\[url\](.*)\[/url\]'si", "<a href='$1'>$1</a>", $string);
			$string = preg_replace("'\[url=(.*?)\](.*)\[/url\]'si", "<a href='$1'>$2</a>", $string);
			
			$string = preg_replace("'\[(red|green|blue|orange|yellow|pink|white|black)\]'si", "<span style='color: $1'>", $string);
			$string = str_ireplace('[/color]', '</span>', $string); 
			
			// This is the best tag
			$string = preg_replace("'\[sp=(.*?)\](.*?)\[/sp\]'si", "<span style=\"border-bottom: 1px dotted #f00;font-style:italic\" title=\"did you mean: $1\">$2</span>", $string);
			
		}
		// This fucks up CSS not inline, I may move it eventually
		$string = nl2br($string);

		return $string;
	}
	
	// Ban stuff
	/*
		userban() Ban a user
		id 			- can either be an user id or a user handle
		expire		- number of hours until the ban expires. Setting it to 0 makes it 'permanent'
		permanent 	- flag to set the permabanned powerlevel (-2). by default it's set to -1
		reason		- reason shown to the user
		ircreason	- info message sent to the IRC staff channel
	*/
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
			irc_reporter($ircreason, 1);
		}
			
		return $res;
	}
	
	/*
		ipban() Ban an IP. WOW, I COULD NEVER FIGURE THAT OUT!!!
		reason		- reason shown to the user
		ircreason	- info message sent to the IRC staff channel
		ip 			- IP Address to ban. By default it bans the remote address.
		expire		- number of hours until the ban expires. Setting it to 0 makes it 'permanent'
		manual	 	- marks if the IP Ban was automatic (0) or given by an user (!= 0)
	*/	
	function ipban($reason = "", $ircreason = true, $ip = false, $expire = 0, $manual = 0){
		global $sql, $loguser;
		// Have to do it here to account for PHP being stupid
		if (!$ip) $ip = $_SERVER['REMOTE_ADDR'];
		
		if ($expire) $expire = ctime() + 3600 * intval($expire);
		
		$res = $sql->query("
			INSERT INTO `ipbans`
			(`ip`, `time`, `ban_expire`, `reason`, `userfrom`) VALUES
			('$ip', '".ctime()."', $expire, '$reason', '".($manual ? $loguser['id'] : 0)."')
		");
		
		if (!$res)
			trigger_error("Query failure: couldn't IP Ban $id", E_USER_WARNING);
		
		else if ($ircreason !== false && $reason){
			if ($ircreason === true) $ircreason = $reason;
			irc_reporter($ircreason, 1);
		}
		
		return $res;
	}
	
	/*
		iprange() Checks if the IP is in a specific IP range. WOWZERS
		left 	: range starts from this IP
		in 		: the IP you want to check
		right	: the range ends with this IP
		This is used in admin-ipsearch.php.
	*/
	function iprange($left, $in, $right){
		$start 	= explode(".", $left);
		$end 	= explode(".", $right);
		$ip 	= explode(".", $in);
		$cnt 	= max(count($ip), count($start), count($end));
		
		for ($i = 0; $i < $cnt; $i++){
			if (in_range(filter_int($ip[$i]), filter_int($start[$i]), filter_int($end[$i]), true)) continue;//if ($start[$i] < $ip[$i] && $ip[$i] < $end[$i])
			else return false;
		}
		
		return true;
	}
	
	/*
		ipmask() also checks for an IP range.
		ie: ipmask('127.*.0.1');
	*/
	function ipmask($mask, $ip = ''){
		if (!$ip) $ip = $_SERVER['REMOTE_ADDR'];
		
		$mask 	= explode(".", $mask);
		$chk 	= explode(".", $ip);
		$cnt 	= min(count($mask), count($chk));
		
		for($i = 0; $i < $cnt; $i++){
			/*
				A star obviously allows every number for the ip sect, otherwise we check if the sectors match
				If they don't return false
			*/
			if ($mask[$i] == "*" || $mask[$i] == $chk[$i]) continue;
			else return false;
		}
		// Everything matches
		return true;
	}

	// Used to update the "online users" bar / online.php
	function update_hits($forum = 0, $thread = 0){
		global $loguser, $sql;
		$sql->queryp("
			INSERT INTO hits (user, ip, time, page, useragent, forum, thread) VALUES
			({$loguser['id']}, '{$_SERVER['REMOTE_ADDR']}', ".ctime().", ?, ?, $forum, $thread)
			
			ON DUPLICATE KEY UPDATE
			user 		= {$loguser['id']},
			ip 			= '{$_SERVER['REMOTE_ADDR']}',
			time 		= ".ctime().",
			page 		= ?,
			useragent 	= ?,
			forum 		= $forum,
			thread 		= $thread",
			[$_SERVER['REQUEST_URI'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REQUEST_URI'], $_SERVER['HTTP_USER_AGENT']]
		);
		// Hopefully this should be a tiny faster
		if ($loguser['id'])	$sql->query("UPDATE users SET lastview = ".ctime()." WHERE id = ".$loguser['id']);
	}
	
	// Mostly used after creating posts
	function update_last_post($id, $newdata = false, $fmode = false){
		
		// Modeled after makeuserlink, so you can directly send an array of values to enter

		global $sql, $loguser;
		
		// Update only forum last post data
		if ($fmode){ 
		
			// Separate check to attempt loading the new post data first
			if (!$sql->resultq("SELECT 1 FROM forums WHERE id = $id")){
				trigger_error("Attempted to update last post time for an invalid forum ID (#$id)", E_USER_NOTICE);
				return false;
			}
		
			if (!$newdata){
				$newdata = $sql->fetchq("
					SELECT p.id, p.user, p.time
					FROM posts p
					LEFT JOIN threads t ON p.thread = t.id
					WHERE t.forum = {$id}
					ORDER BY p.time DESC
				");
			}
			
			/*
				The returns are used for admin-threadfix2.php, where you need to check the return value
			*/
			if (!$newdata['id']){
				return $sql->query("UPDATE forums SET lastpostid = NULL WHERE id = $id");
			}
			else{
				return $sql->query("
					UPDATE forums SET
						lastpostid   = {$newdata['id']},
						lastpostuser = {$newdata['user']},
						lastposttime = {$newdata['time']}
					WHERE id = {$id}
				");
			}
			
		}
		
		else { // update thread + forum
			
			if (!$newdata){			
				$newdata = $sql->fetchq("
					SELECT p.id, p.user, p.time, t.forum
					FROM posts p
					LEFT JOIN threads t ON p.thread = t.id
					WHERE p.thread = {$id}
					ORDER BY p.time DESC
				");
			}
			if (!$newdata){
				trigger_error("Attempted to update last post time for an invalid thread ID (#$id)", E_USER_NOTICE);
				return false;
			}
			
			if (!$newdata['id']){
				$sql->query("UPDATE threads SET lastpostid = NULL WHERE id = $id");
				$sql->query("UPDATE forums  SET lastpostid = NULL WHERE id = ".$newdata['forum']);
			}
				
			else{
				
				$sql->query("
					UPDATE threads SET
						lastpostid = {$newdata['id']},
						lastpostuser = {$newdata['user']},
						lastposttime = {$newdata['time']}
					WHERE id = $id
				");
				
				$sql->query("
					UPDATE forums SET
						lastpostid = {$newdata['id']},
						lastpostuser = {$newdata['user']},
						lastposttime = {$newdata['time']}
					WHERE id = {$newdata['forum']}
				");
				
				$time = $newdata['time'];
				
				$sql->query("UPDATE users SET lastpost = $time WHERE id = {$loguser['id']}");
			}
		}
	
	}
	
	function update_user_post($id){
		global $sql;
		
		// Update with the correct last post
		$userdata = $sql->fetchq("
			SELECT p.time, o.time origtime
			FROM posts p
			
			LEFT JOIN posts_old o ON p.id = o.pid
			
			WHERE p.user = $id
			ORDER BY time DESC
			
			LIMIT 1
		");
		
		$time = $userdata['origtime'] ? $userdata['origtime'] : $userdata['time'];
		return $sql->query("UPDATE users SET lastpost = $time WHERE id = $id");
	}
	/*
	function update_threads_read($thread, $forum){
		global $sql, $loguser;
			
		$sql->query("
			UPDATE threads_read SET
			user{$loguser['id']} = ".ctime()."
			WHERE id = $thread
		");
		
		// Delete obsolete entries
		$min = $sql->resultq("SELECT MIN(user{$loguser['id']}) FROM threads_read WHERE id = $thread");
		$sql->query("UPDATE forums_read SET user{$loguser['id']} = $min WHERE id = $forum");
		$sql->query("DELETE FROM forums_read WHERE user{$loguser['id']} < $min");
		
	}*/
	
	
	/*
	function powlcheck($minpowl, $test = NULL){
		trigger_error("Used old powlcheck() function instead of using the powerlevel variables.", E_USER_DEPRECATED);
		global $loguser, $config;
		
		if (isset($test)) return ($test<$minpowl) ? false : true;
		else if ($loguser['powerlevel']<$minpowl)//&& !$config['admin-board'] )
			return false;
		else return true;
		
	}
	*/
	// Permissions
	
	// The standard "sorry not an admin" page
	function admincheck() {
		global $loguser, $isadmin;
		if (!$isadmin){
			irc_reporter(($loguser['id'] ? "User {$loguser['name']} (ID #{$loguser['id']})" : "Guest (IP {$_SERVER['REMOTE_ADDR']})")." attempted to access an admin tool.", 1);
			errorpage("Sorry, but this feature is reserved to administrators. You aren't one, so go away.");
		}
	}
	
	// Rarely used, but it's here
	function canviewforum($fid) {
		global $sql, $loguser;
		$minpower = $sql->resultq("SELECT minpower FROM forums WHERE id = $fid");
		return (!$minpower || $loguser['powerlevel'] >= $minpower);
	}
	
	/*
		Used to check if the logged in user is a local mod
	*/
	function ismod($forum = 0) {
		global $sql, $loguser, $ismod;

		// If we're already a global mod (3) or more don't bother checking anything
		if ($loguser['powerlevel'] > 2) return true; 
		
		// Check if we're a local mod (if we're not banned, which normally isn't even allowed)
		if ($forum && $loguser['powerlevel'] >= 0)
			$makemealocalmod = $sql->resultq("SELECT 1 FROM forummods WHERE (fid = {$forum} AND uid = {$loguser['id']})");
		else
			$makemealocalmod = false;
		
		return $makemealocalmod;
	}
	
	function doforumperm($forum) {
		global $loguser, $isadmin, $miscdata;
		$noposts	= ((!$isadmin && $miscdata['noposts']) || !$loguser['id']);
		$nopolls	= (!$isadmin && $forum['pollstyle']);
		$GLOBALS['canreply'] 	= !$noposts && $loguser['powerlevel'] >= $forum['minpowerreply'];
		$GLOBALS['canthread']	= !$noposts && $loguser['powerlevel'] >= $forum['minpowerthread'];
		$GLOBALS['canpoll']		= !$nopolls && !$noposts && $loguser['powerlevel'] >= $forum['minpowerthread'];
	}
	
	
	// Load/apply stuff
	
	/*
		dotags() Apply tags
		string 	- the text of a post/pm/announcement/whatever
		id		- the user id. Necessary as some tags use an user's information
	*/
	function dotags($string, $id){
		global $sql;
		/*
			Explanation of the dumb format used for tags:
			<tag name> => [<type>, <replacement>]
			where type can be
			 - 0 (direct text replacement)
			 - 1 (resultp SQL query with $id as ?)
			 - eval'd expression (kill me)
		*/
		static $tags = array(
			"&test&" 	=> [0, 'TEST TAG!'],
			"&test2&"	=> [1, 'SELECT name FROM users WHERE id = ?'],
			"&test3&"	=> [2, '13+$id-2'],
		);
		
		foreach($tags as $tag => $res){
			// Is this tag found in the string?
			if (strpos($string, $tag) !== false){
				switch ($res[0]){
					case 0: {
						// Type 0 - Simple replacement
						$string = str_replace($tag, $res[1], $string);
						break;
					}
					case 1: {
						// Type 1 - prepared resultq
						$string = str_replace($tag, $sql->resultp($res[1], [$id]), $string);
						break;
					}
					case 2: {
						// Type 2 - expression
						eval("\$repl = {$res[1]};");
						$string = str_replace($tag, $repl, $string);
						break;
					}
				}
			}
		}
		return $string;
	}
	
	/*
		doranks() Get all the rank name for the current thread/single id
		It returns an array where $rankQuery[<user id>] = <text for this rank>
		id 				- 	can be thread id or user id
		single			- 	if false it treats $id as the thread id, and selects the rank for all the user ids who posted in the thread
							if true  it treats $id as the user id, and extracts the rank text of that
		announcement 	-	is this an announcement or a thread?
	*/
	function doranks($id, $single = false, $announcement = false){
		global $sql, $loguser, $page;
		static $rankQuery = NULL;

		if (!$rankQuery){
			
			if (!$single){
				if (!$announcement){
					$select = $sql->fetchq("
						SELECT DISTINCT user
						FROM posts
						WHERE thread = $id
						
					", true, PDO::FETCH_COLUMN);
					//LIMIT ".(filter_int($_GET['page']) * $loguser['ppp']).",{$loguser['ppp']}
				} else {
					$select = $sql->fetchq("
						SELECT DISTINCT user
						FROM announcements
						WHERE forum = $id
					", true, PDO::FETCH_COLUMN);	
				}
				
				// [0.30 Beta] If we don't check if the returned array is empty
				// It will break the following query
				if (!$select) return array();
				
			}
			
			$rankQuery = $sql->fetchq("
				SELECT u.id, r.text
				FROM users u
				LEFT JOIN ranks r ON
					u.rankset = r.rankset
					AND r.posts = (
						SELECT r.posts
						FROM ranks r
						WHERE r.posts <= u.posts
						LIMIT 1
					)
				WHERE u.id ".($single ? "= $id" : "IN (".implode(",", $select).")
				"), true, PDO::FETCH_COLUMN|PDO::FETCH_UNIQUE);
		} else {
			trigger_error("Multiple calls to doranks()", E_USER_DEPRECATED);
		}
		
		return $rankQuery;
	}

	// Apply smilies to a string
	function dosmilies($string, $return_array = NULL){
		static $smilies = NULL;
		
		// as of now, this is intentionally not run on header and signature
		
		if (!$smilies){ // Load Smilies
			$handle = fopen("smilies.dat", "r");
			if ($handle !== false){
				while (($x = fgetcsv($handle, 128, ",")) !== false){
					if (isset($x[0])){
						$smilies[$x[0]] = "<img src='$x[1]'>";
					}
				}
				fclose($handle);
			}
			else{
				trigger_error("Couldn't open smilies.dat. Smilies will not be processed.", E_USER_WARNING);
				$smilies = array(0);
			}
			
		}
		
		// Dirty hack to return the smilies array for smilies.php
		if (!isset($return_array)) return strtr($string, $smilies);
		else return $smilies;
	}
	
	function loadlayouts($id, $single = false, $announcement = false){
		global $sql, $loguser, $page;

		if (!$single){
			if (!$announcement){
				$select = $sql->fetchq("
					SELECT DISTINCT user
					FROM posts
					WHERE thread = $id
					
				", true, PDO::FETCH_COLUMN);
				//LIMIT ".(filter_int($_GET['page']) * $loguser['ppp']).",{$loguser['ppp']}
			} else {
				$select = $sql->fetchq("
					SELECT DISTINCT user
					FROM announcements
					WHERE forum = $id
				", true, PDO::FETCH_COLUMN);				
			}
			
			// [0.30 Beta] If we don't check if the returned array is empty
			// It will break the following query
			if (!$select) return array();
			
		}
		
		$skiplayout = $loguser['showhead'] ? "" : "NULL";
		
		$layQuery = $sql->query("
			SELECT u.id, $skiplayout u.head, $skiplayout u.sign
			FROM users u
			WHERE u.id ".($single ? "= $id" : "IN (".implode(",", $select).")")
		);
		
		/*
			Intentional design choice:
			the false flag prevents smilies from being applied 
			to header and signature
		*/
		while($x = $sql->fetch($layQuery)){
			$layouts[$x['id']]['head'] = output_filters($x['head'], false, $x['id']);
			$layouts[$x['id']]['sign'] = output_filters($x['sign'], false, $x['id']);
		}
		
		return $layouts;
	}
	
	function getthreadicons(){
		
		$icons = file("posticons.dat");
		
		if (!$icons){
			trigger_error("Couldn't open posticons.dat. Thread icons will not be processed. ", E_USER_WARNING);
			$icons = array("");
		}

		return $icons;
	}
	
	function findthemes($all = false){
		global $sql;
		$themes = $sql->fetchq("SELECT id, name, file FROM themes".($all ? "" : " WHERE special = 0"), true, PDO::FETCH_UNIQUE);
		return $themes;
	}
	
	function findranks($mode = false){
		global $sql;
		
		// The equivalent of the previous function, but for ranks
		// To be called in editprofile.php
		$ranks = $sql->query("SELECT name FROM ranksets");

		$res[] = "None";
		while ($rank = $sql->fetch($ranks))
			$res[] = $rank['name'];
		
		return $mode ? implode("|", $res) : $res;

	}
	
	// Thread.php helpers
	
	function getthreadfrompost($pid){
		global $sql;
		if (!$pid){
			trigger_error("No post id given to getthreadfrompost().", E_USER_WARNING);
			return false;
		}
		$res = $sql->resultq("SELECT thread FROM posts WHERE id = $pid");
		return $res;
	}
	/*
	function getforumfrompost($pid){
		global $sql;
		if (!$pid){
			trigger_error("No post id given to getforumfrompost().", E_USER_WARNING);
			return false;
		}
		$res = $sql->resultq("
			SELECT t.forum
			FROM posts p
			LEFT JOIN threads t ON p.thread = t.id
		");
		return $res;
	}*/
	
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
	
	/*
		imageupload() check if you're uploading a valid image
		file	- an uploaded image (what you get from _FILES)
		maxsize - max image size allowed
		x		- max width allowed
		y		- max height allowed
		dest	- where to move the uploaded file. if set to false it returns a base64 encoded image (ie: for minipics)
	*/
	
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
	
	// Mini functions. You should not be changing these.
	
	function in_range($val, $min, $max, $equal = false){return ($equal ? ($min<=$val && $val<=$max) : ($min<$val && $val<$max));}
	function ctime(){return time()+$GLOBALS['config']['default-time-zone'];}
	function reverse_implode($separator, $string) {return implode($separator,array_reverse(explode($separator, $string)));}
	function x_unquote($x){return str_replace("\"","",$x);}
	function x_die($msg=""){error_printer(true, false, ""); die($msg);}
	function printdate($t, $x=true, $y=true){return date(($x?$GLOBALS['loguser']['dateformat']:"")." ".($y?$GLOBALS['loguser']['timeformat']:""), $t+$GLOBALS['loguser']['tzoff']*3600);}
	function array_extract($a,$i){foreach($a as $j => $c){$r[$j]=$c[$i];}return $r;} //TODO: Why not array_column() ?
	function contains_any($f,$a){foreach($a as $x){if(strpos($f,$x)!==false)return 1;}return 0;}
	function array_ror($a,$c=1){for($i=0;$i<$c;$i++){array_unshift($a, end($a));array_pop($a);}return $a;}
	function d($x){print "<title>VDS - BoardC</title><body style='background: #000; color: #fff'><pre>";var_dump($x); x_die();}
	function prepare_string(&$x){return input_filters(filter_string($x, true));}
	function pick_any($a){return $a[mt_rand(0, count($a)-1)];}
	//function redirect($x){pageheader("Redirect Error Handler");print "Click <a href='$x'>here</a> to continue.";pagefooter();}
	function redirect($url){header("Location: $url");x_die();}
	function choosetime($t){
		if 		($t<60) 	return "$t second".($t==1 ? "" : "s");
		else if ($t<3600)	return floor($t/60)." minute".(floor($t/60)==1 ? "" : "s");
		else if ($t<86400)	return floor($t/3600)." hour".(floor($t/3600)==1 ? "" : "s");
		else 				return floor($t/86400)." day".(floor($t/86400)==1 ? "" : "s");
	}
	function getyeardiff($a, $b){
		$a = new DateTime(date("Y-m-d", $a));
		$b = new DateTime(date("Y-m-d", $b));
		return $b->diff($a)->y;
	}
	
	function getfilename(){
		trigger_error("Used deprecated getfilename() function. Use \$scriptname instead.", E_USER_DEPRECATED);
		$path = explode("/", $_SERVER['PHP_SELF']);
		return $path[count($path)-1];
	}
	
	/*
		Handy reminder why this function is here:
		explode() doesn't like splitting NULL values
	*/
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
			$after = preg_replace("'$remove'", "", $after);
		}
		return $after;
	}
	
	// Token handling for _POST tokens

	function gettoken($extra=""){
		global $config, $loguser;
		$str = (
			filter_string($extra).
			filter_string($loguser['id']).
			$config['auth-salt'].
			filter_string($loguser['since']).
			filter_string($loguser['password']).
			$_SERVER['REMOTE_ADDR']
		);
		return hash("sha256", $str);
	}
	
	function checktoken($get = false, $extra = ""){
		global $config, $loguser;
		
		$str = (
			filter_string($extra).
			filter_string($loguser['id']).
			$config['auth-salt'].
			filter_string($loguser['since']).
			filter_string($loguser['password']).
			$_SERVER['REMOTE_ADDR']
		);
		
		$token		= hash("sha256", $str);		
		$outtoken 	= $get ? filter_string($_GET['auth']) : filter_string($_POST['auth']);

		
		if ($token != $outtoken){
			irc_reporter("Wrong token used by ".($loguser['id'] ? $loguser['name']." " : "[Guest]")."(IP {$_SERVER['REMOTE_ADDR']}) for page {$_SERVER['PHP_SELF']}", 1);
			pageheader("Invalid token");
			
			// Attempt to preserve current variables
			$savepost = $saveget = "";
			unset($_POST['auth'], $_GET['auth'], $_POST['invalidtoken_retry']);
			
			if (is_array($_POST)){
				foreach($_POST as $key => $val){
					$savepost .= "<input type='hidden' name='$key' value=\"".htmlspecialchars($val)."\">\n";
				}
			}

			if (is_array($_GET)){
				foreach($_GET as $key => $val){
					$saveget .= "&$key=$val";
				}
			}
			
			if ($get) {
				$saveget .= "&auth=$token";
			} else {
				$savepost .= "<input type='hidden' name='auth' value='$token'>";
			}
			
			errorpage("
				<form method='POST' action='?$saveget'>
				$savepost
				Sorry, but the token for the required action is either missing or has expired.<br>
				<input type='submit' name='invalidtoken_retry' value='Retry'>
				</form>
			", false);
		}
	}

	function checkgettoken($extra=""){
		// Wrapper
		trigger_error("checkgettoken", E_USER_DEPRECATED);
		return checktoken($extra, true);
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
	
	// Error reporting
	
	function error_reporter($type, $string, $file, $line){
		global $isadmin, $errors;
		
		static $type_txt = array(
			E_ERROR 			=> "Error",
			E_WARNING 			=> "Warning",
			E_NOTICE 			=> "Notice",
			E_USER_ERROR 		=> "User Error",
			E_USER_WARNING 		=> "User Warning",
			E_USER_NOTICE 		=> "User Notice",
			E_STRICT 			=> "Strict Notice",
			E_RECOVERABLE_ERROR => "Recoverable Error",
			E_DEPRECATED 		=> "Deprecated",
			E_USER_DEPRECATED 	=> "User Deprecated",
		);
		
		static $skip_error 		= array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE, E_USER_DEPRECATED);
		static $skip_file 		= array("mysql.php","helpers.php");
		
		// Check if we are allowed to backtrace further with this error type
		// and if any part of the string $file contains either 'mysql.php' or 'helpers.php'
		if (in_array($type, $skip_error) && contains_any($file, $skip_file)){
			$error = debug_backtrace();
			for($i=1; isset($error[$i]) && contains_any($error[$i]['file'], $skip_file); $i++);
			$file = $error[$i]['file'];
			$line = $error[$i]['line'];
		}
		
		if (!$isadmin){
			$msg = "{$type_txt[$type]} - $string in $file at line $line";
			irc_reporter($msg, 1);
		}
		
		
		$errors[] = array($type_txt[$type], $string, $file, $line);
		return $errors;
		
	}
	
	function error_printer($trigger, $report, $errors){
		static $called = false;
		
		if (!$called){
			
			$called = true;
			
			if (!$report || empty($errors)){
				return $trigger ? "" : true;
			}
			
			if ($trigger != false){ // called by pagefooter()
			
				$list = "";
				foreach ($errors as $error){
					$list .= "
						<tr>
							<td class='light'><nobr>".htmlspecialchars($error[0])."</nobr></td>
							<td class='dim'>".htmlspecialchars($error[1])."</td>
							<td class='light'>".htmlspecialchars($error[2])."</td>
							<td class='dim'>".htmlspecialchars($error[3])."</td>
						</tr>";
				}
					
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
	

	function irc_reporter($msg, $id){
		global $config;
		
		if ($config['enable-irc-reporting']){
			switch ($id){
				case 0:{
					$chan = $config['public-chan'];
					break;
				}
				case 1:{
					$chan = $config['private-chan'];
					break;
				}
			}
			// TODO: Actual IRC Reporting
		}
	}
	

?>