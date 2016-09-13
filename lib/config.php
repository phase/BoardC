<?php
/*
	Configuration
*/
	
	// Sql database options
	$sqlhost 		= 'localhost'; // Database host
	$sqluser 		= 'Kak'; // Username
	$sqlpass 		= 'V6TtzduVvmmfrZej'; // Password
	$sqldb 			= 'boardc'; // Database
	$sqlpersist 	= true; // Persist connection
	
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
		'auth-salt' => 				"silly salt string you should change",
		'post-break' =>				2, // 2 seconds to wait between posting consecutive posts / threads
		'allow-thread-erase' =>		true,
		'posts-to-get-title' =>		100,
		

		// Layout
		
		'board-name' =>				"BoardC",	
		'board-title' =>			"<img src='images/testboard.png' title='did you mean: BUGGY BOARD'>",
		'board-url' =>				"http://localhost/board/",
		'footer-title' =>			"The Internet",
		'footer-url' =>				"http://localhost",
		'admin-email' =>			"kak@nothing.null",
		
		// RPG Elements
		'coins-multiplier'	=>		10, // Multiplier used to calculate the amount of coins. NOTE: CHANGING THIS WILL ALTER THE COIN COUNT OF EVERY USER
		
		// File uploads
		
		'enable-file-uploads' =>	true,
		
		'max-icon-size-x'	=>		16,
		'max-icon-size-y'	=>		16,
		'max-icon-size-bytes' => 	10000,
		'max-avatar-size-x'	=>		180,
		'max-avatar-size-y'	=>		180,
		'max-avatar-size-bytes' => 	80000,
		
		
		// Firewall
		'enable-firewall' => 		true,
		'pageview-limit-enable' =>	true,
		'pageview-limit' => 		0,	// Disable
		'pageview-limit-bot' =>		120, // 1 each 120 seconds
		
		// Defaults
		'default-time-zone' => 		0, // Hours
		'default-date-format' => 	"d/m/y",
		'default-time-format' =>	"H:i:s",
		
		// News 'plugin'
		'enable-news' =>			true,
		'news-name'	=>				"News",
		'news-title' =>				"<font size=3>I 'see' News</font>",
		'max-preview-length' =>		500, // Max characters before text is shrunk
		'news-write-perm' =>		1, // Powerlevel required to add news
		'news-admin-perm' =>		4, // Powerlevel required to erase news
		
		// IRC
		'enable-irc-reporting' =>	true, // like it's implemented or something
		'irc-server' =>				'irc.badnik.zone',
		'public-chan' =>			'#powl0-fdsgs',
		'private-chan' =>			'#powl1-dfhjkd',
		
		
		// Development stuff
		
		'dummy-name' =>				"Dummy variable",	
		'force-userid' =>			false,
		'force-sql-debug-on' =>		true,		
		'force-error-printer-on' => true

	
	);
	
	//options for dumb stuff
	$hacks = array(
		'replace-image-before-login' => false,
		'test-ext'					 => false,
		'failed-attempt-at-irc'		 => false,
		'force-modern-web-design'	 => false,
		'super-private'				 => false,
		'mention-the-mailbag'		 => false,
		'joke-faq'					 => false,
	);
	

?>