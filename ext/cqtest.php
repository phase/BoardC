<?php

	// A test page
	chdir("..");
	require "lib/config.php";
	require "lib/mysql.php";
	require "lib/helpers.php";
	
	function errorpage($msg, $title="Error"){
		header("Content-Type: text/html", true); // sure, redeclare the content type twice. why not
		die("<pre><b>$title</b> - $msg");
	}
	
	
	$sql = new mysql;
	$connection = $sql->connect($sqlhost,$sqluser,$sqlpass,$sqlpersist);
	$sql->selectdb($sqldb);
	
	
	header("Content-Type: application/json");
	
	$mode = filter_string($_GET['c']);
	if (!$mode) errorpage("No arguments given.\n\nClick <a href='?c=help'>here</a> for help.");
	
	if ($mode == "help"){
	  errorpage("Where things happen\n".
				"I have no idea why is this here.\n".
				"\n".
				"Current modes:\n".
				"pinfo (post info)\n".
				"isban [id] (banned or not)\n".
				"".
				"", "Test page");

		
	}
	
	else if ($mode == "pinfo"){
		$userinfo = $sql->fetchq("SELECT id, name, posts, threads FROM users", true);
		print json_encode($userinfo);
	}
	else if ($mode == "isban"){
		$id = filter_int($_GET['id']);
		$b = $sql->fetchq("SELECT powerlevel p, ban_expire b FROM users WHERE id = $id");
		print "{\"banned\":".(int)($b['p']<0).",\"expire\",:".(int)$b['b']."}";
	}
	
	else errorpage("Invalid arguments given.");
	
?>