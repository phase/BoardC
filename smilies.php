<?php
	
	require "lib/function.php";
	
	pageheader("Smilies", false, 0, true);
	
	
	$smilies = dosmilies(false, true);
	$txt = "";
	$j   = 0;
	

	foreach($smilies as $stxt => $simg){
		if ($j==4){
			$j=0;
			$txt .= "</tr><tr>";
		}
		$txt .= "<td class='light'>$simg</td><td class='dim'>$stxt</td>";
		$j++;
	}
	
	
	
	?>
	<center>
	
	<table valign='middle' height='100%'><tr><td>
		
		<table class='main c'>
			<tr>
				<?php echo $txt ?>
			</tr>
		</table>
	
	</td></tr></table>
	
	</center>
	</body>
	</html>
	<?php
	
	x_die();

?>