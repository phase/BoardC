<?php

	require "lib/function.php";
	
	/*
		[0.26] Board logs / Exploit attempts
	*/
	
	admincheck();
	
	$table 		= filter_string($_GET['mode']);
	$ord		= filter_int($_GET['ord']);
	$page		= filter_int($_GET['page']);
	
	if (defined('FW_LOADED')){
		$allowed = array("minilog", "log", "jstrap", "hits");
		$default = "minilog";
	} else {
		$allowed = array("jstrap", "hits");
		$default = "hits";
	}
	
	if (!in_array($table, $allowed)) $table = $default;
	
	if ($sysadmin){
		
		if (isset($_POST['ipban'])){
			checktoken();
			/*
				Permaban + IP Ban
			*/
			$ip 	= filter_string($_GET['bi']);
			$user 	= filter_string($_GET['bu']);
			
			if ($user) userban($user, false, true, "Thanks for playing!", "Added permanent ban to User ID #$user");
			ipban("Abusive/Malicious Behaviour", "Added IP ban for $ip", $ip, 0, true);
			
			header("Location: admin-showlogs.php?mode=$table&ord=$ord&page=$page");
			x_die();
		}
		
		if (isset($_GET['trim'])){
			/*
				Truncate specified table
			*/
			if (isset($_POST['doit'])){
				checktoken();
				$sql->query("TRUNCATE $table");
				setmessage("$table has been truncated.");
				redirect("admin-showlogs.php?mode=$table");
			}
			
			pageheader("Trim $table");
			print adminlinkbar()."
			<center>
				<table class='main c'>
				
					<tr><td class='head'>Warning</td></tr>
					
					<tr>
						<td class='light'>
							Are you sure you want to trim the table '$table'?<br>
							<br>
							You cannot undo this action!
						</td>
					</tr>
					
					<form method='POST' action='?mode=$table&trim'>
						<tr>
							<td class='dark'>
								<a href='?mode=$table&ord=$ord&page=$page'>Return</a> - 
								<input type='submit' name='doit' value='Truncate'>
								<input type='hidden' name='auth' value='$token'>
							</td>
						</tr>
					</form>
					
				</table>
			</center>
			";
			pagefooter();
		}
	}
	
	// Page numbers
	$total 		= $sql->resultq("SELECT COUNT(*) FROM $table");
	$limit		= 100;
	$pagectrl	= dopagelist($total, $limit, "admin-showlogs", "&mode=$table&ord=$ord");
	
	/*
		Minilog and log are basically the same table
		The detailed view should be available to both "modes"
	*/
	if ($table == 'minilog' || $table == 'log'){
		
		$view = filter_int($_GET['view']);
		if ($view){
			
			pageheader("Request #$view");
			
			// Get everything from the log table, as it's important
			$all 	= $sql->fetchq("
				SELECT l.*, $userfields user, i.ip ipbanned
				FROM log l
				LEFT JOIN users  u ON u.id  = (SELECT MIN(u.id) FROM users u WHERE l.ip = u.lastip)
				LEFT JOIN ipbans i ON l.ip  = i.ip
				WHERE l.id = $view
			");
			
			// Snip
			$user 	= $all['user'] ? makeuserlink($all['user'], $all) : "Guest";
			
			// Get the verbose reason for the block
			$reason 	= "";
			$banflags 	= explode(";", $all['banflags']);
			
			foreach($banflags as $flagset){
				$reason .= $fw->get_reason_id($flagset)." (".filter_int($flagset).")\n";
			}
			$requests = str_replace("\0", "\n", $all['requests']);
			// End snip
			
			// Next / Previous controls
			$max = $sql->resultq("SELECT MAX(id) FROM log");
			$prev_txt = "<a href='?mode=$table&view=".($view <= 1 ? 1 : $view - 1)."'>&lt;</a>";
			$next_txt = "<a href='?mode=$table&view=".($view == $max ? $max : $view + 1)."'>&gt;</a>";
			
			print adminlinkbar()."
			<table class='main w c'><tr><td class='dim'><a href='?mode=$table'>Return to the log</a></td></tr></table>
			<br><center>
			<table class='main w'>
				<tr>
					<td class='head c' colspan='2'>
						$prev_txt
						Request #$view
						$next_txt
					</td>
				</tr>
				
				<tr>
					<td class='light' style='width: 50%'><b>IP Address</b><br>".ipformat($all)."</td>
					<td class='light' style='width: 50%'><b>Date</b><br>".printdate($all['time'])."</td>
				</tr>
				<tr>
					<td class='light'><b>User</b><br>$user</td>
					<td class='light'><b>Page</b><br>{$all['page']}</td>
				</tr>

				<tr>
					<td class='light'><b>Reason/Banflags</b><br><textarea style='width: 100%; resize: none;' readonly='readonly'>".trim($reason)."</textarea></td>
					<td class='light'><b>Bad requests</b><br><textarea style='width: 100%; resize: none;' readonly='readonly'>$requests</textarea></td>
				</tr>
				
				<tr>
					<td class='light' colspan='2'><b>_GET</b><br><textarea rows=1 style='width: 100%; resize: none;' readonly='readonly'>".htmlspecialchars(urldecode($all['get']))."</textarea></td>
				</tr>
				<tr>
					<td class='light'><b>_POST</b><br><textarea style='width: 100%; height:300px; resize: none;' readonly='readonly'>{$all['post']}</textarea></td>
					<td class='light'><b>_COOKIE</b><br><textarea style='width: 100%; height:300px; resize: none;' readonly='readonly'>{$all['cookie']}</textarea></td>
				</tr>
				
				<tr>
					<td class='light' colspan='2'><b>User Agent</b><br><textarea rows=1 style='width: 100%; resize: none;' readonly='readonly'>".htmlspecialchars($all['useragent'])."</textarea></td>
				</tr>
				<tr>
					<td class='light' colspan='2'><b>Referer</b><br><textarea rows=1 style='width: 100%; resize: none;' readonly='readonly'>".htmlspecialchars($all['referer'])."</textarea></td>
				</tr>
				<tr>
					<td class='light' colspan='2'><b>Host</b><br><textarea rows=1 style='width: 100%; resize: none;' readonly='readonly'>".htmlspecialchars($all['host'])."</textarea></td>
				</tr>				
			</table></center>";
			
			
			pagefooter();
			
		}
	}
	
	// Per-table layouts
	
	if ($table == 'minilog'){
		/*
			Excerpts from the log flagged as malicious (banflags != 0)
		*/
		
		$colspan 	= 6;
		$tablename 	= "Denied requests";


		$list = $sql->query("
			SELECT l.id, l.time, l.ip, l.banflags, l.requests, $userfields user, i.ip ipbanned
			FROM minilog m
			LEFT JOIN users  u ON u.id  = (SELECT MIN(u.id) FROM users u WHERE m.ip = u.lastip)
			LEFT JOIN log    l ON m.id  = l.id
			LEFT JOIN ipbans i ON m.ip  = i.ip
			ORDER BY m.id ".($ord ? "ASC" : "DESC")."
			LIMIT ".($limit*$page).", $limit
		");
		
		$txt = "
			<tr>
				<td class='head c'>#</td>
				<td class='head c'>User</td>
				<td class='head c'>Time</td>
				<td class='head c'>Reason</td>
				<td class='head c'>Bad request</td>
				<td class='head c'>IP</td>
			</tr>
			";
			

		while($x = $sql->fetch($list)){
			$user 	= $x['user'] ? makeuserlink($x['user'], $x) : "Guest";
			
			// Get the verbose reason for the block
			$reason = "";
			$banflags 	= explode(";", $x['banflags']);
			
			foreach($banflags as $flagset){
				$reason .= $fw->get_reason_id($flagset)."<br>";
			}
			
			// Requests are merged with the null value, convert to <br> for a prettier view
			$requests = str_replace("\0", "<br>", $x['requests']);

			
			$txt .= "
				<tr>
					<td class='light c'><a href='?mode=$table&view={$x['id']}'>{$x['id']}</a></td>
					<td class='light c'>$user</td>
					<td class='dim fonts c'>".printdate($x['time'])."</td>
					<td class='light fonts c nobr'>$reason</td>
					<td class='dim fonts c'>$requests</td>
					<td class='light c'><center>".ipformat($x)."</center></td>
				</tr>
				";
		}
	}
	else if ($table == 'log'){
		/*
			Board logs
		*/
		
		$colspan 	= 6;
		$tablename 	= "Requests";
		
		$logs_query = "
			SELECT l.id, l.time, l.ip, l.banflags, l.requests, l.page, l.get, $userfields user, i.ip ipbanned
			FROM log l
			LEFT JOIN users  u ON u.id  = (SELECT MIN(u.id) FROM users u WHERE l.ip = u.lastip)
			LEFT JOIN ipbans i ON l.ip  = i.ip
			ORDER BY l.id ".($ord ? "ASC" : "DESC")."
			LIMIT ".($limit*$page).", $limit
		";
		
		

		/*
			Get and display the log
		*/
		$list = $sql->query($logs_query);
		
		$txt = "
			<tr>
				<td class='head c'>#</td>
				<td class='head c'>User</td>
				<td class='head c'>Time</td>
				<td class='head c'>Page</td>
				<td class='head c'>Bad request</td>
				<td class='head c'>IP</td>
			</tr>
			";
			

		while($x = $sql->fetch($list)){
			$user 	= $x['user'] ? makeuserlink($x['user'], $x) : "Guest";

			$requests = $x['requests'] ? "<span class='danger'><b>YES</b></span>" : "NO";

			
			$txt .= "
				<tr>
					<td class='light c'><a href='?mode=$table&view={$x['id']}'>{$x['id']}</a></td>
					<td class='light c'>$user</td>
					<td class='dim fonts c'>".printdate($x['time'])."</td>
					<td class='light fonts c'>".htmlspecialchars($x['page'])."?".htmlspecialchars($x['get'])."</td>
					<td class='dim fonts c'>$requests</td>
					<td class='light c'><center>".ipformat($x)."</center></td>
				</tr>
				";
		}
	}
	else if ($table == 'jstrap'){
		/*
			Javascript filters
		*/
		$colspan = 4;
		$tablename = "XSS Attempts";
		
		$list = $sql->query("
			SELECT j.id, j.user, j.ip, j.filtered, $userfields uid, i.ip ipbanned
			FROM jstrap j
			LEFT JOIN users  u ON j.user = u.id
			LEFT JOIN ipbans i ON j.ip   = i.ip
			ORDER BY j.id ".($ord ? "ASC" : "DESC")."
			LIMIT ".($limit*$page).", $limit
		");
		
		$txt = "
			<tr>
				<td class='head c'>#</td>
				<td class='head c'>User</td>
				<td class='head c'>Post</td>
				<td class='head c'>IP</td>
			</tr>
			";
		
		while($x = $sql->fetch($list))
			$txt .= "
				<tr>
					<td class='light c'>{$x['id']}</td>
					<td class='dim c'>".makeuserlink($x['uid'], $x)."</td>
					<td class='dim fonts c'>{$x['filtered']}</td>
					<td class='light c'><center>".ipformat($x)."</center></td>
				</tr>
				";
		
	}
	else if ($table == 'hits'){
		
		/*
			Online Users
		*/
		$colspan = 9;
		$tablename = "Online users";
		
		$list = $sql->query("
			SELECT h.*, $userfields uid, i.ip ipbanned, f.name fname, t.name tname
			FROM hits h
			LEFT JOIN users   u ON h.user   = u.id
			LEFT JOIN ipbans  i ON h.ip     = i.ip
			LEFT JOIN forums  f ON h.forum  = f.id
			LEFT JOIN threads t ON h.thread = t.id
			ORDER BY u.lastip ".($ord ? "ASC" : "DESC")."
			LIMIT ".($limit*$page).", $limit
		");
		
		$txt = "
			<tr>
				<td class='head c'>#</td>
				<td class='head c'>User</td>
				<td class='head c'>Time</td>
				<td class='head c'>Page</td>
				<td class='head c'>Forum</td>
				<td class='head c'>Thread</td>
				<td class='head c'>User Agent</td>
				<td class='head c'>Referer</td>
				<td class='head c'>IP</td>
			</tr>
			";
		
		for($i = 1; $x = $sql->fetch($list); $i++){
			$user 	= $x['uid'] 	? makeuserlink($x['uid'], $x) : "Guest";
			$forum 	= $x['forum'] 	? "<a href='forum.php?id={$x['forum']}'>".htmlspecialchars($x['fname'])."</a>" : "";
			$thread = $x['thread'] 	? "<a href='thread.php?id={$x['thread']}'>".htmlspecialchars($x['tname'])."</a>" : "";
			$txt .= "
				<tr>
					<td class='light c'>$i</td>
					<td class='light c'>$user</td>
					<td class='dim fonts c'>".printdate($x['time'])."</td>
					<td class='dim fonts c'>".htmlspecialchars($x['page'])."</td>
					<td class='dim fonts c'>$forum</td>
					<td class='dim fonts c'>$thread</td>
					<td class='dim fonts c'>".htmlspecialchars($x['useragent'])."</td>
					<td class='dim fonts c'>".htmlspecialchars($x['referer'])."</td>
					<td class='light c'><center>".ipformat($x)."</center></td>
				</tr>
				";
		}
		
	}

	
	pageheader("Log viewer - $tablename");
	
	//$banner = $sysadmin ? "" : "<tr><td class='dark c' colspan='$colspan'><b>NOTICE: The logs are in read-only mode!</b></td></tr>";
	
	print adminlinkbar().$message."
	<table class='main w'>
		<tr><td class='head c' colspan='2'>Options</td></tr>
		
		<tr>
			<td class='light c'><b>Log</b></td>
			<td class='dim'>".
				(defined('FW_LOADED')
					? "<a href='?mode=log'>Board logs</a> - <a href='?mode=minilog'>Denied requests</a> - "
					: ""
				)."
				<a href='?mode=jstrap'>XSS Attempts</a> - <a href='?mode=hits'>Online users logs</a>
			</td>
		</tr>
		
		<tr>
			<td class='light c'><b>Sorting</b></td>
			<td class='dim'><a href='?mode=$table'>Sort by newest to oldest</a> - <a href='?mode=$table&ord=1'>Sort by oldest to newest</a></td>
		</tr>
		".($sysadmin ? "
		<tr>
			<td class='light c'><b>Actions</b></td>
			<td class='dim'><a href='?mode=$table&trim'>Truncate $table</a></td>
		</tr>" : "")."
	</table>
	<br>
		
	$pagectrl
	<table class='main w'>
		<tr><td class='head c' colspan='$colspan'>Log viewer - $tablename ($table) | Sorting from ".($ord ? "oldest to newest" : "newest to oldest")."</td></tr>
		$txt
	</table>
	$pagectrl
	";
	
	pagefooter();
	
	function ipformat($data){
		global $sysadmin, $token, $table, $ord, $page;
		$iplink = "<a href='admin-ipsearch.php?ip={$data['ip']}'>{$data['ip']}</a>";
		if ($data['ipbanned'])  return "$iplink<br>[<a href='admin-ipbans.php?ip={$data['ip']}'>IP BANNED</a>]";
		else if (!$sysadmin)	return "$iplink";
		else{
			return "
			<table cellspacing=0>
				<tr>
					<td>$iplink -&nbsp;</td>
					<td>
						<form method='POST' action='?mode=$table&ord=$ord&page=$page&bi={$data['ip']}".($data['user'] ? "&bu={$data['user']}" : "")."'>
							<input type='hidden' name='auth' value='$token'>
							<input type='submit' name='ipban' value='IP Ban'>
						</form>
					</td>
				</tr>
			</table>
			";

		}		
	}
?>