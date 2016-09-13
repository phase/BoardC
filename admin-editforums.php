<?php

	require "lib/function.php";
	
	admincheck();
	
	/*
		New edit forums / categories page
		the old one sucked, the end
		(now with HTML from Jul)
	*/
	
	$txt = "";
	
	if (isset($_GET['preview'])){
		$querypowl 		= intval($_GET['preview']);
		$prevtext		= "&preview=$querypowl";
	} else {
		$querypowl		= 4;
		$prevtext		= "";
	}

	$newtext = isset($_GET['new']) ? "&new" : "";

	// Edit forum
	if (isset($_GET['f'])){
		
		if (isset($_POST['edit']) || isset($_POST['edit2'])){
			
			checktoken();
			
			// No unclickable blank names allowed
			if (!filter_string($_POST['name'])){
				adminerror("The forum name can't be blank.");
			}
			
			if (filter_int($_POST['minpower']) > 4){
				$_POST['minpower'] = 4;
			}
			
			
			if (!$_POST['theme']) 	$theme = 'NULL';
			else 					$theme = filter_int($_POST['theme']);
			
			
			$sql->start();
			
			if (isset($_GET['new'])){
				// New forum?
				$update = $sql->prepare("
					INSERT INTO forums (name, title, minpower, minpowerreply, minpowerthread, hidden, category, ord, theme, threads, posts, pollstyle)
					VALUES (
						?,
						?,
						".filter_int($_POST['minpower']).",
						".filter_int($_POST['minpowerreply']).",
						".filter_int($_POST['minpowerthread']).",
						".filter_int($_POST['hidden']).",
						".filter_int($_POST['cat']).",
						".filter_int($_POST['ord']).",
						$theme,
						".filter_int($_POST['threads']).",
						".filter_int($_POST['posts']).",
						".filter_int($_POST['pollstyle'])."
					)
				");
				
				$id 	= $sql->lastInsertId();//$sql->resultq("SELECT LAST_INSERT_ID()");
				$msg 	= "Created new forum {$_POST['name']} with ID #$id";
				
			} else {
				// Updating an existing one?
				
				// Check if the forum id does exist before attempting this
				$id 	= filter_int($_GET['f']);
				$valid 	= $sql->resultq("SELECT 1 FROM forums WHERE id = $id");
				if (!$valid){
					adminerror("The forum you tried to edit [ID #$id] does not exist.");
				}
				
				$update = $sql->prepare("
					UPDATE forums SET
					
					name 	 		= ?,
					title 	 		= ?,
					minpower 		= ".filter_int($_POST['minpower']).",
					minpowerreply 	= ".filter_int($_POST['minpowerreply']).",
					minpowerthread 	= ".filter_int($_POST['minpowerthread']).",
					hidden	 		= ".filter_int($_POST['hidden']).",
					category 		= ".filter_int($_POST['cat']).",
					ord		 		= ".filter_int($_POST['ord']).",
					theme	 		= $theme,
					threads	 		= ".filter_int($_POST['threads']).",
					posts	 		= ".filter_int($_POST['posts']).",
					pollstyle		= ".filter_int($_POST['pollstyle'])."
					
					WHERE id = $id
				");
				
				$msg = "Edited forum ID #$id";
			}
				

			$c[] = $sql->execute($update, [
				filter_string($_POST['name'], true),
				filter_string($_POST['title'], true),
			]);
			
			
			if ($sql->finish($c)){
				trigger_error($msg, E_USER_NOTICE);
				// Save and close
				if (isset($_POST['edit2'])){
					header("Location: ?$prevtext");
					x_die();
				}
			} else {
				adminerror("Couldn't save the settings.");
			}

		}
		if (!isset($_GET['new'])){
			
			$id = filter_int($_GET['f']);
			

			$forum = $sql->fetchq("
				SELECT 	f.name, f.title, f.minpower, f.minpowerreply, f.minpowerthread,
						f.hidden, f.category, f.ord, f.theme, f.threads, f.posts,
						f.category catchk, f.pollstyle,
						c.id catid, c.name catname
				FROM forums f
				LEFT JOIN categories c ON f.category = c.id
				WHERE f.id = $id
			");
			
			// Delete forum
			if (isset($_GET['del'])){
				
				if (isset($_POST['cancel'])){
					header("Location: ?$prevtext");
					x_die();
				}
				elseif (isset($_POST['reallydelete'])){
					
					checktoken();
					// Sanity check
					$dest	= filter_int($_POST['forumjump2']);
					$valid 	= $sql->resultq("SELECT 1 FROM forums WHERE id = $dest");
					if (!$valid){
						adminerror("The selected forum doesn't exist!");
					}
					
					$sql->start();
					
					$cnt = $sql->fetchq("SELECT posts, threads FROM forums WHERE id = $id");
					// Move all threads and then update the correct last post count for the original forum
					$c[] = $sql->query("UPDATE threads SET forum = $dest WHERE forum = $id");
					$c[] = $sql->query("UPDATE announcements SET forum = $dest WHERE forum = $id");
					//$c[] = $sql->query("UPDATE forums  SET lastpostid = NULL, lastposttime = NULL, lastpostuser = NULL, threads = 0, posts = 0, WHERE id = $id");
					
					// ... and then for the new forum
					$sql->query("UPDATE forums SET posts = posts + {$cnt['posts']}, threads = threads + {$cnt['threads']} WHERE id = $dest");
					// Correct last posts info
					update_last_post($dest, false, true);
					
					// Delete the leftovers
					$c[] = $sql->query("DELETE FROM forums WHERE id = $id");
					$c[] = $sql->query("DELETE FROM forummods WHERE fid = $id");
					
					if ($sql->finish($c)){
						trigger_error("DELETED forum ID #$id; merged into forum ID #$dest", E_USER_NOTICE);
						adminerror("Forum ID #$id deleted!");
					} else {
						adminerror("Couldn't delete forum ID #$id.");
					}
				}
				
				
				//$fname = $sql->resultq("SELECT name FROM forums WHERE id = $id");
				
				pageheader("WARNING");
				print "<br>
				<center>
				<form method='POST' action='?f=$id$prevtext&del'>
				<input type='hidden' name='auth' value='$token'>
				
				<table class='main c'>
					<tr>
						<td class='head'>
							Deleting <b>".htmlspecialchars($forum['name'])."</b>
						</td>
					</tr>
					
					<tr>
						<td class='light'>
							You are about to delete forum ID <b>$id</b>.<br><br>
							All announcements and threads will be moved to the forum below.<br>
							".doforumjump($id, true)."
						</td>
					</tr>
					<tr>
						<td class='dark' colspan=2>
							<input type='submit' name='reallydelete' value='DELETE FORUM'> or <input type='submit' name='cancel' value='Cancel'>
						</td>
					</tr>
				</table>
				</form>
				</center>";
				pagefooter();
			}
			
		}
		else{
			// hurf
			$forum = array(
				'name' 				=> "",
				'minpower'			=> "",
				'title'				=> "",
				'minpower'			=> 0,
				'minpowerthread'	=> 0,
				'minpowerreply'		=> 0,
				'hidden'			=> 0,
				'category'			=> 0,
				'ord'				=> 0,
				'theme'				=> 0,
				'threads'			=> 0,
				'posts'				=> 0,
				'catid'				=> 0,
				'catname'			=> "",
				'pollstyle'			=> 0,
			);
			
			$id = 0;
		}
		
		$categories = $sql->query("SELECT id, name FROM categories");

		$pollstyles = array(
			['id' => 0, 'name' => "Normal"],
			['id' => 1, 'name' => "Disallowed"],
		);
		
		$txt .= "
		<form method='POST' action='?f=$id$newtext$prevtext'>
		<input type='hidden' name='auth' value='$token'>
		<table class='main'>
		<tr>
			<td class='head c' colspan=6>Editing <b>". (isset($_GET['new']) ? "a new forum" : htmlspecialchars($forum['name'])) . "</b></td>
		</tr>

		<tr>
			<td class='head'>Forum Name</td>
			<td class='light' colspan=4><input type='text' name='name' value=\"". htmlspecialchars($forum['name']) ."\"  style='width: 100%;' maxlength='250'></td>
			<td class='light' width=10%><input type='checkbox' name='hidden' value='1'". ($forum['hidden'] ? " checked" : "") ."> <label for='hidden'>Hidden</label></td>
		</tr>

		<tr>
			<td class='head' rowspan=4>Description</td>
			<td class='light' rowspan=4 colspan=3><textarea name='title' ROWS=4 style='width: 100%; resize:none;'>". htmlspecialchars($forum['title']) ."</textarea></td>
			<td class='head' colspan=2>Minimum power needed...</td>
		</tr>

		<tr>
			<td class='head'>...to view the forum</td>
			<td class='light'>". powerList($forum['minpower'], "minpower") . "</td>
		</tr>

		<tr>
			<td class='head'>...to post a thread</td>
			<td class='light'>". powerList($forum['minpowerthread'], "minpowerthread") . "</td>
		</tr>

		<tr>
			<td class='head'>...to reply</td>
			<td class='light'>". powerList($forum['minpowerreply'], "minpowerreply") . "</td>
		</tr>

		<tr>
			<td class='head'  width='10%'>Number of Threads</td>
			<td class='light' width='24%'><input type='text' name='threads' maxlength='8' size='10' value='{$forum['threads']}' class='r'></td>
			<td class='head'  width='10%'>Forum order</td>
			<td class='light' width='23%'><input type='text' name='ord' maxlength='8' size='10' value='{$forum['ord']}' class='r'></td>
			<td class='head ' width='10%'>Poll Style</td>
			<td class='light' width='23%'>". dropdownList($pollstyles, $forum['pollstyle'], "pollstyle") ."</td>
		</tr>

		<tr>
			<td class='head' >Number of Posts</td>
			<td class='light'><input type='text' name='posts' maxlength='8' size='10' value='{$forum['posts']}' class='r'></td>
			<td class='head' >Special Scheme</td>
			<td class='light'>". dothemelist("theme", true, intval($forum['theme'])) ."</td>
			<td class='head' >Category</td>
			<td class='light'>". dropdownList($categories, $forum['catid'], "cat") ."</td>
		</tr>

		<tr>
			<td class='dark' colspan=6><input type='submit' name='edit' value='Save and continue'>&nbsp;<input type='submit' name='edit2' value='Save and close'></td>
		</tr>

		</table></form><br>
		";
		
	}
	else if (isset($_GET['c'])){
		
		// Edit category		
		if (isset($_POST['edit']) || isset($_POST['edit2'])){
			
			checktoken();
			
			// No unclickable blank names allowed
			if (!filter_string($_POST['name'])){
				adminerror("The category name can't be blank.");
			}
			
			if (filter_int($_POST['minpower']) > 4){
				$_POST['minpower'] = 4;
			}
			
			$sql->start();
			
			if (isset($_GET['new'])){
				// New category?
				$update = $sql->prepare("
					INSERT INTO categories (name, minpower, ord)
					VALUES (
						?,
						".filter_int($_POST['minpower']).",
						".filter_int($_POST['ord'])."
					)
				");
				
				$id 	= $sql->lastInsertId();//$sql->resultq("SELECT LAST_INSERT_ID()");
				$msg 	= "Created new category {$_POST['name']} with ID #$id";
				
			} else {
				// Updating an existing one?
				
				// Check if the category id does exist before attempting this
				$id 	= filter_int($_GET['c']);
				$valid 	= $sql->resultq("SELECT 1 FROM categories WHERE id = $id");
				if (!$valid){
					adminerror("The category you tried to edit [ID #$id] does not exist.");
				}
				
				$update = $sql->prepare("
					UPDATE categories SET
					
					name 	 		= ?,
					minpower 		= ".filter_int($_POST['minpower']).",
					ord		 		= ".filter_int($_POST['ord'])."
					WHERE id = $id
				");
				
				$msg = "Edited category ID #$id";
			}
				

			$c[] = $sql->execute($update, [filter_string($_POST['name'], true)] );
			
			
			if ($sql->finish($c)){
				trigger_error($msg, E_USER_NOTICE);
				// Save and close
				if (isset($_POST['edit2'])){
					header("Location: ?$prevtext");
					x_die();
				}
			} else {
				adminerror("Couldn't save the settings.");
			}

		}
		
		if (!isset($_GET['new'])){
			
			$id = intval($_GET['c']);
			
			$cat = $sql->fetchq("
				SELECT name, minpower, ord
				FROM categories
				WHERE id = $id
			");
			
			// Delete category
			if (isset($_GET['del'])){
				
				if (isset($_POST['cancel'])){
					header("Location: ?$prevtext");
					x_die();
				}
				elseif (isset($_POST['reallydelete'])){
					
					checktoken();
					// Sanity check
					$dest	= filter_int($_POST['dest']);
					$valid 	= $sql->resultq("SELECT 1 FROM categories WHERE id = $dest");
					if (!$valid){
						adminerror("The selected category doesn't exist!");
					}
					
					$sql->start();
					$c[] = $sql->query("UPDATE forums SET category = $dest WHERE category = $id");
					$c[] = $sql->query("DELETE FROM categories WHERE id = $id");

					if ($sql->finish($c)){
						trigger_error("DELETED category ID #$id; forums merged into category ID #$dest", E_USER_NOTICE);
						adminerror("Category ID #$id deleted!");
					} else {
						adminerror("Couldn't delete category ID #$id");
					}

				}
				
				
				$categories = $sql->query("SELECT id, name FROM categories");
				
				pageheader("WARNING");
				print "<br>
				<center>
				<form method='POST' action='?c=$id$prevtext&del'>
				<input type='hidden' name='auth' value='$token'>
				
				<table class='main c'>
					<tr>
						<td class='head'>
							Deleting <b>".htmlspecialchars($cat['name'])."</b>
						</td>
					</tr>
					
					<tr>
						<td class='light'>
							You are about to delete category ID <b>$id</b>.<br><br>
							All forums will be moved to the category below.<br>
							". dropdownList($categories, $id, 'dest') ."
						</td>
					</tr>
					<tr>
						<td class='dark' colspan=2>
							<input type='submit' name='reallydelete' value='DELETE FORUM'> or <input type='submit' name='cancel' value='Cancel'>
						</td>
					</tr>
				</table>
				</form>
				</center>";
				pagefooter();
			}
			
		}
		else {
			$id = 0;
			$cat = array(
				'name' 		=> '',
				'minpower' 	=> 0,
				'ord'		=> 0
			);
		}
		
		$txt = "<center>
			<form method='POST' action='?c=$id$newtext$prevtext'>
			<input type='hidden' name='auth' value='$token'>
			
			<table class='main'>
				<tr><td class='head c' colspan=4>Editing <b>".(isset($_GET['new']) ? "a new category" : $cat['name'])."</b></td></tr>
				
				<tr>
					<td class='head c nobr'style='width: 30%'><b>Category name:</b></td>
					<td class='dim' colspan=3><input type='text' maxlength='64' style='width: 500px' name='name' value=\"".htmlspecialchars($cat['name'])."\"></td>
				</tr>
				
				<tr>
					<td class='head c'><b>Powerlevel required to view:</b></td>
					<td class='dim'>".powerList($cat['minpower'], 'minpower')."</td>
					<td class='head c nobr'><b>Category order:</b></td>
					<td class='dim'><input type='text' maxlength='8' size='10' name='ord' value='{$cat['ord']}'></td>
				</tr>
				
				<tr>
					<td class='dark' colspan=4>
						<input type='submit' name='edit' value='Save and continue'>&nbsp;<input type='submit' name='edit2' value='Save and close'>
					</td>
				</tr>
			</table>
			</form></center>";

		
	}
	else{
		$txt .= previewbox($querypowl)."<br>";
		
		/*
			Good news everybody.
			As categories and forums are fetched separately here
			it does show categories with no forums assigned to it
			(this may or may not be put in index.php)
		*/
		
		$categories = $sql->query("
			SELECT id, name
			FROM categories
			WHERE (minpower <= $querypowl OR ISNULL(minpower))
			ORDER BY ord ASC 
		");
		
		$forums = $sql->query("
			SELECT 	f.id fid, f.name fname, f.title, f.hidden, f.threads, f.posts, f.category, c.id validcat,
					f.lastpostid, f.lastpostuser, f.lastposttime, $userfields
			FROM forums f
			
			LEFT JOIN users        u ON f.lastpostuser = u.id
			LEFT JOIN categories   c ON f.category     = c.id

			WHERE f.minpower <= $querypowl
			GROUP BY f.id ASC
			ORDER BY f.category, f.ord, f.id
		");
		
		if (!$forums){
			$txt .= "<table class='main w c'><tr><td class='dark'>(There are no forums here)</td></tr></table>";
		}
		else{
			
			$forummods = $sql->fetchq("SELECT f.fid, $userfields FROM forummods f LEFT JOIN users u ON f.uid = u.id", true, PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
			
			$txt .= "
			<table class='main w nb'>
				<tr><td class='dark' colspan='5'>Commands: <a href='?f&new'>Create a new forum</a> - <a href='?c&new'>Create a new category</a></td></tr>
				<tr>
					<td class='head'>&nbsp;</td>
					<td class='head w c'>Forum</td>
					<td class='head c'>Threads</td>
					<td class='head c'>Posts</td>
					<td class='head c nobr'>Last post</td>
				</tr>";

			$forum = $sql->fetch($forums);
			while ($cat = $sql->fetch($categories)){
				
				$txt .= "
				<tr>
					<td class='dark c fonts nobr'><a href='?c={$cat['id']}$prevtext'>Edit</a> / <a href='?c={$cat['id']}$prevtext&del'>Delete</a>
					<td class='dark c' colspan=4><a href='index.php?cat={$cat['id']}$prevtext'>{$cat['name']}</a></td>
				</tr>";
				
				for(;$forum ;$forum = $sql->fetch($forums)){
					
					// Skip over invalid categories before checking the category id
					if(!$forum['validcat']){
						$invalid[] = $forum;
						continue;
					}
					
					if ($forum['category'] != $cat['id']){
						break;
					}
					
					
					if ($forum['lastpostid']){
						$lastpost = "
							<nobr>".printdate($forum['lastposttime'])."</nobr><br>
							<small>
								<nobr> 
									by ".makeuserlink(false, $forum)." 
									<a href='thread.php?pid={$forum['lastpostid']}#{$forum['lastpostid']}'>
										<img src='{$IMG['statusfolder']}/getlast.png'>
									</a>
								</nobr>
							</small>";
					} else {
						$lastpost = "Nothing";
					}
					
					for ($i = 0; isset($forummods[$forum['fid']][$i]); $i++){
						$mods[] = makeuserlink(false, $forummods[$forum['fid']][$i]);
						unset($forummods[$forum['fid']][$i]);
					}
			
					$txt .= "
					<tr>
						<td class='light c fonts nobr'>
							<a href='?f={$forum['fid']}$prevtext'>Edit</a> / <a href='?f={$forum['fid']}$prevtext&del'>Delete</a>
						</td>
						<td class='dim'>
							<a href='forum.php?id={$forum['fid']}'>
								".($forum['hidden'] ? "({$forum['fname']})" : $forum['fname'])."
							</a>
							<small>
								".($forum['title'] ? "<br>".$forum['title'] : "")."<br>
								".(isset($mods) ? "(Moderated by: ".implode(", ", $mods).")" : "")."
							</small>
						</td>
						<td class='light c'>".$forum['threads']."</td>
						<td class='light c'>".$forum['posts']."</td>
						<td class='dim c'>$lastpost</td>
					</tr>";
					
					unset($mods);
				}	
			}
			// Leftover forums with invalid category ids
			if (isset($invalid)){
				$cat = NULL;
				foreach($invalid as $forum){
					
					// Print out the new category id when it's different
					if ($cat != $forum['category']){
						$cat  = $forum['category'];
						$txt .= "<tr><td class='dark danger c' style='background: #fff' colspan=5><b>Invalid category [ID #$cat]</b></td></tr>";
					}
					
					if ($forum['lastpostid']){
						$lastpost = "
							<nobr>".printdate($forum['lastposttime'])."</nobr><br>
							<small>
								<nobr> 
									by ".makeuserlink(false, $forum)." 
									<a href='thread.php?pid={$forum['lastpostid']}#{$forum['lastpostid']}'>
										<img src='{$IMG['statusfolder']}/getlast.png'>
									</a>
								</nobr>
							</small>";
					} else {
						$lastpost = "Nothing";
					}
					
					for ($i = 0; isset($forummods[$forum['fid']][$i]); $i++){
						$mods[] = makeuserlink(false, $forummods[$forum['fid']][$i]);
						unset($forummods[$forum['fid']][$i]);
					}
			
					$txt .= "
					<tr>
						<td class='light c fonts nobr'>
							<a href='?f={$forum['fid']}$prevtext'>Edit</a> / <a href='?f={$forum['fid']}$prevtext&del'>Delete</a>
						</td>
						<td class='dim'>
							<a href='forum.php?id={$forum['fid']}'>
								".($forum['hidden'] ? "({$forum['fname']})" : $forum['fname'])."
							</a>
							<small>
								".($forum['title'] ? "<br>".$forum['title'] : "")."<br>
								".(isset($mods) ? "(Moderated by: ".implode(", ", $mods).")" : "")."
							</small>
						</td>
						<td class='light c'>{$forum['threads']}</td>
						<td class='light c'>{$forum['posts']}</td>
						<td class='dim c'>$lastpost</td>
					</tr>";
					
					unset($mods);
					
				}
			}
			
			$txt .= "</table>";
		}
	}
	
	pageheader("Edit forums");
	print adminlinkbar().$txt;
	pagefooter();
	
	function previewbox($powl){
		global $power_txt;
		$txt = "";

		for ($i = 0; $i < 5; $i++)
			$txt .= "<option value='$i' ".($powl == $i ? "selected" : "").">{$power_txt[$i]}</option>";
		
		return "
			<form method='POST' action='?'>
				<center>
					<b>Preview forums with powerlevel:</b>
					<select onChange='parent.location=\"?preview=\"+this.options[this.selectedIndex].value' name='preview'>
						$txt
					</select>
				</center>
			</form>";
	}
	
	function adminerror($msg){
		pageheader("Edit forums");
		print adminlinkbar();
		errorpage("$msg", false);
	}
?>