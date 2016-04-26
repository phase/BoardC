<?php
	// based on admin-editmods.php
	require "lib/function.php";

	if (!powlcheck(4))
		errorpage("This is only for admins.");

	$id	   = filter_int($_GET['id']);
	$catid = filter_int($_GET['catid']);
	
	if ($id){
		
		if (!$sql->resultq("SELECT 1 FROM forums WHERE id=$id"))
			errorpage("Forum ID #$id doesn't exist.", false);
		
		if (isset($_GET['del'])){
			
			if (filter_string($_POST['confirm'])){
				
				if ($_POST['confirm'] == 'No')
					header("Location: admin-editforums.php?id=$id");
				else{
					$sql->query("DELETE FROM forums WHERE id=$id");
					errorpage("Forum deleted!", false);
				}	
			}
			
			$forumname = $sql->resultq("SELECT name FROM forums WHERE id=$id");	
			$output = "
			<center><form method='POST' action='admin-editforums.php?id=$id&del'><table class='main c'>
				<tr><td class='head'>Delete Forum</td></tr>
				
				<tr><td class='light'>Are you sure you want to delete '$forumname'?</td></tr>
				<tr><td class='dark'><input type='submit' name='confirm' value='Yes'> <input type='submit' name='confirm' value='No'></td></tr>
			
			</table></form></center>";
		}
		
		else if (isset($_GET['move'])){
			
			if (isset($_POST['domove'])){
				if (!$sql->resultq("SELECT 1 FROM forums WHERE id=".filter_int($_POST['forumjump2'])))
					errorpage("wrong forum", false);
				
				$sql->query("UPDATE threads SET forum=".filter_int($_POST['forumjump2'])." WHERE forum=$id");
				errorpage("Threads moved!", false);
			}
			$output = "
			<center><form method='POST' action='admin-editforums.php?id=$id&move'><table class='main c'>
				<tr><td class='head' colspan=2>Move threads</td></tr>
				
				<tr><td class='dim'>Move all the threads to:</td><td class='light'>".doforumjump($id, true)."</td></tr>
				<tr><td class='dark' colspan=2><input type='submit' name='domove' value='Move threads'></td></tr>
			
			</table></form></center>";
		}
			
		else if (isset($_POST['go'])){
			
			$forumname = $sql->resultq("SELECT name FROM forums WHERE id=$id");
			
			//errorpage("Under construction", false);			
			
			$sql->start();

			if (filter_int($_POST['newpower'])>4)
				$_POST['newpower'] = 4;
			
			if ($_POST['theme']=='-1') $theme = NULL;
			else $theme = filter_int($_POST['theme']);
			//print filter_int($_POST['newhide']);
			
			$update = $sql->prepare("UPDATE forums SET name=?, title=?, powerlevel=?, hidden=?, category=?, ord=?, theme=? WHERE id=$id");
			$c[] = $sql->execute($update, array(
			filter_string($_POST['newname']),
			filter_string($_POST['newtitle']),
			filter_int($_POST['newpower']),
			filter_int($_POST['newhide']),
			filter_int($_POST['newcat']),
			filter_int($_POST['neworder']),
			$theme
			));

			$message = ($sql->finish($c)) ? "'$forumname' forum updated!" : "Couldn't save the settings.";
			
			errorpage($message);
		}
		
		else{
		$forum = $sql->fetchq("
		SELECT f.name,f.title,f.powerlevel,f.hidden,f.category,f.ord,f.theme,c.id catid,c.name catname
		FROM forums f
		LEFT JOIN categories c
		ON category=c.id
		WHERE f.id=$id");

		$powl[$forum['powerlevel']] = "selected";
		$cat[$forum['category']] = "selected";
		
		$themes = findthemes();
		if (isset($forum['theme'])) $theme[$forum['theme']] = "selected";
		$input = "";
		foreach($themes as $i => $x)
			$input .= "<option value=".$x['id']." ".filter_string($theme[$x['id']]).">".$x['name']."</option>";
		$theme_txt = "<select name='theme'><option value='-1'>None</option>$input</select>";
		
		$catlist = "";
		$categories = $sql->query("SELECT id, name FROM categories");
		
		while($category = $sql->fetch($categories))
			$catlist .= "<option value=".$category['id']." ".filter_string($cat[$category['id']]).">".$category['name']."</option>";
			
		
		$style = "style='width: 400px'";
		
		$output = "<center><form method='POST' action='admin-editforums.php?id=$id'>
		<table class='main'>
			<tr><td class='head c' colspan=2>Edit Forum</td></tr>
			
			<tr>
				<td class='dark' colspan=2>
					Commands: <a href='admin-editforums.php?id=$id&del'>Delete Forum</a> - <a href='admin-editforums.php?id=$id&move'>Move Threads</a>					
				</td>
			</tr>
			
			<tr>
				<td class='light c'><b>Name:</b></td>
				<td class='dim'><input type='text' $style name='newname' value='".$forum['name']."'></td>
			</tr>
			<tr>
				<td class='light c'><b>Description:</b></td>
				<td class='dim'><input type='text' $style name='newtitle' value='".$forum['title']."'></td>
			</tr>
			<tr>
				<td class='light c'><b>Minimum powerlevel:</b></td>
				<td class='dim'>
					<select name='newpower'>
						<option value=0 ".filter_string($powl[0]).">Normal Users</option>
						<option value=1 ".filter_string($powl[1]).">Privileged</option>
						<option value=2 ".filter_string($powl[2]).">Local Moderators</option>
						<option value=3 ".filter_string($powl[3]).">Global Moderators</option>
						<option value=4 ".filter_string($powl[4]).">Administrators</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class='light c'><b>Category:</b></td>
				<td class='dim'>
					<select name='newcat'>
						$catlist
					</select>
				</td>
			</tr>
			<tr>
				<td class='light c'><b>Order:</b></td>
				<td class='dim'><input type='text' name='neworder' value='".$forum['ord']."'></td>
			</tr>
			<tr>
				<td class='light c'><b>Force theme:</b></td>
				<td class='dim'>$theme_txt</td>
			</tr>
			<tr>
				<td class='light'><b>Forum settings:</b></td>
				<td class='dim'><input type='checkbox' name='newhide' value=1 ".($forum['hidden'] ? "checked" : "").">Hidden</td>
			</tr>
			<tr>
				<td class='dark' colspan=2>
					<input type='submit' value='Save Settings' name='go'>
				</td>
			</tr>
		</table>
		</form></center>
		";
		}
		
	}

	else if (isset($_GET['newid'])){
		
		if (isset($_POST['go'])){
			
			$sql->start();

			if (filter_int($_POST['power'])>4)
				$_POST['power'] = 4;
			
			if ($_POST['theme']=='-1') $theme = NULL;
			else $theme = filter_int($_POST['theme']);

			$update = $sql->prepare("INSERT INTO forums (name,title,powerlevel,hidden,category,ord,theme) VALUES (?,?,?,?,?,?,?)");
			$c[] = $sql->execute($update, array(
			filter_string($_POST['name']),
			filter_string($_POST['title']),
			filter_int($_POST['power']),
			filter_int($_POST['hide']),
			filter_int($_POST['cat']),
			filter_int($_POST['order']),
			$theme
			));

			$message = ($sql->finish($c)) ? "Created the forum!" : "Couldn't create the forum.";
			
			errorpage($message);
		}
		
		$themes = findthemes();
		if (isset($forum['theme'])) $theme[$forum['theme']] = "selected";
		$input = "";
		foreach($themes as $i => $x)
			$input .= "<option value=".$x['id']." ".filter_string($theme[$x['id']]).">".$x['name']."</option>";
		$theme_txt = "<select name='theme'><option value=''>None</option>$input</select>";

		$catlist = "";
		$categories = $sql->query("SELECT id, name FROM categories");
		
		while($category = $sql->fetch($categories))
			$catlist .= "<option value=".$category['id'].">".$category['name']."</option>";
			
		$style = "style='width: 400px'";
		
		$output = "<center><form method='POST' action='admin-editforums.php?newid'>
		<table class='main'>
			<tr><td class='head c' colspan=2>New Forum</td></tr>
			
			<tr>
				<td class='light c'><b>Name:</b></td>
				<td class='dim'><input type='text' $style name='name'></td>
			</tr>
			<tr>
				<td class='light c'><b>Description:</b></td>
				<td class='dim'><input type='text' $style name='title'></td>
			</tr>
			<tr>
				<td class='light c'><b>Minimum powerlevel:</b></td>
				<td class='dim'>
					<select name='power'>
						<option value=0>Normal Users</option>
						<option value=1>Privileged</option>
						<option value=2>Local Moderators</option>
						<option value=3>Global Moderators</option>
						<option value=4>Administrators</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class='light c'><b>Category:</b></td>
				<td class='dim'>
					<select name='cat'>
						$catlist
					</select>
				</td>
			</tr>
			<tr>
				<td class='light c'><b>Order:</b></td>
				<td class='dim'><input type='text' name='order'></td>
			</tr>
			<tr>
				<td class='light c'><b>Force theme:</b></td>
				<td class='dim'>$theme_txt</td>
			</tr>
			<tr>
				<td class='light c'><b>Forum settings:</b></td>
				<td class='dim'><input type='checkbox' name='hide' value=1>Hidden</td>
			</tr>
			<tr>
				<td class='dark' colspan=2>
					<input type='submit' value='Save Settings' name='go'>
				</td>
			</tr>
		</table>
		</form></center>";		
		
	}
	
	else if ($catid){
		if (!$sql->resultq("SELECT 1 FROM categories WHERE id=$catid"))
			errorpage("Category ID #$catid doesn't exist.", false);
		
		if (isset($_GET['del'])){
			
			if (filter_string($_POST['confirm'])){
				
				if ($_POST['confirm'] == 'No')
					header("Location: admin-editforums.php?catid=$catid");
				else{
					$sql->query("DELETE FROM categories WHERE id=$catid");
					errorpage("Category deleted!", false);
				}	
			}
			
			$catname = $sql->resultq("SELECT name FROM categories WHERE id=$catid");	
			$output = "
			<center><form method='POST' action='admin-editforums.php?catid=$catid&del'><table class='main c'>
				<tr><td class='head'>Delete Category</td></tr>
				
				<tr><td class='light'>Are you sure you want to delete '$catname'?</td></tr>
				<tr><td class='dark'><input type='submit' name='confirm' value='Yes'> <input type='submit' name='confirm' value='No'></td></tr>
			
			</table></form></center>";
		}
			
		else if (isset($_POST['go'])){
		
			$catname = $sql->resultq("SELECT name FROM categories WHERE id=$catid");
			
			$sql->start();

			if (filter_int($_POST['newpower'])>4)
				$_POST['newpower'] = 4;

			$update = $sql->prepare("UPDATE categories SET name=?, powerlevel=?, ord=? WHERE id=$catid");
			$c[] = $sql->execute($update, array(
			filter_string($_POST['newname']),
			filter_int($_POST['newpower']),
			filter_int($_POST['neworder'])
			));

			$message = ($sql->finish($c)) ? "'$catname' category updated!" : "Couldn't save the settings.";
			
			errorpage($message, false);
		}
		
		else{

			$category = $sql->fetchq("SELECT name, ord, powerlevel FROM categories WHERE id=$catid");
			$powl[$category['powerlevel']] = "selected";
			
			$style = "style='width: 400px'";
			
			$output = "<center><form method='POST' action='admin-editforums.php?catid=$catid'>
			<table class='main'>
				<tr><td class='head c' colspan=2>Edit Category</td></tr>
				
				<tr>
					<td class='dark' colspan=2>
						Commands: <a href='admin-editforums.php?catid=$catid&del'>Delete Category</a>					
					</td>
				</tr>
				
				<tr>
					<td class='light c'><b>Name:</b></td>
					<td class='dim'><input type='text' $style name='newname' value='".$category['name']."'></td>
				</tr>
				<tr>
					<td class='light c'><b>Minimum powerlevel:</b></td>
					<td class='dim'>
						<select name='newpower'>
							<option value=0 ".filter_string($powl[0]).">Normal Users</option>
							<option value=1 ".filter_string($powl[1]).">Privileged</option>
							<option value=2 ".filter_string($powl[2]).">Local Moderators</option>
							<option value=3 ".filter_string($powl[3]).">Global Moderators</option>
							<option value=4 ".filter_string($powl[4]).">Administrators</option>
						</select>
					</td>
				</tr>
				<tr>
					<td class='light c'><b>Order:</b></td>
					<td class='dim'><input type='text' name='neworder' value='".$category['ord']."'></td>
				</tr>
				<tr>
					<td class='dark' colspan=2>
						<input type='submit' value='Save Settings' name='go'>
					</td>
				</tr>
			</table>
			</form></center>";
		}
	}
	else if (isset($_GET['newcat'])){
		
		if (isset($_POST['go'])){
			
			$sql->start();

			if (filter_int($_POST['power'])>4)
				$_POST['power'] = 4;

			$update = $sql->prepare("INSERT INTO categories (name,powerlevel,ord) VALUES (?,?,?)");
			$c[] = $sql->execute($update, array(
			filter_string($_POST['name']),
			filter_int($_POST['power']),
			filter_int($_POST['order'])
			));

			$message = ($sql->finish($c)) ? "Created the category!" : "Couldn't create the category.";
			
			errorpage($message, false);
		}
		
		

		$style = "style='width: 400px'";
		
		$output = "<center><form method='POST' action='admin-editforums.php?newcat'>
		<table class='main'>
			<tr><td class='head c' colspan=2>New Category</td></tr>
			
			<tr>
				<td class='light c'><b>Name:</b></td>
				<td class='dim'><input type='text' $style name='name'></td>
			</tr>
			<tr>
				<td class='light c'><b>Minimum powerlevel:</b></td>
				<td class='dim'>
					<select name='power'>
						<option value=0>Normal Users</option>
						<option value=1>Privileged</option>
						<option value=2>Local Moderators</option>
						<option value=3>Global Moderators</option>
						<option value=4>Administrators</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class='light c'><b>Order:</b></td>
				<td class='dim'><input type='text' name='order'></td>
			</tr>
			<tr>
				<td class='dark' colspan=2>
					<input type='submit' value='Save Settings' name='go'>
				</td>
			</tr>
		</table>
		</form></center>";		
		
	}
	else{
		
		$screen = "";
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
		
		while($category = $sql->fetch($categories)){
			$cattxt[$category['id']] = $category['name'];
			$catlist[] = "<td class='dark'><a href='admin-editforums.php?catid=".$category['id']."'>".$category['name']."</a></td>";
		}
		
		while($forum = $sql->fetch($forums)){
			if ($cat != $forum['category']){
				$cat = $forum['category'];
				$forumlist[] = "<td class='dark'><b>".(isset($cattxt[$cat]) ? $cattxt[$cat] : "Invalid Category [ID #".$forum['category']."]")."</b></td>";
			}
			$forumlist[] = "<td class='light'><a href='admin-editforums.php?id=".$forum['id']."'>".$forum['name']."</a></td>";
		}
		// account for unused categories at the end
		//for ($cat; isset($cattxt[$cat]); $cat++)
		//	$forumlist[] = "<td class='dim c' style='border-bottom: 1px solid #000'><b>".$cattxt[$cat]."</b></td>";
		
		$cnt = max(count($forumlist), count($catlist));
		$forumlist = array_pad($forumlist, $cnt, "<td class='dim'>&nbsp</td>");
		$catlist = array_pad($catlist, $cnt, "<td class='dim'>&nbsp</td>");
		
		for($i=0; $i<$cnt; $i++)
			$screen .= "<tr>".$forumlist[$i].$catlist[$i]."</tr>";
		
		$output = "<table class='main w c'>
		<tr><td class='head' colspan=2>Forum Editor</td></tr>
		
		<tr><td class='light' colspan=2>You can add/edit/remove forums and categories.</td></tr>

		<tr><td class='head'>Select a forum:</td><td class='head'>Select a category:</td></tr>
		
		$screen
		<tr><td class='head'><a href='admin-editforums.php?newid'>Add a new forum</a></td><td class='head'><a href='admin-editforums.php?newcat'>Add a new category</a></td>
		</table>";
		
	}
	
	pageheader("Forum Editor");
	
	print adminlinkbar()."<br/>$output";
	pagefooter();

?>