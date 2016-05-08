<?php

	function dorpgstatus($user){
		return "
			<table class='main' style='width: 256px;'>
				<tr><td class='head c'>RPG status TEMP</td></tr>
				<tr><td class='light c' style='height: 212px;'>

					<img src='images/coin.gif'> - ".$user['coins']." | 
					<img src='images/coin2.gif'> - ".$user['gcoins']."<br/>
					HP: ".$user['hp']."<br/>
					MP: ".$user['mp']."<br/>
					Atk: ".$user['atk']."<br/>
					Def: ".$user['def']."<br/>
					Int: ".$user['intl']."<br/>
					MDf: ".$user['mdf']."<br/>
					Dex: ".$user['dex']."<br/>
					Lck: ".$user['lck']."<br/>
					Spd: ".$user['spd']."<br/>
					
					<font color=red>Image not implemented</font>
					
				</td></tr>
			</table>";
	}
	
	function getuseritems($user){
		global $sql;
		for($i=0, $max=$sql->resultq("SELECT MAX(id) FROM shop_categories"); $i<$max+1; $i++)
			if (filter_int($user["item$i"]))
				$q[] = $user["item$i"];
		return isset($q) ? $q : false;
	}
	
	function calcexp($since, $posts){
		/*
		$time = ctime()-$since;
		$a = floor(log($time)*$posts);
		$b = "";

		print $a;
		*/
		return "<font color=red>Not implemented</font>";
	}
?>