<?php

	require "lib/function.php";

	if (!powlcheck(4))
		errorpage("you aren't an admin<br/>so, go away");

	pageheader("Perpetual under construction page");
	
	print adminlinkbar();
	
	if (filter_string($_POST['submit'])){
		
		if ($_POST['theme']=='-1') $theme = NULL;
		else $theme = filter_int($_POST['theme']);
		
		$sql->start();
		$update = $sql->prepare("UPDATE misc SET disable = ?, views = ?, theme = ?");
		$c[] = $sql->execute($update, array(
			filter_int($_POST['disable']),
			filter_int($_POST['views']),
			$theme
			)
			);
			
		$message = ($sql->finish($c)) ? "Settings updated!" : "Couldn't update the settings.";
		errorpage($message, false);
	}
	else if (isset($_GET['trim'])){
		$sql->query("TRUNCATE hits");
	}
	
	$opt = $sql->fetchq("SELECT * FROM misc", true)[0];
	
	$themes = findthemes();
	if (isset($opt['theme'])) $theme[$opt['theme']] = "selected";
	$input = "";
	foreach($themes as $i => $x)
		$input .= "<option value=".$x['id']." ".filter_string($theme[$x['id']]).">".$x['name']."</option>";
	$theme_txt = "<select name='theme'><option value='-1'>None</option>$input</select>";
	
	print "<br/><form method='POST' action='admin.php'>
	<table class='main w'>
		<tr><td class='head c' colspan=2>Dip Switches</td></tr>
		
		<tr><td class='light c' colspan=2>Every time you use this page you show your laziness.</br>
		As you could be using <a href=\"/phpmyadmin\">PMA</a> instead of this unfinished thing.</td></tr>

		<tr><td class='dark' colspan=2>Commands: <a href='?trim'>Trim Hits</a></tr>
		
		<tr>
			<td class='light' style='width: 250px;'>
				Disable the board<br/>
				<small>Does exactly what you'd think.<br/>Only admins will be able to use this board.</small>
			</td>
			<td class='dim'>
				<input type='checkbox' name='disable' value=1 ".($opt['disable'] ? "checked" : "").">Disable Board&nbsp;
			</td>
		</tr>
		<tr>
			<td class='light'>
				Views counter<br/>
				<small>I have no idea why is this here.</small>
			</td>
			<td class='dim'>
				<input style='width: 50px;' type='text' name='views' value='".$opt['views']."'>
			</td>
		</tr>
		<tr>
			<td class='light'>
				Force theme<br/>
				<small>Every user will be forced to use this theme, regardless of user or forum setting.</small>
			</td>
			<td class='dim'>
				$theme_txt
			</td>
		</tr>
		
		<tr><td class='dark' colspan=2><input type='submit' value='Save Settings' name='submit'></td></tr>
		
		</table></form>
	";
	

	pagefooter();

?>