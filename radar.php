<?php
	
	require "lib/function.php";
	
	function radar_select($q){
		global $sql;
		
		$users = $sql->query($q);
		for($txt="";$x=$sql->fetch($users);$txt)
			$txt .= "<option value='".$x['id']."'>".($x['displayname'] ? $x['displayname'] : $x['name'])." (".$x['posts'].")</option>";
		
		return $txt;
	}
	
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to access the post radar!<br/>Click <a href='login.php'>here</a> to login.");
	
	if (isset($_POST['update'])){
		
		$add = filter_int($_POST['add']);
		$rem = filter_int($_POST['rem']);
		$mode = filter_int($_POST['mode']); // 0 = standard, 1 = automatic
		
		if ($add) $sql->query("INSERT INTO radar (user, sel) VALUES (".$loguser['id'].", $add)");
		if ($rem) $sql->query("DELETE FROM radar WHERE user = ".$loguser['id']." AND sel = $rem");
		if ($mode != $loguser['radar_mode']) $sql->query("UPDATE users SET radar_mode = $mode WHERE id = ".$loguser['id']);
		
		header("Location: radar.php");
	}
	
	pageheader("Post radar");
	
	$rem_txt = radar_select("
		SELECT r.sel id, u.name, u.displayname, u.posts
		FROM radar r
		LEFT JOIN users u ON r.sel = u.id
		WHERE r.user = ".$loguser['id']."
		ORDER BY u.name ASC
	");
	
	$add_txt = radar_select("SELECT id, name, displayname, posts FROM users	ORDER BY name ASC");
	
	/*
	$radar = $sql->query("
		SELECT r.sel, u.name, u.displayname, u.posts
		FROM radar r
		LEFT JOIN users u ON r.sel = u.id
		WHERE r.user = ".$loguser['id']."
	");
	
	$rem_txt = "";
	//$noshow = array(0); // don't show users to add already selected
	
	while($x = $sql->fetch($radar)){
		$name = $x['displayname'] ? $x['displayname'] : $x['name'];
		$rem_txt .= "<option value='".$x['id']."'>$name</option>";
	//	$noshow[] = $x['id'];
	}
	
	
	$users = $sql->query("SELECT id, name, displayname, posts FROM users");// WHERE id NOT IN (".implode(", ", $noshow).")");
	
	$add_txt = "";
	while($x = $sql->fetch($users)){
		$name = $x['displayname'] ? $x['displayname'] : $x['name'];
		$add_txt .= "<option value='".$x['id']."'>$name</option>";
	}
*/
	print "<br/>
	<form method='POST' action='radar.php'>
		<center><table class='main'>
			<tr><td class='head c' colspan=2>Post Radar</td></tr>
			
			<tr>
				<td class='light c' style='width: 150px'><b>Options:</b></td>
				<td class='dim'><input type='checkbox' name='mode' value=1 ".($loguser['radar_mode'] ? "checked" : "")."> Automatic mode</td>
			</tr>
			<tr>
				<td class='light c'><b>Add an user:</b></td>
				<td class='dim'><select name='add'><option value='0'>Do not add anybody</option>$add_txt</select></td>
			</tr>
			<tr>
				<td class='light c'><b>Remove an user:</b></td>
				<td class='dim'><select name='rem'><option value='0'>Do not remove anybody</option>$rem_txt</select></td>
			</tr>
			
			<tr><td class='dim' colspan=2><input type='submit' name='update' value='Save changes'></td></tr>
		</table></center>
	</form>
	
	";
	
	
	pagefooter();

?>