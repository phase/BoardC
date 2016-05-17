<?php

	// Serious FAQ coming soon. or not.
	require "lib/function.php";
	
	$powl_table = array(
		'-2'=> "Permabanned",
		'-1'=> "Banned",
		0	=> "a normal user",
		1 	=> "Privileged",
		2 	=> "a Local Moderator",
		3 	=> "a Global Moderator",
		4 	=> "an Administrator",
		5 	=> "an evil overlord",
	);
	
	$lolrule = array( //lolz
		"USE COMMON SENSE",
		"DON'T BE A DICK",
		"DO WHAT THE ADMINS SAY",
		"DON'T BE AN IDIOT",
		"YOU ARE ".strtoupper($powl_table[$loguser['powerlevel']]).", DEAL WITH IT",
		"Keep F5ing to get them all!",
		
	);
	
	$onlyrule = rand(0, count($lolrule)-1);
	
	$faq = array(
	
		'General disclaimer'						=> "<center>There is no FAQ.</center>",
		'Except for this <s>one</s> golden rule'	=> "<center><h1>".$lolrule[$onlyrule]."</h1></center>"
		
	);
	
	// FAQ Format
	$txt = "";
	foreach($faq as $i => $x)
		$txt .= "
		<table class='main w'>
			<tr>
				<td class='head c'>
					<b>$i</b><div style='float: right;'>[<a href='#top'>^</a>]</div>
				</td>
			</tr>
			<tr>
				<td class='light'>
					$x
				</td>
			</tr>
		</table><br/>
		";
		
	pageheader("The Rules");
	print "<br id='top'/>$txt";
	pagefooter();
	
?>