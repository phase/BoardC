<?php

	require "lib/function.php";

	admincheck();
	
	if (isset($_POST['submit'])){
		checktoken();
		
		if (!$_POST['theme']) 	$theme = NULL;
		else 					$theme = filter_int($_POST['theme']);
		
		$sql->start();
		$update = $sql->prepare("UPDATE misc SET disable = ?, views = ?, theme = ?, noposts = ?, regmode = ?, regkey = ?, threshold = ?, private = ?");
		$c[] = $sql->execute($update,
			array(
				filter_int($_POST['disable']),
				filter_int($_POST['views']),
				$theme,
				filter_int($_POST['noposts']),
				filter_int($_POST['regmode']),
				prepare_string($_POST['regkey']),
				prepare_string($_POST['threshold']),
				filter_int($_POST['private']),
			)
		);
			
		$message = $sql->finish($c) ? "Settings updated!" : "Couldn't update the settings.";
		setmessage($message);
		redirect("?");
	}
	
	$opt 	= $sql->fetchq("SELECT * FROM misc");
	
	$reg_sel[$opt['regmode']] = 'selected';
	
	
	pageheader("Admin's Toolbox");
	
	print adminlinkbar().$message;
		
	print "
	<form method='POST' action='admin.php'>
	<input type='hidden' name='auth' value='$token'>
	
	<table class='main w'>
		<tr><td class='head c' colspan=2>Dip Switches</td></tr>
		
		<tr><td class='light c' colspan=2>This is the board configuration page.<br>
		Please wait while ducks are being chased by a laughing dog.</td></tr>
		
		<tr>
			<td class='light' style='width: 270px;'>
				<b>Disable the board</b><br>
				<small>Does exactly what you'd think.<br>Only admins will be able to use this board.</small>
			</td>
			<td class='dim'>
				<input type='checkbox' name='disable' value=1 ".($opt['disable'] ? "checked" : "").">&nbsp;Disable Board
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Views counter</b><br>
				<small>I have no idea why is this here.</small>
			</td>
			<td class='dim'>
				<input style='width: 150px;' type='text' name='views' value='".$opt['views']."'>
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Force theme</b><br>
				<small>Every user will be forced to use this theme, regardless of user or forum setting.</small>
			</td>
			<td class='dim'>
				".dothemelist("theme", true, filter_int($opt['theme']))."
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Disable posting</b><br>
				<small>Prevents the creation of new replies or threads (or polls).</small>
			</td>
			<td class='dim'>
				<input type='checkbox' name='noposts' value=1 ".($opt['noposts'] ? "checked" : "").">&nbsp;Disable posting
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b><img src='{$IMG['statusfolder']}/hot.gif'> threshold</b><br>
				<small>
				Posts in a thread required to reach <img src='{$IMG['statusfolder']}/hot.gif'> status.
				</small>
			</td>
			<td class='dim'>
				<input style='width: 50px;' type='text' name='threshold' value=\"".$opt['threshold']."\">
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Registration mode</b><br>
				<small>Restricts the registration of new users (not admin rereggies).</small>
			</td>
			<td class='dim'>
				<select name='regmode'>
					<option value='0' ".filter_string($reg_sel[0]).">Open registration</option>
					<option value='1' ".filter_string($reg_sel[1]).">Require administrator request</option>
					<option value='2' ".filter_string($reg_sel[2]).">Require passkey</option>
					<option value='3' ".filter_string($reg_sel[3]).">Disabled</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Registration key</b><br>
				<small>
				This key will be required to create an account.<br>
				To enable this, select 'Require passkey' from the previous select box.
				</small>
			</td>
			<td class='dim'>
				<input style='width: 250px;' type='text' name='regkey' value=\"".$opt['regkey']."\">
			</td>
		</tr>
		<tr>
			<td class='light'>
				<b>Private board</b><br>
				<small>
				Marks the board as private.<br>
				You are required to login in order to view a private board.
				</small>
			</td>
			<td class='dim'>
				<input type='checkbox' name='private' value=1 ".($opt['private'] ? "checked" : "").">&nbsp;Set the board private
			</td>
		</tr>
		
		<tr>
			<td class='dark' colspan=2>
				<input type='submit' value='Save Settings' name='submit'>
			</td>
		</tr>
		
		</table></form>
	";
	

	pagefooter();

?>