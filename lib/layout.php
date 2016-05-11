<?php

	function pageheader($title, $show = true, $forum = 0){
		global $config, $hacks, $fw_error, $loguser, $views, $miscdata, $meta;
		
		$meta_txt = "";
		
		if (filter_bool($meta['noindex'])){
			$meta_txt = "<meta name='robots' content='noindex, nofollow, noarchive'>";
			header('X-Robots-Tag: noindex, nofollow, noarchive', true);
		}
		
		$links = "";
		
		$isadmin = powlcheck(4);
		
		if (!powlcheck(5))
			$fw_error = "";
		
		if (powlcheck(1))
			$links .= "<a href='shoped.php'>Shop Editor</a> - ";		
		
		if ($isadmin)
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
		";
		
		if ($isadmin)
			$links2 .= " - <a href='announcement.php'>Announcements</a>";
		
		$links2 .= "<br/>
		<a href='latestposts.php'>Latest posts</a>
		";
		
		if (isset($miscdata['theme'])) $loguser['theme'] = $miscdata['theme'];
		
		$themes = findthemes(false, true);
		$css = file_get_contents("css/".$themes[$loguser['theme']]['file']);
		
		if (!$css)
			$css = "";
		else if (strpos($css, "META")){
			/*
			Special META flags
			Board name - 
			Board title (image) - 
			*/
			$cssmeta = explode(PHP_EOL,$css, 4);
			$config['board-name'] = $cssmeta[1];
			$config['board-title'] = $cssmeta[2];
		}
		
		if ($show)
			$title .= " - ".$config['board-name'];
		
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

	function dialog($title, $head, $msg){
		
		x_die("
		<!doctype html>
		<head>
			<title>$title</title>
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
					<td class='head'><center><b>$head</b></center></td>
				</tr>
				<tr>
					<td class='dim'>
					$msg
					</td>
				</tr>
			</table></center>
			</body>
		</head>");
		
	}
	
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
	
	function doannbox($id = 0){
		
		global $sql, $loguser, $userfields;
		
		$announce = $sql->query("
			SELECT a.id aid, a.name aname, a.title atitle, a.user, a.time, a.forum, $userfields, SUM(n.user".$loguser['id'].") new
			FROM announcements a
			LEFT JOIN users u ON a.user = u.id
			LEFT JOIN new_announcements n ON a.id = n.id
			WHERE a.forum = 0 ".($id ? "OR a.forum = $id" : "")."
			GROUP BY a.id DESC, a.forum ASC
		");
		
		$txt = "";
		
			while($ann=$sql->fetch($announce))
				$txt .= "
					<tr>
						<td colspan='7' class='head c fonts'>".($ann['forum'] ? "Forum a" : "A")."nnouncements</td>
					</tr>
					<tr>
						<td class='dim c'>".($ann['new'] ? "<img src='images/status/new.gif'>" : "")."</td>
						<td class='light' colspan='6'><a href='announcement.php?id=".$ann['forum']."'>".$ann['aname']."</a> -- Posted by ".makeuserlink(false, $ann)." on ".printdate($ann['time']).($ann['atitle'] ? "<small><br/>".$ann['atitle']."</small>" : "")."</td>
					</tr>";
			
		return $txt;
		
	}
	
	function donamecolor($powl, $sex, $usercolor = false){
		if (!$usercolor){
			if ($powl>4) $powl = 4;
			//if ($powl<0) $powl = '-1';
			return "class='nmcol$powl$sex'";
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
		$querypowl = $loguser['powerlevel']<0 ? 0 : $loguser['powerlevel'];
		
		$forums = $sql->query("
		SELECT f.id, f.name, f.category, c.name catname
		FROM forums f
		LEFT JOIN categories c
		ON f.category = c.id
		WHERE (f.powerlevel<=$querypowl AND c.powerlevel<=$querypowl $hidden)
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

		if (isset($use)) $sel[$use] = "selected";
		
		$txt = "Avatar: <select name='avatar'>
					<option value='0'>-Normal avatar-</option>";
		if ($moods)
			while ($mood = $sql->fetch($moods))
				$txt .= "<option value='".$mood['file']."' ".filter_string($sel[$mood['file']]).">".$mood['title']."</option>";
		
		return "$txt</select>";
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
?>