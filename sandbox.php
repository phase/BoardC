<?php
	
	require "lib/function.php";
	
	admincheck();
	
	
	$stat = array('HP','MP','Atk','Def','Int','MDf','Dex','Lck','Spd');
	$esta = array('hp','mp','atk','def','intl','mdf','dex','lck','spd');
	$c = count($stat);
	echo "<pre>";
	for($i = 0; $i < $c; $i++){
		echo "ALTER TABLE `shop_items` CHANGE `{$esta[$i]}` `s{$stat[$i]}` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;\n";
		echo "ALTER TABLE `users_rpg` CHANGE `{$esta[$i]}` `s{$stat[$i]}` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;\n";
	}
	
	
	
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