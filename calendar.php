<?php

	$meta['noindex'] = true;

	// most of HTML code straight from Jul
	
	require "lib/function.php";
	
	/*function lazy($a){
		print "<title>Birthday stuff</title><body style='background: #000; color: #fff'><pre>";
		var_dump($a);
		x_die();
	}*/
	
	if (!filter_int($_GET['y'])) $year = date("Y");
	else $year = filter_int($_GET['y']);
	
	if (!filter_int($_GET['m'])) $month = date("n");
	else $month = filter_int($_GET['m']);
	
	if (!filter_int($_GET['d'])) $day = 0;
	else $day = filter_int($_GET['d']);
	
	$month_txt = date("F",mktime(0,0,0,$month,1,0));
	
	pageheader("Calendar for $month_txt $year");	
	
	$users = $sql->query("
	SELECT id, name, displayname, namecolor, sex, powerlevel, birthday
	FROM users
	WHERE MONTH(FROM_UNIXTIME(birthday)) = $month
	GROUP BY DAY(FROM_UNIXTIME(birthday)) ASC
	");
	

	$userdb = array();
	
	
	
	while($user = $sql->fetch($users))
		$userdb[date("j",$user['birthday'])][] = $user;
	
	
	//lazy($userdb);
	
	
	$txt = "<tr>";
	$days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
	
	// draw empty blocks
	$j=date("N", mktime(0,0,0,$month,1,$year)); // first day of month
	for ($i=1;$i<$j;$i++)
		$txt.="<td class='dim' valign='top' width='14.3%' height='80'></td>";
	
	// draw actual calendar days
	for ($i=1;$i<=$days;$i++,$j++){
		
		if ($j==8){
			$txt.="</tr><tr>";
			$j=1;
		}
		// get actual birthdays
		if (isset($userdb[$i])){
			$birth_txt = "<br>";
			foreach($userdb[$i] as $x)
				$birth_txt .= "<br>- ".makeuserlink(false, $x)." turns ".getyeardiff($x['birthday'],mktime(0,0,0,$month,32,$year));
		}
		else $birth_txt = "";
		
		// colored subtables
		if ($day == $i) $tblclass = "dark";
		else if ($j==1 || $j==7) $tblclass = "dim";
		else $tblclass = "light";
		
		$txt.="
		<td class='$tblclass fonts' valign='top' width='14.3%' height='80'>
			<a href='calendar.php?y=$year&m=$month&d=$i'>$i</a>
			$birth_txt
		</td>
		";
	}
	
	// draw empty blocks again
	for ($j;$j<8;$j++)
		$txt.="<td class='dim' valign='top' width='14.3%' height='80'></td>";
	$txt.="</tr>";
	
	
	// date selection
	$datesel = "Month: ";
	for ($i=1;$i<13;$i++){
		$w = ($i==$month) ? "z" : "a";
		$datesel.="<$w href='calendar.php?y=$year&m=$i'>$i</$w> ";
	}
	$datesel.="| Year: ";
	foreach(range($year-2,$year+2) as $i){
		$w = ($i==$year) ? "z" : "a";
		$datesel.="<$w href='calendar.php?y=$i&m=$month'>$i</$w> ";		
	}
	
	
	print "
	<a href='index.php'>".$config['board-name']."</a> - Calendar
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
	";

	pagefooter();

?>