<?php
	
	require "lib/function.php";
	
	//pageheader("Smilies");
	
	// Snip
	if (isset($miscdata['theme'])) $loguser['theme'] = $miscdata['theme']-1;
	
	$themes = findthemes(false, true);
	$css = file_get_contents("css/".$themes[$loguser['theme']]['file']);
	
	if (!$css) $css = "";
	
	else if (strpos($css, "META")){
		/*
		Special META flags
		Board name - 
		Board title (image) - [not needed here]
		*/
		$cssmeta = explode(PHP_EOL,$css, 3);
		$config['board-name'] = $cssmeta[1];
		//$config['board-title'] = $cssmeta[2];
	}
	// End snip
	
	$smilies = dosmilies(false, true);
	$txt = "";
	$j = 0;
	
	foreach($smilies as $stxt => $simg){
		if ($j==4){
			$j=0;
			$txt .= "</tr><tr>";
		}
		$txt .= "<td class='light'>$simg</td><td class='dim'>$stxt</td>";
		$j++;
	}
	
	print "
	<html>
		<head>
			<title>Smilies - ".$config['board-name']."</title>
			<style type='text/css'>$css</style>
			<link rel='icon' type='image/png' href='images/favicon.png'>
		</head>
		<body><center>
			<table valign='middle' height='100%'><tr><td>
				
				<table class='main c'>
					<tr>
						$txt
					</tr>
				</table>
			
			</td></tr></table>
		</body></center>
	</html>
	";
	
	x_die();

?>