<?php

	require "lib/function.php";
	
	if (!powlcheck(4))
		errorpage("You wouldn't know how to fix threads (again).");
	
	
	function fix($id){
		// Shrunk from the function in function.php
		// This version saves query by not modifying automatically the forum counters
		// (this is the only script where it's not needed to do so)
		global $sql;
		$newdata = $sql->fetchq("SELECT id, user, time FROM posts WHERE thread = $id ORDER BY time DESC");
		
		if (!filter_int($newdata['id']))
			$sql->query("UPDATE threads SET lastpostid = NULL WHERE id = $id");
		
		else
			$sql->query("
				UPDATE threads
				SET	lastpostid = ".$newdata['id'].",lastpostuser = ".$newdata['user'].",lastposttime = ".$newdata['time']."
				WHERE id = $id
			");			
	}
	
	if (filter_string($_POST['go'])){
		print "<!doctype html><title>Thread Fix 2</title><body style='background: #008; color: #fff;'>
		<pre><b style='background: #fff; color: #008'>Last post time fix</b>\n\nFixing threads...";

		$sql->start();
		
		$iddb = $sql->query("SELECT id FROM threads");
		
		while ($id = $sql->fetch($iddb))
			fix($id['id']);
		
		print " Done!\nFixing forums...";
		
		$iddb = $sql->query("SELECT id FROM forums");
		
		while ($id = $sql->fetch($iddb))
			update_last_post($id['id'], false, true);
		
		print " Done!\nApplying changes. This may take a while...";
		
		$sql->end();
		
		x_die(" Done!\n\n<a href='index.php' style='background: #fff;'>Click here to return</a>");
	}
	
	pageheader("Thread Fix");
	
	print adminlinkbar()."<br/>
	<form method='POST' action='admin-threadfix2.php'>
	<table class='main w'>
		<tr><td class='head c'>Thread fix</td></tr>
		
		<tr><td class='light c'>
			This will cycle through threads and forums to correct their last post info.<br/>
			The process may take a while.
		</tr></tr>
		<tr><td class='dim'>Press the button to start -> <input type='submit' value='Start' name='go'></td></tr></table>
	</form>	
	";
	
	
	
	pagefooter();

?>