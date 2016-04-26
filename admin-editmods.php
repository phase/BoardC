<?php

	require "lib/function.php";

	if (!powlcheck(4))
		errorpage("<a href='index.php?sec=1'>Make me a local mod!</a>");

	pageheader("Local Mods");
	
	print adminlinkbar();

	$id = filter_int($_GET['id']);
	
	if ($id){
		if (!$sql->resultq("SELECT 1 FROM forums WHERE id=$id"))
			errorpage("Forum ID #$id doesn't exist.");
		
		$forumname = $sql->resultq("SELECT name FROM forums WHERE id=$id");
		
		if (isset($_POST['go'])){
			
			//errorpage("Under construction", false);
			
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
				
			if (!isset($c)) errorpage("Nothing to update.", false);
			
			$message = ($sql->finish($c)) ? "'$forumname' mods updated!" : "Couldn't update the mod list.";
			
			errorpage($message, false);
			errorpage("",false);

		}

		// Build user listboxes (multiple)		
		$userlist = array("", "", "");
	
		$userlistq = $sql->query("SELECT id, name, powerlevel FROM users WHERE powerlevel IN (0, 1, 2)");
		
		if ($userlistq){
			while ($user = $sql->fetch($userlistq))
				$userlist[$user['powerlevel']] .= "<option value='".$user['id']."'>".$user['name']."</option>\n";
			
			unset($user);
		}
		unset($userlistq);	
		
		
		// Get Forum Mods
		$modlist = "";
		
		$forummods = $sql->query("
		SELECT f.uid user, u.name username, u.powerlevel powl
		FROM forummods AS f
		LEFT JOIN users AS u
		ON f.uid = u.id
		WHERE f.fid=$id
		ORDER BY f.fid
		");
		
		if ($forummods){
			while($mods = $sql->fetch($forummods))
				$modlist .= "<option value='".$mods['user']."'>".$mods['username']." [Powl: ".$mods['powl']."]</option>\n";

			unset($mods);
		}
		else $mods_cnt = false;
		unset($forummods);
		
		print "<br/><center><form method='POST' action='admin-editmods.php?id=$id'>
		<table class='main'>
			<tr><td class='head c' colspan=2>Forum Mods - $forumname</td></tr>
			
			<tr>
				<td class='dark c' colspan=2>
					Select the users to add/remove, then click on Save Settings.<br/><small>Better description coming soon (?)</small>
				</td>
			</tr>
			
			<tr><td class='head c'>Remove users:</td><td class='head c' colspan=2>Add users:</td></tr>
			
			<tr>
				<td class='light' style='text-align: right;'>
					Current local mods:<br/>
					<select size='10' style='min-width: 200px' name='forummods[]' multiple>
						$modlist
					</select>
				</td>
				<td class='dim'>
					<table><tr><td>
						User list (Normal)</br>
						<select size='10' style='min-width: 200px' name='userlist0[]' multiple>
							".$userlist[0]."
						</select>
					</td><td>
						User list (Privileged)</br>
						<select size='10' style='min-width: 200px' name='userlist1[]' multiple>
							".$userlist[1]."
						</select>
					</td><td>
						User list (Staff)</br>
						<select size='10' style='min-width: 200px' name='userlist2[]' multiple>
							".$userlist[2]."
						</select>
					</td></tr></table>
				</td>
			</tr>
			
			<tr>
				<td class='dark' colspan=3>
					<input type='submit' value='Save Settings' name='go'>
				</td>
			</tr>
		</table>
		</form></center>
		";

		
	}
	else{

		$forumlist = "";
		$cat = 0;
		$cattxt = array();
		
		$forums = $sql->query("
		SELECT f.id id, f.name name, title, hidden, threads, posts, category
		FROM forums AS f
		LEFT JOIN categories AS c
		ON category = c.id
		ORDER BY c.ord , f.ord, f.id		
		");
		
		$categories = $sql->query("SELECT id, name, ord, powerlevel FROM categories ORDER BY ord");
		
		while($category = $sql->fetch($categories))
			$cattxt[$category['id']] = $category['name'];
		
		while($forum = $sql->fetch($forums)){
			if ($cat != $forum['category']){
				$cat = $forum['category'];
				$forumlist .= "<b>".(isset($cattxt[$cat]) ? $cattxt[$cat] : "Invalid Category [ID #".$forum['category']."]")."</b><br/>";
				//$cat++;
			}
			$forumlist .= "<a href='admin-editmods.php?id=".$forum['id']."'>".$forum['name']."</a><br/>";
		}
		
		print "<br/><table class='main w'>
		<tr><td class='head c'>Forum Mods</td></tr>
		
		<tr><td class='dim c'>Select a forum from the list to edit the local moderators.</td></tr>

		<tr><td class='dark'></td></tr>
		
		<tr><td class='light c'>$forumlist</tr>
		</table>";
		
	}
	
	pagefooter();

?>