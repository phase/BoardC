<?php
	die("Unfinished.");
	/*
		CSS Editor
		TODO!
	*/
	
	require "lib/function.php";
	
	if (!$loguser['id']) 	errorpage("You need to be logged in to do this!");
	if (!powlcheck(1)) 		errorpage("dwihewkfkewesgr remove this check");
	
	pageheader("Custom Theme Editor");
	
	$scheme = $sql->fetchq("SELECT * FROM customthemes WHERE id = {$loguser['id']}");
	
	// Table creation because egvrjcalalcnvhbwtsk
	$fields["Cells"] = array(
		
		"Sample text"	=> [1, 'sample_field', 'Sample title', "Sample description"],
	);
	
	print "
	<form method='POST' action='edittheme.php'>
		<table class='main w'>
			<tr><td class='head c'>TEMP!</td></tr>
		</table>
	</form>
	";
	
	pagefooter();
?>