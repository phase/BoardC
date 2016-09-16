<?php

	require "lib/function.php";
	
	// _GET -> query assignments
	$sortl = array(
		'posts' => "posts", //DESC",
		'exp' 	=> "exp", //TEMP!
		'name' 	=> "name", //ASC",
		'reg' 	=> "since", //DESC",
		'ip' 	=> "lastip", //ASC",
		'age'	=> "birthday" //ASC"
	);
	$sexl = array(
		'm' => "AND sex = 0",
		'f' => "AND sex = 1",
		'n' => "AND sex = 2",
		'a' => ""
	);

	// defaults
	if (!isset($_GET['pow'])) 		$powerdo = "";
	else if ($_GET['pow'] == 'b') 	$powerdo = "AND powerlevel < 0";
	else if ($_GET['pow'] == 'a') 	$powerdo = "AND powerlevel > 3";
	else if ($_GET['pow'] == 's') 	$powerdo = "AND powerlevel > 1";
	else 							$powerdo = "AND powerlevel = ".((int) $_GET['pow']);
	
	
	if (!isset($_GET['sex'])) {
		$sexdo = "";
	} else {
		if (!in_array($_GET['sex'], $sexl)) errorpage("Invalid selection.");
		$sexdo = $sexl[$_GET['sex']];
	}
	
	if (!isset($_GET['sort'])) {
		$sortdo = $sortl['posts'];
	} else {
		if (!in_array($_GET['sort'], $sortl)) errorpage("Invalid selection.");
		$sortdo = $sortl[$_GET['sort']];
	}						
	
	// Order (ASC / DESC)
	if (filter_int($_GET['ord'])) 	$orddo = 1;
	else 							$orddo = 0;
	
	
	if ($sortdo == 'lastip') admincheck();
	
	// Used for the url in the page list
	$sortp 	= "&sort=".(isset($_GET['sort']) ? $_GET['sort'] : "posts");
	$sexp 	= $sexdo ? "&sex=".$_GET['sex'] : "";
	$powlp 	= $powerdo ? "&pow=".$_GET['pow'] : "";
	$ordp 	= $orddo ? "&ord=1" : "&ord=0";
	

	if ($sortdo != 'exp'){
		// Attempt to save memory by using a standard query rather than fetchall
		// as the latter is only required when sorting by EXP
		$users = $sql->query("
			SELECT $userfields, u.icon, u.posts, u.since, u.lastip, r.bonus_exp
			FROM users u
			LEFT JOIN rpg_classes r ON u.class = r.id
			WHERE 1
			$sexdo
			$powerdo
			ORDER BY $sortdo ".($orddo ? "ASC" : "DESC")."
		");
	} else {
		// EXP Sorting
		$users = $sql->fetchq("
			SELECT $userfields, u.icon, u.posts, u.since, u.lastip, r.bonus_exp
			FROM users u
			LEFT JOIN rpg_classes r ON u.class = r.id
			WHERE 1
			$sexdo
			$powerdo
		", true);
		// First loop to calculate exp value
		$cnt = count($users);
		for($i = 0; $i < $cnt; $i++){
			$users[$i]['exp'] = calcexp($users[$i]['posts'], (ctime()-$users[$i]['since']) / 86400, $users[$i]['bonus_exp']);
		}
		uasort($users, 'sortbyexp_'.($orddo ? 'ASC' : 'DESC'));
	}
	
	

	$txt 	= "";
	$count 	= 1;
	
	foreach($users as $user){
		
		$icon = $user['icon'] ? "<img src='{$user['icon']}'>" : "";
		
		if (!isset($user['exp'])) $user['exp'] = calcexp($user['posts'], (ctime()-$user['since']) / 86400, $user['bonus_exp']);

		$txt .= "
		<tr>
			<td class='dim c'>$count.</td>
			<td class='light c'>$icon</td>
			<td class='dim'>".makeuserlink(false, $user)."</td>
			<td class='dim c'>".printdate($user['since'])."</td>
			<td class='light c'>{$user['posts']}</td>
			<td class='light c'>".calclvl($user['exp'])."</td>
			<td class='light c nobr'>{$user['exp']}</td>
			".($isadmin ? "<td class='dim c'>{$user['lastip']}</td>" : "")."
		</tr>
		";
		
		$count++;
	}
	
	pageheader("Memberlist");
	
	$count--;
	
	print "
	<br>
	<table class='main w c'>
		<tr>
			<td class='head' colspan='2'>
				$count user".($count == 1 ? "" : "s")." found.
			</td>
		</tr>
		
		<tr class='fonts'>
			<td class='light'>Sort by:</td>
			
			<td class='dim'>
				<a href='memberlist.php?sort=posts$sexp$powlp$ordp'>Total posts</a> |
				<a href='memberlist.php?sort=exp$sexp$powlp$ordp'>EXP</a> |
				<a href='memberlist.php?sort=name$sexp$powlp$ordp'>User name</a> |
				<a href='memberlist.php?sort=reg$sexp$powlp$ordp'>Registration date</a> |
				<a href='memberlist.php?sort=age$sexp$powlp$ordp'>Age</a>
				".($isadmin ? "| <a href='memberlist.php?sort=ip$sexp$powlp$ordp'>IP address</a>" : "")."
			</td>
		</tr>
		
		<tr class='fonts'>
			<td class='light'>Sex:</td>
			
			<td class='dim'>
				<a href='memberlist.php?sex=m$sortp$powlp$ordp'>Male</a> |
				<a href='memberlist.php?sex=f$sortp$powlp$ordp'>Female</a> |
				<a href='memberlist.php?sex=n$sortp$powlp$ordp'>N/A</a> |
				<a href='memberlist.php?$sortp$powlp$ordp'>All</a>
			</td>
		</tr>

		<tr class='fonts'>
			<td class='light'>Power Level:</td>
			
			<td class='dim'>
				<a href='memberlist.php?$sortp$sexp$ordp&pow=-2'>{$power_txt['-2']}</a> | 
				<a href='memberlist.php?$sortp$sexp$ordp&pow=-1'>{$power_txt['-1']}</a> |
				<a href='memberlist.php?$sortp$sexp$ordp&pow=0' >{$power_txt[0]}</a> |
				<a href='memberlist.php?$sortp$sexp$ordp&pow=1' >{$power_txt[1]}</a> |
				<a href='memberlist.php?$sortp$sexp$ordp&pow=2' >{$power_txt[2]}</a> |
				<a href='memberlist.php?$sortp$sexp$ordp&pow=3' >{$power_txt[3]}</a> |
				<a href='memberlist.php?$sortp$sexp$ordp&pow=4' >{$power_txt[4]}</a> |
				<a href='memberlist.php?$sortp$sexp$ordp&pow=5' >{$power_txt[5]}</a> <br> 
				<a href='memberlist.php?$sortp$sexp$ordp'>All users</a> | 
				<a href='memberlist.php?$sortp$sexp$ordp&pow=b'>All banned</a> | 
				<a href='memberlist.php?$sortp$sexp$ordp&pow=s'>All staff</a> | 
				<a href='memberlist.php?$sortp$sexp$ordp&pow=a'>All administrators</a>
			</td>
		</tr>
		
		<tr class='fonts'>
			<td class='light'>Sorting order:</td>
			
			<td class='dim'>
				<a href='memberlist.php?$sortp$sexp$powlp&ord=1'>Ascending</a> | 
				<a href='memberlist.php?$sortp$sexp$powlp&ord=0'>Descending</a>
			</td>
		</tr>
		
	</table>
	
	<br>	
	<table class='main w'>
		<tr class='c'>
			<td class='head' style='width: 30px'>#</td>
			<td class='head' style='width: 16px'>&nbsp;</td>
			<td class='head'>Username</td>
			<td class='head' style='width: 200px'>Registered on</td>
			<td class='head' style='width: 60px'>Posts</td>
			<td class='head' style='width: 35px'>Level</td>
			<td class='head' style='width: 100px'>EXP</td>
			".($isadmin ? "<td class='head' style='width: 100px'>IP Address</td>" : "")."
		</tr>
		$txt
	</table>
	".dopagelist($count, 50, 'memberlist', "$sortp$sexp$powlp$ordp")."
	";
	
	pagefooter();
	
	// *SIGH*
	function sortbyexp_asc($a, $b){
		if($a['exp']=='NAN' && $a['exp']!='0') $a['exp']=-1;
		if($b['exp']=='NAN' && $b['exp']!='0') $b['exp']=-1;
		return($a['exp']-$b['exp']);
	}
	function sortbyexp_desc($a, $b){
		if($a['exp']=='NAN' && $a['exp']!='0') $a['exp']=-1;
		if($b['exp']=='NAN' && $b['exp']!='0') $b['exp']=-1;
		return($b['exp']-$a['exp']);
	}
	
?>