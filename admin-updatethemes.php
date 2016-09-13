<?php

	require "lib/function.php";
	
	admincheck();
	
	if (isset($_POST['go'])) {
		
		checktoken();
		pageheader("Update themes");
		print adminlinkbar();
		
		?>
		<center>
		<table class='main'>
			<tr>
				<td class='head c'>
					Update themes
				</td>
			<tr>
				<td class='dim' style='padding: 5px'>
					<pre><?php
		
		$themes = fopen('themes.dat', 'r');
		
		if ($themes) {
			
			$sql->start();
			$sql->query("TRUNCATE themes");
			
			$in = $sql->prepare("INSERT INTO themes (name, file, special) VALUES (?,?,?)");
			
			for($cnt = 0; ($x = fgetcsv($themes, 128, ";")) !== false; $cnt++){
				print "$x[0] - $x[1]\n";
				$sql->execute($in, [$x[0], $x[1], filter_int($x[2])]);
			}
			
			fclose($themes);
			
			$sql->end();
			
			print "$cnt themes found.";
			
		}
		else {
			print "ERROR: Couldn't open themes.dat in the board root directory.";
		}
		
					?></pre>
				</td>
			</tr>
		</table>
		</center>
		<?php
	}
	else {
	
		pageheader("Update themes");
		
		print adminlinkbar();
		?>
		<br>
		<form method='POST' action='admin-updatethemes.php'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
		<table class='main w'>
			<tr><td class='head c'>Update Themes</td></tr>
			
			<tr>
				<td class='light c'>
					This will recreate the themes table in the database based on the contents of themes.dat.<br>
					If you proceed, the table will be truncated.
				</td>
			</tr>
			
			<tr><td class='dim'>Press the button to start -> <input type='submit' value='Start' name='go'></td></tr>
		</table>
		
		</form>	
		<?php
		
	}
	
	pagefooter();

?>