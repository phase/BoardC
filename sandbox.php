<?php
	
	require "lib/function.php";
	
	
	if (!$isadmin){
		errorpage("Kak says not today!<br>Click <a href='index.php'>here</a> to return to the index.");
	}
	
	echo "<pre>Hi, I'm the magical genie of all secrets.";
	echo "\nHere's your \$loguser data:";
	d($loguser);
	
	x_die();
	
	
	echo "<pre>Converting serialized poll data...";
	
	$all = $sql->query("SELECT id, title FROM threads WHERE ispoll = 1");
	
	$sql->start();
	$addp = $sql->prepare("INSERT INTO polls (thread, question, briefing, multivote) VALUES (?,?,?,?)");
	$addc = $sql->prepare("INSERT INTO poll_choices (thread, name, color) VALUES (?,?,?)");
	
	foreach($all as $x){
		$y = split_null($x['title']);
		$c[] = $sql->execute($addp, [$x['id'], $y[0], $y[1], $y[2]]);
		for($i = 3; isset($y[$i+1]); $i+=2){
			$c[] = $sql->execute($addc, [$x['id'], $y[$i], $y[$i+1]]);
		}
	}
	
	if ($sql->finish($c)) print "OK!";
	else print "FAIL";

	x_die();
	/*
	$stat = array('HP','MP','Atk','Def','Int','MDf','Dex','Lck','Spd');
	$esta = array('hp','mp','atk','def','intl','mdf','dex','lck','spd');
	$c = count($stat);
	echo "<pre>";
	for($i = 0; $i < $c; $i++){
		echo "ALTER TABLE `shop_items` CHANGE `{$esta[$i]}` `s{$stat[$i]}` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;\n";
		echo "ALTER TABLE `users_rpg` CHANGE `{$esta[$i]}` `s{$stat[$i]}` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;\n";
	}
	*/
	
	
	x_die();
	
	
	/*
	if (isset($_GET['pupd2'])){
		$x = $sql->query("SELECT id FROM threads");
		while ($y = $sql->fetch($x))
			$sql->query("INSERT INTO threads_read (id, user1) VALUES ({$y['id']}, ".ctime().")");
		
		$x = $sql->query("SELECT id FROM announcements");
		while ($y = $sql->fetch($x))
			$sql->query("INSERT INTO announcements_read (id, user1) VALUES ({$y['id']}, ".ctime().")");
		
		x_die("OK");
	}
	*/