<?php

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
?>