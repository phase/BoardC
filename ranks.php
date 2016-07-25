<?php

	/*
		Ranks page
		Well, this was surprisingly painful.
	*/
	
	require "lib/function.php";
	
	// Powerlevel required for viewing all ranks
	$isadmin = powlcheck(4);
	
	$threshold = ctime()-(365*86400);
	
	pageheader("Ranks");
	
	// Rankset you chose to display
	$rankset 	= isset($_POST['rankset']) ? filter_int($_POST['rankset']) : 1; 
	$allusers 	= filter_bool($_POST['showall']); // Show all users (even those without your same rankset)

	$userlist = $sql->query("
		SELECT u.posts, u.lastview, $userfields, (u.lastview < $threshold) inactive,
		(
			SELECT r.posts FROM ranks r
			WHERE rankset = $rankset AND posts <= u.posts
			ORDER BY r.posts DESC
			LIMIT 1
		) rank
		FROM users u
		WHERE ".($allusers ? "1" : "u.rankset = $rankset")."
		ORDER BY u.posts ASC
	");

	// Selection text
	$rankset_sel[$rankset]	= "selected"; 		
	$show_sel[$allusers] 	= "checked";
	
	// Build rank selection listbox
	$ranksets 			= $sql->query("SELECT name FROM ranksets");
	$rankset_txt 		= "";
	for($i = 1; $x = $sql->fetch($ranksets); $i++)
		$rankset_txt .= "<option value='$i' ".filter_string($rankset_sel[$i]).">{$x['name']}</option>";
	
	
	// Build table

	$ranks 				= $sql->fetchq("SELECT text, posts FROM ranks WHERE rankset = $rankset ORDER BY posts ASC", true);
	$ranks_cnt 			= count($ranks);
	
	$totalusers 		= $sql->resultq("SELECT COUNT(id) FROM users".($allusers ? "" : " WHERE rankset = $rankset"));
	
	$j = $inactive 	= 0;
	$txt 			= "";
	$usertmp 		= array();
	$ranking 		= $totalusers;
	
	while (true){

		// Check if we have got to the next rank with the post requirement
		$x = $sql->fetch($userlist);
		
		if (!$x || $x['rank'] > $ranks[$j]['posts']){ // bigger rank
		
			if ($usertmp){
				$txt .= "
				<tr>
						<td class='dim fonts' width='200'>{$ranks[$j]['text']}</td>
						<td class='light c' width='60'>{$ranks[$j]['posts']}</td>
						<td class='light c' width='60'>".($totalusers)."</td>
						<td class='light c' width='30'>".($totalusers-$ranking)."</td>
						<td class='dim fonts c' width='*'>".implode(", ", $usertmp).($usertmp && $inactive ? ", " : "").($inactive ? "$inactive inactive" : "")."</td>
				</tr>";
				$j++;
			}
			$totalusers = $ranking;
			$usertmp = array();
			$inactive = 0;
			
			// Increase the current rank until we have reached the correct one
			while ($x && $x['rank'] > $ranks[$j]['posts']){
				$txt .= "
				<tr>
						<td class='dim fonts' width='200'>".($isadmin ? $ranks[$j]['text'] : "? ? ?")."</td>
						<td class='light c' width='60'>".($isadmin ? $ranks[$j]['posts'] : "? ? ?")."</td>
						<td class='light c' width='60'>".($isadmin ? "$ranking" : "?")."</td>
						<td class='light c' width='30'>".($isadmin ? "0" : "?")."</td>
						<td class='dim fonts c' width='*'>".($isadmin ? "Nobody" : "?")."</td>
				</tr>";
				$j++; // Increase rank
			}
			if (!$x){
				// End of the loop. Print the unused ranks and break.
				for($j; $j < $ranks_cnt; $j++)
					$txt .= "
					<tr>
							<td class='dim fonts' width='200'>".($isadmin ? $ranks[$j]['text'] : "? ? ?")."</td>
							<td class='light c' width='60'>".($isadmin ? $ranks[$j]['posts'] : "? ? ?")."</td>
							<td class='light c' width='60'>".($isadmin ? "$ranking" : "?")."</td>
							<td class='light c' width='30'>".($isadmin ? "0" : "?")."</td>
							<td class='dim fonts c' width='*'>".($isadmin ? "Nobody" : "?")."</td>
					</tr>";	
				break;
			}
		}
		
		// Add to set
		if ($x['inactive'])
			$inactive++;
		else
			$usertmp[] = makeuserlink(false, $x);

		$ranking--;
	}
	
	if ($totalusers != $ranking){
		$txt .= "
					<tr>
							<td class='dim fonts' width='200'>{$ranks[$j]['text']}</td>
							<td class='light c' width='60'>{$ranks[$j]['posts']}</td>
							<td class='light c' width='60'>".($totalusers)."</td>
							<td class='light c' width='30'>".($totalusers-$ranking)."</td>
							<td class='dim fonts c' width='*'>".implode(", ", $usertmp).($usertmp && $inactive ? ", " : "").($inactive ? "$inactive inactive" : "")."</td>
		</tr>";
		$j++;
	}
	
	print "
	<a href='index.php'>{$config['board-name']}</a> - Ranks</td>
	
	<form action='ranks.php' method='POST'>
		<table class='main w'>
		
			<tr><td class='head c' colspan='2'>&nbsp;</td></tr>
			
			<tr>
			
				<td class='light c'>
					<b>Rank set</b>
				</td>
				
				<td class='dim'>
					<select name='rankset'>
						$rankset_txt
					</select>
				</td>
			</tr>
			
			<tr>
				<td class='light c'>
					<b>Users to show</b>
				</td>
				
				<td class='dim'>
					<input type='radio' name='showall' value='0' ".filter_string($show_sel[0])."> Selected rank set only
					&nbsp; &nbsp;
					<input type='radio' name='showall' value='1' ".filter_string($show_sel[1])."> All users
				</td>
			</tr>
			
			<tr><td class='head c' colspan='2'>&nbsp;</td></tr>
			
			<tr>
				<td class='light c'>&nbsp;</td>
				<td class='dim'><input value='View' type='submit'></td>
			</tr>
			
		</table>
	</form>
	<br>
	<table class='main w'>
		<tr>
			<td class='head c' width='150'>Rank</td>
			<td class='head c' width='60'>Posts</td>
			<td class='head c' width='60'>Ranking</td>
			<td class='head c' colspan='2'>Users on that rank</td>
		</tr>
		$txt
	</table>
	";
	
	pagefooter();

?>