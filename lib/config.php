<?php
/*
	Configuration
*/
	
	// Sql database options
	$sqlhost = 		'localhost';
	$sqluser = 		'Kak';
	$sqlpass = 		'V6TtzduVvmmfrZej';
	$sqldb = 		'boardc';
	$sqlpersist = 	true;
	
	// Root Admin IPs
	$adminips = array(
//		'127.0.0.1',
	);
	
	// $config options
	
	$config = array(

		// Board Options
		
		
		'admin-board' =>			false,
		'allow-rereggie' =>			false,		
		'deleted-user-id' => 		2,
		'trash-id' 		  => 		3,
		'show-comments'		=>		false,
		

		// Layout
		
		'board-name' =>				"BoardC",	
		'board-title' =>			"<img src='images/sampletitle.png' title='Board Sea'>",
		'board-version' =>			"0.17a Development Build",
		'board-url' =>				"http://localhost/board/",
		'admin-email' =>			"kak@nothing.null",
		
		// RPG Elements
		
		'coins-rand-min' =>			150, // $config variables for easy testing and consistency
		'coins-rand-max' =>			500,
		'coins-bonus-newthread' =>	100,
		
		// File uploads
		
		'enable-file-uploads' =>	true,
		
		'max-icon-size-x'	=>		16,
		'max-icon-size-y'	=>		16,
		'max-icon-size-bytes' => 	10000,
		'max-avatar-size-x'	=>		180,
		'max-avatar-size-y'	=>		180,
		'max-avatar-size-bytes' => 	80000,
		
		
		// Firewall
		'enable-firewall' => 		0, // todo: rewrite firewall
		'pageview-limit-enable' =>	false,
		'pageview-limit' => 		1,
		'pageview-limit-bot' =>		10, //1 each 10 seconds
		
		// Defaults
		'default-time-zone' => 		3600,//GMT+1
		'default-date-format' => 	"j/n/y",
		'default-time-format' =>	"G:i:s",
		
		// Development stuff
		
		'dummy-name' =>				"Dummy variable",	
		'force-userid' =>			false,
		'force-sql-debug-on' =>		false,		

	
	);
	
	//options for dumb stuff
	$hacks = array(
		'replace-image-before-login' => true,
		'correct-board-name'		 => false,
		'test-ext'					 => false,
	);
	

?>