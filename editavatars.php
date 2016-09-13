<?php
	
	require "lib/function.php";
	
	if (!$loguser['id'])	errorpage("You need to be logged in to edit your avatars.");
	if ($isbanned) 			errorpage("Banned users aren't allowed to do edit avatars.");
	
	
	$id = filter_int($_GET['id']);
	if (!$id) $id = $loguser['id'];
	
	if (!$isadmin && $id != $loguser['id']){
		errorpage("You aren't allowed to do this!");
	}
	
	$user = $sql->fetchq("SELECT $userfields FROM users u WHERE id = $id");
	if (!$user) errorpage("This user doesn't exist.");
	
	if (filter_int($_FILES['newfile']['size'])){
		/*
			Add a new (non-default) avatar
		*/
		checktoken();
		
		// No blank titles
		$title = filter_string($_POST['newtitle']);
		if (!$title) errorpage("The avatar title cannot be blank.");
		
		// ID to be used for the image name
		$newid = (int) $sql->resultq("SELECT MAX(file) FROM user_avatars WHERE user = $id");
		$newid++; // Increase as value 0 is reserved to the default avatar

		
		$res = imageupload($_FILES['newfile'], $config['max-avatar-size-bytes'], $config['max-avatar-size-x'], $config['max-avatar-size-y'], "userpic/$id/$newid");
		$sql->queryp("
			INSERT INTO user_avatars (user, file, title)
			VALUES (?,?,?)
			", [$id, $newid, htmlspecialchars(input_filters($title))]
		);
		
		redirect("editavatars.php?id=$id");
	}
	else if (filter_int($_FILES['default']['size'])){
		/*
			Add a new default avatar
		*/
		checktoken();
		
		$res = imageupload($_FILES['default'], $config['max-avatar-size-bytes'], $config['max-avatar-size-x'], $config['max-avatar-size-y'], "userpic/$id/0");
		$sql->queryp("INSERT INTO user_avatars (user, file, title) VALUES (?,?,?)", array($id, 0, "Default"));
		redirect("editavatars.php?id=$id");
	}
	else if (isset($_POST["change0"])){
		/*
			Change the default avatar
		*/
		checktoken();
		
		if (filter_int($_FILES["new0"]['size'])){
			$res = imageupload($_FILES["new0"], $config['max-avatar-size-bytes'], $config['max-avatar-size-x'], $config['max-avatar-size-y'], "userpic/$id/0");
			setmessage("Default avatar updated! Please wait a couple of seconds for the changes to take effect.");
		} else {
			setmessage("No default avatar selected.");
		}
		
		redirect("editavatars.php?id=$id");
	}

	$quickmood = $sql->query("SELECT file FROM user_avatars WHERE user = $id");
	while ($i = $sql->fetch($quickmood)){
		
		if (isset($_POST["del{$i['file']}"])){
			/*
				Delete an avatar (including the default one)
				Removes the entry from the database and deletes the image
			*/
			checktoken();
			
			$del = $i['file'];
			$sql->query("DELETE from user_avatars WHERE user = $id AND file = $del");
			unlink("userpic/$id/$del");
			redirect("editavatars.php?id=$id");
		}	
		else if (isset($_POST["change{$i['file']}"]) && $i['file']){
			/*
				Change the non-default avatars ($i['file'] > 0)
			*/
			checktoken();
			
			$i = $i['file']; // A fucking counter shouldn't ever use a long variable
			
			$title = filter_string($_POST["ren$i"]);
			if (!$title) errorpage("The avatar title cannot be blank.");
			
			// Conditional in case you just want to update the avatar name
			if (filter_int($_FILES["new$i"]['size'])){
				$res = imageupload($_FILES["new$i"], $config['max-avatar-size-bytes'], $config['max-avatar-size-x'], $config['max-avatar-size-y'], "userpic/$id/$i");
				setmessage("Avatar '$title' updated! Please wait a couple of seconds for the changes to take effect.");
			} else {
				setmessage("Avatar title updated to '$title'");
			}
			
			$sql->queryp("
				UPDATE user_avatars
				SET title = ?
				WHERE user = $id AND file = $i",
				[ htmlspecialchars(input_filters($title)) ]
			);
			
			
			redirect("editavatars.php?id=$id");
		}
	}

	$maxsize_txt = "<small>Max size: {$config['max-avatar-size-x']}x{$config['max-avatar-size-y']} | ".($config['max-avatar-size-bytes']/1000)." KB</small>";
	
	// Always show default avatar table, so you can upload one
	if (is_file("userpic/$id/0")) {
		$img = "
			<tr>
				<td class='dim avatarbox' style='background-image: url(userpic/$id/0);' colspan=2>
				</td>
			</tr>
			
			<tr>
				<td class='light'>
					Name:
				</td>
				<td class='dim w'>
					<input type='text' value='[Default Avatar]' readonly>
				</td>
			</tr>
			
			<tr>
				<td class='light'>
					Reupload:
				</td>
				<td class='dim w'>
					<input type='hidden' name='MAX_FILE_SIZE' value='{$config['max-avatar-size-bytes']}'>
					<input name='new0' type='file'>
				</td>
			</tr>
		";
		$cmd = "
			<input type='submit' name='change0' value='Update'> - <input type='submit' name='del0' value='Delete'>";
	}
	else {
		$img = "
			<tr>
				<td class='dim avatarbox' colspan=2>
				</td>
			</tr>

			<tr>
				<td class='light'>
					Name:
				</td>
				<td class='dim w'>
					<input type='text' value='[Default Avatar]' readonly>
				</td>
			</tr>
			
			<tr>
				<td class='light'>
					Upload:
				</td>
				<td class='dim w'>
					<input type='hidden' name='MAX_FILE_SIZE' value='{$config['max-avatar-size-bytes']}'>
					<input name='default' type='file'>
				</td>
			</tr>
		";
		$cmd = "<input type='submit' name='newav' value='Upload'>";
	}
	
	
	
	$mood = $sql->query("
		SELECT id, file, title
		FROM user_avatars
		WHERE user = $id AND file != 0
		ORDER by file ASC
	");
	
	$txt = "";
	
	if ($mood) {
		
		while($data = $sql->fetch($mood)) {	
			
			$txt .= "
				<table class='main' style='display: inline-block;'>
				
					<!-- <tr><td class='head c' colspan=2>{$data['title']}</td></tr> -->
					
					<tr>
						<td class='dim avatarbox' style='background-image: url(userpic/$id/{$data['file']});' colspan=2>
						</td>
					</tr>
					
					<tr>
						<td class='light'>
							Rename:
						</td>
						<td class='dim w'>
							<input type='text' name='ren{$data['file']}' value=\"{$data['title']}\">
						</td>
					</tr>
					
					<tr>
						<td class='light'>
							Reupload:
						</td>
						<td class='dim w'>
							<input type='hidden' name='MAX_FILE_SIZE' value='{$config['max-avatar-size-bytes']}'>
							<input name='new{$data['file']}' type='file'>
						</td>
					</tr>
					
					<tr>
						<td class='dark c' colspan=2>
							<input type='submit' name='change{$data['file']}' value='Update'>&nbsp;-
							&nbsp;<input type='submit' name='del{$data['file']}' value='Delete'>
						</td>
					</tr>
						
				</table>
			";
		}
	}
	
	
	pageheader("User Avatars");
	
	
	?>
	<!-- extra global css for avatar tables -->
	<style type='text/css'>
		.avatarbox{
			background-repeat: no-repeat;
			background-position: center;
			min-width: <?php echo $config['max-avatar-size-x'] ?>px;
			height: <?php echo $config['max-avatar-size-y'] ?>px;
		}
	</style>
	<form method='POST' action='editavatars.php?id=<?php echo $id ?>' enctype='multipart/form-data'>
	<input type='hidden' name='auth' value='<?php echo $token ?>'>
	
	<table class='main w c'>
		<tr>
			<td class='head'>
				User avatars for <?php echo makeuserlink(false, $user, true) ?>
			</td>
		</tr>
	</table>
	
	<?php echo $message ?>

	<table class='main' style='display: inline-block;'>
		<!-- <tr><td class='head c' colspan=2>Default Avatar</td></tr> -->
		<?php echo $img ?>
		<tr><td class='dark c' colspan=2><?php echo $cmd ?></td></tr>
	</table>
	
	<?php echo $txt ?>
	
	<center>
	<table class='main'>
	
		<tr>
			<td class='head c' colspan=2>
				New Avatar
			</td>
		</tr>
		
		<tr>
			<td class='light'>
				Title:
			</td>
			<td class='dim'>
				<input type='text' name='newtitle'>
			</td>
		</tr>
		
		<tr>
			<td class='light'>
				Upload:
			</td>
			<td class='dim'>
				<input type='hidden' name='MAX_FILE_SIZE' value='<?php echo $config['max-avatar-size-bytes'] ?>'>
				<input name='newfile' type='file'>
			</td>
		</tr>
		
		<tr>
			<td class='light c' colspan=2>
				<?php echo $maxsize_txt ?>
			</td>
		</tr>
		
		<tr>
			<td class='dark c' colspan=2>
				<input type='submit' name='newav' value='Upload'>
			</td>
		</tr>
		
	</table>
	</center>
	
	</form>	
	<?php
	pagefooter();
?>