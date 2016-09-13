<?php
	
	require "lib/function.php";
	
	if (!$loguser['id']) {
		errorpage("
			You need to be logged in to access the post radar!<br>
			Click <a href='login.php'>here</a> to login.
		");
	}
	
	if (isset($_POST['update'])) {
		checktoken();
		
		$add 	= filter_int($_POST['add']);
		$rem 	= filter_int($_POST['rem']);
		$mode 	= filter_int($_POST['mode']); // 0 = standard, 1 = automatic
		
		if ($add) $sql->query("INSERT INTO radar (user, sel) VALUES ({$loguser['id']}, $add)");
		if ($rem) $sql->query("DELETE FROM radar WHERE user = {$loguser['id']} AND sel = $rem");
		if ($mode != $loguser['radar_mode']) $sql->query("UPDATE users SET radar_mode = $mode WHERE id = {$loguser['id']}");
		
		redirect("radar.php");
	}
	
	pageheader("Post radar");
	
	$rem_txt = radar_select("
		SELECT r.sel id, u.name, u.displayname, u.posts
		FROM radar r
		LEFT JOIN users u ON r.sel = u.id
		WHERE r.user = {$loguser['id']}
		ORDER BY u.name ASC
	");
	
	$add_txt = radar_select("SELECT id, name, displayname, posts FROM users	ORDER BY name ASC");
	
	/*
		Page layout
	*/
	
	$autosel = $loguser['radar_mode'] ? "checked" : "";
	
	?>
	<br>
	<form method='POST' action='radar.php'>
	<input type='hidden' name='auth' value='<?php echo $token ?>'>
	<center>
	
	<table class='main'>
	
		<tr><td class='head c' colspan=2>Post Radar</td></tr>
		
		<tr>
			<td class='light c' style='width: 150px'><b>Options:</b></td>
			<td class='dim'>
				<input type='checkbox' name='mode' value=1 <?php echo $autosel ?>>
				<label for='mode'>Automatic mode</label>
			</td>
		</tr>
		
		<tr>
			<td class='light c'><b>Add an user:</b></td>
			<td class='dim'>
				<select name='add'>
					<option value='0'>Do not add anybody</option>
					<?php echo $add_txt ?>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class='light c'><b>Remove an user:</b></td>
			<td class='dim'>
				<select name='rem'>
					<option value='0'>Do not remove anybody</option>
					<?php echo $rem_txt ?>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class='dim' colspan=2>
				<input type='submit' name='update' value='Save changes'>
			</td>
		</tr>
	</table>
	
	</center>
	</form>
	
	<?php
	
	
	pagefooter();
	
	function radar_select($q){
		global $sql;
		
		$users = $sql->query($q);
		for($txt="";$x=$sql->fetch($users);$txt){
			$txt .= "<option value='{$x['id']}'>".($x['displayname'] ? $x['displayname'] : $x['name'])." ({$x['posts']})</option>";
		}
		return $txt;
	}

?>