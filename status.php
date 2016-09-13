<?php
	
	/*
		Also ported from Acmlmboard 1.92, but modified to look like the one from Jul.
		Holy shit the original code was completely uncommented and poorly formatted.
	*/
	
	$meta['allorigin'] = true;
	
	require "lib/function.php";
	
	// Defaults
	$u 	= filter_int($_GET['u']);
	if (!$u) x_die();
	
	$user = $sql->fetchq("
		SELECT 	u.name, u.displayname, u.posts, u.powerlevel, u.since,
				u.spent, u.gcoins, u.gspent, u.class, r.*
		FROM users u
		LEFT JOIN users_rpg r ON u.id = r.id
		WHERE u.id = $u
	");
	
	$p    = $user['posts'];
	$d    = (ctime() - $user['since']) / 86400;
	

	/*
	$eqitems=mysql_query("SELECT * FROM items WHERE id=$user[eq1] OR id=$user[eq2] OR id=$user[eq3] OR id=$user[eq4] OR id=$user[eq5] OR id=$user[eq6] OR id=$it");
	while($item=mysql_fetch_array($eqitems)) $items[$item[id]]=$item;
	if($ct){
	$GPdif=floor($items[$user['eq'.$ct]][coins]*0.6)-$items[$it][coins];
	$user['eq'.$ct]=$it;
	}
	$st=getstats($user,$items);
	$st[GP]+=$GPdif;
 */
 
 
 

	$items	= getuseritems($u, false);
	
	/*
		This is to handle status.php previews in the item shop
	*/
	$extra 		= filter_int($_GET['it']); // Extra item to preview
	
	if ($extra) {
		// Get patch info that will replace the item in the same category
		$extraitem = $sql->fetchq("
			SELECT s.cat, s.sHP, s.sMP, s.sAtk, s.sDef, s.sInt, s.sMDf, s.sDex, s.sLck, s.sSpd
			FROM shop_items s
			WHERE s.id = $extra
		", true, PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);
		
		$items = array_replace($items, $extraitem);
	}
	
	
	$st 	= getstats($user, $items);
	//print "<pre>";
	//print_r($st);
	//die;

	if ($st['lvl'] > 0){
		$pct = 1 - calcexpleft($st['exp']) / totallvlexp($st['lvl']);
	}
	
	Header('Content-type:image/png');
	
	$img     = ImageCreate(256, 224);
	
	/*
		Color palettes
	*/
	$c['bg']   = ImageColorAllocate($img, 40, 40, 90);
	$c['bxb0'] = ImageColorAllocate($img, 0, 0, 0);
	$c['bxb1'] = ImageColorAllocate($img, 225, 200, 180);
	$c['bxb2'] = ImageColorAllocate($img, 190, 160, 130);
	$c['bxb3'] = ImageColorAllocate($img, 130, 110, 90);
	for ($i = 0; $i < 100; $i++)
		$c[$i] = ImageColorAllocate($img, 10, 16, 80 + $i);
	$c['barE1']   = ImageColorAllocate($img, 120, 150, 180);
	$c['barE2']   = ImageColorAllocate($img, 30, 60, 90);
	$c['bar1'][1] = ImageColorAllocate($img, 215, 91, 129);
	$c['bar2'][1] = ImageColorAllocate($img, 90, 22, 43);
	$c['bar1'][2] = ImageColorAllocate($img, 255, 136, 154);
	$c['bar2'][2] = ImageColorAllocate($img, 151, 0, 38);
	$c['bar1'][3] = ImageColorAllocate($img, 255, 139, 89);
	$c['bar2'][3] = ImageColorAllocate($img, 125, 37, 0);
	$c['bar1'][4] = ImageColorAllocate($img, 255, 251, 89);
	$c['bar2'][4] = ImageColorAllocate($img, 83, 81, 0);
	$c['bar1'][5] = ImageColorAllocate($img, 89, 255, 139);
	$c['bar2'][5] = ImageColorAllocate($img, 0, 100, 30);
	$c['bar1'][6] = ImageColorAllocate($img, 89, 213, 255);
	$c['bar2'][6] = ImageColorAllocate($img, 0, 66, 93);
	$c['bar1'][7] = ImageColorAllocate($img, 196, 33, 33);
	$c['bar2'][7] = ImageColorAllocate($img, 70, 12, 12);
	
	ImageColorTransparent($img, 0);
	
	
	/*
		Layout
	*/
	$name = $user['displayname'] ? $user['displayname'] : $user['name'];
	
	// Boxes
	box(0, 0, 2 + strlen($name), 3); 	// Name Box
	box(0, 3, 2 + strlen($st['ext']), 3); 	// Extra box
	box(0, 7, 32, 4); 					// HP/MP Box
	box(0, 12, 32, 9); 					// RPG Stats
	box(0, 22, 18, 6); 					// Level / EXP
	box(19, 22, 13, 6); 				// Coins
	
	// Font color definitions
	$fontY = fontc(255, 250, 240, 255, 240, 80, 0, 0, 0);
	$fontR = fontc(255, 230, 220, 240, 160, 150, 0, 0, 0);
	$fontG = fontc(190, 255, 190, 60, 220, 60, 0, 0, 0);
	$fontB = fontc(160, 240, 255, 120, 190, 240, 0, 0, 0);
	$fontW = fontc(255, 255, 255, 210, 210, 210, 0, 0, 0);
	
	
	twrite($fontW, 1, 1, 0, "$name"); // Name
	twrite($fontB, 1, 4, 0, "{$st['ext']}"); // Extra
	
	// HP / MP Status
	twrite($fontB, 1, 8, 0, 'HP:      /');
	twrite($fontR, 3, 8, 7, $st['HP']);
	twrite($fontY, 11, 8, 5, $st['HP']);
	twrite($fontB, 1, 9, 0, 'MP:      /');
	twrite($fontR, 3, 9, 7, $st['MP']);
	twrite($fontY, 11, 9, 5, $st['MP']);
	
	
	// Other RPG statuses
	for ($i = 2; $i < 9; $i++) {
		twrite($fontB, 1, 11 + $i, 0, "{$stat[$i]}:");
		twrite($fontY, 4, 11 + $i, 6, $st[$stat[$i]]);
	}
	
	twrite($fontB, 1, 23, 0, 'Level');
	twrite($fontY, 6, 23, 11, $st['lvl']);
	twrite($fontB, 1, 25, 0, 'EXP:');
	twrite($fontY, 1, 25, 16, $st['exp']);
	twrite($fontB, 1, 26, 0, 'Next:');
	twrite($fontY, 1, 26, 16, calcexpleft($st['exp']));
	
	twrite($fontB, 20, 23, 0, 'Coins:');
	twrite($fontY, 20, 25, 0, chr(0));
	twrite($fontG, 20, 26, 0, chr(0));
	twrite($fontY, 21, 25, 10, $st['coins']);
	twrite($fontG, 21, 26, 10, $st['gcoins']);
	

	$sc[1] = 1;
	$sc[2] = 5;
	$sc[3] = 25;
	$sc[4] = 100;
	$sc[5] = 250;
	$sc[6] = 500;
	$sc[7] = 1000;
	$sc[8] = 99999999;
	
	//$st['HP'] = 9999;
	//$st['MP'] = 5999;
	bars();
	
	ImagePNG($img);
	ImageDestroy($img);
	
	function twrite($font, $x, $y, $l, $text) {
		global $img;
		
		$x 		*= 8;
		$y 		*= 8;
		$text 	.= '';
		
		if (strlen($text) < $l) {
			$x += ($l - strlen($text)) * 8;
		}
		for ($i = 0; $i < strlen($text); $i++) {
			ImageCopy($img, $font, $i * 8 + $x, $y, (ord($text[$i]) % 16) * 8, floor(ord($text[$i]) / 16) * 8, 8, 8);
		}
	}
	function fontc($r1, $g1, $b1, $r2, $g2, $b2, $r3, $g3, $b3) {
		$font = ImageCreateFromPNG('images/rpg/font.png');
		ImageColorTransparent($font, 1);
		ImageColorSet($font, 6, $r1, $g1, $b1);
		ImageColorSet($font, 5, ($r1 * 2 + $r2) / 3, ($g1 * 2 + $g2) / 3, ($b1 * 2 + $b2) / 3);
		ImageColorSet($font, 4, ($r1 + $r2 * 2) / 3, ($g1 + $g2 * 2) / 3, ($b1 + $b2 * 2) / 3);
		ImageColorSet($font, 3, $r2, $g2, $b2);
		ImageColorSet($font, 0, $r3, $g3, $b3);
		return $font;
	}
	function box($x, $y, $w, $h) {
		global $img, $c;
		$x *= 8;
		$y *= 8;
		$w *= 8;
		$h *= 8;
		ImageRectangle($img, $x + 0, $y + 0, $x + $w - 1, $y + $h - 1, $c['bxb0']);
		ImageRectangle($img, $x + 1, $y + 1, $x + $w - 2, $y + $h - 2, $c['bxb3']);
		ImageRectangle($img, $x + 2, $y + 2, $x + $w - 3, $y + $h - 3, $c['bxb1']);
		ImageRectangle($img, $x + 3, $y + 3, $x + $w - 4, $y + $h - 4, $c['bxb2']);
		ImageRectangle($img, $x + 4, $y + 4, $x + $w - 5, $y + $h - 5, $c['bxb0']);
		for ($i = 5; $i < $h - 5; $i++) {
			$n = (1 - $i / $h) * 100;
			ImageLine($img, $x + 5, $y + $i, $x + $w - 6, $y + $i, $c[$n]);
		}
	}
	function bars() {
		global $st, $img, $c, $sc, $pct, $stat, $s;
		
		// Divide to make sure the max status between HP and MP doesn't overflow
		for ($s = 1; @(max($st['HP'], $st['MP']) / $sc[$s]) > 113; $s++);
		if (!$sc[$s]) $sc[$s] = 1; // Default
		
		// HP and MP
		statbar(17, 8, $st['HP'], $c['bar1'][$s]);
		statbar(17, 9, $st['MP'], $c['bar1'][$s]);
		
		// More dividing
		for ($i = 2; $i < 9; $i++) $st2[$i] = $st[$stat[$i]];
		for ($s = 1; @(max($st2) / $sc[$s]) > 161; $s++);
		if (!$sc[$s]) $sc[$s] = 1;
		
		// RPG Stats
		for ($i = 2; $i < 9; $i++) {
			statbar(11, 11 + $i, $st[$stat[$i]], $c['bar1'][$s]);
		}
		
		// Level bar (thin)
		$e1 = 128 * $pct; // 8 + 72
		ImageFilledRectangle($img, 9, 8 * 24 + 2, 8 + 128,  8 * 24 + 5, $c['bxb0']);
		ImageFilledRectangle($img, 8, 8 * 24 + 1, 7 + 128,  8 * 24 + 4, $c['barE2']);
		ImageFilledRectangle($img, 8, 8 * 24 + 1, 7 + $e1, 8 * 24 + 4, $c['barE1']);
		
	}
	
	/*
		progressbar
		- x tile
		- y tile
		- stat value
		- color
	*/
	function statbar($x, $y, $status, $color){
		global $st, $img, $c, $sc, $pct, $stat, $s;
		$x *= 8;
		$y *= 8;
		ImageFilledRectangle($img, $x + 1, $y + 1, $x + ($status / $sc[$s]),     $y + 7, $c['bxb0']); // Black "lines"
		ImageFilledRectangle($img, $x,     $y,     $x - 1 + ($status / $sc[$s]), $y + 6, $color);
		
		//ImageFilledRectangle($img, 136, 40, 135 + $st['HP'] / $sc[$s], 46, $c['bar1'][$s]);
	}

?>