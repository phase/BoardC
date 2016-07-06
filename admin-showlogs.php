<?php

	require "lib/function.php";
	
	/*
		[0.26] Board logs
		
		Notice - Hits aren't updated when visiting this page.
	*/
	
	if (!powlcheck(4)){
		update_hits();
		errorpage("Go away.");
	}
	
	$sysadmin 	= powlcheck(5); // Can edit / ban
	
	$table 		= filter_string($_GET['mode']);
	$ord		= filter_int($_GET['ord']);
	$page		= filter_int($_GET['page']);
	
	$allowed = array("minilog", "log", "jstrap", "hits");
	if (!in_array($table, $allowed)) $table = "minilog";
	
	if ($sysadmin){
		if (isset($_GET['ban'])){
			/*
				Permaban + IP Ban
				(The valid check goes by the IP as it's guaranteed one is sent, unlike the user ID)
			*/
			$ip 	= filter_string($_GET['bi']);
			$user 	= filter_string($_GET['bu']);
			// Is it valid?
			if (md5($ip) != $_GET['ban'] || !$ip)
				errorpage("Sorry, but the choice is invalid (or the md5 hash screwed up)");
			
			if ($user) userban($user, false, true, "Thanks for playing!", "Added permanent ban to User ID #$user");
			ipban("Abusive/Malicious Behaviour", "Added IP ban for $ip", $ip, true);
			
			header("Location: admin-showlogs.php?mode=$table&ord=$ord&page=$page");
		}
		
		if (isset($_GET['trim'])){
			/*
				Truncate specified table
			*/
			if (isset($_POST['doit'])){
				$sql->query("TRUNCATE $table");
				header("Location: admin-showlogs.php?mode=$table");
			}
			
			pageheader("Trim $table");
			print adminlinkbar()."
			<center>
				<table class='main c'>
					<tr><td class='head'>Warning</td></tr>
					
					<tr><td class='light'>
						Are you sure you want to trim the table '$table'?<br>
						<br>
						You cannot undo this action!
					</td></tr>
					<form method='POST' action='?mode=$table&trim'>
						<tr><td class='head'>
							<a href='?mode=$table&ord=$ord&page=$page'>Return</a> - <input type='submit' name='doit' value='Truncate'>
						</td></tr>
					</form>
					
				</table>
			</center>
			";
			pagefooter();
		}
	}
	
	$total 		= $sql->resultq("SELECT COUNT(id) FROM $table");
	$limit		= 100;
	$pagectrl	= dopagelist($total, $limit, "admin-showlogs", "&mode=$table&ord=$ord");
	
	if ($table == 'minilog' || $table == 'log'){
		// Detailed view
		$view = filter_int($_GET['view']);
		if ($view){
			pageheader("Request #$view");
			
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
			
			foreach($banflags as $flagset)
				$reason .= $fw->get_reason_id($flagset)." (".filter_int($flagset).")\n";
			$requests = str_replace("\0", "\n", $all['requests']);
			// End snip
			
			// Next / Previous controls
			$max = $sql->resultq("SELECT MAX(id) FROM log");
			$prev_txt = "<a href='?mode=$table&view=".($view <= 1 ? 1 : $view + 1)."'>&lt;</a>";
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
					<td class='light' style='width: 50%'><b>IP Address</b><br><a href='admin-ipsearch.php?ip={$all['ip']}'>{$all['ip']}</a>".banformat($all)."</td>
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
	if ($table == 'minilog'){
		/*
			Excerpts from the log flagged as malicious (banflags != 0)
		*/
		
		//if (!file_exists("lib/firewall.php"))
		//	errorpage("Sorry, but this page requires the firewall to be loaded.");
	
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
			
			foreach($banflags as $flagset)
				$reason .= "<nobr>".$fw->get_reason_id($flagset)."</nobr><br>";

			// Requests are merged with the null value, convert to <br> for a prettier view
			$requests = str_replace("\0", "<br>", $x['requests']);

			
			$txt .= "
				<tr>
					<td class='light c'><a href='?mode=$table&view={$x['id']}'>{$x['id']}</a></td>
					<td class='light c'>$user</td>
					<td class='dim fonts c'>".printdate($x['time'])."</td>
					<td class='light fonts c'>$reason</td>
					<td class='dim fonts c'>$requests</td>
					<td class='light c'><nobr><a href='admin-ipsearch.php?ip={$x['ip']}'>{$x['ip']}</a>".banformat($x)."</nobr></td>
				</tr>
				";
		}
	}
	else if ($table == 'log'){
		/*
			Log viewer
		*/
		
		//if (!file_exists("lib/firewall.php"))
		//	errorpage("Sorry, but this page requires the firewall to be loaded.");
	
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
					<td class='light c'><nobr><a href='admin-ipsearch.php?ip={$x['ip']}'>{$x['ip']}</a>".banformat($x)."</nobr></td>
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
					<td class='light c'><a href='admin-ipsearch.php?ip={$x['ip']}'>{$x['ip']}</a>".banformat($x)."</td>
				</tr>
				";
		
	}
	else if ($table == 'hits'){
		/*
			Board logs
		*/
		$colspan = 8;
		$tablename = "Board logs";
		
		$list = $sql->query("
			SELECT h.*, $userfields uid, i.ip ipbanned, f.name fname
			FROM hits h
			LEFT JOIN users  u ON h.user  = u.id
			LEFT JOIN ipbans i ON h.ip    = i.ip
			LEFT JOIN forums f ON h.forum = f.id
			ORDER BY h.id ".($ord ? "ASC" : "DESC")."
			LIMIT ".($limit*$page).", $limit
		");
		
		$txt = "
			<tr>
				<td class='head c'>#</td>
				<td class='head c'>User</td>
				<td class='head c'>Time</td>
				<td class='head c'>Page</td>
				<td class='head c'>Forum</td>
				<td class='head c'>User Agent</td>
				<td class='head c'>Referer</td>
				<td class='head c'>IP</td>
			</tr>
			";
		
		while($x = $sql->fetch($list)){
			$user 	= $x['uid'] 	? makeuserlink($x['uid'], $x) : "Guest";
			$forum 	= $x['forum'] 	? "<a href='forum.php?id={$x['forum']}'>{$x['fname']}</a>" : "";
			$txt .= "
				<tr>
					<td class='light c'>{$x['id']}</td>
					<td class='light c'>$user</td>
					<td class='dim fonts c'>".printdate($x['time'])."</td>
					<td class='dim fonts c'>".htmlspecialchars($x['page'])."</td>
					<td class='dim fonts c'>$forum</td>
					<td class='dim fonts c'>".htmlspecialchars($x['useragent'])."</td>
					<td class='dim fonts c'>".htmlspecialchars($x['referer'])."</td>
					<td class='light c'><nobr><a href='admin-ipsearch.php?ip={$x['ip']}'>{$x['ip']}</a>".banformat($x)."</nobr></td>
				</tr>
				";
		}
		
	}
	else errorpage("Unknown error - Table $table");

	
	pageheader("Log viewer - $tablename");
	
	//$banner = $sysadmin ? "" : "<tr><td class='dark c' colspan='$colspan'><b>NOTICE: The logs are in read-only mode!</b></td></tr>";
	
	print adminlinkbar()."
	<table class='main w'>
		<tr><td class='head c' colspan='2'>Options</td></tr>
		
		<tr>
			<td class='light c'><b>Log</b></td>
			<td class='dim'><a href='?mode=log'>Requests</a> - <a href='?mode=minilog'>Denied requests</a> - <a href='?mode=jstrap'>XSS Attempts</a> - <a href='?mode=hits'>Board logs</a></td>
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
	
	// Nested ? condition fun!
	function banformat($data){
		global $sysadmin;
		return $data['ipbanned'] ? "<small><br>[IP BANNED]</small>" : ($sysadmin ? " - <a href='?ban=".md5($data['ip'])."&bi={$data['ip']}".($data['user'] ? "&bu={$data['user']}" : "")."&mode={$_GET['mode']}'>Ban</a>" : "");
	}
?>