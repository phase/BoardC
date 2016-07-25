<?php

	require "lib/function.php";
	
	pageheader("Online users");
	
	$isadmin = powlcheck(4);
	//if ($isadmin) print adminlinkbar();
	
	$time = filter_int($_GET['time']);
	$ip = filter_string($_POST['ip']);
	
	if (!$time) $time = 300; // 5 minutes
	
	if ($isadmin){
		if (isset($_GET['ban'])){
			$id = filter_int($_GET['ban']);
			userban($id, false, false, "", "Banned User ID #$id (online.php ban)"); 
			header("Location: online.php?time=$time");
		}
		else if (filter_string($_GET['ipban'])){
			$ip = base64_decode($_GET['ipban']);
			ipban("online.php ban", "IP Banned $ip (online.php ban)", $ip, true); 
			header("Location: online.php?time=$time");
		}
	}
	
	$online = $sql->query("
		SELECT 	COUNT(h.ip) tmp, h.ip, h.time, h.page, h.useragent, i.id ipbanned,
				$userfields, u.posts, u.lastpost, x.bot, x.proxy, x.tor
		FROM hits h
		
		LEFT JOIN users  u ON h.user = u.id
		LEFT JOIN ipbans i ON h.ip   = i.ip
		LEFT JOIN ipinfo x ON h.ip   = x.ip
		
		WHERE h.time>".(ctime()-$time)."
		".($ip && $isadmin ? "AND h.ip = '$ip'" : "")."
		
		GROUP BY h.ip, h.user
		ORDER BY ".(isset($_GET['ipsort']) && $isadmin ? "h.ip" : "h.time ASC")."
	");
	
	$txt = array("", "");
	$i = array(0, 0);
	
	// Text used to show flags
	$ipinfo_txt = array(
		["<span class='disabled'>B</span>","<span class='danger'>B</span>"], // Bot
		["<span class='disabled'>P</span>","<span class='danger'>P</span>"], // Proxy
		["<span class='disabled'>T</span>","<span class='danger'>T</span>"], // Tor
	);
	
	
	while ($user = $sql->fetch($online)){
		
		
		if ($user['id']){ // registered user
			$page = htmlspecialchars(input_filters($user['page']));
			
			$i[0]++;
			$txt[0] .= "
			<tr>
				<td class='light c'>$i[0]</td>
				<td class='dim'>".makeuserlink(false, $user).($isadmin ? "<small> - <a class='danger' href='?ban=".$user['id']."'>Ban</a></small>" : "")."</td>
				<td class='light c'>".printdate($user['time'])."</td>
				<td class='light c'>".($user['lastpost'] ? printdate($user['lastpost']) : "None")."</td>
				<td class='dim'><a href='$page' rel='nofollow'>$page</a></td>
				<td class='dim c'>".$user['posts']."</td>
				".($isadmin ? "
				<td class='light c'>
					<a href='admin-ipsearch.php?ip=".$user['ip']."'>".$user['ip']."</a>
					<small> - 
						<a class='danger' href='?ipban=".base64_encode($user['ip'])."'>IP Ban</a> 
						<a href='https://www.google.com/search?q=".$user['ip']."'>[G]</a> 
						<a href='https://en.wikipedia.org/wiki/User:".$user['ip']."'>[W]</a>
						".($user['ipbanned'] ? "<br/>[IP BANNED]" : "")."
					</small>
				</td>
				<td class='light c'>
					".$ipinfo_txt[0][$user['bot']].$ipinfo_txt[1][$user['proxy']].$ipinfo_txt[2][$user['tor']]."
				</td>
				"
				: "")."
			</tr>";
		}
		else{ // guest
			$page = htmlspecialchars(input_filters($user['page']));
			
			$i[1]++;
			$txt[1] .= "
			<tr>
				<td class='light c'>$i[1]</td>
				<td class='dim fonts c'>".htmlspecialchars(input_filters($user['useragent']))."</td>
				<td class='light c'>".printdate($user['time'])."</td>
				<td class='dim'><a href='$page' rel='nofollow'>$page</a></td>
				".($isadmin ? "
				<td class='light c'>
					<a href='admin-ipsearch.php?ip=".$user['ip']."'>".$user['ip']."</a> 
					<a class='danger' href='?ipban=".base64_encode($user['ip'])."'>IP Ban</a> 
					<small>
						<a href='https://www.google.com/search?q=".$user['ip']."'>[G]</a> 
						<a href='https://en.wikipedia.org/wiki/User:".$user['ip']."'>[W]</a>
						".($user['ipbanned'] ? "<br/>[IP BANNED]" : "")."
					</small>
				</td>
				<td class='light c'>
					".$ipinfo_txt[0][$user['bot']].$ipinfo_txt[1][$user['proxy']].$ipinfo_txt[2][$user['tor']]."
				</td>				
				" : "")."
			</tr>";
		}
	}
	
	
	if ($isadmin)
		print "<center><form method='POST' action='online.php'>
			<table class='main'>
				<tr><td class='head fonts c' colspan='2'>Admin functions</td></tr>
				<tr>
					<td class='light c' style='width: 100px'><b>IP Filter</b></td>
					<td class='dim'><input type='text' name='ip' value=\"$ip\"> <input type='submit' value='Go'></td>
				</tr>
			</table></form></center>";
		
	print "<div class='fonts'>Show online users during the last: <a href='?time=60'>minute</a> | <a href='?time=300'>5 minutes</a> | <a href='?time=900'>15 minutes</a> | <a href='?time=3600'>hour</a> | <a href='?time=86400'>day</a>".($isadmin ? " | <a href='?ipsort'>Sort by IP</a>" : "")."</div>
	Online users during the last ".choosetime($time).":
	<table class='main w'>
		<tr>
			<td class='head c' style='width: 20px'>&nbsp;</td>
			<td class='head c' style='width: 200px'>Username</td>
			<td class='head c' style='width: 130px'>Last activity</td>
			<td class='head c' style='width: 180px'>Last post</td>
			<td class='head c'>URL</td>
			<td class='head c' style='width: 60px'>Posts</td>
			".($isadmin ? "<td class='head c' style='width: 230px'>IP</td><td class='head c' style='width: 45px'>Flags</td>" : "")."
		</tr>
	$txt[0]	
	</table><br/>
	Guests online in the past ".choosetime($time).":
	<table class='main w'>
		<tr>
			<td class='head c' style='width: 20px'>&nbsp;</td>
			<td class='head c' style='width: 300px'>User agent</td>
			<td class='head c' style='width: 130px'>Last activity</td>
			<td class='head c'>URL</td>
			".($isadmin ? "<td class='head c' style='width: 180px'>IP</td><td class='head c' style='width: 45px'>Flags</td>" : "")."
		</tr>
	$txt[1]	
	</table>
	
	";
	
	pagefooter();

?>