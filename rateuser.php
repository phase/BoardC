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
		SELECT u.id, u.name, u.displayname, u.powerlevel, u.namecolor, u.sex, r.rating, u.icon
		FROM users u
		LEFT JOIN ratings r
		ON u.id = r.userfrom
		WHERE r.userto = $id");
		
		if (!$users)
			errorpage("This user doesn't exist.", false);
		
		$list = "";
		while($x = $sql->fetch($users))
			$list .= "<tr><td class='dim br'>".makeuserlink(false, $x, true)."</td><td class='light b'>".$x['rating']."</td></tr>";
		
		print "<br/>
		<center><table class='main c nb'>
			<tr><td class='head b' colspan=2>Ratings for ".makeuserlink($id)."</td></tr>
			<tr><td class='dark br'>From:</td><td class='dark b'>Rating:</td></tr>
			$list
		</table></center>
		";
		
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