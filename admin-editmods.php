<?php
	
	require "lib/function.php";

	admincheck();

	pageheader("Local Mods");
	
	print adminlinkbar();

	$id = filter_int($_GET['id']);
	
	$valid = $sql->resultq("SELECT 1 FROM forums WHERE id = $id");
	if (!$valid){
		$id = $sql->resultq("SELECT MIN(id) FROM forums");
	}
	
	$forumname = $sql->resultq("SELECT name FROM forums WHERE id = $id");
	
	if (isset($_POST['go'])){
		
		checktoken();
		
		$sql->start();
		
		$remove = $sql->prepare("DELETE FROM forummods WHERE fid = $id AND uid = ?");
		$add 	= $sql->prepare("INSERT INTO forummods (fid, uid) VALUES ($id, ?)");
		
		
		if (isset($_POST['forummods']))
			if (is_array($_POST['forummods']))
				foreach ($_POST['forummods'] as $k)
					$c[] = $sql->execute($remove, array($k));

		for ($i=0;$i<3;$i++)
			if (isset($_POST["userlist$i"]))
				if (is_array($_POST["userlist$i"]))
					foreach ($_POST["userlist$i"] as $k)
						$c[] = $sql->execute($add, array($k));
			
		if (isset($c)) 	$message = ($sql->finish($c)) ? "Updated local mod list for <b>".htmlspecialchars($forumname)."</b>!" : "Couldn't update the mod list.";
		else			$message = "There was nothing to update.";
		
		//print messagebar("Message", $message);
		setmessage($message);
		header("Location: ?id=$id");
		x_die();

	}

	// Get existing forum mods
	
	
	$forummods = $sql->query("
		SELECT f.uid, u.name, u.displayname, u.powerlevel
		FROM forummods AS f
		LEFT JOIN users AS u ON f.uid = u.id
		WHERE f.fid = $id
		ORDER BY u.powerlevel DESC
	");
	
	$modlist 	= "";
	$powl 		= NULL;
	$duplicates = array(0);
	
	if ($forummods){
		while($mods = $sql->fetch($forummods)){
			// Instead of printing the powerlevel after the name, use different optgroups
			if ($powl != $mods['powerlevel']){
				$powl 		= $mods['powerlevel'];
				$modlist   .= "</optgroup><optgroup label='{$power_txt[$powl]}'>";
			}
			$modlist .= "
				<option value='{$mods['uid']}'>
					".htmlspecialchars($mods['displayname'] ? $mods['displayname'] : $mods['name'])."
				</option>";
			$duplicates[] = $mods['uid'];// used to hide these in the user list
		}
		$modlist .= "</optgroup>";
		unset($mods);
	}
	
	// Build user listboxes (multiple)		
	$userlist = array("", "", "");

	$userlistq = $sql->query("
		SELECT id, name, displayname, powerlevel
		FROM users
		WHERE powerlevel IN (0, 1, 2) AND id NOT IN (".implode(",", $duplicates).")
	");
	
	if ($userlistq){
		while ($user = $sql->fetch($userlistq)){
			$userlist[$user['powerlevel']] .= "
				<option value='{$user['id']}'>
					".htmlspecialchars($user['displayname'] ? $user['displayname'] : $user['name'])."
				</option>";
		}
		unset($user);
	}
	unset($userlistq);	
	
	
	// Forum jumping copied from the function, but with some changes (it shows forums in invalid category IDs)
	$forums = $sql->query("
		SELECT f.id, f.name, f.category, c.name catname
		FROM forums f
		LEFT JOIN categories c ON f.category = c.id
		ORDER BY c.ord , f.ord, f.id
	");
	$cat 		= NULL;
	$forumjump 	= "";
	while ($forum = $sql->fetch($forums)){
		// $cat holds the previous category id, and it's updated only when it changes
		if ($forum['category'] != $cat){
			$cat 		= $forum['category'];
			if (!$forum['catname']) $forum['catname'] = "[Invalid category ID #$cat]";
			$forumjump .= "</optgroup><optgroup label='{$forum['catname']}'>";
		}
		
		$forumjump .= "<option value={$forum['id']} ".($id == $forum['id'] ? "selected" : "").">{$forum['name']}</option>";
	}
	
	print $message;
	?>
	<center>
	<form method='POST' action='admin-editmods.php?id=<?php echo $id ?>'>
	<input type='hidden' name='auth' value='<?php echo $token ?>'>
	
	<b>Select a forum from the list:</b>
	<select name='id' onChange='parent.location="?id="+this.options[this.selectedIndex].value'>
		<?php echo $forumjump ?>
		</optgroup>
	</select>
	<noscript><input type='submit' value='Go' name='fjumpgo'></noscript>
	<br>
	<br>
	<table class='main'>
		<tr><td class='head c' colspan=2>Forum Mods - <?php echo $forumname ?></td></tr>
		
		<tr>
			<td class='dark c' colspan=2>
				Select the users to add/remove, then click on Save Settings.<br><small>Better description coming soon (?)</small>
			</td>
		</tr>
		
		<tr><td class='head c'>Remove users:</td><td class='head c' colspan=2>Add users:</td></tr>
		
		<tr>
			<td class='light' style='text-align: right;'>
				Current local mods:<br>
				<select size='10' style='min-width: 200px' name='forummods[]' multiple>
					<?php echo $modlist ?>
				</select>
			</td>
			<td class='dim'>
				<table>
					<tr>
						<td>
							User list (<?php echo $power_txt[0] ?>)</br>
							<select size='10' style='min-width: 200px' name='userlist0[]' multiple>
								<?php echo $userlist[0] ?>
							</select>
						</td><td>
							User list (<?php echo $power_txt[1] ?>)</br>
							<select size='10' style='min-width: 200px' name='userlist1[]' multiple>
								<?php echo $userlist[1] ?>
							</select>
						</td><td>
							User list (<?php echo $power_txt[2] ?>)</br>
							<select size='10' style='min-width: 200px' name='userlist2[]' multiple>
								<?php echo $userlist[2] ?>
							</select>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		
		<tr>
			<td class='dark' colspan=3>
				<input type='submit' value='Save Settings' name='go'>
			</td>
		</tr>
	</table>
	
	</form>
	</center>
	<?php

	pagefooter();

?>