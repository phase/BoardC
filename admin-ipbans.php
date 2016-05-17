<?php

	require "lib/function.php";
	if (!powlcheck(4)) errorpage("No.");
	
	if (isset($_POST['ipban'])){
		// Here we go
		if (!filter_string($_POST['newip'])) errorpage("You forgot to enter an IP!");
		// Sanity check
		if ($loguser['lastip'] == $_POST['newip'])
			errorpage("Uh no, that's <i>your</i> IP addrress.");
		
		$reason = filter_string($_POST['reason']);
		$ircreason = filter_string($_POST['ircreason']);
		
		ipban($reason, $ircreason, $_POST['newip'], true);
		header("Location: admin-ipbans.php");
	}
	if (isset($_POST['dodel']) && isset($_POST['delban'])){
		// Delete selected IP bans
		if (!empty($_POST['delban'])){
			$i = 0;
			foreach ($_POST['delban'] as $ban){
				$q[] = "id = $ban";
				$i++;
			}
			
			$sql->query("DELETE from ipbans WHERE ".implode(" OR ", $q));
		}
		
		errorpage("$i IPs unbanned.");
	}
	
	
	$page 		= filter_int($_GET['page']);
	$reason 	= filter_string($_GET['reason']); // Filter reason (for Password, Regkey)
	$limit 		= 100;
	
	$q_where 	= $reason ? "WHERE reason = '".addslashes($reason)."'" : "";
	
	$total		= $sql->resultq("SELECT COUNT(id) FROM ipbans");
	$bans		= $sql->query("SELECT i.id banid, ip, time, reason, userfrom, $userfields FROM ipbans i LEFT JOIN users u ON userfrom = u.id $q_where ORDER BY time DESC LIMIT ".($page*$limit).",$limit ");
	$pagectrl	= dopagelist($total, $limit, "admin-ipbans", "&reason=$reason");
	
	for($txt = ""; $x = $sql->fetch($bans); 1)
		$txt .= "
		<tr>
			<td class='dim c'	><input type='checkbox' name='delban[]' value='".$x['banid']."'></td>
			<td class='dim c'	>".$x['banid']."</td>
			<td class='light c'	>".$x['ip']."</td>
			<td class='dim c'	>".printdate($x['time'])."</td>
			<td class='light'	>".($x['reason'] ? htmlspecialchars($x['reason']) : "None")."</td>
			<td class='dim c'	>".($x['userfrom'] ? makeuserlink(false, $x, true) : "Automatic")."</td>
		</tr>
	";
	// urlencode $_GET
	pageheader("IP bans");
	print adminlinkbar()."
	<form method='POST' action='admin-ipbans.php'>
	<table class='main w'>
		<tr>
			<td class='dark'>
				Special filters: <a href='?'>All</a> - <a href='?reason=Password'>Password recovery</a> - <a href='?reason=Regkey'>Registration Key</a> - <a href='?reason=".urlencode("online.php ban")."'>Online.php bans</a> - <a href='?reason=".urlencode("Abusive/Malicious Behaviour")."'>Malicious behaviour/Minilog ban</a>
			</td>
		</tr>
	</table>
	<br/>
	$pagectrl
	<table class='main w'>
		<tr>
			<td class='head c' colspan='2'>#</td>
			<td class='head c'>IP Address</td>
			<td class='head c'>Ban date</td>
			<td class='head c'>Reason</td>
			<td class='head c'>Banned by</td>
		</tr>
		$txt
		<tr><td class='dark' colspan='6'><input type='submit' name='dodel' value='Delete selected'></td></tr>
	</table>
	<br/>
	
	<center>
		<table class='main'>
			<tr><td class='head c' colspan='2'>New IP ban</td></tr>
			
			<tr>
				<td class='light c' style='width: 120px'><b>IP Address</b></td>
				<td class='dim'><input type='text' name='newip'></td>
			</tr>
			<tr>
				<td class='light c'><b>Reason</b></td>
				<td class='dim'><input type='text' name='reason' style='width: 500px'></td>
			</tr>
			<tr>
				<td class='light c'><b>IRC Reason</b></td>
				<td class='dim'><input type='text' name='ircreason' style='width: 500px'></td>
			</tr>
			<tr><td class='dark' colspan='2'><input type='submit' name='ipban' value='IP Ban'></td></tr>
		</table>
	</center>
	</form>
	
	$pagectrl
	";
	pagefooter();

?>