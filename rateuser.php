<?php

	$meta['noindex'] = true;
	
	require "lib/function.php";
	$isadmin = powlcheck(4);
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to do that.");
		
	if ($loguser['powerlevel']<0)
		header("Location: index.php");
		
	$id = filter_int($_GET['id']);
	
	if (!$id)
		errorpage("No user selected.");
	
	if ($isadmin && isset($_GET['view'])){
		
		pageheader("View ratings");
		
		print adminlinkbar();
		
		$users = $sql->query("
			SELECT $userfields, r.rating, r.userfrom
			FROM users u
			LEFT JOIN ratings r	ON u.id = r.userfrom OR r.userto = u.id
			WHERE r.userto = $id OR r.userfrom = $id
			GROUP BY r.id
		");
		
		if (!$users)
			errorpage("This user doesn't exist.", false);
		
		$list[0] = $list[1] = array();
		while($x = $sql->fetch($users)){
			$i = ($x['userfrom'] == $id) ? 1 : 0;
			$list[$i][] = ">".makeuserlink(false, $x, true)."</td><td class='light b'>".$x['rating'];
		}
		
		$username = makeuserlink($id, false, true);
		$w = "style='width: 25%'";
		print "<br/>
		<table class='main w c'>
			<tr><td class='head' colspan=2>Ratings to $username</td><td class='head' colspan=2>Ratings by $username</td></tr>
			<tr><td class='dark' $w>From:</td><td class='dark' $w>Rating:</td><td class='dark' $w>To:</td><td class='dark' $w>Rating:</td></tr>";
			
		for ($i=0;isset($list[0][$i]) || isset($list[1][$i]); $i++)
			print "<tr><td class='dim'".(isset($list[0][$i]) ? $list[0][$i] : "colspan=2>&nbsp;")."</td><td class='dim'".(isset($list[1][$i]) ? $list[1][$i] : "colspan=2>&nbsp;")."</td></tr>";
		
		print "</table>";
		
		pagefooter();
	}
		
	if ($id == $loguser['id'])
		errorpage("You can't rate yourself.");
	
	$user = $sql->fetchq("SELECT u.name, r.rating FROM users u LEFT JOIN ratings r ON r.userto = $id AND r.userfrom = ".$loguser['id']." WHERE u.id = $id");
	
	if (!$user['name'])
		errorpage("This user doesn't exist.");
	
	if (isset($_POST['vote'])){
		// TODO: A proper fix
//		$sql->query("DELETE from ratings WHERE ");
		
		if (isset($user['rating'])) //already have a vote
			$sql->query("UPDATE ratings SET rating=".filter_int($_POST['rating'])." WHERE userfrom = ".$loguser['id']." AND userto = $id");
		else 
			$sql->query("INSERT INTO ratings (rating, userto, userfrom) VALUES (".filter_int($_POST['rating']).", $id,".$loguser['id'].")");
		
		errorpage("Done.");
		
	}
	
	pageheader("Rate user");
	
	if ($user['rating'] !== NULL)
		$sel[$user['rating']] = "checked";
	
	for($i=0,$inputs="";$i<=10;$i++)
		$inputs .= "<input type='radio' name='rating' value='$i' ".filter_string($sel[$i])."> $i";
		
	print "
	<center><form method='POST' action='rateuser.php?id=$id'><table class='main c'>
		<tr><td class='head'>Rate ".$user['name']."</td></tr>
		<tr><td class='light'>Select a rating from the checkboxes</td></tr>
		
		<tr><td class='dim'>$inputs</td></tr>
		<tr><td class='dark'><input type='submit' name='vote' value='Rate'></td></tr>
	</table></form></center>
	";
	
	pagefooter();

?>