<?php
	// based on listposts.php
	require "lib/function.php";
	
	/*
		Filter user input
	*/
	$id	= filter_int($_GET['id']);
	if (!$id) errorpage("No user specified.");
	
	if (!isset($_GET['time'])) 	$time = 86400; // 1 day
	else 						$time = filter_int($_GET['time']);
	
	$user = $sql->fetchq("SELECT $userfields FROM users u WHERE u.id = $id");
	if (!$user)	errorpage("Invalid user.");
		
		
	if ($time) 	$time_txt = "AND time > ".(ctime()-$time);
	else 		$time_txt = ""; // Check all posts when time == 0
	
	
	/*
		Rather than count all values manually, get an array already ordered perfectly
	*/
	
	$postdb = $sql->fetchq("
		SELECT HOUR(FROM_UNIXTIME(time)) hour, COUNT(id) postcount
		FROM posts
		WHERE user = $id $time_txt
		GROUP BY hour
	", true, PDO::FETCH_KEY_PAIR);
	
	// And fill the unused hour values with 0
	$postdb = array_replace(array_fill('0', '24', 0), $postdb);
	
	$txt = "";
	
	// Get the max value to scale the bar properly
	$max = max($postdb);
	if ($max != 0) $mul = 100/$max;
	else $mul = 0;
	
	// Print out everything for each hours
	for($i = 0; $i < 24; $i++){
		$txt .= "
			<tr class='fonts'>
				<td class='light c'>$i:00 - $i:59</td>
				<td class='light c'>{$postdb[$i]}</td>
				<td class='dim'>
					<img src='images/bar/bar-on.gif' height='8' width='".($mul*$postdb[$i])."%'>
				</td>				
			</tr>
		";
	}
	
	/*
		Page layout
	*/
	$when = $time ? " during the last ".choosetime($time) : " in total";
	
	pageheader("Posts by time of day");
	
	?>
	<div class='fonts'>
		Timeframe: 
		<a href='postsbytime.php?id=<?php echo $id ?>&time=86400'>During last day</a> | 
		<a href='postsbytime.php?id=<?php echo $id ?>&time=604800'>During last week</a> | 
		<a href='postsbytime.php?id=<?php echo $id ?>&time=2592000'>During last 30 days</a> | 
		<a href='postsbytime.php?id=<?php echo $id ?>&time=31536000'>During last year</a> | 
		<a href='postsbytime.php?id=<?php echo $id ?>&time=0'>Total</a>
	</div>
	
	Posts from <?php echo makeuserlink(false, $user) ?> by time of day <?php echo $when ?>:
	
	<table class='main w'>
		<tr>
			<td class='head c' style='width: 100px'>Time</td>
			<td class='head c' style='width: 50px'>Posts</td>
			<td class='head c'></td>
		</tr>
		<?php echo $txt ?>
	</table>
	
	<?php
	
	pagefooter();
?>