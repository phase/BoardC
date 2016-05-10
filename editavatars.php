<?php
	
	require "lib/function.php";
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to do this.");
		
	$id = filter_int($_GET['id']);
	
	if (!$id)
		$id = $loguser['id'];

	if (!powlcheck(4) && $id != $loguser['id'])
		errorpage("You're not an admin!");
	
	$user = $sql->fetchq("
		SELECT $userfields
		FROM users u
		WHERE id = $id
	");
	
	if (!$user)
		errorpage("This user doesn't exist.");
	
	if (isset($_GET['del'])){
		$sql->query("DELETE from user_avatars WHERE user=$id AND file = ".filter_int($_GET['del']));
		unlink("userpic/$id/".filter_int($_GET['del']));
		header("Location: editavatars.php?id=$id");
	}	
	
	else if (filter_int($_FILES['newfile']['size'])){

		$title = filter_string($_POST['newtitle']);
		
		if (!$title)
			errorpage("The avatar title cannot be blank.");
		
		$newid = $sql->resultq("SELECT MAX(file) FROM user_avatars WHERE user=$id");
		$newid = filter_int($newid)+1; // in a different line to prevent E_STRICT, +1 to also prevent value 0 (reserved to the default avatar)

		$res = imageupload($_FILES['newfile'], $config['max-avatar-size-bytes'], $config['max-avatar-size-x'], $config['max-avatar-size-y'], "userpic/$id/$newid");
		$sql->queryp("INSERT INTO user_avatars (user, file, title) VALUES (?,?,?)", array($id, $newid, htmlspecialchars(input_filters($title)) ));
		
		header("Location: editavatars.php?id=$id");
	}
	else if (filter_int($_FILES['default']['size'])){

		$res = imageupload($_FILES['default'], $config['max-avatar-size-bytes'], $config['max-avatar-size-x'], $config['max-avatar-size-y'], "userpic/$id/0");
		$sql->query("DELETE FROM user_avatars WHERE user=$id AND file=0");
		$sql->queryp("INSERT INTO user_avatars (user, file, title) VALUES (?,?,?)", array($id, 0, "Default"));
		header("Location: editavatars.php?id=$id");
	}
	else if (isset($_POST["change0"])){	// A common file upload function is simply not enough. these "else if" blocks are almost exactly the same
		if (filter_int($_FILES["new0"]['size']))
			$res = imageupload($_FILES["new0"], $config['max-avatar-size-bytes'], $config['max-avatar-size-x'], $config['max-avatar-size-y'], "userpic/$id/0");

		header("Location: editavatars.php?id=$id");
	}	

	$mood = $sql->query("
		SELECT id, file, title
		FROM user_avatars
		WHERE user = $id
		AND file != 0
		ORDER by file ASC
	");
	

	if (is_file("userpic/$id/0")){
		$img = "<img src='userpic/$id/0'><br/>Reupload: <input type='hidden' name='MAX_FILE_SIZE' value='".$config['max-avatar-size-bytes']."'><input name='new0' type='file'>";
		$cmd = "<input type='submit' name='change0' value='Update'> - <a href='?id=$id&del=0'>Delete</a>";
	}
	else{
		$img = "Upload: <input type='hidden' name='MAX_FILE_SIZE' value='".$config['max-avatar-size-bytes']."'><input name='default' type='file'><br/>
						<small>Max size: ".$config['max-avatar-size-x']."x".$config['max-avatar-size-y']." | ".($config['max-avatar-size-bytes']/1000)." KB</small>";
		$cmd = "<input type='submit' name='newav' value='Upload'>";
	}
	// Always show default avatar table, so you can upload one
	$txt = "<form method='POST' action='editavatars.php?id=$id' enctype='multipart/form-data'>
	<table class='main w c'>
		<tr><td class='head'>User avatars for ".makeuserlink(false, $user, true)."</td></tr>
	</table>
	<table><tr><td class='c' valign='top'>
			<table class='main c'>
				<tr><td class='head'>Default Avatar</td></tr>
				
				<tr><td class='light'>$img</td></tr>
				
				<tr><td class='dark'>$cmd</td></tr>
			</table></td>";
			
	if ($mood){
		for ($i=1; $data = $sql->fetch($mood); ++$i){
			
			if (!isset($data['id'])) break;
			if ($i==4) {
				$txt .= "</tr><tr>";
				$i=0;
			}
			
			// [0.22a] The only way to update the avatar info is here, away from the other upload actions
			if (isset($_POST["change".$data['file']])){
				$title = filter_string($_POST["ren".$data['file']]);
		
				if (!$title)
					errorpage("The avatar title cannot be blank.");
				
				if (filter_int($_FILES["new".$data['file']]['size']))
					$res = imageupload($_FILES["new".$data['file']], $config['max-avatar-size-bytes'], $config['max-avatar-size-x'], $config['max-avatar-size-y'], "userpic/$id/".$data['file']);
				
				$sql->queryp("UPDATE user_avatars SET title = ? WHERE user = $id AND file = ".$data['file'], array(htmlspecialchars(input_filters($title))));
				
				//errorpage("IT WORKS");
				header("Location: editavatars.php?id=$id");
			}
			
			$txt .= "<td class='c'>
				<table class='main c'>
					<tr><td class='head'>".$data['title']."</td></tr>
					
					<tr><td class='light'><img src='userpic/$id/".$data['file']."'><br/>
					Rename: <input type='text' name='ren".$data['file']."' value=\"".$data['title']."\"><br/>
					Reupload: <input type='hidden' name='MAX_FILE_SIZE' value='".$config['max-avatar-size-bytes']."'><input name='new".$data['file']."' type='file'>
					</td></tr>
					
					<tr><td class='dark c'><input type='submit' name='change".$data['file']."' value='Update'> - <a href='?id=$id&del=".$data['file']."'>Delete</a></td></tr>
						
				</table></td>
			";
		}
	}
	
	$txt .= "</tr></table>
			<center><table class='main c'>
				<tr><td class='head'>New Avatar</td></tr>
				
				<tr>
					<td class='light'>
						Title: <input type='text' name='newtitle'><br/>
						Upload: <input type='hidden' name='MAX_FILE_SIZE' value='".$config['max-avatar-size-bytes']."'><input name='newfile' type='file'><br/>
						<small>Max size: ".$config['max-avatar-size-x']."x".$config['max-avatar-size-y']." | ".($config['max-avatar-size-bytes']/1000)." KB</small></td></tr>
				<tr><td class='dark'><input type='submit' name='newav' value='Upload'></td></tr>
			</td></tr></table></center></form>";

	
	pageheader("User Avatars");
	print $txt;

	pagefooter();
?>