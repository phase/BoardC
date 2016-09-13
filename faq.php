<?php

	// Serious FAQ coming soon. or not.
	
	require "lib/function.php";
	
	if ($hacks['joke-faq']) {
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
			"WELCOME TO BOARDC<BR>ASK DUMB QUESTIONS <!-- like why the header is wiping off in IE6 -->AND GET BANNED (edition)",
			"YOU ARE ".strtoupper($powl_table[$loguser['powerlevel']]).", DEAL WITH IT",
			"Keep F5ing to get them all!",
			"IE6 IS THE BEST BROWSER IN THE WORLD AND IT DOESN'T SUCK IN ANY WAY, SHAPE OR FORM",
			"IF YOU'RE N00BED AND MAKE A DEAL OUT OF IT YOU'RE STILL A N00B",
			"(you might want to read <a href='https://jul.rustedlogic.net/faq.php'>this</a> FAQ instead)",
			"PRERELEASE VERSIONS CAN BE BUGGY, NOW STOP COMPLAINING AND JUST FILE A BUG REPORT ON THIS FORUM / ON GITHUB",
			"DON'T BE A <S>DICK</S> DUCK",
		);
		
		if (file_exists("lib/firewall.php")){
			$lolrule[] = "NO, I DON'T GIVE OUT THE FIREWALL (like it's special or anything)";
		}
		
		$onlyrule = pick_any($lolrule);
		
		$faq = array(
		
			'General disclaimer'						=> "<center>There is no FAQ.</center>",
			'Except for this <s>one</s> golden rule'	=> "<center><h1>$onlyrule</h1></center>"
			
		);
		
	} else {
		
		$faq = array(
			// obvious placeholder but not really
			'Message'	=> "<center>If you're here, you should probably already know how to behave on forums.</center>",
			
		);
		
	}
	
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
		</table><br>
		";
		
	pageheader("The Rules");
	print "<br id='top'>$txt";
	pagefooter();
	
?>