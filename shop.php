<?php
	// based on shoped.php
	
	require "lib/function.php";
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to access the item shop.");
	

	$id = filter_int($_GET['cat']);
	$txt = "";

	function itemformat($x, $sell = false){
		// Get the operator, reverse it appropriately when an item is sold and calculate the new RPG status value
		// This should probably go by a switch() instead of $otmp and eval
		global $sql, $loguser;
		
		static $act = array('+','x','/','-');
		static $sta = array('hp', 'mp', 'atk', 'def', 'intl', 'mdf', 'dex', 'lck', 'spd');
		
		foreach($sta as $s){
			$oper = array_search($x[$s][0], $sell ? array_reverse($act) : $act);
			if ($oper === false){
				trigger_error("Item started with invalid operand ".$x[$s][0], E_USER_WARNING);
				errorpage("An unknown error occurred.");
			}
			$n = (float)(trim($x[$s], '+-x/ '));
			if ($oper == 2 && $n == 0) $n = 1; // Divide by zero check
			$otmp = ($oper == 1) ? "*" : $act[$oper]; // replace with correct muliplication
			eval("\$tmp = ".$loguser[$s]." $otmp \$n;");
			$q[] = "$s = '".floor(($tmp < 1) ? 1 : $tmp)."'"; // renders *0 incorrect but prevents awkward situations
		}

		$sql->query("UPDATE users_rpg SET ".implode(", ", $q)." WHERE id = ".$loguser['id']);
	}

	$q = getuseritems($loguser);
	
	if ($q){
		$itemeq = $sql->fetchq("
		SELECT id, cat, coins, gcoins, hp, mp, atk, def, intl, mdf, dex, lck, spd FROM shop_items
		WHERE id IN (".implode(", ", $q).")
		", true);
		$ideq = array_extract($itemeq, "id");
	}
	else $ideq = $itemeq = array();
	
	if (filter_int($_GET['buy'])){
		// Buy an item, automatically sell the old one at 0.8 price
		
		$itemget = $sql->fetchq("SELECT id, cat, coins, gcoins, hp, mp, atk, def, intl, mdf, dex, lck, spd, special FROM shop_items WHERE id = ".filter_int($_GET['buy']));
		
		if ($loguser['coins']  < $itemget['coins'])  errorpage("Not enough coins to buy '".		 $itemget['name']."'");
		if ($loguser['gcoins'] < $itemget['gcoins']) errorpage("Not enough green coins to buy '".$itemget['name']."'");
		
		$sql->start();
		
		// If you have an item in the same category, sell it
		if ($itemeq){
			foreach($itemeq as $sell){
				if ($sell['cat'] == $itemget['cat']){
					$coinsq  = " + ".floor($sell['coins']*0.8);
					$gcoinsq = " + ".floor($sell['gcoins']*0.8);
					itemformat($sell, true);
				}
			}
		}
		$sql->query("
		UPDATE users SET
		coins = coins ".filter_string($coinsq)." - ".$itemget['coins'].",
		gcoins = gcoins ".filter_string($gcoinsq)." - ".$itemget['gcoins']."
		WHERE id = ".$loguser['id']);
		
		$sql->query("UPDATE users_rpg SET item".$itemget['cat']." = ".$itemget['id']." WHERE id = ".$loguser['id']);
		
		itemformat($itemget);
		
		if ($itemget['special'] == 1) $sql->query("UPDATE users SET sex = 1 WHERE id = ".$loguser['id']);
		else if ($itemget['special'] == 2) $sql->query("UPDATE users SET title = 'Catgirl' WHERE id = ".$loguser['id']); // ?
		else if ($itemget['special'] == 4) $sql->query("UPDATE users SET coins = 99999999, gcoins = 99999999 WHERE id = ".$loguser['id']);
		else if ($itemget['special'] == 1) $sql->query("UPDATE users SET sex = 0 WHERE id = ".$loguser['id']);
		
		$sql->end();
		
		errorpage("Item bought!");
		
	}
	else if (filter_int($_GET['sell'])){
		
		if (!$itemeq) // quick check
			errorpage("No.");
		
		$sid = filter_int($_GET['sell']);
		
		foreach ($itemeq as $chk)
			if ($chk['id'] == $sid)
				$sell = $chk;
		
		if (!isset($sell))
			errorpage("No.");
		
		$sql->start();
		
		$sql->query("UPDATE users SET coins = coins + ".floor($sell['coins']*0.8).", gcoins = gcoins + ".floor($sell['gcoins']*0.8)." WHERE id = ".$loguser['id']);
		$sql->query("UPDATE users_rpg SET item".$sell['cat']." = 0 WHERE id = ".$loguser['id']);
		
		itemformat($sell, true);
		
		$sql->end();
		
		errorpage("Item sold!");

	}
	
	pageheader("Item shop");
	
	// Select a category
	if (!$id){
		
		$cat = $sql->query("SELECT * FROM shop_categories ORDER BY ord ASC, id ASC");
		if ($cat) $catlist = $sql->fetch($cat, true);
		else $catlist = false;
	
		foreach($catlist as $i => $cat)
			$txt .= "
			<tr>
				<td class='dim'><a href='shop.php?cat=".$cat['id']."'>".$cat['name']."</a></td>
				<td class='light fonts'>".$cat['title']."</td>
				<td class='dim fonts'>".filter_string($itemeq[$i]['name'])."</td>
			</tr>";
		
		print "<br/><table><tr><td>
		
		".dorpgstatus($loguser)."
		
		</td><td class='w' valign='top'>
		
		<table class='main c w'>
			<tr><td class='head' colspan=3>Shop list</td></tr>
			<tr><td class='dark'>Shop</td><td class='dark'>Description</td><td class='dark'>Item equipped</td></tr>
			".($txt ? $txt : "<tr><td class='dark' colspan=3>The item shop is currently empty. Come again later!</td></tr>")."
			
		</table>
		
		
		</td></tr></table>";
		
		pagefooter();
	}
	
	// List items in the category
	
	function l($x){return ($x=='+0') ? "&nbsp;" : $x;}
	
	$items = $sql->query("SELECT * FROM shop_items WHERE cat = $id ORDER by ord ASC, id ASC");
	
	if ($items){
		while ($item = $sql->fetch($items)){
			$item = array_map('l', $item);
			
			if (in_array($item['id'], $ideq)){
				$buy_txt = "<td class='dim' colspan=2><a href='?sell=".$item['id']."'>Sell</a></td>";
				$col_txt = "selected";
			}
			else{
				$can_buy = ($loguser['coins']>=$item['coins'] && $loguser['gcoins']>=$item['gcoins']);
				$buy_txt = "<td class='dim ".($can_buy ? "' style='width: 10px'><a href='?buy=".$item['id']."'>Buy</a>" : "disabled' style='width: 10px'>Buy")."</td>
					<td class='light' style='width: 10px'><s href='?preview=".$item['id']."'>Preview</s></td>";
				$col_txt = $can_buy ? "" : "disabled";
			}
					
			$item['coins']  = ($item['coins'] > 9999999)  ? "tons" : filter_int($item['coins']);
			$item['gcoins'] = ($item['gcoins'] > 9999999) ? "tons" : filter_int($item['gcoins']);
			
			$txt .= "
				<tr>
	

					$buy_txt
					<td class='light'>".$item['name']."<small> - ".$item['title']."</small></td>
					
					<td class='dim $col_txt'>".$item['hp']."</td>
					<td class='dim $col_txt' >".$item['mp']."</td>
					<td class='dim $col_txt' >".$item['atk']."</td>
					<td class='dim $col_txt' >".$item['def']."</td>
					<td class='dim $col_txt' >".$item['intl']."</td>
					<td class='dim $col_txt' >".$item['mdf']."</td>
					<td class='dim $col_txt' >".$item['dex']."</td>
					<td class='dim $col_txt' >".$item['lck']."</td>
					<td class='dim $col_txt' >".$item['spd']."</td>
					<td class='light $col_txt'>".$item['coins']."</td>
					<td class='light $col_txt'>".$item['gcoins']."</td>
				</tr>
			";
		}
	}
	else $txt = "<tr><td class='light c' colspan=15>There are no items in this category.</td></tr>";

	print "<br/><table class='main w c'><tr><td class='dim'><a href='shop.php'>Return to shop list".(powlcheck(1) ? " - <a href='shoped.php?cat=$id'>Edit shop</a>" : "")."</a></td></tr></table>
	".dorpgstatus($loguser)."
	<br/><table class='main c w'>
	
			
			<tr class='c'>
				<td class='head' style='width: 10px' colspan=2>Commands</td>
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
				<td class='head' style='width: 5%'><img src='images/coin.gif'></td>
				<td class='head' style='width: 6%'><img src='images/coin2.gif'></td>
			</tr>
			$txt
		</table>
		";
	
	pagefooter();

?>