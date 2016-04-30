<?php

	require "lib/function.php";
	
	if (!powlcheck(4))
		errorpage("I honestly could let everybody access this page like AB2 did, but this is still an admin tool.<br/>So, return to the <a href='index.php'>index</a>");
	
	function bmsg($msg){
		x_die("$msg\n<a href='index.php' style='background: #fff;'>Click here to return</a>");
	}

	if (isset($_POST['go'])){
		
		print "<!doctype html><title>Update themes</title><body style='background: #008; color: #fff;'>
		<pre><b style='background: #fff; color: #008'>Themes update</b>\n\n";
		
		$themes = fopen('themes.dat', 'r');
		
		if (!$themes)
			bmsg("ERROR: Couldn't find themes.dat in the board root directory.");
		
		$sql->start();
		$sql->query("TRUNCATE themes");
		$in = $sql->prepare("INSERT INTO themes (name, file) VALUES (?,?)");
		
		while(($x = fgetcsv($themes, 128, ";")) !== false){
			print "$x[0] - $x[1]\n";
			$sql->execute($in, array($x[0], $x[1]));
		}
		
		fclose($themes);
		$sql->end();
		
		bmsg("\n\nOperation completed!");
	}
	
	
	pageheader("Update themes");
	
	print adminlinkbar()."<br/>
	<form method='POST' action='admin-updatethemes.php'>
	<table class='main w'>
		<tr><td class='head c'>Update Themes</td></tr>
		
		<tr><td class='light c'>
			This will recreate the themes table in the database based on the contents of themes.dat.<br/>
			If you proceed, the table will be truncated.
		</tr></tr>
		<tr><td class='dim'>Press the button to start -> <input type='submit' value='Start' name='go'></td></tr></table>
	</form>	
	";
	
	pagefooter();

?>