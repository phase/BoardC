<?php

	require "lib/function.php";
	
	pageheader("Online users");
	
	$isadmin = powlcheck(4);
	//if ($isadmin) print adminlinkbar();
	
	$time = filter_int($_GET['time']);
	if (!$time) $time = 300; // 5 minutes
	
	if (filter_string($_GET['ipban'])){
		if (!$isadmin) errorpage("<h1>No.</h1>", false);
		$ip = base64_decode($_GET['ipban']);
		ipban("online.php ban", "IP Banned $ip (online.php ban)", $ip); 
		header("Location: online.php?time=$time");
	}
	
	
	$online = $sql->query("
	SELECT h.ip, h.time, h.page, h.useragent, i.id ipbanned,
	u.id, u.name, u.displayname, u.namecolor, u.powerlevel, u.sex, u.icon, u.posts, p.time ptime
	FROM hits h
	LEFT JOIN users u 
	ON h.user = u.id
	LEFT JOIN posts AS p
	ON u.id=p.user
	LEFT JOIN ipbans i
	ON h.ip = i.ip
	WHERE h.time>".(ctime()-$time)."
	ORDER BY h.time DESC, p.time DESC
	");
	
	$txt = array("", "");
	$i = array(0, 0);
	$ipdb = array();// sigh
	$udb = array();// sigh
	
	
	while ($user = $sql->fetch($online)){
		
		if ($user['id']){ // registered user
			if (filter_int($udb[$user['id']])) continue;// more sigh
			else $udb[$user['id']] = true;
			$i[0]++;
			$txt[0] .= "
			<tr>
				<td class='light c'>$i[0]</td>
				<td class='dim'>".makeuserlink(false, $user)."</td>
				<td class='light c'>".printdate($user['time'])."</td>
				<td class='light c'>".($user['ptime'] ? printdate($user['ptime']) : "None")."</td>
				<td class='dim'>".htmlspecialchars(input_filters($user['page']))."</td>
				<td class='dim c'>".$user['posts']."</td>
				".($isadmin ? "<td class='light c'>".$user['ip']." <a class='danger' href='?ipban=".base64_encode($user['ip'])."'>Ban</a> <small><a href='https://www.google.com/search?q=".$user['ip']."'>[G]</a> <a href='https://en.wikipedia.org/wiki/User:".$user['ip']."'>[W]</a>".($user['ipbanned'] ? "<br/>[IP BANNED]" : "")."</small></td>" : "")."
			</tr>";
		}
		else{ // guest
			if (filter_int($ipdb[$user['ip']])) continue;// more sigh
			else $ipdb[$user['ip']] = true;
			$i[1]++;
			$txt[1] .= "
			<tr>
				<td class='light c'>$i[1]</td>
				<td class='dim fonts c'>".htmlspecialchars(input_filters($user['useragent']))."</td>
				<td class='light c'>".printdate($user['time'])."</td>
				<td class='dim'>".htmlspecialchars(input_filters($user['page']))."</td>
				".($isadmin ? "<td class='light c'>".$user['ip']." <a class='danger' href='?ipban=".base64_encode($user['ip'])."'>Ban</a> <small><a href='https://www.google.com/search?q=".$user['ip']."'>[G]</a> <a href='https://en.wikipedia.org/wiki/User:".$user['ip']."'>[W]</a>".($user['ipbanned'] ? "<br/>[IP BANNED]" : "")."</small></td>" : "")."
			</tr>";
		}
	}
	
	
	
	print "<div class='fonts'>Show online users during the last: <a href='?time=60'>minute</a> | <a href='?time=300'>5 minutes</a> | <a href='?time=900'>15 minutes</a> | <a href='?time=3600'>hour</a> | <a href='?time=86400'>day</a></div>
	Online users during the last ".choosetime($time).":
	<table class='main w'>
		<tr>
			<td class='head c' style='width: 20px'>&nbsp;</td>
			<td class='head c' style='width: 200px'>Username</td>
			<td class='head c' style='width: 120px'>Last activity</td>
			<td class='head c' style='width: 180px'>Last post</td>
			<td class='head c'>URL</td>
			<td class='head c' style='width: 60px'>Posts</td>
			".($isadmin ? "<td class='head c' style='width: 180px'>IP</td>" : "")."
		</tr>
	$txt[0]	
	</table><br/>
	Guests online in the past ".choosetime($time).":
	<table class='main w'>
		<tr>
			<td class='head c' style='width: 20px'>&nbsp;</td>
			<td class='head c' style='width: 300px'>User agent</td>
			<td class='head c' style='width: 120px'>Last activity</td>
			<td class='head c'>URL</td>
			".($isadmin ? "<td class='head c' style='width: 180px'>IP</td>" : "")."
		</tr>
	$txt[1]	
	</table>
	
	";
	
	pagefooter();
	

?>