<?php

	require "lib/function.php";
	
	pageheader("Memberlist");
	
	$isadmin = powlcheck(4);
	$page = filter_int($_GET['page']);
	
	$sortl = array(
		'posts' => "posts DESC",
		'exp' 	=> "posts",
		'name' 	=> "name ASC",
		'reg' 	=> "since DESC",
		'ip' 	=> "lastip ASC",
		'age'	=> "birthday ASC"
	);
	$sexl = array(
		'm' => "AND sex = 0",
		'f' => "AND sex = 1",
		'n' => "AND sex = 2",
		'a' => ""
	);

	// defaults
	if (!isset($_GET['pow'])) $powerdo = "";
	else if ($_GET['pow'] == '-1') $powerdo = "AND powerlevel < 0";
	else if ($_GET['pow'] == '4') $powerdo = "AND powerlevel > 3";
	else $powerdo = "AND powerlevel = ".filter_int($_GET['pow']);
	
	if (!isset($_GET['sex'])) $sexdo = "";
	else $sexdo = $sexl[$_GET['sex']];
	
	if (!isset($_GET['sort'])) $sortdo = $sortl['posts'];
	else $sortdo = $sortl[$_GET['sort']];
	

	// uh no
	if ($sexdo===NULL || $sortdo===NULL || (!$isadmin && $sortdo == 'lastip'))
		errorpage("Invalid selection.", false);
	
	// pagecount
	$sortp = "&sort=".(isset($_GET['sort']) ? $_GET['sort'] : "posts");
	$sexp = $sexdo ? "&sex=".$_GET['sex'] : "";
	$powlp = $powerdo ? "&pow=".$_GET['pow'] : "";
	
	
	$users = $sql->query("
	
		SELECT id, name, displayname, sex, powerlevel, namecolor, icon, posts, since, lastip, birthday
		FROM users
		WHERE 1
		$sexdo
		$powerdo
		ORDER BY $sortdo
		LIMIT $page,50
		
	");
	
	$count = 0;
	$txt = "";
	
	while($user = $sql->fetch($users)){
		$count++;
		
		$icon = $user['icon'] ? "<img src='".$user['icon']."'>" : "";

		$txt .= "
		<tr>
			<td class='dim c'>$count.</td>
			<td class='light c'>$icon</td>
			<td class='dim'>".makeuserlink(false, $user)."</td>
			<td class='dim c'>".printdate($user['since'])."</td>
			<td class='light c'>".$user['posts']."</td>
			<td class='light c'>-</td>
			<td class='light c'><nobr>".calcexp($user['since'], $user['posts'])."</nobr></td>
			".($isadmin ? "<td class='dim c'>".$user['lastip']."</td>" : "")."
		</tr>
		";
		

	}
	
	print "<br/>
	<table class='main w c'>
		<tr>
			<td class='head' colspan='2'>
				$count user".($count == 1 ? "" : "s")." found.
			</td>
		</tr>
		
		<tr class='fonts'>
			<td class='light'>Sort by:</td>
			
			<td class='dim'>
				<a href='memberlist.php?sort=posts$sexp$powlp'>Total posts</a> |
				<a href='memberlist.php?sort=exp$sexp$powlp'>EXP</a> |
				<a href='memberlist.php?sort=name$sexp$powlp'>User name</a> |
				<a href='memberlist.php?sort=reg$sexp$powlp'>Registration date</a> |
				<a href='memberlist.php?sort=age$sexp$powlp'>Age</a>
				".($isadmin ? "| <a href='memberlist.php?sort=ip$sexp$powlp'>IP address</a>" : "")."
			</td>
		</tr>
		<tr class='fonts'>
			<td class='light'>Sex:</td>
			
			<td class='dim'>
				<a href='memberlist.php?sex=m$sortp$powlp'>Male</a> |
				<a href='memberlist.php?sex=f$sortp$powlp'>Female</a> |
				<a href='memberlist.php?sex=n$sortp$powlp'>N/A</a> |
				<a href='memberlist.php?$sortp$powlp'>All</a>
			</td>
		</tr>
		".($isadmin ? "
		<!-- something -->
		" : "")."
		<tr class='fonts'>
			<td class='light'>Powerlevel:</td>
			
			<td class='dim'>
<!--				<a href='memberlist.php?$sortp$sexp&pow=-2'>Permabanned</a> | -->
				<a href='memberlist.php?$sortp$sexp&pow=-1'>Banned</a> |
				<a href='memberlist.php?$sortp$sexp&pow=0'>Normal</a> |
				<a href='memberlist.php?$sortp$sexp&pow=1'>Privileged</a> |
				<a href='memberlist.php?$sortp$sexp&pow=2'>Local Moderator</a> |
				<a href='memberlist.php?$sortp$sexp&pow=3'>Global Moderator</a> |
				<a href='memberlist.php?$sortp$sexp&pow=4'>Administrator</a> |
<!-- 			<a href='memberlist.php?$sortp$sexp&pow=5'>Sysadmin</a> | -->
				<a href='memberlist.php?$sortp$sexp'>All</a>
			</td>
		</tr>
	</table>
	<br/>	
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
	".dopagelist($count, 50, 'memberlist', "$sortp$sexp$powlp")."
	";
	
	pagefooter();
	
?>