<?php
	
	// Forked from the updated calendar.php
	$meta['noindex'] = true;

	require "lib/function.php";
	
	// $startingday added to toggle between starting the table with Monday (0) or Sunday (1)
	$startingday 	= 1; // TODO: this should be a per-user setting
	
	$markday 		= $startingday ? 1 : 6; // Used to highlight a different day value
	
	// Defaults
	if (!filter_int($_GET['y'])) 	$year 	= date("Y");
	else 							$year 	= filter_int($_GET['y']);
	
	if (!filter_int($_GET['m'])) 	$month 	= date("n");
	else 							$month 	= filter_int($_GET['m']);
	
	if (!filter_int($_GET['d'])) 	$day 	= date("j");
	else 							$day 	= filter_int($_GET['d']);
	
	
	$month_txt 		= date("F", mktime(0, 0, 0, $month, 1, 0));
	$days 			= cal_days_in_month(CAL_GREGORIAN, $month, $year);
	$acs_stuff		= array_fill(0, $days+1, ['ranks_txt' => '', 'pcount' => 0]); // Fill the array with the correct amount of days so we don't get uninitialized variable notices
	$acs_day		= array('ranks_txt' => '', 'pcount' => 0, 'points_txt' => ''); 
	$txt 			= "";
	
	pageheader("ACS Calendar for $month_txt $year");	
	
	/*
		Daily Points (and counts)
	*/
	$users = $sql->query("
		SELECT $userfields, COUNT(p.id) pcount, DAY(FROM_UNIXTIME(p.time)) pday
		FROM users u
		LEFT JOIN posts p ON u.id = p.user
		WHERE MONTH(FROM_UNIXTIME(p.time)) = $month AND YEAR(FROM_UNIXTIME(p.time)) = $year
		GROUP BY pday ASC, u.id
		ORDER BY pday ASC, pcount DESC
	");
	
	$prevday = NULL; // Hold backup of the previous day
	

	while ($user = $sql->fetch($users)){
		$curday 	= filter_int($user['pday']);
						
		// Initialize the monthlypoints here, as they are needed by doacs()
		if ($curday && !isset($monthlypoints[$user['id']])){
			$monthlypoints[$user['id']] = 0;
		}
		// When the previous day changes do this
		if ($prevday && $prevday != $curday){
			$acs_stuff[$prevday] = doacs($userdb);
			
			if ($day == $prevday){
				$acs_day = $acs_stuff[$prevday];
			}
			unset($userdb);
		}

		// We continue building the list
		$userdb[] = $user;
		
		// Save for later the ACS for the current day
		$prevday = $curday;
	}
	// Leftovers
	// We use $curday to know if we have looped through the previous loop
	if (isset($curday)){
		$acs_stuff[$prevday] = doacs($userdb);
		
		if ($day == $prevday){
			$acs_day = $acs_stuff[$prevday];
		}
	}
	
	
	/*
		Monthly Points
	*/

	$month_pts = $sql->fetchq("
		SELECT $userfields, COUNT(p.id) pcount
		FROM users u
		LEFT JOIN posts p ON u.id = p.user
		WHERE MONTH(FROM_UNIXTIME(p.time)) = $month AND YEAR(FROM_UNIXTIME(p.time)) = $year
		GROUP BY u.id
		ORDER BY pcount DESC
	", true);
	
	$acs_month = doacs($month_pts, true);
		
	$acs_txt = "".
		strtoupper($month_txt)." $day
		<hr style='width: 100px; margin-left: 0px;'>
		Total amount of posts: {$acs_day['pcount']}<br>
		<br>
		<table cellspacing='0'>{$acs_day['ranks_txt']}</table><br>
		<br>
		Daily Points
		<hr style='width: 100px; margin-left: 0px;'>
		<table cellspacing='0'>{$acs_day['points_txt']}</table><br>
		<br>
		Monthly Points
		<hr style='width: 100px; margin-left: 0px;'>
		<table cellspacing='0'>{$acs_month['points_txt']}</table>
	";
	
	
	
	/*
		Printing the layout
	*/
	
	// Draw empty blocks
	$j = date("N", mktime(0, 0, 0, $month, 1, $year)) + $startingday; // first day of month
	
	// If we didn't put the $j < 8 check it would print an empty line on days starting in the top left corner
	if ($j < 8){
		for ($i = 1; $i < $j; $i++){
			$tblclass 	 = ($i == $markday || $i == 7) ? "dim" : "light";
			$txt 		.= "<td class='$tblclass' valign='top' width='14.3%'></td>";
		}
	}
	
	// Draw actual calendar days
	for ($i = 1; $i <= $days; $i++, $j++){
		
		// Last day reached: new row
		if ($j == 8){
			$txt .= "</tr><tr>";
			$j 	  = 1;
		}
		
		// colored subtables
		if 		($day == $i) 				$tblclass = "dark";
		else if ($j == $markday || $j==7) 	$tblclass = "dim";
		else 								$tblclass = "light";
		
		$txt .= "
		<td class='$tblclass fonts' valign='top' width='14.3%' ".($acs_stuff[$i]['ranks_txt'] ? "height='80'" : "").">
			<table cellspacing='0'>
				<tr>
					<td colspan=3>
						<a href='acs.php?y=$year&m=$month&d=$i'>$i</a> -- <i>Total posts : {$acs_stuff[$i]['pcount']}</i>
					</td>
				</tr>
				{$acs_stuff[$i]['ranks_txt']}
			</table>
		</td>
		";

	}
	
	// Draw empty blocks again
	for ($j; $j < 8; $j++){
		$tblclass = ($j == $markday || $j == 7) ? "dim" : "light";
		$txt 	 .= "<td class='$tblclass' valign='top' width='14.3%'></td>";
	}
	
	// Date selection
	$datesel = "Month: ";
	for ($i = 1; $i < 13; $i++){
		$w 			= ($i == $month) ? "z" : "a";
		$datesel   .= "<$w href='acs.php?y=$year&m=$i'>$i</$w> ";
	}
	$datesel .= "| Year: ";
	for ($i = $year - 2; $i <= $year + 2; $i++){
		$w 			= ($i == $year) ? "z" : "a";
		$datesel   .= "<$w href='acs.php?y=$i&m=$month'>$i</$w> ";		
	}
	
	// By default we start on Monday
	$days_char = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
	if ($startingday){
		$days_char = array_ror($days_char, 1); // Rotate array elements to the right, so that we start on Sunday.
	}

	
	print "
	<a href='index.php'>{$config['board-name']}</a> - ACS
	<table class='main w'>
		<tr><td class='head c' colspan='7' style='font-size: 25px'>$month_txt $year</td></tr>
		
		<tr class='c'>
			<td class='head'>$days_char[0]</td>
			<td class='head'>$days_char[1]</td>
			<td class='head'>$days_char[2]</td>
			<td class='head'>$days_char[3]</td>
			<td class='head'>$days_char[4]</td>
			<td class='head'>$days_char[5]</td>
			<td class='head'>$days_char[6]</td>
		</tr>
		<tr>
			$txt
		</tr>
		
		<tr>
			<td class='dim c fonts' colspan='7'>
				$datesel
			</td>
		</tr>
	</table>
	<!-- acs report starts here -->
	
	<table class='main w'>
		<tr>
			<td class='head c' colspan='2'>
				ACS Report for $month_txt $year
			</td>
		</tr>
		
		<tr>
			<td class='dim' width='50%' valign='top'>
				$acs_txt
			</td>
			<td class='light' width='50%' valign='top'>
				<textarea style='width: 100%; height: 400px;' readonly='readonly'>".lazyfilter($acs_txt)."</textarea>
			</td>
		</tr>
	</table>
	";

	pagefooter();
	
	function lazyfilter($str){return htmlspecialchars(preg_replace("'[\x01-\x1F]'", "", $str));}
	
	function doacs($data, $month = false){
		global $monthlypoints;
		
		$res = array(
			'pcount' 		=> 0,
			'ranks_txt'		=> "",
			'points_txt' 	=> "",
		);
		
		$rank 		= 0;
		$previous 	= NULL;
		
		foreach($data as $x){
			// Increase the rank only when the post count is different compared to the last
			// Note that for this to work properly, the query results have to be ordered by pcount
			if ($previous != $x['pcount']) $rank++;
			$previous = $x['pcount'];
			$res['pcount'] += $previous;  // add to total post count for the day
			
			$userlink = makeuserlink(false, $x);
			
			// Give points
			if (!$month){
				
				if 		($rank == 1) $points = 10;
				else if ($rank == 2) $points = 7;
				else if ($rank == 3) $points = 5;
				else if ($rank == 4) $points = 3;
				else 				 $points = 1;
			
			
				$monthlypoints[ $x['id'] ] += $points;
				
				// Daily ranks
				$res['ranks_txt'] .= "
					<tr>
						<td>$rank</td>
						<td>$userlink</td>
						<td>$previous</td>
					</tr>";
			}
			else $points = $monthlypoints[ $x['id'] ];
			
			// User points
			$res['points_txt'] .= "
				<tr>
					<td>$rank</td>
					<td>$userlink</td>
					<td>$points</td>
				</tr>";
				

		}

		return $res;
	}

?>