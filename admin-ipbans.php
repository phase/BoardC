<?php

	require "lib/function.php";
	
	admincheck();
	
	// Quick hack to allow linking other pages to searches here
	if (isset($_GET['ip'])){
		$_POST['searchip'] 	= $_GET['ip'];
	}
	
	if (isset($_POST['ipban'])){
		checktoken();

		// Here we go
		if (!filter_string($_POST['newip'])) errorpage("You forgot to enter an IP!");
		
		// Sanity check
		if ($loguser['lastip'] == $_POST['newip'])
			errorpage("Uh no, that's <i>your</i> IP address.");
		
		$reason 	= filter_string($_POST['reason'], true);
		$ircreason 	= filter_string($_POST['ircreason']); // Don't strip out control codes for this!
		$expire 	= filter_int($_POST['expire']);
		
		ipban($reason, $ircreason, $_POST['newip'], $expire, true);
		setmessage("Added IP ban for {$_POST['newip']}.");
		header("Location: ?");
		x_die();		
	}
	else if (isset($_POST['dodel']) && isset($_POST['delban'])){
		checktoken();
		
		// Delete selected IP bans
		if (!empty($_POST['delban'])){
			
			$i = 0;
			foreach ($_POST['delban'] as $ban){
				$q[] = "id = $ban";
				$i++;
			}
			
			$sql->query("DELETE from ipbans WHERE ".implode(" OR ", $q));
			setmessage("Removed IP ban for $i IP(s).");
		} else {
			setmessage("No IP bans selected.");
		}
		header("Location: ?");
		x_die();
	}
	
	
	$page 		= filter_int($_GET['page']);
	$reason 	= filter_string($_GET['reason']); // Filter reason (for Password, Regkey)
	$limit 		= 100;
	
	$total		= $sql->resultq("SELECT COUNT(id) FROM ipbans");
	$bans		= $sql->query("
		SELECT i.id banid, ip, time, reason, userfrom, i.ban_expire, $userfields
		FROM ipbans i
		LEFT JOIN users u ON userfrom = u.id
		WHERE ".($reason ? "reason = '".addslashes($reason)."'" : "1")."
		AND ".(isset($_POST['searchip']) ? "ip LIKE '".addslashes($_POST['searchip'])."%'" : "1")."
		ORDER BY time DESC
		LIMIT ".($page*$limit).",$limit
	");
	$pagectrl	= dopagelist($total, $limit, "admin-ipbans", "&reason=$reason");
	
	for($txt = ""; $x = $sql->fetch($bans); 1){
		$txt .= "
			<tr>
				<td class='dim c'	><input type='checkbox' name='delban[]' value='{$x['banid']}'></td>
				<td class='dim c'	>{$x['banid']}</td>
				<td class='light c'	>{$x['ip']}</td>
				<td class='dim c'	>".printdate($x['time'])."</td>
				<td class='dim c'	>".($x['ban_expire'] ? printdate($x['ban_expire'])." (".choosetime($x['ban_expire']-ctime()).")" : "-")."</td>
				<td class='light'	>".($x['reason'] ? htmlspecialchars($x['reason']) : "None")."</td>
				<td class='dim c'	>".($x['userfrom'] ? makeuserlink(false, $x, true) : "Automatic")."</td>
			</tr>
		";
	}
	
	pageheader("IP bans");
	print adminlinkbar().$message;
	
	?>
	<form method='POST' action='admin-ipbans.php'>
	<input type='hidden' name='auth' value='<?php echo $token ?>'>

	<center>
		<table class='main'>
			<tr>
				<td class='head c' colspan=2>
					Search
				</td>
			</tr>
			<tr>
				<td class='light c' style='width: 120px'>
					<b>Special filters:</b>
				</td>
				<td class='dim'>
					<a href='?'>All</a> - 
					<a href='?reason=Recovery'>Password recovery</a> - 
					<a href='?reason=Regkey'>Registration Key</a> - 
					<a href='?reason=<?php echo urlencode("online.php ban") ?>'>Online.php bans</a> - 
					<a href='?reason=<?php echo urlencode("Abusive/Malicious Behaviour") ?>'>Malicious behaviour/Minilog ban</a>
				</td>
			</tr>
			<tr>
				<td class='light c'>
					<b>Search IP:</b>
				</td>
				<td class='dim'>
					<input type='text' name='searchip' value="<?php echo htmlspecialchars(filter_string($_POST['searchip'])) ?>">
				</td>
			</tr>
			<tr><td class='dark' colspan='2'><input type='submit' name='dosearch' value='Search'></td></tr>
		</table>
	</center>
	
	<br>
	
	<?php echo $pagectrl ?>
	<table class='main w'>
		<tr>
			<td class='head c' colspan='2'>#</td>
			<td class='head c'>IP Address</td>
			<td class='head c'>Ban date</td>
			<td class='head c'>Expiration date</td>
			<td class='head c'>Reason</td>
			<td class='head c'>Banned by</td>
		</tr>
		<?php echo $txt ?>
		<tr><td class='dark' colspan='7'><input type='submit' name='dodel' value='Delete selected'></td></tr>
	</table>
	
	<br>
	
	<center>
		<table class='main'>
			<tr><td class='head c' colspan='2'>Add IP ban</td></tr>
			
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
			<tr>
				<td class='light c'><b>Ban for</b></td>
				<td class='dim'><input type='text' name='expire' style='width: 50px' value='0'> hours. (Leave blank or set to 0 for a permanent ban).</td>
			</tr>
			<tr><td class='dark' colspan='2'><input type='submit' name='ipban' value='IP Ban'></td></tr>
		</table>
	</center>
	
	</form>
	<?php
	print $pagectrl;
	
	pagefooter();

?>