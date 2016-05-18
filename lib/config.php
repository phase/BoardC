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
		
		// Max allowed
		'post-limit' =>				0,
		'posts-to-get-title' =>		100,
		

		// Layout
		
		'board-name' =>				"BoardC",	
		'board-title' =>			"<img src='images/sampletitle2.png' title='Now in Badly-Drawn-Lego&trade; flavour!'>",
		'board-version' =>			"Development Build 0.25",
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
		'default-time-zone' => 		0,// Hours (also, this was always set wrong since the beginning)
		'default-date-format' => 	"d/m/y",
		'default-time-format' =>	"H:i:s",
		
		// News 'plugin'
		'enable-news' =>			true,
		'news-name'	=>				"News",
		'news-title' =>				"<font size=3>I 'see' News</font>",
		'max-preview-length' =>		500, // Max characters before text is shrunk
		'news-write-perm' =>		1, // Powerlevel required to add news
		'news-admin-perm' =>		4, // Powerlevel required to erase news
		
		
		// Development stuff
		
		'dummy-name' =>				"Dummy variable",	
		'force-userid' =>			false,
		'force-sql-debug-on' =>		true,		

	
	);
	
	//options for dumb stuff
	$hacks = array(
		'replace-image-before-login' => true,
		'correct-board-name'		 => false,
		'test-ext'					 => false,
	);
	

?>