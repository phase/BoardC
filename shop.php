<?php
	// based on shoped.php
	
	require "lib/function.php";
	
	if (!$loguser['id']) errorpage("You need to be logged in to access the item shop.");

	$id 	= filter_int($_GET['cat']);
	$txt 	= "";

	
	// Returns an array where you check the elements with isset(<category id>)
	$equipped 		= getuseritems($loguser['id'], true);
	if ($equipped){
		$id_equipped 	= array_flip(array_extract($equipped, 'id'));
	} else {
		$id_equipped	= array();
	}
	
	if (filter_int($_GET['buy'])){
		checktoken(true);
		// Buy an item, automatically sell the old one at 0.6 price
		
		$itemget = $sql->fetchq("SELECT id, name, cat, coins, gcoins, special FROM shop_items WHERE id = ".filter_int($_GET['buy']));
		
		// No chance
		if ($loguser['coins']  < $itemget['coins'])  errorpage("Not enough coins to buy '".		 $itemget['name']."'");
		if ($loguser['gcoins'] < $itemget['gcoins']) errorpage("Not enough green coins to buy '".$itemget['name']."'");
		
		$sql->start();
		
		// If you have an item in the same category, sell it
		if (isset($equipped[$itemget['cat']])){
			$uneq = $sql->fetchq("
				SELECT coins, gcoins
				FROM shop_items
				WHERE id = {$equipped[$itemget['cat']]['id']}
			");
			
			// Don't bother with a separate value
			$itemget['coins'] -= floor($uneq['coins']  * 0.6);
			$itemget['coins'] -= floor($uneq['gcoins'] * 0.6);

		}
		
		// Update spent coins...
		$c[] = $sql->query("
			UPDATE users SET
				spent  = spent  + ({$itemget['coins']}),
				gspent = gspent + ({$itemget['gcoins']})
			WHERE id = {$loguser['id']}
		");
		
		// ... and the equipped item
		$c[] = $sql->query("
			UPDATE users_rpg SET
				eq{$itemget['cat']} = {$itemget['id']}
			WHERE id = {$loguser['id']}");
		
		// Special effects
		switch ($itemget['special']){
			case 1: $c[] = $sql->query("UPDATE users SET sex = 1 WHERE id = ".$loguser['id']);			 break;
			case 2: $c[] = $sql->query("UPDATE users SET title = 'Catgirl' WHERE id = ".$loguser['id']); break;
			case 4: $c[] = $sql->query("UPDATE users SET gcoins = 99999999 WHERE id = ".$loguser['id']); break;
			case 5: $c[] = $sql->query("UPDATE users SET sex = 0 WHERE id = ".$loguser['id']);			 break;
		}

		if ($sql->finish($c)){
			errorpage("
				The {$itemget['name']} has been bought and equipped.<br>
				Click <a href='?'>here</a> to return to the shop.
			");
		} else {
			errorpage("Couldn't buy the item. An unknown error occurred.");
		}
	
	}
	else if (filter_int($_GET['sell'])){
		checktoken(true);
		
		$sell = (int) $_GET['sell'];
		// Search through everything in $equipped['id']
		$valid = isset($id_equipped[$sell]);
		if (!$valid) errorpage("No."); // It's safe to not do strict comp as there is no category with ID 0

		
		$sql->start();
		
		$itemget = $sql->fetchq("SELECT id, name, cat, coins, gcoins FROM shop_items WHERE id = $sell");
		
		// Update spent coins...
		$c[] = $sql->query("
			UPDATE users SET
				spent  = spent  - (".floor($itemget['coins']  * 0.6)."),
				gspent = gspent - (".floor($itemget['gcoins'] * 0.6).")
			WHERE id = {$loguser['id']}
		");
		
		// ... and the equipped item
		$c[] = $sql->query("
			UPDATE users_rpg SET
				eq{$itemget['cat']} = 0
			WHERE id = {$loguser['id']}");
		
		if ($sql->finish($c)){
			errorpage("
				The {$itemget['name']} has been unequipped and sold.<br>
				Click <a href='?'>here</a> to return to the shop.
			");
		} else {
			errorpage("Couldn't sell the item. An unknown error occurred.");
		}

	}
	
	pageheader("Item shop");
	
	
	/*
		Category selection
	*/
	if (!$id){
		
		$catlist = $sql->query("
			SELECT id, name, title
			FROM shop_categories
			ORDER BY ord ASC, id ASC
		");
		
		foreach($catlist as $cat){
			$eqname = isset($equipped[$cat['id']]) ? $equipped[$cat['id']]['name'] : "&nbsp;";
			$txt .= "
			<tr>
				<td class='dim'><a href='shop.php?cat={$cat['id']}'>{$cat['name']}</a></td>
				<td class='light fonts'>{$cat['title']}</td>
				<td class='dim fonts'>$eqname</td>
			</tr>";
		}
		
			
		?>
		<br>
		<table>
			<tr>
				<td>
		
				<img src='status.php?u=<?php echo $loguser['id'] ?>'>
		
				</td>
				<td class='w' valign='top'>
				
					<table class='main c w'>
						<tr><td class='head' colspan=3>Shop list</td></tr>
						<tr>
							<td class='dark'>Shop</td>
							<td class='dark'>Description</td>
							<td class='dark'>Item equipped</td>
						</tr>
					<?php
					
					if (!$txt) {
						
					  ?><tr>
							<td class='dark' colspan=3>
								The item shop is currently empty. Come again later!
							</td>
						</tr><?php
					
					} else {
						
						echo $txt;
						
					}
					
					?>
					</table>
				</td>
			</tr>
		</table>
		<?php

	}
	else {
		
		// List items in the category
		
		// Strict comparision needed, otherwise it also touches the coin values
		function clean($x){return ($x === '+0' || $x === '-0' || $x === 'x1' || $x === '/1' ) ? "&nbsp;" : $x;}
		
		$valid = $sql->resultq("SELECT 1 FROM shop_categories WHERE id = $id");
		if (!$valid) errorpage("This category doesn't exist!", false);
		
		$items = $sql->query("
			SELECT * FROM shop_items
			WHERE cat = $id
			ORDER by ord ASC, id ASC
		");
		
		if ($items) {
			while ($item = $sql->fetch($items)) {
				$stats_txt = "";
				
				// Blank entries that don't change statuses
				$item = array_map('clean', $item);
				$nocomp = true;

				
				if (isset($id_equipped[$item['id']])){
					// Item equipped
					$buy_txt = "<td class='dim c' colspan=2><a href='?sell={$item['id']}&auth=$token'>Sell</a></td>";
					$tr_style = "equal";
					$td_style = "";
					$nocomp = true;
				} else if ($loguser['coins'] < $item['coins'] || $loguser['gcoins'] < $item['gcoins']){
					// Not enough money
					$buy_txt = "
						<td class='dim c' colspan=2>
							<a href='#status' onclick='preview({$loguser['id']}, {$item['id']}, {$item['cat']}, \"". htmlentities($item['name'], ENT_QUOTES) ."\")'>
								Preview
							</a>
						</td>";
					$tr_style = "disabled";
					$td_style = "";
					$nocomp = true;
				} else {
					// Equippable items
					
					$buy_txt = "
						<td class='dim c' style='width: 30px'>
							<a href='?buy={$item['id']}&auth=$token'>Buy</a>
						</td>
						<td class='light c' style='width: 50px'>
							<a href='#status' onclick='preview({$loguser['id']}, {$item['id']}, {$item['cat']}, \"". htmlentities($item['name'], ENT_QUOTES) ."\")'>
								Preview
							</a>
						</td>";
					$tr_style = "";
					$td_style = "";
					$nocomp = false;
				}
				
			
				foreach ($stat as $x) {
					
					$xval 	= substr($item["s$x"], 1); // stat value
					$xoper 	= substr($item["s$x"], 0, 1); // Current operator
					$std_style = "";
					/*
						This whole code block selects a proper text color based 
						on how the item stats are affected when replacing the equipped item
						with this one
						
						this should be skipped when there's no item equipped for the category
						(I hate myself for having implemented all the four operators rather than just two)
					*/
					
					if (isset($equipped[$item['cat']]) && !$nocomp) {
				
						$cval 	= substr($equipped[$item['cat']]["s$x"], 1); // Stat value for the currently equipped item in the category
						$coper 	= substr($equipped[$item['cat']]["s$x"], 0, 1); // Operator for the currently equipped item in the category
						
						$ccomp = getstatval($coper, $cval);
						$xcomp = getstatval($xoper, $xval);
						
						//print "$ccomp - $xcomp; ";
						
						// There are only two different sets of operators that act differently
						$xset = ($xoper == '+' || $xoper == '-');
						$cset = ($coper == '+' || $coper == '-');
						
						// Compare the value with the current
						if ($xset == $cset){
							
							if ($xcomp < $ccomp) {
								$std_style = "lower";
							} else if ($xcomp > $ccomp) {
								$std_style = "higher";
							} else {
								$std_style = "equal";
							}
							
						} else {
							$std_style = "";
						}
					}
					
					// All multiplied numbers aren't stored as floats on the database, so we do this
					if ($xoper == 'x' || $xoper == '/'){
						$item["s$x"] = $xoper.($xval/100);
					}
					
					$stats_txt .= "<td class='dim c $std_style'>".$item["s$x"]."</td>";
				}
				
						
				$item['coins']  = ($item['coins'] > 9999999)  ? "tons" : $item['coins'];
				$item['gcoins'] = ($item['gcoins'] > 9999999) ? "tons" : $item['gcoins'];
				
				$txt .= "
					<tr class='$tr_style'>
						$buy_txt
						<td class='light'>
							".htmlspecialchars($item['name'])."
							<span class='fonts' style='color: #88f;'> - ".htmlspecialchars($item['title'])."</span>
						</td>
						$stats_txt
						<td class='light r $td_style'>".$item['coins'] ."</td>
						<td class='light r $td_style'>".$item['gcoins']."</td>
					</tr>
				";
			}
		}
		else {
			$txt = "<tr><td class='light c' colspan=15>There are no items in this category.</td></tr>";
		}
		
		?>
		<!-- extra css for item stat comparision -->
		<style>
			.disabled	{color:#888888}
			.higher	{color:#abaffe}
			.equal	{color:#ffea60}
			.lower	{color:#ca8765}
		</style>
		
		<!-- extra javascript to handle preview links -->
		<script>
		  function preview(user,item,cat,name){
		    document.getElementById('prev').src='status.php?u='+user+'&it='+item+'&'+Math.random();
		    document.getElementById('pr').innerHTML='Equipped with<br>'+name+'<br>---------->';
		  }
		</script>
		
		<br>
		
		<table class='main w c'>
			<tr>
				<td class='dim'>
					<a href='shop.php'>
						Return to shop list
					</a>
					<?php echo ($isprivileged ? " - <a href='shoped.php?cat=$id'>Edit shop</a>" : "") ?>
				</td>
			</tr>
		</table>
		
		<table>
			<tr>
				<td id='current'>
					<img id='status' src='status.php?u=<?php echo $loguser['id'] ?>'>
				</td>
				<td id='pr'>
					<!-- this will contain 'equipped with' text -->
				</td>
				<td>
					<!-- this will contain the preview status image -->
					<img id='prev' src='images/_.png'>
				</td>
			</tr>
		</table>
		<br>		
		<table class='main w'>
		
				<tr class='c lh'>
					<td class='head' style='width: 80px' colspan=2>Commands</td>
					<td class='dark' style='width: 10px' rowspan='10000'>&nbsp;</td>
					<td class='head'>Item</td>
					<td class='head' style='width: 50px'>HP</td>
					<td class='head' style='width: 50px'>MP</td>
					<td class='head' style='width: 50px'>Atk</td>
					<td class='head' style='width: 50px'>Def</td>
					<td class='head' style='width: 50px'>Int</td>
					<td class='head' style='width: 50px'>MDf</td>
					<td class='head' style='width: 50px'>Dex</td>
					<td class='head' style='width: 50px'>Lck</td>
					<td class='head' style='width: 50px'>Spd</td>
					<td class='head' style='width: 6%'><img src='images/coin.gif'></td>
					<td class='head' style='width: 5%'><img src='images/coin2.gif'></td>
				</tr>
				<?php echo $txt ?>
			</table>
		<?php	
		
	}
	
	pagefooter();
?>