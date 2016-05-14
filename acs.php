<?php

	// based on calendar.php
	$meta['noindex'] = true;
	
	require "lib/function.php";

	if (!filter_int($_GET['y'])) $year = date("Y");
	else $year = filter_int($_GET['y']);
	
	if (!filter_int($_GET['m'])) $month = date("n");
	else $month = filter_int($_GET['m']);
	
	if (!filter_int($_GET['d'])) $day = date("j");
	else $day = filter_int($_GET['d']);
	
	$month_txt = date("F",mktime(0,0,0,$month,1,0));
	
	
	pageheader("ACS Calendar for $month_txt $year");	
	
	
	$users = $sql->query("
		SELECT $userfields, COUNT(p.id) pcount, DAY(FROM_UNIXTIME(p.time)) pday
		FROM users u
		LEFT JOIN posts p ON u.id = p.user
		WHERE MONTH(FROM_UNIXTIME(p.time)) = $month
		GROUP BY pday ASC, u.id
		ORDER BY pcount DESC
	");

	$userdb = array();

	while($user = $sql->fetch($users)){
		$userdb[ $user['pday'] ][] = $user;

		if ( !isset( $monthlypoints[ $user['id'] ] ) )
			$monthlypoints[ $user['id'] ] = 0;
	}
	
	
	$txt 	= "<tr>";
	$days	= cal_days_in_month(CAL_GREGORIAN, $month, $year);
	
	// draw empty blocks
	$j = date("N", mktime(0,0,0,$month,1,$year) ); // first day of month
	for ($i = 1; $i < $j; $i++)
		$txt .= "<td class='dim' valign='top' width='14.3%'></td>";
	
	// draw actual calendar days
	for ($i = 1; $i <= $days; $i++,$j++){
		
		if ($j == 8){ // New line after seven days
			$txt .= "</tr><tr>";
			$j = 1;
		}
		
		// print acs stuff
		if ( isset($userdb[$i]) ){
			$acs_stuff = doacs($userdb[$i]);
			
			// if it's the ACS from the current day save it for later
			if ($day == $i) $acs_day = $acs_stuff;
			//extract($acs_stuff); // This will return $pcount, $ranks_txt ans $points_txt
		}
		else 
			$acs_stuff = array(
				'pcount'		=> 0,
				'ranks_txt' 	=> "",
				'points_txt'	=> "",
			);
		
		
		// colored subtables
		if ($day == $i) $tblclass = "dark";
		else if ($j==1 || $j==7) $tblclass = "dim";
		else $tblclass = "light";
		
		$txt.="
		<td class='$tblclass fonts' valign='top' width='14.3%' ".($acs_stuff['ranks_txt'] ? "height='80'" : "").">
			<table cellspacing='0'>
				<tr>
					<td colspan=3>
						<a href='acs.php?y=$year&m=$month&d=$i'>$i</a> -- <i>Total posts : ".$acs_stuff['pcount']."</i>
					</td>
				</tr>
				".$acs_stuff['ranks_txt']."
			</table>
		</td>
		";
	}
	unset($userdb);

	// draw empty blocks again
	for ($j;$j<8;$j++)
		$txt.="<td class='dim' valign='top' width='14.3%'></td>";
	$txt.="</tr>";
	
	
	// date selection
	$datesel = "Month: ";
	for ($i=1;$i<13;$i++){
		$w = ($i==$month) ? "z" : "a";
		$datesel.="<$w href='acs.php?y=$year&m=$i'>$i</$w> ";
	}
	$datesel.="| Year: ";
	foreach(range($year-2,$year+2) as $i){
		$w = ($i==$year) ? "z" : "a";
		$datesel.="<$w href='acs.php?y=$i&m=$month'>$i</$w> ";		
	}
	
	// ACS text
	
	// Daily points
	if (!isset($acs_day))
		$acs_day = array(
			'pcount'		=> 0,
			'ranks_txt' 	=> "",
			'points_txt'	=> "",
		);
		
	// Monthly points
	
	//d($monthlypoints);
	$month_pts = $sql->query("
		SELECT $userfields, COUNT(p.id) pcount
		FROM users u
		LEFT JOIN posts p ON u.id = p.user
		WHERE MONTH(FROM_UNIXTIME(p.time)) = $month
		GROUP BY u.id
		ORDER BY pcount DESC
	");
	
	$userdb = array();
	
	while($user = $sql->fetch($month_pts))
		$userdb[] = $user;
	
	$acs_month = doacs($userdb, true);
		
	$acs_txt = 
		strtoupper($month_txt)." $day
		<hr style='width: 100px; margin-left: 0px;'>
		Total amount of posts: ".$acs_day['pcount']."
		<br>
		<br>
		<table cellspacing='0'>".$acs_day['ranks_txt']."</table>
		<br>
		<br>
		Daily Points
		<hr style='width: 100px; margin-left: 0px;'>
		<table cellspacing='0'>".$acs_day['points_txt']."</table>
		<br>
		<br>
		Monthly Points
		<hr style='width: 100px; margin-left: 0px;'>
		<table cellspacing='0'>".$acs_month['points_txt']."</table>
	";
	
	print "
	<a href='index.php'>".$config['board-name']."</a> - ACS
	<table class='main w'>
		<tr><td class='head c' colspan='7' style='font-size: 25px'>$month_txt $year</td></tr>
		
		<tr class='c'>
			<td class='head'>M</td>
			<td class='head'>T</td>
			<td class='head'>W</td>
			<td class='head'>T</td>
			<td class='head'>F</td>
			<td class='head'>S</td>
			<td class='head'>S</td>
		</tr>
		$txt
		
		<tr><td class='dim c fonts' colspan='7'>
		$datesel
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
				<textarea style='width: 100%; height: 400px;' readonly='readonly'>".htmlspecialchars(sgfilter($acs_txt))."</textarea>
			</td>
		</tr>
	</table>
	";

	pagefooter();
	
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
			//if ($rank>4) $points = 1;
			//else $points = 10-floor(7/4*$rank);
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