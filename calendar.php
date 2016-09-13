<?php

	$meta['noindex'] = true;

	require "lib/function.php";
	
	// $startingday added to toggle between starting the table with Monday (0) or Sunday (1)
	$startingday 	= 1; // TODO: this should be a per-user setting
	
	$markday 		= $startingday ? 1 : 6; // Used to highlight a different day value
	
	// Defaults
	if (!filter_int($_GET['y'])) 	$year = date("Y");
	else 							$year = intval($_GET['y']);
	
	if (!filter_int($_GET['m'])) 	$month = date("n");
	else 							$month = intval($_GET['m']);
	
	if (!filter_int($_GET['d'])) 	$day = 0;
	else 							$day = intval($_GET['d']);
	
	if (!filter_string($_GET['v'])) $view = 'b'; // Birthdays
	else							$view = $_GET['v']; // Events
	
	
	$month_txt 		= date("F", mktime(0, 0, 0, $month, 1, 0));
	$days 			= cal_days_in_month(CAL_GREGORIAN, $month, $year);
	$event_txt 		= array_fill(0, $days+1, "");  // Fill the array with the correct amount of days so we don't get uninitialized variable notices
	$txt 			= "";
	
	pageheader("Calendar for $month_txt $year");	
	
	if ($view == 'b'){
		// Get the birthdays from this month, then create the text
		$users = $sql->query("
			SELECT DAY(FROM_UNIXTIME(birthday)) day, id, name, displayname, namecolor, sex, powerlevel, birthday
			FROM users
			WHERE MONTH(FROM_UNIXTIME(birthday)) = $month
		");
		
		while($x = $sql->fetch($users)){
			$event_txt[$x['day']] .= "<br>- ".makeuserlink(false, $x)." turns ".getyeardiff($x['birthday'], mktime(0, 0, 0, $month, 32, $year));
		}		
	}
	else if ($view == 'e'){
		
		// Do the same for events, except with a bunch of extra checks
		$users = $sql->query("
			SELECT DAY(FROM_UNIXTIME(e.time)) day, u.id, u.name, u.displayname, u.namecolor, u.sex, u.powerlevel, e.text
			FROM users u
			LEFT JOIN events e ON u.id = e.user
			WHERE MONTH(FROM_UNIXTIME(e.time)) = $month AND YEAR(FROM_UNIXTIME(e.time)) = $year AND (
				e.private = 0 OR ".intval($isadmin)." OR (e.private = 1 AND e.user = {$loguser['id']})
			)
		");
		while($x = $sql->fetch($users)){
			$event_txt[$x['day']] .= "<br>- ".makeuserlink(false, $x)." [".output_filters($x['text'])."]";
		}			
	}
	else{
		errorpage("Invalid action.");
	}
	

		

	
	// Draw empty blocks
	$j = date("N", mktime(0, 0, 0, $month, 1, $year)) + $startingday; // first day of month
	
	// If we didn't put the $j < 8 check it would print an empty line on days starting in the top left corner
	if ($j < 8){
		for ($i = 1; $i < $j; $i++){
			$tblclass = ($i == $markday || $i == 7) ? "dim" : "light";
			$txt .= "<td class='$tblclass' valign='top' width='14.3%' height='80'></td>";
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
		<td class='$tblclass fonts' valign='top' width='14.3%' height='80'>
			<a href='calendar.php?v=$view&y=$year&m=$month&d=$i'>$i</a>
			<br>
			{$event_txt[$i]}
		";
	}
	
	// Draw empty blocks again
	for ($j; $j < 8; $j++){
		$tblclass = ($j == $markday || $j == 7) ? "dim" : "light";
		$txt 	 .= "<td class='$tblclass' valign='top' width='14.3%' height='80'></td>";
	}
	
	// Date selection
	$datesel = "Month: ";
	for ($i = 1; $i < 13; $i++){
		$w 			= ($i == $month) ? "z" : "a";
		$datesel   .= "<$w href='calendar.php?v=$view&y=$year&m=$i'>$i</$w> ";
	}
	$datesel .= "| Year: ";
	for ($i = $year - 2; $i <= $year + 2; $i++){
		$w 			= ($i == $year) ? "z" : "a";
		$datesel   .= "<$w href='calendar.php?v=$view&y=$i&m=$month'>$i</$w> ";		
	}
	
	// By default we start on Monday
	$days_char = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
	if ($startingday){
		$days_char = array_ror($days_char, 1); // Rotate array elements to the right, so that we start on Sunday.
	}
	
	// hurf
	if ($view == 'b'){
		$w = "z";
		$x = "a";
	}
	else{
		$w = "a";
		$x = "z";
	}
	
	print "
	<table class='w'>
		<tr>
			<td>
				<a href='index.php'>{$config['board-name']}</a> - Calendar
			</td>
			<td style='text-align: right'>
				Show: <$w href='calendar.php?v=b&y=$year&m=$month'>Birthdays</$w> - <$x href='calendar.php?v=e&y=$year&m=$month'>Events</$x>
			</td>
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
	";

	pagefooter();

?>