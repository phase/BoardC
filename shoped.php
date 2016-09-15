<?php

	require "lib/function.php";
	
	if (!$isprivileged)	errorpage("No.");
	
	$txt = "";
	
	/*
		We are mostly doing batch replaces, so errors have to be silently fixed
	*/
	function filter_stat(&$x){
		$x = str_replace('X', 'x', $x); // Just in case, as the uppercase X isn't checked
		
		// If the operator isn't allowed, the value is blank or attempts to divide by 0, return +0
		static $oper 	= array('+','-','x','/');
		if (!in_array($x[0], $oper) || (strpos($x, '/0') !== false) return "+0";
		
		
		$val = substr($x, 1);
		// The input is a decimal value for these, but is stored like this in the db
		if ($x[0] == 'x' || $x[0] == '/') {
			$val = (float) $val; // Just in case
			$val = floor($val * 100);
		}
		
		// Filter the value properly
		$val = (int) $val;
		return "'".$x[0].$val."'";
	}
	

	/*
		ITEM FORMAT:
		First character: operator (+,-,x,/)
		The rest: an integer. On x and / it's divided by 100 for extra precision
	*/
	$id = filter_int($_GET['cat']);
	

	
	if (isset($_POST['new'])){
		checktoken();
		
		if (!filter_string($_POST['newname'])) errorpage("The name cannot be blank!");
		
		if ($id){
			/*
				New Item
			*/
			$valid = $sql->resultq("SELECT 1 FROM shop_categories WHERE id = $id");
			if (!$valid) errorpage("This category doesn't exist.");
			
			$coins 	= filter_int($_POST['newcoins']);
			$gcoins = filter_int($_POST['newgcoins']);
			
			if ($coins < 0 || $gcoins < 0){
				irc_reporter($loguser['name']." tried to be funny by creating an item with a negative cost.", 1);
				errorpage("You don't pay warnings much heed, do you?");
			}
			
			$sql->queryp("
			INSERT INTO shop_items
			(name, title, cat, ord, sHP, sMP, sAtk, sDef, sInt, sMDf, sDex, sLck, sSpd, coins, gcoins, special)
			VALUES
			(
				?,
				?,
				".filter_int($_POST['newcat']).",
				".filter_int($_POST['neword']).",
				".filter_stat($_POST['newhp']).",
				".filter_stat($_POST['newmp']).",
				".filter_stat($_POST['newatk']).",
				".filter_stat($_POST['newdef']).",
				".filter_stat($_POST['newint']).",
				".filter_stat($_POST['newmdf']).",
				".filter_stat($_POST['newdex']).",
				".filter_stat($_POST['newlck']).",
				".filter_stat($_POST['newspd']).",
				".filter_int($_POST['newcoins']).",
				".filter_int($_POST['newgcoins']).",
				".filter_int($_POST['newspecial'])."
			)",
			[
				prepare_string($_POST['newname']),
				prepare_string($_POST['newtitle'])
			]);
			
			setmessage("Added new item '{$_POST['newname']}'");
			redirect("shoped.php?cat=$id");
			
		}
		else {
			/*
				New Category
			*/
			admincheck();
			
			$sql->queryp("
				INSERT INTO shop_categories (name, title, ord) VALUES
				(
					?,
					?,
					".filter_int($_POST['neword'])."
				)",
				[
					prepare_string($_POST['newname']),
					prepare_string($_POST['newtitle'])
				]);
			$newid = $sql->lastInsertId();
			$sql->query("ALTER TABLE users_rpg ADD COLUMN eq$newid int(32) NOT NULL DEFAULT '0'");
			
			setmessage("Added new category '{$_POST['newname']}'");
			redirect("shoped.php");
			
		}
		
		
	}
	else if (isset($_POST['update'])){
		
		if ($id){
			
			$valid = $sql->resultq("SELECT 1 FROM shop_categories WHERE id = $id");
			if (!$valid) errorpage("This category doesn't exist.");
			
			$lolcount = 0;
			
			// Item update or delete
			$sql->start();
			
			foreach($_POST['idx'] as $i){
				
				$coins 	= filter_int($_POST["coins$i"]);
				$gcoins = filter_int($_POST["gcoins$i"]);
				
				if ($coins < 0 || $gcoins < 0){
					$lolcount++;
				}
				
				if (!filter_string($_POST["name$i"])) {
					errorpage("You have edited one of the name fields (ID #$i) to be blank!");
				}
				
				/*
					Edit the current item
				*/
				if (!filter_int($_POST["del$i"])){
					$c[] = $sql->queryp("
						UPDATE shop_items SET
							name    = ?,
							title   = ?,
							cat     = ".filter_int($_POST["cat$i"]).",
							ord     = ".filter_int($_POST["ord$i"]).",
							sHP     = ".filter_stat($_POST["HP$i"]).",
							sMP     = ".filter_stat($_POST["MP$i"]).",
							sAtk    = ".filter_stat($_POST["Atk$i"]).",
							sDef    = ".filter_stat($_POST["Def$i"]).",
							sInt    = ".filter_stat($_POST["Int$i"]).",
							sMDf    = ".filter_stat($_POST["MDf$i"]).",
							sDex    = ".filter_stat($_POST["Dex$i"]).",
							sLck    = ".filter_stat($_POST["Lck$i"]).",
							sSpd    = ".filter_stat($_POST["Spd$i"]).",
							coins   = $coins,
							gcoins  = $gcoins,
							special = ".filter_int($_POST["special$i"])."
						WHERE id = $i
					",
					[
						prepare_string($_POST["name$i"]),
						prepare_string($_POST["title$i"])
					]);
					
				} else {
					// Or delete it
					$c[] = $sql->query("DELETE from shop_items WHERE id = $i");
					// Remove references to deleted item (sorry, users)
					$c[] = $sql->query("UPDATE users_rpg SET eq$id = 0 WHERE eq$id = $i");
				}
			}
			
			if ($sql->finish($c)){
				if (!$lolcount) {
					redirect("shoped.php?cat=$id");
				} else {
					irc_reporter($loguser['name']." tried to be funny and edited $lolcount item(s) to have negative cost.", 1);
					errorpage("Thank you for editing the items, except for the part about the negative cost.");
				}
			} else {
				errorpage("An unknown error occurred while editing the items.");
			}
			
		}
		
		else {
			// Category update or delete
			admincheck();
			
			foreach ($_POST['idx'] as $i){
				
				if (!filter_string($_POST["name$i"])) errorpage("You have edited one of the name fields (ID #$i) to be blank!");
				
				if (!filter_int($_POST["del$i"])) {
					$sql->queryp("
						UPDATE shop_categories SET
							name  = ?,
							title = ?,
							ord   = ".filter_int($_POST["ord$i"])."
						WHERE id = $i
					", [prepare_string($_POST["name$i"]), prepare_string($_POST["title$i"])]
					);
				} else {
					$sql->query("DELETE from shop_categories WHERE id = $i");
					$sql->query("DELETE from shop_items WHERE cat = $i");
					$sql->query("ALTER TABLE users_rpg DROP COLUMN eq$i");
				}
			}
			
			redirect("shoped.php");
			
		}
		
	}
	else $msg = "";
	
	// hacky array to make it work under dropdownList() without changes
	$special_list = array(
		['id' => 0, 'name' => "None"],
		['id' => 1, 'name' => "Forces female gender"],
		['id' => 2, 'name' => "Forces catgirl status"],
		['id' => 3, 'name' => "Shows HTML comments"],
		['id' => 4, 'name' => "Maxes out coins"],
		['id' => 5, 'name' => "Forces male gender"],
	);
	
	
	
	pageheader("Shop Editor");

	// category fetching always done as it's also needed for the listbox
	$catlist = $sql->fetchq("SELECT * FROM shop_categories ORDER BY ord ASC, id ASC", true);
	
	/*
		Catehgory selection
	*/
	if (!$id){
		
		if (!$isadmin) {
			print shoplinkbar($catlist);
			pagefooter();
		}
		
		foreach($catlist as $cat) {
			$txt .= "
			<tr>
				<td class='dim c'>
					{$cat['id']} | ".
					"<a href='shoped.php?cat={$cat['id']}'>
						(View items)
					</a>".
					" | ".
					"<input type='checkbox' name='del{$cat['id']}' value='{$cat['id']}'>".
					"<label for='del{$cat['id']}'>Delete</label>".
					"<input type='hidden' name='idx[]' value='{$cat['id']}'>
				</td>
				<td class='light'>
					<input type='text' name='name{$cat['id']}' value=\"".htmlspecialchars($cat['name'])."\">
				</td>
				<td class='dim'>
					<input type='text' name='title{$cat['id']}' style='width: 600px' value=\"".htmlspecialchars($cat['title'])."\">
				</td>
				<td class='light'>
					<input type='text' name='ord{$cat['id']}' style='width: 40px' value='{$cat['ord']}'>
				</td>
			</tr>
			";
		}
		if (!$txt) {
			$txt = "
				<tr>
					<td class='light c' colspan=4>
						There are no categories defined.<br>
						You won't be able to create items unless you create one.
					</td>
				</tr>";
		}
		?>
		<br>
		<form method='POST' action='shoped.php'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		<center>
		
		<table class='main'>
		
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
				<td class='dim'>&nbsp;</td>
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

			<?php echo $txt ?>
			
						
			<tr>
				<td class='dim c' colspan=4>
					<input type='submit' name='update' value='Save Changes'>
				</td>
			</tr>

		</table>
		
		</center>
		</form>
		
		<?php
		
		pagefooter();
	}
	
	
	/*
		Items editor
	*/
	
	$valid = $sql->resultq("SELECT 1 FROM shop_categories WHERE id = $id");
	if (!$valid) errorpage("This category doesn't exist.", false);
	
	?>
	<center>
	<table class='main c'>
		<tr>
			<td class='head'>
				<b>WARNING</b>
			</td>
		</tr>
		<tr>
			<td class='dark'>
				MAKE AN ITEM WITH A NEGATIVE COST AND YOU <span style="border-bottom: 1px dotted #f00;font-style:italic" title="did you mean: won't really (but don't try it anyway, it won't work)">WILL</span> GET BANNED
			</td>
		</tr>
	</table>
	</center>
	<?php
	
	$items = $sql->query("SELECT * FROM shop_items WHERE cat = $id ORDER by ord ASC, id ASC");
	
	/*
		Rows for the current items in the category
	*/
	while ($item = $sql->fetch($items)) {
		$txt .= "
			<tr>
				<td class='dim'>
					<input type='checkbox' name='del{$item['id']}' value='{$item['id']}'>
					<input type='hidden' name='idx[]' value='{$item['id']}'>
				</td>
				<td class='light'>
					<input type='text' name='name{$item['id']}' style='width: 150px' value=\"".htmlspecialchars($item['name'])."\">
				</td>
				<td class='dim'>
					<input type='text' name='title{$item['id']}' style='width: 300px' value=\"".htmlspecialchars($item['title'])."\">
				</td>
				<td class='light'>".dropdownList($catlist, $item['cat'], "cat".$item['id'])."</td>
				<td class='light'><input type='text' name='ord{$item['id']}' value='{$item['ord']}' style='width: 40px'></td>
			";
		// RPG Statuses
		foreach ($stat as $x) {
			$oper = substr($item["s$x"], 0, 1);
			
			if ($oper == 'x' || $oper == '/') {
				$item["s$x"] = "$oper".number_format(substr($item["s$x"], 1)  / 100, 2); // Decimal numbers
			}
			
			$txt .= "
				<td class='light'>
					<input type='text' name='$x{$item['id']}' value='".$item["s$x"]."' style='width: 40px'>
				</td>";
		}
		$txt .= "
				<td class='light'><input type='text' name='coins{$item['id']}' value='{$item['coins']}' style='width: 60px'></td>
				<td class='light'><input type='text' name='gcoins{$item['id']}' value='{$item['gcoins']}' style='width: 60px'></td>
				<td class='light'>".dropdownList($special_list, $item['special'], "special".$item['id'])."</td>
			</tr>
		";
	}
	
	if (!$txt) {
		$txt = "<tr><td class='light c' colspan=17>There are no items in this category.</td></tr>";
	}
	
	$catname = $sql->resultq("SELECT name FROM shop_categories WHERE id = $id");
	
	print shoplinkbar($catlist, $id);

	?>

	<form method='POST' action='shoped.php?cat=<?php echo $id ?>'>
	<input type='hidden' name='auth' value='<?php echo $token ?>'>
	
	<center>
	<table class='main'>
			<tr><td class='head c' colspan=17>Editing <?php echo $catname ?></td></tr>
			<tr>
				<td class='light c' colspan=17>
					Enter one of the four allowed operators (+,-,x,/) and then a number.<br>
					Decimal numbers are only allowed for x and / operations.<br>
					<br>
					THE OPERATOR IS ALWAYS <b>MANDATORY</b> WITHOUT EXCEPTIONS. THE VALUE WILL BE REMOVED OTHERWISE.<br>
					(the same goes for invalid operators)
				</td>
			</tr>
			
			<tr><td class='dark c' colspan=17>New item</td></tr>
			
			<tr class='c'>
				<td class='head'>&nbsp;</td>
				<td class='head'>Name</td>
				<td class='head'>Description</td>
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
				<td class='head nobr'>Special effect</td>
			</tr>
			
	
			<tr class='c'>
				<td class='dim'>&nbsp;</td>
				<td class='light'><input type='text' name='newname' style='width: 150px'></td>
				<td class='dim'>  <input type='text' name='newtitle' style='width: 300px'></td>
				<td class='light'><?php echo dropdownList($catlist, $id, "newcat") ?></td>
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
				<td class='light'><?php echo dropdownList($special_list, 0, "newspecial") ?></td>
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
				<td class='head nobr'>Special effect</td>
			</tr>

			<?php echo $txt ?>
			
			<tr>
				<td class='dim c' colspan=17>
					<input type='submit' name='update' value='Save Changes'>
				</td>
			</tr>
			
		</table>
		</center>
		
		</form>
		<?php
	
	pagefooter();
	
	
	function shoplinkbar($cats, $sel = 0){
		global $sql, $isadmin;
		
		$txt 	= "";
		$cnt	= $sql->resultq("SELECT COUNT(id) FROM categories");
		$width 	= floor(1 / $cnt * 100);
		
		foreach ($cats as $cat){
			
			if ($cat['id'] == $sel){
				$txt .= "
				<td class='dark' style='width: $width%'>
					<a class='notice' href='?cat={$cat['id']}'>
						{$cat['name']}
					</a>
				</td>";
			} else {
				$txt .= "
					<td class='light' style='width: $width%'>
						<a href='?cat={$cat['id']}'>
							{$cat['name']}
						</a>
					</td>";
			}
			
		}
		
		if ($isadmin) {
			$editcat = "
			<tr>
				<td class='dark' colspan=$cnt>
					[<a href='shoped.php'>Edit categories</a>]
				</td>
			</tr>";
		} else {
			$editcat = "";
		}
		
		return "
		<br>
		<table class='main w c'>
			<tr>
				<td class='head' colspan=$cnt>
					Item Categories
				</td>
			</tr>
			$editcat
			$txt
		</table>
		<br>";
	}

?>