<?php

	/*
		The majority of this file has been ported from Acmlmboard 1.92
	*/
		
	$stat = array('HP','MP','Atk','Def','Int','MDf','Dex','Lck','Spd');
	
	function dorpgstatus($id){
		return "
			<table class='main' style='width: 256px;'>
				<tr><td class='head c'>RPG status</td></tr>
				
				<tr>
					<td class='light c' style='height: 212px;'>
						<img src='status.php?u=$id'>
					</td>
				</tr>
			</table>";
	}
	
	function drawlevelbar($level, $expleft){
		if ($level != 'NAN'){
			$total 	= totallvlexp($level);
			$back 	= floor(100 / $total * $expleft);
		} else {
			$back 	= 100;
		}
		$filled = 100 - $back;
		return "".
			"<img src='images/bar/barleft.gif'  style='height: 8px'>".
			"<img src='images/bar/bar-on.gif'   style='width:{$filled}px; height: 8px'>".
			"<img src='images/bar/bar-off.gif'  style='width:{$back}px; height: 8px'>".
			"<img src='images/bar/barright.gif' style='height: 8px'>";
	}
	
	/*
		user - user id
		name - get extra categories
		extra - add an extra item 
	*/
	function getuseritems($user, $name = false, $extra = 0){
		global $sql;
		
		// We can't know what categories exist, so we need to build the query
		// based on the category IDs returned by the following query
		$num 	= $sql->fetchq("SELECT id FROM shop_categories", true, PDO::FETCH_COLUMN);
		$q 		= "";
		foreach($num as $i){
			$q .= "r.eq$i = s.id OR ";
		}
		
		// For our convenience we group this
		$itemdb = $sql->fetchq("
			SELECT s.cat, s.sHP, s.sMP, s.sAtk, s.sDef, s.sInt, s.sMDf, s.sDex, s.sLck, s.sSpd, s.special".($name ? ", s.id, s.name" : "")."
			FROM shop_items s
			INNER JOIN users_rpg r ON ($q $extra)
			WHERE r.id = $user
		", true, PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
		
		return $itemdb;
	}
	
	// Return a value that can be easily compared to another one
	function getstatval($oper, $val){
		switch ($oper) {
			case '+': return $val;
			case '-': return 0 - $val; 
			case 'x': return $val / 100; // Remember this numbers are stored as int in the database 
			case '/': return 100 / $val; // I hope the floating point precision is good enough
		}
	}
	
	function basestat($p, $d, $stat, $ext = 0){
		//$p += 0; // what
		$e = calcexp($p,$d,$ext);
		$l = calclvl($e);
		
		if($l == 'NAN'){
			return 1;
		}
		// RPG Status multipliers + Base stat
		switch($stat){
			case 0: return (pow($p,0.26) * pow($d,0.08) * pow($l,1.11) * 0.95) + 20; //HP
			case 1: return (pow($p,0.22) * pow($d,0.12) * pow($l,1.11) * 0.32) + 10; //MP
			case 2: return (pow($p,0.18) * pow($d,0.04) * pow($l,1.09) * 0.29) +  2; //Str
			case 3: return (pow($p,0.16) * pow($d,0.07) * pow($l,1.09) * 0.28) +  2; //Atk
			case 4: return (pow($p,0.15) * pow($d,0.09) * pow($l,1.09) * 0.29) +  2; //Def
			case 5: return (pow($p,0.14) * pow($d,0.10) * pow($l,1.09) * 0.29) +  1; //Shl
			case 6: return (pow($p,0.17) * pow($d,0.05) * pow($l,1.09) * 0.29) +  2; //Lck
			case 7: return (pow($p,0.19) * pow($d,0.03) * pow($l,1.09) * 0.29) +  1; //Int
			case 8: return (pow($p,0.21) * pow($d,0.02) * pow($l,1.09) * 0.25) +  1; //Spd
		}
	}
	
	function getstats($u, $items){
		global $sql, $stat;
		
		$p 	= $u['posts'];
		$d 	= (ctime() - $u['since']) / 86400;
				
		$m = array_fill(0, 9, 1);
		$a = array_fill(0, 9, 0);
		
		foreach($items as $item){
			// Apply boost for each status
			for($k = 0; $k < 9; $k++){
				$is = $item["s{$stat[$k]}"]; // ie: sInt, sMDf...
				
				$oper = substr($is, 0, 1);
				$val  = (int) substr($is, 1); // Everything except the operator
				
				//print "{$stat[$k]} - ".$oper.$val."<br>";
				/*
					+ or - : add or remove directly
					x or / : divide by 100 to make decimal numbers possible
					TODO: NOTE THAT THE LATTER ISN'T IMPLEMENTED IN THE ITEM SHOP
				*/
				// Check first character to handle operation
				switch ($oper){
					case '+': $a[$k] += $val; break;
					case '-': $a[$k] -= $val; break;
					case 'x': $m[$k] *= $val / 100; break;
					case '/': $m[$k] /= $val / 100; break;
					default: $a[$k] += 0;
				}

			}
		}
		
		/*
			RPG Classes / Bonus EXP (for... some reason)
		*/
		$ext = $sql->fetchq("SELECT name , bonus_exp FROM rpg_classes WHERE id = {$u['class']}");
		if (!$ext) {
			$stats['ext'] 		= "None";
			$stats['bonus_exp'] = 0;
		} else {
			$stats['ext'] 		= $ext['name'];
			$stats['bonus_exp'] = $ext['bonus_exp'];
		}
		
		// Calculate stats.
		for($i = 0; $i < 9; $i++){
			//print "<pre>{$stat[$i]} - ".floor(basestat($p, $d, $i))." | ".floor(basestat($p, $d, $i) * $m[$i])."\n";
			$stats[$stat[$i]] = max(1, floor(basestat($p, $d, $i, $stats['ext']) * $m[$i]) + $a[$i]);
		}
		

		
		$stats['coins']	 	= coins($p, $d) - $u['spent'];
		$stats['gcoins'] 	= $u['gcoins'] - $u['gspent'];
		$stats['exp']		= calcexp($p, $d, $stats['bonus_exp']);
		$stats['lvl']		= calclvl($stats['exp']);
		
		return $stats;
	}
	
	// posts - days
	function coins($p, $d){
		global $config;
		//$p += 0;
		if($p < 0 || $d < 0) return 0;
		return floor(pow($p, 1.3) * pow($d, 0.4)) + $p * $config['coins-multiplier'];
	}
	
	function calcexpgainpost($posts, $days){
		return floor(1.5 * pow($posts * $days, 0.5));
	}

	function calcexpgaintime($posts, $days){
		return sprintf('%01.3f', 172800 * (pow(($days / $posts), 0.5) / $posts));
	}

	function calcexpleft($exp){
		return calclvlexp(calclvl($exp) + 1) - $exp;
	}

	function totallvlexp($lvl){
		return calclvlexp($lvl + 1) - calclvlexp($lvl);
	}

	function calclvlexp($lvl){
		if ($lvl == 1) 	return 0;
		else 			return floor(pow(abs($lvl) , 3.5)) * ($lvl > 0 ? 1 : -1);
	}

	function calcexp($posts, $days, $extra = 0){
		if (!$days || !$posts) 			return $extra;
		else if ($posts / $days > 0) 	return floor($posts * pow($posts * $days, 0.5)) + $extra;
		else 							return 'NAN';
	}

	function calclvl($exp){
		if ($exp >= 0) {
			$lvl = floor(pow($exp, 2 / 7));
			if (calclvlexp($lvl + 1) == $exp) $lvl++;
			if (!$lvl) $lvl = 1;
		} else {
			$lvl = - floor(pow(-$exp, 2 / 7));
		}
		
		if ($exp == 'NAN') $lvl = 'NAN';
		return $lvl;
	}
?>