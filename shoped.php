<?php

	require "lib/function.php";
	
	/*
	this page is confusing but it works
	
	also for whatever reason I made it so you can create your own item categories as long as you are an admin
	in retrospect, it just made this page more confusing
	that, and deleting a whole category is the equivalent of erasing a thread (a bad thing)
	
	for consistency with Jul, Normal+ users can access the shop editor
	but only to create/delete/edit items
	*/
	
	if (!powlcheck(1))
		errorpage("You're not a privileged user!");
	

	$isadmin = powlcheck(4);	
	
	$txt = "";
	
	// Lazy function and shoped.php specific formatting
	function q(&$x){return htmlspecialchars(input_filters($x));}
	function i(&$x){
		if (!in_array($x[0],array('+','-','x','/'))) $x = "+$x";
		$res = $x[0].((float)(trim($x, '+-x/ '))); // not filter_int to prevent E_STRICT
		if ($res != "/0") return $res;
		else errorpage("Division by zero in one of the items.");
	}
	

	/*
	id, name, title, cat, ord, hp, mp, atk, def, intl, mdf, dex, lck, spd, coins, gcoins, special
	
	stored as varchar
	first character text, describes operator (+,-,x,/)
	remainder is a number
	*/
	
	$id = filter_int($_GET['cat']);
	
	if (isset($_POST['new'])){
		
		if (!filter_string($_POST['newname']))
			errorpage("Name is a required field!");
		
		if ($id){
			// New Item
			if (filter_int($_POST['newcoins'])<0 || filter_int($_POST['newgcoins'])<0){
					trigger_error($loguser['name']." likes taking unnecessary risks by creating an item with negative cost.", E_USER_NOTICE);
					errorpage("Uh no, I did say that didn't work.");
			}
			
			
			$sql->queryp("
			INSERT INTO shop_items
			(name, title, cat, ord, hp, mp, atk, def, intl, mdf, dex, lck, spd, coins, gcoins, special)
			VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
			array(
				q($_POST['newname']),
				q($_POST['newtitle']),
				filter_int($_POST['newcat']),
				filter_int($_POST['neword']),
				i($_POST['newhp']),
				i($_POST['newmp']),
				i($_POST['newatk']),
				i($_POST['newdef']),
				i($_POST['newint']),
				i($_POST['newmdf']),
				i($_POST['newdex']),
				i($_POST['newlck']),
				i($_POST['newspd']),
				filter_int($_POST['newcoins']),
				filter_int($_POST['newgcoins']),
				filter_int($_POST['newspecial'])
			));
			
			header("Location: shoped.php?cat=$id");
			
			
		}
		
		else if ($isadmin){
			
			// New category
			$sql->queryp("INSERT INTO shop_categories (name, title, ord) VALUES (?,?,?)", array(q($_POST['newname']), q($_POST['newtitle']), filter_int($_POST['neword'])));
			$sql->query("ALTER TABLE users_rpg ADD COLUMN item".filter_int($sql->resultq("SELECT MAX(id) FROM shop_categories"))." int(32) NOT NULL DEFAULT '0'");
			$msg = "Added new category '".$_POST['newname']."'";
			header("Location: shoped.php");
			
		}
		
		
	}
	else if (isset($_POST['update'])){
		
		if ($id){
			
			
			// Item update or delete
			$sql->start();
			
			foreach($_POST['id'] as $i){
				
				if (filter_int($_POST["coins$i"])<0 || filter_int($_POST["gcoins$i"])<0){
					trigger_error($loguser['name']." likes taking unnecessary risks by creating an item with negative cost.", E_USER_NOTICE);
					errorpage("Uh no, I did say that didn't work.");
				}
				
				else if (!filter_string($_POST["name$i"])) errorpage("You have edited one of the name fields (ID #$i) to be blank!");
				
				
				if (!filter_int($_POST["del$i"])){
					$sql->queryp("
					UPDATE shop_items 
					SET
					name=?, title=?, cat=?, ord=?, hp=?, mp=?, atk=?, def=?, intl=?, mdf=?, dex=?, lck=?, spd=?, coins=?, gcoins=?, special=?
					WHERE id = $i",
					array(
						q($_POST["name$i"]),
						q($_POST["title$i"]),
						filter_int($_POST["cat$i"]),
						filter_int($_POST["ord$i"]),
						i($_POST["hp$i"]),
						i($_POST["mp$i"]),
						i($_POST["atk$i"]),
						i($_POST["def$i"]),
						i($_POST["int$i"]),
						i($_POST["mdf$i"]),
						i($_POST["dex$i"]),
						i($_POST["lck$i"]),
						i($_POST["spd$i"]),
						filter_int($_POST["coins$i"]),
						filter_int($_POST["gcoins$i"]),
						filter_int($_POST["special$i"])
					));
					
				}
				
				else $sql->query("DELETE from shop_items WHERE id = $i");
			}
			
			$sql->end();
			header("Location: shoped.php?cat=$id");
			
		}

		
		else if ($isadmin){
			// Category update or delete
			
			$sql->start();
			
			foreach ($_POST['id'] as $i){
				
				if (!filter_string($_POST["name$i"])) errorpage("You have edited one of the name fields (ID #$i) to be blank!");
				if (!filter_int($_POST["del$i"])) $sql->queryp("UPDATE shop_categories SET name=?, title=?, ord=? WHERE id = $i", array(q($_POST["name$i"]), q($_POST["title$i"]), filter_int($_POST["ord$i"])));
				else{
					$sql->query("DELETE from shop_categories WHERE id = $i");
					$sql->query("ALTER TABLE users_rpg DROP COLUMN item$i");
				}
			}
			
			$sql->end();
			header("Location: shoped.php");
			
		}
		
	}
	else $msg = "";
	
	// hacky array to make it work under dolist() without changes
	$special_list = array(
		array('id' => 0, 'name' => "None"),
		array('id' => 1, 'name' => "Forces female gender"),
		array('id' => 2, 'name' => "Forces catgirl status"),
		array('id' => 3, 'name' => "Shows HTML comments"),
		array('id' => 4, 'name' => "Maxes out coins"),
	);
	
	
	
	pageheader("Shop editor");

	// category fetching always done as it's also needed for the listbox
	$cat = $sql->query("SELECT * FROM shop_categories ORDER BY ord ASC, id ASC");
	if ($cat) $catlist = $sql->fetch($cat, true);
	else $catlist = false;
	
	
	// id, name, title, ord
	if (!$id){
		
		if (!$isadmin){ // only admins can edit categories
			foreach($catlist as $cat)
				$txt .= "<tr><td class='dark'><a href='shoped.php?cat=".$cat['id']."'>".$cat['name']."</a></td></tr>";
			print "<br/><center>
			<table class='main c'>
				<tr><td class='head'>Shop editor</td></tr>
				<tr><td class='light'>Select an item category to edit the respective items.</td></tr>
				".($txt ? $txt : "<tr><td class='dark'>There are no categories yet. Cannot edit items.</td></tr>")."
				</table></center>
			";
			pagefooter();
		}
		
		if ($catlist)
			foreach ($catlist as $cat)
				$txt .= "
				<tr>
					<td class='dim c'>
					".$cat['id']." | <a href='shoped.php?cat=".$cat['id']."'>(View items)</a> | <input type='checkbox' name='del".$cat['id']."' value='".$cat['id']."'> Delete<input type='hidden' name='id[]' value='".$cat['id']."'>
					</td>
					<td class='light'>
						<input type='text' name='name".$cat['id']."' value=\"".$cat['name']."\">
					</td>
					<td class='dim'>
						<input type='text' name='title".$cat['id']."' style='width: 600px' value=\"".$cat['title']."\">
					</td>
					<td class='light'>
						<input type='text' name='ord".$cat['id']."' style='width: 40px' value='".$cat['ord']."'>
					</td>
				</tr>
				";
		
		else $txt = "<tr><td class='light c' colspan=4>There are no categories defined.<br/>You won't be able to create items unless you create one</td></tr>";
		
		
		print "<br/><form method='POST' action='shoped.php'>
		<center><table class='main'>
			<tr><td class='head c' colspan=4>Shop editor - Main</td></tr>
			<tr>
				<td class='light c' colspan=4>
					View, create and edit categories.
				</td>
			</tr>
			<tr><td class='dark c' colspan=4>New category</td></tr>		
			<tr class='c'>
				<td class='head'>&nbsp;</td>
				<td class='head'>Name</td>
				<td class='head'>Title</td>
				<td class='head' style='width: 40px'>Order</td>
			</tr>
			
	
			<tr>
				<td class='dim'>
					&nbsp;
				</td>
				<td class='light'>
					<input type='text' name='newname'>
				</td>
				<td class='dim'>
					<input type='text' name='newtitle' style='width: 600px'>
				</td>
				<td class='light'>
					<input type='text' name='neword' value='0' style='width: 40px'>
				</td>
				
			</tr>
			
			
			<tr><td class='dim c' colspan=4><input type='submit' name='new' value='Create'></td></tr>
			
			
			<tr><td class='dark c' colspan=4>Existing categories:</td></tr>
			<tr class='c'>
				<td class='head'>Actions</td>
				<td class='head'>Name</td>
				<td class='head'>Title</td>
				<td class='head'>Order</td>
			</tr>

			$txt
			<tr><td class='dim c' colspan=4><input type='submit' name='update' value='Update'></td></tr>
		</table></center></form>
		
		";
		
		pagefooter();
	}
	
	/*
	Items editor
	*/
	function dolist($list, $name, $sel = false){
		$txt = "";
		if ($sel) $x[$sel] = "selected";
		foreach ($list as $cat)
			$txt .= "<option value='".$cat['id']."' ".filter_string($x[$cat['id']]).">".$cat['name']."</option>";
		return "<select name='$name'>$txt</select>";
	}	
	
	$items = $sql->query("SELECT * FROM shop_items WHERE cat = $id ORDER by ord ASC, id ASC");
	
	while ($item = $sql->fetch($items))
		$txt .= "
			<tr>
				<td class='dim'><input type='checkbox' name='del".$item['id']."' value='".$item['id']."'><input type='hidden' name='id[]' value='".$item['id']."'></td>
				<td class='light'><input type='text' name='name".$item['id']."' style='width: 150px' value=\"".($item['name'])."\"></td>
				<td class='dim'>  <input type='text' name='title".$item['id']."' style='width: 300px' value=\"".($item['title'])."\"></td>
				<td class='light'>".dolist($catlist, "cat".$item['id'], $item['cat'])."</td>
				<td class='light'><input type='text' name='ord".$item['id']."' value='".$item['ord']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='hp".$item['id']."' value='".$item['hp']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='mp".$item['id']."' value='".$item['mp']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='atk".$item['id']."' value='".$item['atk']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='def".$item['id']."' value='".$item['def']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='int".$item['id']."' value='".$item['intl']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='mdf".$item['id']."' value='".$item['mdf']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='dex".$item['id']."' value='".$item['dex']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='lck".$item['id']."' value='".$item['lck']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='spd".$item['id']."' value='".$item['spd']."' style='width: 40px'></td>
				<td class='light'><input type='text' name='coins".$item['id']."' value='".$item['coins']."' style='width: 60px'></td>
				<td class='light'><input type='text' name='gcoins".$item['id']."' value='".$item['gcoins']."' style='width: 60px'></td>
				<td class='light'>".dolist($special_list, "special".$item['id'], $item['special'])."</td>
			</tr>
		";
	if (!$txt) $txt = "<tr><td class='light c' colspan=17>There are no items in this category.</td></tr>";

	print "<br/><table class='main w c'><tr><td class='dim'><a href='shoped.php'>Select a different category</a></td></tr></table>
	<br/><form method='POST' action='shoped.php?cat=$id'>
		<center><table class='main'>
			<tr><td class='head c' colspan=17>Shop editor - ".$sql->resultq("SELECT name FROM shop_categories WHERE id=$id")."</td></tr>
			<tr>
				<td class='light c' colspan=17>
					You can create or edit items here. Nothing too special.<br/>
					Just, don't even think about creating an item with a negative cost, as it doesn't work.<br/>
					Doing so won't grant you with an IP ban, but <i>seriously</i> don't try. >_><br/><br/>
					
					For the RPG values, enter a valid sign (+,-,x,/) and then a number.
				</td>
			</tr>
			<tr><td class='dark c' colspan=17>New item</td></tr>
			
			<tr class='c'>
				<td class='head'>&nbsp;</td>
				<td class='head'>Name</td>
				<td class='head'>Title</td>
				<td class='head'>Category</td>
				<td class='head'>Order</td>
				<td class='head'>HP</td>
				<td class='head'>MP</td>
				<td class='head'>Atk</td>
				<td class='head'>Def</td>
				<td class='head'>Int</td>
				<td class='head'>MDf</td>
				<td class='head'>Dex</td>
				<td class='head'>Lck</td>
				<td class='head'>Spd</td>
				<td class='head'><img src='images/coin.gif'></td>
				<td class='head'><img src='images/coin2.gif'></td>
				<td class='head'>Special effect</td>
			</tr>
			
	
			<tr class='c'>
				<td class='dim'>&nbsp;</td>
				<td class='light'><input type='text' name='newname' style='width: 150px'></td>
				<td class='dim'>  <input type='text' name='newtitle' style='width: 300px'></td>
				<td class='light'>".dolist($catlist, "newcat", $id)."</td>
				<td class='light'><input type='text' name='neword' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newhp' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newmp' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newatk' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newdef' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newint' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newmdf' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newdex' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newlck' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newspd' value='0' style='width: 40px'></td>
				<td class='light'><input type='text' name='newcoins' value='0' style='width: 60px'></td>
				<td class='light'><input type='text' name='newgcoins' value='0' style='width: 60px'></td>
				<td class='light'>".dolist($special_list, "newspecial")."</td>
			</tr>
			
			
			<tr><td class='dim c' colspan=17><input type='submit' name='new' value='Create'></td></tr>
			
			
			<tr><td class='dark c' colspan=17>Existing items:</td></tr>
			<tr class='c'>
				<td class='head'><b>DEL</b></td>
				<td class='head'>Name</td>
				<td class='head'>Title</td>
				<td class='head'>Category</td>
				<td class='head'>Order</td>
				<td class='head'>HP</td>
				<td class='head'>MP</td>
				<td class='head'>Atk</td>
				<td class='head'>Def</td>
				<td class='head'>Int</td>
				<td class='head'>MDf</td>
				<td class='head'>Dex</td>
				<td class='head'>Lck</td>
				<td class='head'>Spd</td>
				<td class='head'><img src='images/coin.gif'></td>
				<td class='head'><img src='images/coin2.gif'></td>
				<td class='head'><nobr>Special effect</nobr></td>
			</tr>

			$txt
			<tr><td class='dim c' colspan=17><input type='submit' name='update' value='Update'></td></tr>
		</table></center></form>
		";
	
	pagefooter();

?>