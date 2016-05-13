<?php

	require "lib/function.php";
	
	pageheader("Active users");
	
	if (!isset($_GET['time'])) $time = 86400;
	else $time = filter_int($_GET['time']);
	
	$type = filter_string($_GET['type']);
	if (!$type) $type = 'posts';

	$types 		= array("posts", "thread", "pmsent", "pmget");
	$types_txt 	= array("posts made", "new threads", "PMs sent by you", "PMs sent to you");
	
	// No invalid choices
	for ($i=0,$type_list="";$i<4;$i++){
		if ($types[$i] == $type){
			$valid = true;
			$w = "z";
		}
		else $w = "a";
		
		$type_list .= "<$w href='?type=".$types[$i]."&time=$time'>".$types_txt[$i]."</$w>".($i==3 ? "" : " - ");
	}
	if (!isset($valid)) errorpage("What are you trying to do.", false);
	
	// Type-specific strings
	switch($type){
		case 'posts' : {
			$what = "Posts";
			$show_txt = "most active posters";
			$pm_filter = "";
			break;
		}
		case 'thread': {
			$what = "Threads";
			$show_txt = "most thread creators";
			$pm_filter = "";
			break;
		}
		case 'pmsent': {
			$what = "PMs";
			$show_txt = "who you've sent the most messages to";
			$pm_filter = ($time ? "AND" : "WHERE")." p.user = ".$loguser['id'];
			break;
		}
		case 'pmget' : {
			$what = "PMs";
			$show_txt = "most message senders";
			$pm_filter = ($time ? "AND" : "WHERE")." p.userto = ".$loguser['id'];
			break;
		}
		default: errorpage("Unknown error (mode $type)", false);
	}
	
	$q_table = strtolower($what);
	$q_time = $time ? "WHERE p.time > ".(ctime()-$time) : "";
	
	$users = $sql->query("
		SELECT COUNT(p.id) total, $userfields, u.since
		FROM $q_table p
		LEFT JOIN users u ON p.user = u.id
		$q_time
		$pm_filter
		GROUP BY u.id
		ORDER BY total DESC
	");
	
	// If you're looking  for the global posts, don't count manually and just use the total post counter in misc
	if ($users)
		$max = ($time || $pm_filter) ? $sql->resultq("SELECT COUNT(id) FROM $q_table p $q_time $pm_filter") : $miscdata["$q_table"];
	else $max = 0;
	
	$txt = "";
	$totalusers = 0;
	$pre = NULL;
	for ($i=0;$x=$sql->fetch($users);$i){
		
		// Increase counter if the previous post count is different (allow multiple "rankings")
		if ($pre != $x['total']) $i++;
		$pre = $x['total'];
		
		$icon = $x['icon'] ? "<img src=\"".$x['icon']."\" height='16' width='16'>" : "&nbsp;";
		
		$width = sprintf("%.01f",$pre/$max*100);
		
		$txt .= "
			<tr>
				<td class='light c'>$i</td>
				<td class='light c' width='16'>$icon</td>
				<td class='dim'>".makeuserlink(false, $x)."</td>
				<td class='light c'>".printdate($x['since'])."</td>
				<td class='dim c' width='30'><b>$pre</b></td>
				<td class='dim c' width='100'>$width%<br><img src='images/temp/minibar.png' height='3' align='left' width='$width%'> </td>
			</tr>
		";
		
		$totalusers++;
	}
	
	
	
	print "
	<table class='fonts w'>
		<tr>
			<td align='left' width='50%'>
				Show $show_txt in the:
				<br/>
				<a href='?type=$type&time=3600'>last hour</a> - 
				<a href='?type=$type&time=86400'>last day</a> - 
				<a href='?type=$type&time=604800'>last week</a> - 
				<a href='?type=$type&time=2592000'>last 30 days</a> - 
				<a href='?type=$type&time=0'>from the beginning</a>
			</td>
			<td align='right' width='50%'>
				Most active users by:<br>
				$type_list
			</td>
			</td>
		</tr>
	</table>
	
	<table class='main w'>
			
			<tr><td class='dark c' colspan='6'><b>".ucfirst($show_txt).($time ? " during the last ".choosetime($time) : "")."</b></td></tr>
			
			<tr>
				<td class='head c' width='30'>#</td>
				<td class='head c' colspan='2'>Username</td>
				<td class='head c' width='200'>Registered on</td>
				<td class='head c' colspan='2' width='130'>$what</td>
			</tr>
			
			$txt
		<tr>
			<td class='dark c' colspan='6'>$totalusers users, $max posts</td>
		</tr>
	</table>
	";
	
	pagefooter();

?>