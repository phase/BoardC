<?php

	require "lib/function.php";
	
	if (!powlcheck(4))
		errorpage("You're not an admin!");
		
	pageheader("IP Search");
	
	print adminlinkbar();
	
	$ip		= filter_string($_POST['ip']);
	$ip1	= filter_string($_POST['ip1']);
	$ip2	= filter_string($_POST['ip2']);

	
	if (isset($_GET['ip'])){
		$_POST['dosearch'] = true;
		$ip = $_GET['ip'];
	}
		
	
	if (isset($_POST['dosearch'])){
		
		if (!$ip && (!$ip1 || !$ip2))
			errorpage("You have left the IP field empty!", false);
		
		$users = $sql->query("SELECT lastip, id, name, displayname, namecolor, powerlevel, sex, since FROM users ".($ip ? "WHERE lastip = '$ip'" : "")." ORDER by id ASC");
		$hits = $sql->query("
		SELECT h.ip, h.time, h.page, h.useragent, u.id, u.name, u.displayname, u.namecolor, u.powerlevel, u.sex
		FROM hits h
		LEFT JOIN users u
		ON h.user = u.id
		".($ip ? "WHERE h.ip = '$ip' AND " : "WHERE")." ".(filter_int($_POST['usedb']) ? "h.id IN (SELECT MAX(x.id) FROM hits x WHERE x.ip = h.ip)" : "1")."
		ORDER BY h.time DESC
		LIMIT 0,200
		");
		
		$udb = array();
		$useudb = filter_int($_POST['useudb']);
		$txt = array("", "", "", "");
		// id, userlink, since, ip
		while ($user = $sql->fetch($users)){
			if (!$ip && !iprange($ip1, $user['lastip'], $ip2)) continue;
			$txt[0] .= "<tr><td class='dim'>".$user['id']."</td><td class='light'>".makeuserlink(false, $user)."</td><td class='light'>".printdate($user['since'])."</td><td class='dim'>".$user['lastip']."</td></tr>";
		}
		while ($hit = $sql->fetch($hits)){
			if (!$ip && !iprange($ip1, $hit['ip'], $ip2)) continue;
			if ($useudb){
				if (isset($udb[$hit['id']])) continue;
				else $udb[$hit['id']] = true;
			}
			$txt[1] .= "<tr><td class='light'>".($hit['id'] ? makeuserlink(false, $hit) : "[Guest]")."</td><td class='light'>".printdate($hit['time'])."</td><td class='dim'>".htmlspecialchars(input_filters($hit['useragent']))."</td><td class='dim'>".htmlspecialchars(input_filters($hit['page']))."</td><td class='dim'>".$hit['ip']." <small><a href='https://www.google.com/search?q=".$hit['ip']."'>[G]</a> <a href='https://en.wikipedia.org/wiki/User:".$hit['ip']."'>[W]</a></small></td></tr>";
		}
		$txt = "<center><br/>
		<table class='main'>
			<tr><td class='dark c' colspan=4>Users</td></tr>
			<tr>
				<td class='head c'>ID</td>
				<td class='head c'>User</td>
				<td class='head c'>Registered on</td>
				<td class='head c'>Last IP</td>
			</tr>
			$txt[0]
		</table>
		<br/>
		<table class='main w'>
			<tr><td class='dark c' colspan=5>Hits</td></tr>
			<tr>
				<td class='head c'>User</td>
				<td class='head c'>Time</td>
				<td class='head c'>User Agent</td>
				<td class='head c'>Page</td>
				<td class='head c'>IP</td>
			</tr>
			$txt[1]
		</table>
		
		</center>		
		";
	}
	else $txt = "";
	
	$udbsel = filter_int($_POST['useudb']) ? "checked" : "";
	
	print "
	<form method='POST' action='admin-ipsearch.php'>
	<center><table class='main'>
		<tr><td class='head c' colspan=2>IP Search</td></tr>
		
		<tr>
			<td class='light'><b>IP Address:</b></td>
			<td class='dim'><input type='text' name='ip' value=\"$ip\"></td>
		</tr>
		<tr>
			<td class='light'><b>Search for an IP range:</b></td>
			<td class='dim'><input type='text' name='ip1' value=\"$ip1\"> and <input type='text' name='ip2' value=\"$ip2\"></td>
		</tr>
		<tr><td class='dim' colspan=2><input type='submit' name='dosearch' value='Search'> <input type='checkbox' name='useudb' value=1 $udbsel>Show only last match for each user ID</td></tr>
	</table></center>
	</form>
	
	$txt
	";
	
	pagefooter();
	
	

?>