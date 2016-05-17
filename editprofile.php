<?php
	$meta['noindex'] = true;
	
	/*
	unified editprofile and edituser
	
	admin only options appear (and can get saved to the database) based on powerlevel checks
	*/
	require "lib/function.php";
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to do that.");
	
	if (!powlcheck(0))
		errorpage("Banned users aren't allowed to edit their profile.");
	
	$isadmin = powlcheck(4);
	$sysadmin = powlcheck(5);
	
		
	if (isset($_GET['id'])){
		// Edit another user
		if (!$isadmin)
			errorpage("Powerlevel too low.");
		
		$edituser = true;
		$id = filter_int($_GET['id']);
		$pagetitle = "Edit User";
	}
	else{
		// Edit your own profile
		$edituser = false;
		$id = $loguser['id'];
		$pagetitle = "Edit Profile";
	}
	
	// Do common $_POST here
	if (isset($_POST['save'])){
		
		if (!$sysadmin && $_POST['powerlevel'] > 4){
			ipban("Make me a sysadmin!", "Auto IP banned ".$_SERVER['REMOTE_ADDR']." for attempting to become a sysadmin.");
			errorpage("A for effort, <s>F for still failing</s> I for IP Ban");
		}
		
		// if an item forces female or catgirl status, prevent a change
		$q = getuseritems($sql->fetchq("SELECT * FROM users_rpg WHERE id = $id"));
		
		if (!empty($q)){
			$itemdb = $sql->query("
			SELECT hp, mp, atk, def, intl, mdf, dex, lck, spd, special
			FROM shop_items
			WHERE id IN (".implode(", ", $q).")
			");
		
			while ($item = $sql->fetch($itemdb)){
				if 		($item['special'] == 1) $_POST['sex'] = 1;
				else if ($item['special'] == 2) $_POST['title'] = "Catgirl"; // ?
				else if ($item['special'] == 4) $_POST['coins'] = $_POST['gcoins'] = 99999999;
				else if ($item['special'] == 5) $_POST['sex'] = 0;
			}
		}
		
		
		$theme = filter_int($_POST['theme']);
		$theme_list = findthemes();
		if (!isset($theme_list[$theme]))
			$theme = 1;
		
		
		// build pquery based on checks
		$query = "UPDATE users SET title=?,head=?,sign=?,sex=?,realname=?,location=?,birthday=?,bio=?,email=?,youtube=?,twitter=?,facebook=?,dateformat=?,timeformat=?,tzoff=?,showhead=?,signsep=?,theme=?";
		$newdata = array();		

		$newdata = array(
			input_filters(filter_string($_POST['title'])),
			input_filters(filter_string($_POST['head'])),
			input_filters(filter_string($_POST['sign'])),
			filter_int($_POST['sex']),
			input_filters(filter_string($_POST['realname'])),
			input_filters(filter_string($_POST['location'])),
			(filter_int($_POST['birthmonth']) && filter_int($_POST['birthday']) && filter_int($_POST['birthyear'])) ? mktime(0,0,0,filter_int($_POST['birthmonth']),filter_int($_POST['birthday']),filter_int($_POST['birthyear'])) : NULL,
			input_filters(filter_string($_POST['bio'])),
			input_filters(filter_string($_POST['email'])),
			input_filters(filter_string($_POST['youtube'])),
			input_filters(filter_string($_POST['twitter'])),
			input_filters(filter_string($_POST['facebook'])),
			input_filters(filter_string($_POST['dateformat'])),
			input_filters(filter_string($_POST['timeformat'])),
			filter_int($_POST['tzoff']),
			filter_int($_POST['showhead']),
			filter_int($_POST['signsep']),
			$theme
		);
		
		// Prevent 0 posts per page/thread
		if (filter_int($_POST['ppp'])){
			$newdata[] = filter_int($_POST['ppp']);
			$query .= ",ppp=?";
		}
		
		if (filter_int($_POST['tpp'])){
			$newdata[] = filter_int($_POST['tpp']);
			$query .= ",tpp=?";
		}
		
		if (filter_int($_POST['iconrm'])){
			$newdata[] = NULL;
			$query .= ",icon=?";
		}
		
		// new icon stuff
		else if (filter_int($_FILES['newicon']['size'])){
			$newdata[] = imageupload($_FILES['newicon'], $config['max-icon-size-bytes'], $config['max-icon-size-x'], $config['max-icon-size-y']);
			$query .= ",icon=?";
		}
		

		if ($isadmin){
			if (!filter_string($_POST['name'])) errorpage("You forgot the name, doofus!");
			$newdata[] = preg_replace('/[^\da-z]/i', '', $_POST['name']);
			$newdata[] = filter_int($_POST['powerlevel']);
			$newdata[] = filter_int($_POST['coins']);
			$newdata[] = filter_int($_POST['gcoins']);
			//$newdata[] = (filter_int($_POST['banmonth']) && filter_int($_POST['banday']) && filter_int($_POST['banyear'])) ? mktime(0,0,0,filter_int($_POST['banmonth']),filter_int($_POST['banday']),filter_int($_POST['banyear'])) : 0;
			$newdata[] = ($_POST['powerlevel'] == "-1") ? filter_int($_POST['ban_hours'])*3600+ctime() : 0;
			$query .= ",name=?,powerlevel=?,coins=?,gcoins=?,ban_expire=?";
		}
		if (powlcheck(1)){
			$newdata[] = input_filters($_POST['displayname']);
			$newdata[] = input_filters($_POST['namecolor']);
			$query .= ",displayname=?,namecolor=?";
		}
		
		if (filter_string($_POST['pass1']) == filter_string($_POST['pass2']) && filter_string($_POST['pass1'])){
			$newdata[] = password_hash($_POST['pass1'], PASSWORD_DEFAULT);
			$query .= ",password=?";			
		}
		
		$query.= " WHERE id = $id";
		
		$sql->start();
		$c[] = $sql->queryp($query, $newdata);
		if ($sql->finish($c)) errorpage("Profile updated!");
		else errorpage("Couldn't update the profile.");
		
	}
	
	$user = $sql->fetchq("SELECT * FROM users WHERE id = $id");
	
	if (!$user)
		errorpage("This user doesn't exist!");
	
	if ($user['powerlevel']>$loguser['powerlevel'])
		errorpage("not gonna happen");
	
	pageheader($pagetitle);
	
	// Tables here, for edit profile
	
	$fields["Login information"] = array(
	//field name=> type [0 - text, 1 - textbox, 2-radio, 3-listbox, 4-custom], name, description, extra
		"User name" => array(4, "name", ""), // static
		"Password" 	=> array(4, "password", "You can change your password by entering a new one here."), // password field
	);
	
	$fields["Appareance"] = array(
		"Custom title" 	=> array(0, "title", "This title will be shown below your rank."),
		"Post header" 	=> array(1, "head", "This will get added before the start of each post you make. This can be used to give a default font color and face to your posts (by putting a &lt;font&gt; tag). This should preferably be kept small, and not contain too much text or images."),
		"Signature" 	=> array(1, "sign", "This will get added at the end of each post you make, below an horizontal line. This should preferably be kept to a small enough size."),
		"Icon"			=> array(4, "icon", "This will appear next to your username. Select a PNG image to upload."),
	);
	
	$fields["Personal information"] = array(
		"Sex" 		=> array(2, "sex", "Male or female. (or N/A if you don't want to tell it).", "Male|Female|N/A"),
		"Real name" => array(0, "realname", "Your real name (you can leave this blank)."),
		"Location" 	=> array(0, "location", "Where you live (city, country, etc.)."),
		"Birthday"	=> array(4, "birthday", "Your date of birth."), // multi text
		"Bio"		=> array(1, "bio", " Some information about yourself, showing up in your profile."),
	);

	$fields["Online services"] = array(
		"Email address" => array(0, "email", "This is only shown in your profile; you don't have to enter it if you don't want to."),
		"Homepage URL" 	=> array(0, "homepage", "Your homepage URL (must start with the \"http://\"), if you have one."),
		"Homepage Name" => array(0, "homepage_name", "Your homepage name, if you have a homepage."),
		"YouTube" 		=> array(0, "youtube", "Your YouTube username (IE: spudd)."),
		"Twitter" 		=> array(0, "twitter", "Your Twitter username (without the leading @) (IE: jack)."),
		"Facebook" 		=> array(0, "facebook", "Your Facebook ID number or username (IE: john.smith)."),
	);
	
	$fields["Options"] = array(
		"Custom date format" 			=> array(0, "dateformat", "Edit the date format here to affect how dates are displayed. Leave it blank to return to the default format (m-d-y h:i:s A)<br/><a href='http://php.net/manual/en/function.date.php'>See the date() function in the PHP manual</a> for more information."),
		"Custom time format" 			=> array(0, "timeformat", "The time format used to display hours. Leave it blank to return to the default format."),
		"Timezone offset"	 			=> array(0, "tzoff", "How many hours you're offset from the time on the board (".printdate(ctime()).")."),
		"Posts per page"				=> array(0, "ppp", "The maximum number of posts you want to be shown in a page in threads."),
		"Threads per page"	 			=> array(0, "tpp", "The maximum number of threads you want to be shown in a page in forums."),
		"Signatures and post headers"	=> array(2, "showhead", "You can disable them here, which can make thread pages smaller and load faster.", "Disabled|Enabled"),
		"Signature separator"			=> array(3, "signsep", "You can choose from a few signature separators here.", "None|Dashes|Line|Full horizontal line"),
		"Theme"	 						=> array(3, "theme", "You can select from a few themes here.", findthemes(true)),
	);
	
	// powerlevel specific stuff
	
	if (powlcheck(1)){
		$fields["Login information"]["Display name"]= array(0, "displayname", "This will be shown instead of the real handle.");
		$fields["Appareance"]["Name color"] 		= array(0, "namecolor", "Your username will be shown using this color (leave this blank to return to the default color). This is an hexadecimal number.");
	}
	if ($isadmin){
		
		$fields["Login information"]["User name"]	= array(0, "name", "Change the real handle by entering one here.");
		$fields["Login information"]["Powerlevel"]	= array(4, "powerlevel", "");
		//$fields["Login information"]["Banned until"]= array(4, "ban_expire", "");
		$fields["Login information"]["Banned for"]	= array(4, "ban_hours", "");
		$fields["Options"]["Coins"]					= array(0, "coins", "Change the normal coin value.");
		$fields["Options"]["Green coins"]			= array(0, "gcoins", "Admin only coins, increment those whenever you feel like.");
		
	}
	
	
	
	// extra fields
	$name = $user['name'];
	
	$password = "<input type='password' name='pass1' autocomplete='".($edituser ? "off" : "on" )."'> Retype: <input type='password' name='pass2' autocomplete='".($edituser ? "off" : "on" )."'>";
	
	
	$powl[$user['powerlevel']] = "selected";
	
	$powerlevel = "	<select name='powerlevel'>
						<option value='-2' ".filter_string($powl["-2"]).">Permabanned</option>
						<option value='-1' ".filter_string($powl["-1"]).">Banned</option>
						<option value=0 ".filter_string($powl[0]).">Normal User</option>
						<option value=1 ".filter_string($powl[1]).">Privileged</option>
						<option value=2 ".filter_string($powl[2]).">Local Moderator</option>
						<option value=3 ".filter_string($powl[3]).">Global Moderator</option>
						<option value=4 ".filter_string($powl[4]).">Administrator</option>
						".($sysadmin ? "<option value=5 ".filter_string($powl[5]).">Sysadmin</option>" : "")."
					</select>";

					
	$icon = "
	<input type='hidden' name='MAX_FILE_SIZE' value='".$config['max-icon-size-bytes']."'>
	<input name='newicon' type='file'><input type='checkbox' name='iconrm' value=1> Erase<br/>
	<small>Max size: ".$config['max-icon-size-x']."x".$config['max-icon-size-y']." | ".($config['max-icon-size-bytes']/1000)." KB</small>
	";
	
	
	if (isset($user['birthday']))
		$birthval = explode("|", date("n|j|Y", $user['birthday']));
	else $birthval = array("", "", "");
	
	$birthday = "
	Month: <input name='birthmonth' type='text' maxlength='2' size='2' value='$birthval[0]'>
	Day: <input name='birthday' type='text' maxlength='2' size='2' value='$birthval[1]'>
	Year: <input name='birthyear' type='text' maxlength='4' size='4' value='$birthval[2]'>
	";
	
	/*
	Uncomment if you like setting the manual date for bans
	if ($user['ban_expire'])
		$banval = explode("|", date("n|j|Y", $user['ban_expire']));
	else $banval = array("", "", "");
	
	$ban_expire = "
	Month: <input name='banmonth' type='text' maxlength='2' size='2' value='$banval[0]'>
	Day: <input name='banday' type='text' maxlength='2' size='2' value='$banval[1]'>
	Year: <input name='banyear' type='text' maxlength='4' size='4' value='$banval[2]'>
	";
	*/
	$ban_val = ($user['powerlevel'] == "-1") ? floor(($user['ban_expire']-ctime())/3600) : 0;
	$ban_hours = "<input name='ban_hours' type='text' style='width: 50px' value='$ban_val'> hours";

	// build table
	$t = "";
	foreach($fields as $i => $field){
		$t .= "<tr><td class='head c b' colspan=2>$i</td></tr>";
		foreach($field as $j => $data){
			
			$desc = $edituser ? "" : "<br/><small>$data[2]</small>";
			if (!$data[0]) // text box
				$input = "<input type='text' name='$data[1]' value=\"".$user[$data[1]]."\">";
			else if ($data[0] == 1) // large
				$input = "<textarea name='$data[1]' rows='10' cols='80' style='width: 100%; max-width: 800px; resize:vertical;' wrap='virtual'>".htmlspecialchars($user[$data[1]])."</textarea>";
			else if ($data[0] == 2){ // radio
				$ch[$user[$data[1]]] = "checked"; //example $sex[$user['sex']]
				$choices = explode("|", $data[3]);
				$input = "";
				foreach($choices as $i => $x)
					$input .= "<input name='$data[1]' type='radio' value=$i ".filter_string($ch[$i])."> $x";
				unset($ch);
			}
			else if ($data[0] == 3){ // listbox
				$ch[$user[$data[1]]] = "selected";
				$choices = explode("|", $data[3]);
				$input = "";
				foreach($choices as $i => $x)
					$input .= "<option value=$i ".filter_string($ch[$i]).">$x</option>";
				$input = "<select name='$data[1]'>$input</select>";
				unset($ch);
			}
			else
				$input = $$data[1];
				
			$t .= "<tr><td class='light c br'>$j$desc</td><td class='dim b'>$input</td></tr>";
		}
	}

	print "
	<form method='POST' action='editprofile.php".($edituser ? "?id=$id" : "")."' enctype='multipart/form-data'>
	<table class='main w nb'>
		$t
	<tr><td class='head c b' colspan=2>&nbsp;</td></tr>
	<tr><td class='light c br'>&nbsp;</td><td class='dim b'><input type='submit' name='save' value=\"$pagetitle\"></td></tr>
	</table>
	</form>
	";
	
	
	pagefooter();

?>