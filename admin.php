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
		$update = $sql->prepare("UPDATE misc SET disable = ?, views = ?, theme = ?, noposts = ?, regmode = ?, regkey = ?");
		$c[] = $sql->execute($update,
			array(
				filter_int($_POST['disable']),
				filter_int($_POST['views']),
				$theme,
				filter_int($_POST['noposts']),
				filter_int($_POST['regmode']),
				filter_string($_POST['regkey']),
			)
		);
			
		$message = $sql->finish($c) ? "Settings updated!" : "Couldn't update the settings.";
		errorpage($message, false);
	}
	else if (isset($_GET['trim'])){
		$sql->query("TRUNCATE hits");
		header("Location: admin.php"); // Make sure you don't accidentaly trim the hits again by refreshing
	}
	
	$opt 	= $sql->fetchq("SELECT * FROM misc", true)[0];
	
	
	$themes = findthemes(false, true);
	
	if (isset($opt['theme'])) $theme[$opt['theme']] = "selected";
	$input 	= "";
	$sta	= 1;
	foreach($themes as $i => $x){
		if ($sta != $x['special']){
			$sta 	= $x['special'];
			$input .= "</optgroup><optgroup label='".($sta ? "Special" : "Normal")." themes'>";
		}
		
		$input	.= "<option value=".$x['id']." ".filter_string($theme[$x['id']]).">".$x['name']."</option>";
	}
	$theme_txt	 = "<select name='theme'><option value='-1'>None</option>$input</optgroup></select>";
	
	$reg_sel[$opt['regmode']] = 'selected';
		
	print "<br/><form method='POST' action='admin.php'>
	<table class='main w'>
		<tr><td class='head c' colspan=2>Dip Switches</td></tr>
		
		<tr><td class='light c' colspan=2>Every time you use this page you show your laziness.</br>
		As you could be using <a href=\"/phpmyadmin\">PMA</a> instead of this unfinished thing.</td></tr>

		<tr><td class='dark' colspan=2>Commands: <a href='?trim'>Trim Hits</a></tr>
		
		<tr>
			<td class='light' style='width: 260px;'>
				<b>Disable the board</b><br/>
				<small>Does exactly what you'd think.<br/>Only admins will be able to use this board.</small>
			</td>
			<td class='dim'>
				<input type='checkbox' name='disable' value=1 ".($opt['disable'] ? "checked" : "").">Disable Board&nbsp;
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Views counter</b><br/>
				<small>I have no idea why is this here.</small>
			</td>
			<td class='dim'>
				<input style='width: 50px;' type='text' name='views' value='".$opt['views']."'>
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Force theme</b><br/>
				<small>Every user will be forced to use this theme, regardless of user or forum setting.</small>
			</td>
			<td class='dim'>
				$theme_txt
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Disable posting</b><br/>
				<small>Prevents the creation of new replies or threads (or polls).</small>
			</td>
			<td class='dim'>
				<input type='checkbox' name='noposts' value=1 ".($opt['noposts'] ? "checked" : "").">Disable posting&nbsp;
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Registration mode</b><br/>
				<small>Restricts the registration of new users (not admin rereggies).</small>
			</td>
			<td class='dim'>
				<select name='regmode'>
					<option value='0' ".filter_string($reg_sel[0]).">Open registration</option>
					<option value='1' ".filter_string($reg_sel[1]).">Require administrator request</option>
					<option value='2' ".filter_string($reg_sel[2]).">Require passkey</option>
					<option value='3' ".filter_string($reg_sel[3]).">Disabled</option>					
					"./*
					NOTE: I have no idea what is up with the 'Only normal users can register you' and other options like this in a certain version of Acmlm+Erk.
					As it makes no sense (and it's probably a rereggie risk), the only other registration mode I've implemented is the Regkey mode (idea stolen from the original Justus League)
					*/"
				</select>
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Registration key</b><br/>
				<small>
				This key will be required to create an account.<br/>
				To enable this, select 'Require passkey' from the previous select box.
				</small>
			</td>
			<td class='dim'>
				<input style='width: 250px;' type='text' name='regkey' value=\"".$opt['regkey']."\">
			</td>
		</tr>
		<tr><td class='dark' colspan=2><input type='submit' value='Save Settings' name='submit'></td></tr>
		
		</table></form>
	";
	

	pagefooter();

?>