<?php
	
	require "lib/function.php";
	
	$id 	= filter_int($_GET['id']); // Forum ID (0 for global)
	$action = filter_string($_GET['act']);
	$ismod 	= ismod($id);
	
	// Every single action on this page (that isn't viewing the announcements) requires (local) mod status
	if ($action && !$ismod){
		errorpage("You aren't allowed to do this!");
	}
	
	// Edit goes by announcement ID
	if ($id && $action != 'edit') {
		
		$valid = $sql->resultq("SELECT 1 FROM forums WHERE id = $id");
		if (!$valid || !canviewforum($id)) {
			errorpage("Couldn't view the announcements. Either the forum doesn't exist or you don't have access to it.");
		}
	}
	
	$txt 	= "";
	
	if ($ismod) {
			
		if ($action == 'new') {
			/*
				New announcement
			*/
			
			$name 	= filter_string($_POST['name'], true);
			$title 	= filter_string($_POST['title'], true);
			
			/*
				Text quoted from another PM
			*/		
			
			$quote 	= filter_int($_GET['quote']);

			if ($quote) {
				$quoted = $sql->fetchq("
					SELECT a.text, u.name, a.forum
					FROM announcements a
					LEFT JOIN users u ON a.user = u.id
					WHERE a.id = $quote
				");
				
				if ($quoted && canviewforum($quoted['forum'])) {
					$msg = "[quote={$quoted['name']}]{$quoted['text']}[/quote]";
				} else {
					$msg = "";
				}
				
			} else {
				$msg = isset($_POST['message']) ? $_POST['message'] : "";
			}
			
			if (isset($forum['theme'])) $loguser['theme'] = (int) $forum['theme'];
		
			if (isset($_POST['submit'])) {
				checktoken();
				
				if (!$msg)	errorpage("You've written an empty announcement!");
				if (!$name)	errorpage("The announcement name can't be blank!");
				
				
				$sql->start();
				
				$a 	 = $sql->prepare("
					INSERT INTO announcements (name, title, user, time, text, nohtml, nosmilies, nolayout, avatar, forum) VALUES 
					(?,?,?,?,?,?,?,?,?,?)");
				$c[] = $sql->execute($a, [
					input_filters($name),
					input_filters($title),
					$loguser['id'],
					ctime(),
					input_filters($msg),
					filter_int($_POST['nohtml']),
					filter_int($_POST['nosmilies']),
					filter_int($_POST['nolayout']),
					filter_int($_POST['avatar']),
					$id
				]);
				$sql->query("INSERT INTO announcements_read () VALUES ()");
				if ($sql->finish($c)){
					setmessage("Announcement created!");
					header("Location: announcement.php?id=$id");
					x_die();
				}
				else errorpage("Couldn't create the announcement.");
			}

			pageheader("New announcement");
			
			if (isset($_POST['preview'])) {
				
				$data = array(
					'id' 		=> $sql->resultq("SELECT MAX(id) FROM announcements") + 1,
					'user' 		=> $loguser['id'],
					'ip' 		=> $loguser['lastip'],
					'deleted' 	=> 0,
					'text' 		=> $msg,
					'rev' 		=> 0,
					'time' 		=> ctime(),
					'nolayout' 	=> filter_int($_POST['nolayout']),
					'nosmilies' => filter_int($_POST['nosmilies']),
					'nohtml' 	=> filter_int($_POST['nohtml']),
					'avatar' 	=> filter_int($_POST['avatar']),
					'new'		=> 0,
					'noob'		=> 0
				);
				
				$ranks 		= doranks($loguser['id'], true);
				$layouts	= loadlayouts($loguser['id'], true);
				
				?>
				
				<table class='main w c'>
					<tr>
						<td class='head' style='border-bottom: none;'>
							Announcement Preview
						</td>
					</tr>
				</table>
				
				<?php
				print threadpost(array_merge($loguser,$data), false, false, true, false, true);
			}
			
			
			
			$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
			$nohtmlc 	= isset($_POST['nohtml']) 	 ? "checked" : "";
			$nolayoutc 	= isset($_POST['nolayout'])  ? "checked" : "";
			
			$fname = $id ? " <a href='forum.php?id=$id'>".$sql->resultq("SELECT name FROM forums WHERE id = $id")."</a> -" : "";
			
			
			print "<a href='index.php'>".$config['board-name']."</a> -$fname <a href='announcement.php?id=$id'>Announcements</a> - New announcement";
			?>
			<form action='announcement.php?act=new&id=<?php echo $id ?>' method='POST'>
			<input type='hidden' name='auth' value='<?php echo $token ?>'>
			
				<table class='main w'>
				
					<tr>
						<td class='head' style='width: 150px'>
							&nbsp;
						</td>
						<td class='head'>
							&nbsp;
						</td>
					</tr>
					
					
					<tr>
						<td class='light c'>
							<b>Name:</b>
						</td>
						<td class='dim'>
							<input name='name' value="<?php echo htmlspecialchars($name) ?>" size='60' type='text'>
						</td>
					</tr>
					
					<tr>
						<td class='light c'>
							<b>Title:</b>
						</td>
						<td class='dim'>
							<input name='title' value="<?php echo htmlspecialchars($title) ?>" size='60' type='text'>
						</td>
					</tr>	
					
					<tr>
						<td class='light c'>
							<b>Message:</b>
						</td>
						<td class='dim' style='width: 806px; border-right: none'>
							<textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'><?php echo htmlspecialchars($msg) ?></textarea>
						</td>
					</tr>
					
					
					<tr>
						<td class='light'>
							&nbsp;
						</td>
						<td class='dim'>
							<input type='submit' value='Create announcement' name='submit'>&nbsp;
							<input type='submit' value='Preview announcement' name='preview'>&nbsp;
							<input type='checkbox' name='nohtml'    value=1 <?php echo $nohtmlc    ?>><label for='nohtml'>Disable HTML</label>
							<input type='checkbox' name='nolayout'  value=1 <?php echo $nolayoutc  ?>><label for='nolayout'>Disable Layout</label>
							<input type='checkbox' name='nosmilies' value=1 <?php echo $nosmiliesc ?>><label for='nosmilies'>Disable Smilies</label>
							<?php  echo getavatars($loguser['id'], filter_int($_POST['avatar'])) ?>
						</td>
					</tr>
					
				</table>
			</form>
			<?php
			
			pagefooter();
		}
		else if ($action == 'edit'){
			
			/*
				Edit announcement
			*/
			
			if (!$id)	 errorpage("No announcement ID specified.");
			

			if (isset($forum['theme'])) $loguser['theme'] = (int) $forum['theme'];
			
			$skiplayout = $loguser['showhead'] ? "" : "NULL";
			
			$post = $sql->fetchq("
				SELECT 	a.id, a.text, a.name aname, a.title atitle, a.time, a.rev, a.user,
						a.forum, a.nohtml, a.nosmilies, a.nolayout, a.avatar, o.time rtime,
						a.lastedited,
						$skiplayout u.head, $skiplayout u.sign, u.lastip ip, $userfields uid, u.class,
						u.title, u.posts, u.since, u.location, u.lastview, u.lastpost, 0 new, 0 deleted, u.rankset
				FROM announcements a
				
				LEFT JOIN users             u ON a.user = u.id
				LEFT JOIN announcements_old o ON a.id    = (SELECT MAX('o.id') FROM announcements_old o WHERE o.aid = a.id)
				
				WHERE a.id = $id
			");
			
			if (!$post || !canviewforum($post['forum'])){
				errorpage("This announcement doesn't exist.");
			}

			$name 	= isset($_POST['aname']) 	? $_POST['aname']   : $post['aname'];
			$title 	= isset($_POST['atitle']) 	? $_POST['atitle']  : $post['atitle'];
			$msg 	= isset($_POST['message']) 	? $_POST['message'] : $post['text'];
			

			
			if (isset($_POST['submit'])){
				checktoken();
				
				if (!$msg) 	errorpage("You've edited the announcement message to be blank!");
				if (!$name) errorpage("You've edited the announcement name to be blank!");
				
				
				$sql->start();
				
				/*
					Make a backup of the old announcement
				*/
				$bak  = $sql->prepare("
					INSERT INTO announcements_old (aid ,name , title, text, time, rev, nohtml, nosmilies, nolayout, avatar)
					VALUES (?,?,?,?,?,?,?,?,?,?)
				");
				$c[] = $sql->execute($bak, [
					$post['id'],
					$post['name'],
					$post['title'],
					$post['text'],
					$post['time'],
					$post['rev'],
					$post['nohtml'],
					$post['nosmilies'],
					$post['nolayout'],
					$post['avatar']
				]);
				
				/*
					...and then edit the new one
				*/				
				$a = $sql->prepare("
					UPDATE announcements SET
						name		= ?,
						title		= ?,
						text		= ?,
						time		= ".ctime().",
						rev			= ".($post['rev']+1).",
						nohtml		= ".filter_int($_POST['nohtml']).",
						nosmilies	= ".filter_int($_POST['nosmilies']).",
						nolayout	= ".filter_int($_POST['nolayout']).",
						lastedited	= {$loguser['id']},
						avatar		= ".filter_int($_POST['avatar'])."
					WHERE id = $id
				");
				

				$c[] = $sql->execute($a, [
					prepare_string($name),
					prepare_string($title),
					prepare_string($msg)
				]);
				
				if ($sql->finish($c)){
					setmessage("The announcement has been edited successfully.");
					header("Location: announcement.php?id={$post['forum']}");
					x_die();
				}
				else errorpage("Couldn't edit the announcement.");
			}
			
			pageheader("Edit announcement");
			
			
			if (isset($_POST['preview'])) {
				
				$postids = getpostcount($post['user'], true);
				
				$data = array(
					'rev' 		=> $post['rev'] + 1,
					'text' 		=> $msg,
					'nolayout' 	=> filter_int($_POST['nolayout']),
					'nosmilies' => filter_int($_POST['nosmilies']),
					'nohtml' 	=> filter_int($_POST['nohtml']),
					'postcur' 	=> array_search($post['id'], $postids[$post['user']])+1,
					'crev' 		=> $post['rev'] + 1,
					'time' 		=> ctime(),
					'rtime' 	=> $post['time'],
					'lastedited'=> $loguser['id'],
					'avatar'	=> filter_int($_POST['avatar']),
					'noob'		=> 0
				);
				
				$ranks 		= doranks($post['user'], true);
				$layouts	= loadlayouts($post['user'], true);
				
				?>
				<table class='main w'>
					<tr>
						<td class='head c' style='border-bottom: none'>
							Announcement Preview
						</td>
					</tr>
				</table>
				<?php
				print threadpost(array_merge($post, $data), false, false, true, false, false, true);
				
				// Preserve announcement options
				$nsm = filter_int($_POST['nosmilies']);
				$nht = filter_int($_POST['nohtml']);
				$nly = filter_int($_POST['nolayout']);
				$cha = filter_int($_POST['avatar']);
				
				
			}
			else {
				
				$nsm = $post['nosmilies'];
				$nht = $post['nohtml'];
				$nly = $post['nolayout'];
				$cha = $post['avatar'];
				
			}
			
			$nosmiliesc = $nsm ? "checked" : "";
			$nohtmlc 	= $nht ? "checked" : "";
			$nolayoutc 	= $nly ? "checked" : "";

			$fname = $id ? " <a href='forum.php?id=$id'>".$sql->resultq("SELECT name FROM forums WHERE id = $id")."</a> -" : "";
			
			// used to be maxlength='100'
			print "<a href='index.php'>".$config['board-name']."</a> -$fname <a href='announcement.php?id=$id'>Announcements</a> - Edit announcement";
			?>
			<form action='announcement.php?act=edit&id=<?php echo $id ?>'  method='POST'>
			<input type='hidden' name='auth' value='<?php echo $token ?>'>
			
				<table class='main w'>
				
					<tr>
						<td class='head' colspan=2>
							Edit announcement
						</td>
					</tr>
					
					
					<tr>
						<td class='light c' style='width: 150px'>
							<b>Name:</b>
						</td>
						<td class='dim'>
							<input name='name' value="<?php echo htmlspecialchars($name) ?>" size='60' type='text'>
						</td>
					</tr>
					
					<tr>
						<td class='light c'>
							<b>Title:</b>
						</td>
						<td class='dim'>
							<input name='title' value="<?php echo htmlspecialchars($title) ?>" size='60' type='text'>
						</td>
					</tr>	
					
					<tr>
						<td class='light c'>
							<b>Message:</b>
						</td>
						<td class='dim' style='width: 806px; border-right: none'>
							<textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'><?php echo htmlspecialchars($msg) ?></textarea>
						</td>
					</tr>
					
					
					<tr>
						<td class='light'>
							&nbsp;
						</td>
						<td class='dim'>
							<input type='submit' value='Edit announcement' name='submit'>&nbsp;
							<input type='submit' value='Preview announcement' name='preview'>&nbsp;
							<input type='checkbox' name='nohtml'    value=1 <?php echo $nohtmlc    ?>><label for='nohtml'>Disable HTML</label>
							<input type='checkbox' name='nolayout'  value=1 <?php echo $nolayoutc  ?>><label for='nolayout'>Disable Layout</label>
							<input type='checkbox' name='nosmilies' value=1 <?php echo $nosmiliesc ?>><label for='nosmilies'>Disable Smilies</label>
							<?php  echo getavatars($post['user'], $cha) ?>
						</td>
					</tr>
				
				</table>
			</form>
			<?php
			
			pagefooter();
		}
		else if (isset($_GET['del'])) {
			/*
				Directly delete an announcement
				There is no marker for deleted announcements, so they get directly deleted.
			*/
			$del = intval($_GET['del']);
			
			// Check both validity and correct forum
			$valid = $sql->resultq("
				SELECT id
				FROM announcements
				WHERE id = $del AND forum = $id
			");
			
			if (!$valid) {
				errorpage("The announcement ID is invalid.");
			}
			
			
			if (isset($_POST['return'])){
				
				header("Location: announcement.php?id=$id");
				x_die();
				
			} else if (isset($_POST['dokill'])) {
				checktoken();
				
				$sql->query("
					DELETE FROM announcements, announcents_old
					LEFT JOIN announcements_old ON announcements.id = announcents_old.aid
					WHERE announcements.id = $del
				");
				setmessage("Announcement deleted!");
				header("Location: announcement.php?id=$id");
				x_die();
			}
			
			$fname = $sql->resultq("SELECT name FROM forums WHERE id = $id");
			pageheader("$fname - Delete announcement");
			
			?>
				<center>
				<form method='POST' action='<?php echo "announcement.php?id=$id&del=$del" ?>'>
				<input type='hidden' name='auth' value='<?php echo $token ?>'>
				
				<table class='main c'>
				
					<tr><td class='head'>Delete Announcement</td></tr>
					
					<tr>
						<td class='light'>
							Are you sure you want to delete this announcement (ID #<?php echo $del ?>) from '<?php echo $fname ?>'?<br>
							<small>This is a permanent action!</small>
						</td>
					</tr>
					
					<tr>
						<td class='dim'>
							<input type='submit' name='dokill' value='Yes'>&nbsp;<input type='submit' name='return' value='No'>
						</td>
					</tr>
				
				</table>
				
				</form>
				</center>
			<?php
			
			pagefooter();
		}	
	}
	
	/*
		Get all announcements for the forum
	*/
	$page = filter_int($_GET['page']);
	
	
	
	$new_check = $loguser['id'] ? "(a.time > n.user{$loguser['id']})" : "(a.time > ".(ctime()-300).")";

	$ann = $sql->query("
	
		SELECT  a.id, a.name aname, a.title atitle, a.user, a.time, a.text, 0 deleted, a.nohtml,
				a.nosmilies, a.nolayout, a.avatar, a.lastedited, a.rev, 0 noob, o.time rtime,
				$userfields uid, u.title, u.posts, u.since, u.location, u.lastview, u.class,
				u.lastip ip, u.lastpost, $new_check new, u.rankset
		FROM announcements a
		
		LEFT JOIN users              u ON a.user = u.id
		LEFT JOIN announcements_old  o ON o.time = (SELECT MIN(o.time) FROM announcements_old o WHERE o.aid = a.id)
		LEFT JOIN announcements_read n ON a.id    = n.id
		
		WHERE a.forum = $id
		GROUP BY a.id DESC
		LIMIT ".($page*$loguser['ppp']).", ".$loguser['ppp']."
		
	");
	
	if ($id){
		$fname 	= $sql->resultq("SELECT name FROM forums WHERE id = $id");
		$s  	= " - <a href='forum.php?id=$id'>$fname</a>";
	}
	else $fname = $s = "";
	
	/*
		Display announcements
	*/
	if ($ann){
		// Page number stuff
		$count 		= $sql->resultq("SELECT COUNT(id) FROM announcements WHERE forum = $id");
		$pagectrl 	= dopagelist($count, $loguser['ppp'], "announcement");
		
		$ranks		= doranks($id, false, true);
		$layouts	= loadlayouts($id, false, true);
		
		while ($a = $sql->fetch($ann)) {
			
			// Collect announcement IDs to update the last view table
			if ($a['new']) {
				$id_list[] = $a['id'];
			}
			
			
			if ($ismod){
				/*
					Fetch old version of the announcement
				*/
				if (isset($_GET['rev']) && filter_int($_GET['pin']) == $a['id']){
					$a = array_replace($a, $sql->fetchq("
						SELECT text, rev crev, time, nohtml, nosmilies, nolayout, avatar
						FROM announcements_old
						WHERE aid = {$a['id']} AND rev = ".filter_int($_GET['rev'])
					));
				}
				
			}
			
			
			// Insert name and title at the top of the annoucement, along with a hr separator
			$a['text'] = "<center><b>{$a['aname']}</b><br><small>{$a['atitle']}</small></center><hr class='w'>{$a['text']}";
			
			$txt .= threadpost($a, false, false, $ismod ? false : true, false, false, true);

		}
		
		if (isset($id_list))
			$sql->query("UPDATE announcements_read SET user{$loguser['id']} = ".ctime()." WHERE id IN (".implode(",", $id_list).")");
	}

	else{
		$txt .= "
	<table class='main w c'>
		<tr>
			<td class='light'>
				There are no announcements under this section.".($ismod ? "<br>To create a new one, click <a href='announcement.php?act=new&id=$id'>here</a>." : "")."
			</td>
		</tr>
	</table>";
		$pagectrl = "";
	}
	

	$newann = $ismod ? "<table class='w'><tr><td style='text-align: right'><a href='announcement.php?act=new&id=$id'>New announcement</a></td></tr></table>" : "";
	
	pageheader("Announcements".($id ? " - $fname" : ""));
	
	print "
	<table class='w'>
		<tr>
			<td><a href='index.php'>{$config['board-name']}</a>$s - Announcements</td>
			<td style='text-align: right'>$newann</td>
		</tr>
	</table>
	
	$pagectrl
	<table class='main w'>
		<tr>
			<td class='head c' style='width: 200px'>User</td>
			<td class='head c'>Announcement</td>
		</tr>
	</table>
	$txt
	
	<table class='w'>
		<tr>
			<td>$pagectrl</td>
			<td style='text-align: right'>$newann</td>
		</tr>
	</table>
	";
	
	pagefooter();

?>