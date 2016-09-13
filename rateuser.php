<?php

	$meta['noindex'] = true;
	
	require "lib/function.php";
	
	if (!$loguser['id']) 	errorpage("You need to be logged in to do that.");
	if ($isbanned) 			errorpage("Banned users aren't allowed to rate users.");
		
	$id = filter_int($_GET['id']);
	if (!$id) errorpage("No user selected.");
	
	if ($isadmin && isset($_GET['view'])){
		
		$valid = $sql->resultq("SELECT 1 FROM users WHERE id = $id");
		if (!$valid) errorpage("This user doesn't exist.");
		
		pageheader("View ratings");
		
		print adminlinkbar();

		$votefrom = $sql->query("
			SELECT $userfields, r.rating
			FROM users u
			LEFT JOIN ratings r	ON u.id = r.userfrom
			WHERE r.userto = $id
		");
		
		$voteto = $sql->query("
			SELECT $userfields, r.rating
			FROM users u
			LEFT JOIN ratings r	ON u.id = r.userto
			WHERE r.userfrom = $id
		");
		
		$list[0] = $list[1] = array();
		while($x = $sql->fetch($votefrom))
			$list[0][] = ">".makeuserlink(false, $x, true)."</td><td class='light b'>".$x['rating'];
		while($x = $sql->fetch($voteto))
			$list[1][] = ">".makeuserlink(false, $x, true)."</td><td class='light b'>".$x['rating'];
		
		
		$username = makeuserlink($id, false, true);
		$w = "style='width: 25%'";
		?>
		<br>
		<table class='main w c'>
			<tr>
				<td class='head' colspan=2>Ratings to <?php echo $username ?></td>
				<td class='head' colspan=2>Ratings by <?php echo $username ?></td>
			</tr>
			
			<tr>
				<td class='dark' <?php echo $w ?>>From:</td>
				<td class='dark' <?php echo $w ?>>Rating:</td>
				<td class='dark' <?php echo $w ?>>To:</td>
				<td class='dark' <?php echo $w ?>>Rating:</td>
			</tr>
		<?php
		
		// Continue until neither column have other values
		for ($i = 0; isset($list[0][$i]) || isset($list[1][$i]); $i++){
			print "
			<tr>
				<td class='dim'".(isset($list[0][$i]) ? $list[0][$i] : "colspan=2>&nbsp;")."</td>
				<td class='dim'".(isset($list[1][$i]) ? $list[1][$i] : "colspan=2>&nbsp;")."</td>
			</tr>";
		}
		?>
		</table>
		<?php
		
		pagefooter();
	}
		
	if ($id == $loguser['id'])	errorpage("You can't rate yourself.");
	
	$user = $sql->fetchq("
		SELECT u.name, r.rating
		FROM users u
		LEFT JOIN ratings r ON r.userto = $id AND r.userfrom = {$loguser['id']}
		WHERE u.id = $id
	");
	
	if (!$user['name'])	errorpage("This user doesn't exist.");
	
	if (isset($_POST['vote'])){
		checktoken();
		
		if (isset($user['rating'])) { //already have a vote
			$sql->query("
				UPDATE ratings SET
				rating=".filter_int($_POST['rating'])."
				WHERE userfrom = {$loguser['id']} AND userto = $id
			");
		} else { 
			$sql->query("
				INSERT INTO ratings (rating, userto, userfrom) VALUES
				(".filter_int($_POST['rating']).", $id,".$loguser['id'].")
			");
		}
		errorpage("Done.");
		
	}
	
	
	
	pageheader("Rate user");
	
	if ($user['rating'] !== NULL) $sel[$user['rating']] = "checked";
	
	for($i = 0, $inputs = ""; $i <= 10; $i++){
		$inputs .= "<input type='radio' name='rating' value='$i' ".filter_string($sel[$i])."> $i";
	}
	
	?>
	<br>
	<center>
	<form method='POST' action='rateuser.php?id=<?php echo $id ?>'>
	<input type='hidden' name='auth' value='<?php echo $token ?>'>
	
	<table class='main c'>
		<tr><td class='head'>Rate <?php echo makeuserlink($id) ?></td></tr>
		<tr><td class='light'>Select a rating from the checkboxes</td></tr>
		<tr><td class='dim'><?php echo $inputs ?></td></tr>
		<tr><td class='dark'><input type='submit' name='vote' value='Rate'></td></tr>
	</table>
	
	</form>
	</center>
	<?php
	
	pagefooter();

?>