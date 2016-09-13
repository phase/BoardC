<?php
	$meta['noindex'] = true;
	
	/*
		unified editprofile and edituser
		
		admin only options appear (and can get saved to the database) based on powerlevel checks
	*/
	require "lib/function.php";
	
	if (!$loguser['id']) 	errorpage("You need to be logged in to do that.");
	if ($isbanned) 			errorpage("Banned users aren't allowed to edit their profile.");
	
	if (isset($_GET['id'])){
		admincheck();
		// Edit another user
		$edituser 	= true;
		$id 		= (int) $_GET['id'];
		$pagetitle 	= "Edit User";
	}
	else{
		// Profile lock
		if ($loguser['profile_locked'])	errorpage("Sorry, but your profile has been locked.");
		
		// Edit your own profile
		$edituser 	= false;
		$id			= $loguser['id'];
		$pagetitle 	= "Edit Profile";

	}
	
	// Do common $_POST here
	if (isset($_POST['save'])){
		
		// Sanity check. Somehow I've forgotten to check for the user being valid here.
		// (which doesn't even matter, as the update query would just fail, but w/e)
		// For the second check though it does matter in a way
		$valid = $sql->fetchq("SELECT id, powerlevel FROM users WHERE id = $id");
		if (!$valid['id']) 									errorpage("This user doesn't exist!");
		if ($valid['powerlevel'] > $loguser['powerlevel'])	errorpage("No.");
		
		checktoken();
		
		// No nonsense
		if (!$sysadmin && filter_int($_POST['powerlevel']) > 4){
			irc_reporter("{$loguser['name']} tried to be funny and attempted to become a root admin.", 1);
			errorpage("
				Be thankful that doing this nonsense doesn't throw an IP ban anymore<br>
				(which was somewhat silly, but at the time the user ban function didn't work)<br>
				<br>
				&nbsp;-Kak
			");
			//ipban("Make me a sysadmin!", "Auto IP banned ".$_SERVER['REMOTE_ADDR']." for attempting to become a sysadmin.");
			//errorpage("A for effort, <s>F for still failing</s> I for IP Ban");
		}
		
		// if an item forces female or catgirl status, prevent a change
		$itemdb = getuseritems($id);
		
		foreach ($itemdb as $item){
			if 		($item['special'] == 1) $_POST['sex'] = 1;
			else if ($item['special'] == 2) $_POST['title'] = "Catgirl"; // ?
			else if ($item['special'] == 4) $_POST['coins'] = $_POST['gcoins'] = 99999999;
			else if ($item['special'] == 5) $_POST['sex'] = 0;
		}
		
		/*
			Build the query
		*/
		$query = "
			UPDATE users SET
			head=?, sign=?, sex=?, realname=?, location=?, birthday=?, bio=?, email=?,
			publicemail=?, youtube=?, twitter=?, facebook=?, dateformat=?, timeformat=?,
			tzoff=?, showhead=?, signsep=?, theme=?, rankset=?
		";
		
		
		// Check if the birthday is valid
		$month 	= filter_int($_POST['birthmonth']);
		$day 	= filter_int($_POST['birthday']);
		$year 	= filter_int($_POST['birthyear']);
		
		$birthday = fieldstotimestamp(0,0,0, $month, $day, $year);
		unset($month, $day, $year);
		
		// Check if the theme exists (and isn't hidden)
		$theme 		= filter_int($_POST['theme']);
		$theme_list = findthemes();
		
		if (!isset($theme_list[$theme])) $theme = 1; // Jul Night
		unset($theme_list);
		

		// H E L P
		$newdata = array(
			prepare_string	($_POST['head']),
			prepare_string	($_POST['sign']),
			filter_int		($_POST['sex']),
			prepare_string	($_POST['realname']),
			prepare_string	($_POST['location']),
							 $birthday,
			prepare_string	($_POST['bio']),
			prepare_string	($_POST['email']),
			filter_int		($_POST['publicemail']),
			prepare_string	($_POST['youtube']),
			prepare_string	($_POST['twitter']),
			prepare_string	($_POST['facebook']),
			prepare_string	($_POST['dateformat']),
			prepare_string	($_POST['timeformat']),
			filter_int		($_POST['tzoff']),
			filter_int		($_POST['showhead']),
			filter_int		($_POST['signsep']),
							 $theme,
			filter_int		($_POST['rankset'])
		);
		
		
		if (can_set_title()) {
			$newdata[] = prepare_string($_POST['title']);
			$query .= ",title=?";
		}

					
		// Prevent 0 posts per page/thread
		if (filter_int($_POST['ppp']) > 0){
			$newdata[] = filter_int($_POST['ppp']);
			$query .= ",ppp=?";
		}
		
		if (filter_int($_POST['tpp']) > 0){
			$newdata[] = filter_int($_POST['tpp']);
			$query .= ",tpp=?";
		}
		
		// Erase minipic
		if (filter_int($_POST['iconrm'])){
			$newdata[] = NULL;
			$query .= ",icon=?";
		}
		
		// Upload minipic (as a base64 encoded image)
		else if (filter_int($_FILES['newicon']['size'])){
			$newdata[] = imageupload($_FILES['newicon'], $config['max-icon-size-bytes'], $config['max-icon-size-x'], $config['max-icon-size-y']);
			$query .= ",icon=?";
		}
		
		if ($isadmin){
			
			if ($edituser){
				
				if (!filter_string($_POST['name'])) errorpage("You forgot the name, doofus!");
				
				// Also flat out warn for this
				if (preg_replace('/[^\da-z ]/i', '', $_POST['name']) != $_POST['name']){
					errorpage("
						The username you entered contains illegal characters.<br>
						Only alphanumeric characters and spaces are allowed.
					");
				}
				
				// [0.29R4] And this. whoops
				$exists = $sql->resultq("SELECT 1 FROM users WHERE name = '{$_POST['name']}' AND id != $id");
				if ($exists) errorpage("This username already exists!");
				
				$newdata[] = $_POST['name'];
				$newdata[] = filter_int($_POST['powerlevel']);
				//$newdata[] = (filter_int($_POST['banmonth']) && filter_int($_POST['banday']) && filter_int($_POST['banyear'])) ? mktime(0,0,0,filter_int($_POST['banmonth']),filter_int($_POST['banday']),filter_int($_POST['banyear'])) : 0;
				$newdata[] = ($_POST['powerlevel'] == "-1") ? filter_int($_POST['ban_hours']) * 3600 + ctime() : 0;
				$newdata[] = filter_int($_POST['profile_locked']);
				$newdata[] = filter_int($_POST['editing_locked']);
				$newdata[] = filter_int($_POST['title_status']);
				$newdata[] = filter_int($_POST['posts']);
				$newdata[] = filter_int($_POST['threads']);
				
				$query .= ",name=?,powerlevel=?,ban_expire=?,profile_locked=?,editing_locked=?,title_status=?,posts=?,threads=?";
				
				// Registration date / time hilarity
				$month 	= filter_int($_POST['sincemonth']);
				$day 	= filter_int($_POST['sinceday']);
				$year 	= filter_int($_POST['sinceyear']);
				$hour 	= filter_int($_POST['sincehour']);
				$min 	= filter_int($_POST['sincemin']);
				$sec 	= filter_int($_POST['sincesec']);
				$since 	= fieldstotimestamp($hour, $min, $sec, $month, $day, $year);
				if ($since){
					$newdata[] = $since;
					$query .= ",since=?";
				}
				unset($since, $month, $day, $year, $hour, $min, $sec);
			}
		
			// $newdata[] = filter_int($_POST['coins']); these are now generated. rip
			// The value shown in the editprofile field silently decrements the spent coins
			// so you have to take them into account here
			$spent = $sql->resultq("SELECT gspent FROM users WHERE id = $id");
			$newdata[] = filter_int($_POST['gcoins'])+$spent;
			$query .= ",gcoins=?";
		}
		
		if ($isprivileged){
			$newdata[] = prepare_string($_POST['displayname']);
			$newdata[] = prepare_string($_POST['namecolor']);
			$query .= ",displayname=?,namecolor=?";
		}
		
		// Change password
		if (filter_string($_POST['pass1']) == filter_string($_POST['pass2']) && filter_string($_POST['pass1'])){
			$newdata[] = password_hash($_POST['pass1'], PASSWORD_DEFAULT);
			$query .= ",password=?";			
		}
		
		$query .= " WHERE id = $id";
		
		$res = $sql->queryp($query, $newdata);
		if ($res) 	errorpage("Profile updated!<br>Click <a href='profile.php?id=$id'>here</a> to view the profile.");
		else 		errorpage("An unknown error occurred while updating the profile.");
		
	}
	
	$user = $sql->fetchq("SELECT *, (gcoins-gspent) gcoins FROM users WHERE id = $id");
	
	if (!$user) 									errorpage("This user doesn't exist!");
	if ($user['powerlevel']>$loguser['powerlevel'])	errorpage("No.");
	
	pageheader($pagetitle);
	
	
	/*
		Format of the tables:
		
		table_format(<name of section>, <array>);
		
		<array> itself is a "list" with multiple arrays. each has this format:
		<title of field> => [<type>, <input name>, <description>, <extra data>]
		
		<title of field> - Text on the left
		
		<type> ID of input field
		Types: 
			0 - Input text. (TODO: All of these have the same width, which does not look great)
			1 - Wide Textarea
			2 - Radio buttons. Uses <extra> for the choices, ie: (No|Yes)
			3 - Listbox. Uses <extra> to get choices. See above.
			4 - Custom. It prints a variable with the same name of <input name>. It's up to you to create the variable.
			
		<input name> Name of the input field.
		<description> Small text shown below the title of the field. NOTE: This is only shown when editing your own profile.
		<extra data> Used for separating lists of elements. The IDs start from 0
			
		table_format automatically appends array elements to the existing group
	
	*/
	
		
	table_format("Login information", array(
		"User name" 	=> [4, "name", ""], // static
		"Password"		=> [4, "password", "You can change your password by entering a new one here."], // password field
	));
	if ($isprivileged) {
		table_format("Login information", array(
			"Display name" 	=> [0, "displayname", "This will be shown instead of the real handle."],
		));
	}
	
	if ($isadmin && $edituser) {
		// Set type from static to input, as an admin should be able to do that.
		$fields["Login information"]["User name"][0] = 0;
		
		// ... and also gets the extra "Administrative bells and whistles"
		table_format("Administrative bells and whistles", array(
			"Power Level" 				=> [4, "powerlevel", ""], // Custom listbox with negative values.
			//"Banned until"] 			=> [4, "ban_expire", ""],
			"Ban for"					=> [4, "ban_hours", ""],
			"Number of posts"			=> [0, "posts", ""],
			"Number of threads"			=> [0, "threads", ""],
			"Registration time"			=> [4, "since", ""],
			"Lock Profile"				=> [2, "profile_locked", "", "Unlocked|Locked"],
			"Restrict Posting"			=> [2, "editing_locked", "", "Unlocked|Lock editing|Lock editing and posting"],
			"Custom Title Privileges" 	=> [2, "title_status", "", "Determine by rank/posts|Enabled|Revoked"],
		));
	}
	

	if (can_set_title()) {
		table_format("Appareance", array(
			"Custom title" => [0, "title", "This title will be shown below your rank."],
		));
	}
	if ($isprivileged) {
		table_format("Appareance", array(
			"Name color" => [0, "namecolor", "Your username will be shown using this color (leave this blank to return to the default color). This is an hexadecimal number.<br>You can use the <a href='hex.php' target='_blank'>Color Chart</a> to select a color to enter here."],
		));
	}
	table_format("Appareance", array(
		"User rank"		=> [3, "rankset", "You can hide your rank, or choose from different sets.", findranks(true)],
		"Post header" 	=> [1, "head", "This will get added before the start of each post you make. This can be used to give a default font color and face to your posts (by putting a &lt;font&gt; tag). This should preferably be kept small, and not contain too much text or images."],
		"Signature" 	=> [1, "sign", "This will get added at the end of each post you make, below an horizontal line. This should preferably be kept to a small enough size."],
		"Icon"			=> [4, "icon", "This will appear next to your username. Select a PNG image to upload."],
	));
	
	
	
	
	table_format("Personal information", array(
		"Sex" 		=> [2, "sex", "Male or female. (or N/A if you don't want to tell it).", "Male|Female|N/A"],
		"Real name" => [0, "realname", "Your real name (you can leave this blank)."],
		"Location" 	=> [0, "location", "Where you live (city, country, etc.)."],
		"Birthday"	=> [4, "birthday", "Your date of birth."],
		"Bio"		=> [1, "bio", " Some information about yourself, showing up in your profile."],
	));

	
	
	table_format("Online services", array(
		"Email address" => [0, "email", "This is only shown in your profile; you don't have to enter it if you don't want to."],
		"Public email" 	=> [2, "publicemail", "You can select a few privacy options for the email field.", "Private|Hide to guests|Public"],
		"Homepage URL" 	=> [0, "homepage", "Your homepage URL (must start with the \"http://\") if you have one."],
		"Homepage Name" => [0, "homepage_name", "Your homepage name, if you have a homepage."],
		"YouTube" 		=> [0, "youtube", "Your YouTube username (IE: spudd)."],
		"Twitter" 		=> [0, "twitter", "Your Twitter username (without the leading @) (IE: jack)."],
		"Facebook" 		=> [0, "facebook", "Your Facebook ID number or username (IE: john.smith)."],
	));
	
	
	
	table_format("Options", array(
		"Custom date format" 			=> [0, "dateformat", "Edit the date format here to affect how dates are displayed. Leave it blank to return to the default format ({$config['default-date-format']})<br><a href='http://php.net/manual/en/function.date.php'>See the date() function in the PHP manual</a> for more information."],
		"Custom time format" 			=> [0, "timeformat", "The time format used to display hours. Leave it blank to return to the default format ({$config['default-time-format']})."],
		"Timezone offset"	 			=> [0, "tzoff", "How many hours you're offset from the time on the board (".printdate(ctime()).")."],
		"Posts per page"				=> [0, "ppp", "The maximum number of posts you want to be shown in a page in threads."],
		"Threads per page"	 			=> [0, "tpp", "The maximum number of threads you want to be shown in a page in forums."],
		"Signatures and post headers"	=> [2, "showhead", "You can disable them here, which can make thread pages smaller and load faster.", "Disabled|Enabled"],
		"Signature separator"			=> [3, "signsep", "You can choose from a few signature separators here.", "None|Dashes|Line|Full horizontal line"],
		"Theme"	 						=> [4, "theme", "You can select from a few themes here."],
	));
	if ($isadmin){
		table_format("Options", array(
			//"Coins" 		=> [0, "coins", "Change the normal coin value."],
			"Green coins" 	=> [0, "gcoins", "Admin only coins, increment those whenever you feel like."],
		));
	}
	
	
	/*
		Custom fields start here
	*/
	
	// Static text for the username (shown when editing your own profile)
	$name = $user['name'];
	
	// Password field + confirmation
	$password = "<input type='password' name='pass1'> Retype: <input type='password' name='pass2'>";
	

	if ($edituser){
		// Powerlevels. The list is custom to restrict access to sysadmin status if you're not a sysadmin.
		// 				That wouldn't work anyway, but still.
		
		$powl[$user['powerlevel']] = "selected";
		
		$powerlevel = "
		<select name='powerlevel'>
							<option value='-2' ".filter_string($powl["-2"]).">{$power_txt['-2']}</option>
							<option value='-1' ".filter_string($powl["-1"]).">{$power_txt['-1']}</option>
							<option value=0 " .  filter_string($powl[0])  . ">{$power_txt[0]   }</option>
							<option value=1 " .  filter_string($powl[1])  . ">{$power_txt[1]   }</option>
							<option value=2 " .  filter_string($powl[2])  . ">{$power_txt[2]   }</option>
							<option value=3 " .  filter_string($powl[3])  . ">{$power_txt[3]   }</option>
							<option value=4 " .  filter_string($powl[4])  . ">{$power_txt[4]   }</option>
			".($sysadmin ? "<option value=5 " .  filter_string($powl[5])  . ">{$power_txt[5]   }</option>" : "")."
		</select>
		";
		
		// Registration date
		
		$since = datetofields($user['since'], 'since').timetofields($user['since'], 'since');
	}
	
	// Upload a new minipic / Remove the existing one
	$icon = "
		<input type='hidden' name='MAX_FILE_SIZE' value='{$config['max-icon-size-bytes']}'>
		<input name='newicon' type='file'>
		<input type='checkbox' name='iconrm' value=1><label for='iconrm'>Erase</label><br>
		<small>
			Max size: {$config['max-icon-size-x']}x{$config['max-icon-size-y']} | ".($config['max-icon-size-bytes']/1000)." KB
		</small>
	";
	
	// Generate day/year/month from the unix timestamp of the birthday
	$birthday = datetofields($user['birthday'], 'birth'); // returns three input boxes (birthmonth, birthday and birthyear)

	/*
		Uncomment if you like setting the manual date for bans
		$ban_expire = datetofields($user['ban_expire'], 'ban');
	*/
	
	// Hours left before the user is unbanned
	$ban_val 	= ($user['powerlevel'] == "-1") ? floor(($user['ban_expire']-ctime())/3600) : 0;
	$ban_hours 	= "<input name='ban_hours' type='text' style='width: 50px' value='$ban_val'> hours";
	
	// The system that was used previously forced all hidden themes to be the last in the database
	// This meant that all forum theme settings would break when normal themes are added (as the hidden themes would be shifted)
	// Stop this nonsense RIGHT NOW
	$theme = dothemelist('theme', false, $user['theme']);

	// This part takes care of formatting the arrays into proper HTML.
	// Do not touch this code, or else the entire table system will (probably) break!
	$t = "";
	foreach($fields as $i => $field){
		$t .= "<tr><td class='head c' colspan=2><b>$i</b></td></tr>";
		foreach($field as $j => $data){
			$desc = $edituser ? "" : "<br><small>$data[2]</small>";
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
				
			$t .= "<tr><td class='light c lh'><b>$j</b>$desc</td><td class='dim'>$input</td></tr>";
		}
	}

	?>
	<form method='POST' action='editprofile.php<?php echo ($edituser ? "?id=$id" : "") ?>' enctype='multipart/form-data'>
	<input type='hidden' name='auth' value='<?php echo $token ?>'>
	<!-- This bit of Inu's HTML was lifted directly from Jul. It's a workaround for the autocomplete. -->
	<input style='display:none' type='text'     name='__f__usernm__'>
	<input style='display:none' type='password' name='__f__passwd__'>
				
	<table class='main w'>
		<?php echo $t ?>
		<tr>
			<td class='head c' colspan=2>
				&nbsp;
			</td>
		</tr>
		
		<tr>
			<td class='light c'>
				&nbsp;
			</td>
			<td class='dim'>
				<input type='submit' name='save' value='<?php echo $pagetitle ?>'>
			</td>
		</tr>
	</table>
	
	</form>
	<?php
	
	
	pagefooter();
	
	// I'd rather have a small function to be used only here than have a long line
	function can_set_title(){
		global $config, $edituser, $loguser, $isadmin, $isprivileged;
		if (
			$isadmin || /* An admin can do whatever */ 
			$edituser || /* Just in case */
			$loguser['title_status'] == 1 || /* 1 = can always change (regardless of restrictions outside profile_lock or being banned)*/ 
			(
				$loguser['title_status'] != 2 && /* 2 = locked (can't change) */
				(
					$isprivileged || /* minimum powerlevel to being always able to change title (that can be still revoked)*/
					$loguser['posts'] >= $config['posts-to-get-title'] /* standard requirement for the title */
				)
			)
		) return true;
		
		return false;
	}
	
	/*
		Used to clean up the table list of array_merge crap
	*/
	function table_format($name, $array){
		global $fields;
		
		if (isset($fields[$name])){
			// Already exists: merge arrays
			$fields[$name] = array_merge($fields[$name], $array);
		} else {
			// It doesn't: Create a new one.
			$fields[$name] = $array;
		}
	}

?>