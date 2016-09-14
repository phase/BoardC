<?php

/*
	[0.20] Unified PM page
	featuring more HTML adapted from Jul to mimic the UI (again)
*/
	
	require "lib/function.php";
	
	if (!$loguser['id']) errorpage("You need to be logged in to read / send private messages!");
	if ($ispermabanned)  errorpage("<s>SORRY, BUT</s> YOU BLEW IT!<br><small>EDIT: We're not sorry.</small>");
	
	$action = filter_string($_GET['act']);
	$id 	= filter_int($_GET['id']);
	$page	= filter_int($_GET['page']);
	
	/*
		the send action doesn't use an ID
		the view action uses the ID for the pm ID
	*/
	if ($action == 'sent' || !$action){
		if (!$id || $id == $loguser['id']){
			$id = $loguser['id'];
			$id_txt = ""; // hide this if you're viewing your own pms
		} else {
			admincheck();
			$id_txt = "&id=$id";
		}
	}
	
	$txt 		= "";
	$pmcount	= 0;
	
	if ($action == 'send')
	{
		
		/*
			Get the quoted PM if you can see it
		*/
		$quote = filter_int($_GET['quote']);

		if ($quote){
			$quoted = $sql->fetchq("
				SELECT p.text, p.title, p.name, u.name username, u.displayname
				FROM pms p
				LEFT JOIN users u ON p.user = u.id
				WHERE p.id = $quote AND (p.userto = {$loguser['id']} OR p.user = {$loguser['id']})
			");
		} else {
			$quoted = NULL;
		}
		
		/*
			Add the quoted text if it's valid
		*/
		if ($quoted) {
			$sendto = $quoted['username'];
			$msg 	= "[quote={$quoted['username']}]{$quoted['text']}[/quote]";
			$name 	= "Re: {$quoted['name']}";
			$title 	= $quoted['title'];
		} else {
			if ($id) {
				$sendto = $sql->resultq("SELECT name FROM users WHERE id = $id");
			} else {
				$sendto = isset($_POST['sendto']) 	? $_POST['sendto'] 	: "";
			}
			$msg 	= isset($_POST['message']) 		? $_POST['message'] : "";
			$name 	= isset($_POST['name']) 		? $_POST['name'] 	: "";
			$title 	= isset($_POST['title']) 		? $_POST['title'] 	: "";
		}
		
		
		if (isset($_POST['submit'])){
			checktoken();
			
			if (!$msg)		errorpage("You've written an empty message!");
			if (!$name)		errorpage("You've written an empty subject!");
			
			$userto = $sql->resultp("SELECT id FROM users WHERE name = ?", [prepare_string($_POST['sendto'])]);
			if (!$userto)	errorpage("This user doesn't exist!");
			
			$sql->start();
			$a 	= $sql->prepare("
				INSERT INTO pms (name, title, user, userto, time, text, nohtml, nosmilies, nolayout, avatar) VALUES
				(
					?,
					?,
					{$loguser['id']},
					$userto,
					".ctime().",
					?,
					".filter_int($_POST['nohtml']).",
					".filter_int($_POST['nosmilies']).",
					".filter_int($_POST['nolayout']).",
					".filter_int($_POST['avatar'])."
				)");
			$c[] 	= $sql->execute($a, [
				prepare_string($name),
				prepare_string($title),
				prepare_string($msg)
			]);
			
			if ($sql->finish($c)) redirect("private.php");
			else errorpage("Couldn't send the PM.");
		}		
		
		
		pageheader("New PM");
		
		if (isset($_POST['preview'])){
			
			$data = array(
				'id' 		=> $sql->resultq("SELECT MAX(id) FROM pms")+1,
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
				'new' 		=> 0,
				'noob'		=> 0				
			);
			
			$ranks 		= doranks($loguser['id'], true);
			$layouts	= loadlayouts($loguser['id'], true);
			
			?>
			<table class='main w c'>
				<tr>
					<td class='head' style='border-bottom: none;'>
						PM Preview
					</td>
				</tr>
			</table>
			<?php
			
			print threadpost(array_merge($loguser,$data), false, false, true, false, true);
		}
		
		$nosmiliesc = isset($_POST['nosmilies']) ? "checked" : "";
		$nohtmlc 	= isset($_POST['nohtml']) 	 ? "checked" : "";
		$nolayoutc 	= isset($_POST['nolayout'])  ? "checked" : "";
		
		// used to be maxlength='100'
		?>
		<a href='index.php'><?php echo $config['board-name'] ?></a> - <a href='private.php'>Private messages</a> - Compose PM
		<form action='private.php?act=send' method='POST'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
		<table class='main w'>
		
			<tr><td class='head' style='width: 150px'>&nbsp;</td><td class='head'>&nbsp;</td></tr>
			
			<tr>
				<td class='light c'>
					<b>Send to:</b><br>
					<small>Enter the real handle</small>
				</td>
				<td class='dim'>
					<input name='sendto' value="<?php echo htmlspecialchars($sendto) ?>" size='30' maxlength='25' type='text'>
				</td>
			</tr>
			
			<tr>
				<td class='light c'><b>Subject:</b></td>
				<td class='dim'>
					<input name='name' value="<?php echo htmlspecialchars($name) ?>" size='60' type='text'>
				</td>
			</tr>
			
			<tr>
				<td class='light c'><b>Title:</b></td>
				<td class='dim'><input name='title' value="<?php echo htmlspecialchars($title) ?>" size='60' type='text'></td>
			</tr>
			
			<tr>
				<td class='light c'><b>Message:</b></td>
				<td class='dim' style='width: 806px; border-right: none'>
					<textarea name='message' rows='21' cols='80' style='width: 100%; width: 800px; resize:both;' wrap='virtual'><?php
						echo htmlspecialchars($msg)
					?></textarea>
				</td>
			</tr>
			
			<tr>
				<td class='light'></td>
				<td class='dim'>
					<input type='submit' value='Send message' name='submit'>&nbsp;
					<input type='submit' value='Preview message' name='preview'>&nbsp;
					<input type='checkbox' name='nohtml'    value=1 <?php echo $nohtmlc    ?>>Disable HTML&nbsp;
					<input type='checkbox' name='nolayout'  value=1 <?php echo $nolayoutc  ?>>Disable Layout&nbsp;
					<input type='checkbox' name='nosmilies' value=1 <?php echo $nosmiliesc ?>>Disable Smilies&nbsp;
					<?php echo getavatars($loguser['id'], filter_int($_POST['avatar'])) ?>
				</td>
			</tr>
		</table>
		
		</form>
		<?php
		
		pagefooter();
	}
	else if ($action == 'view')
	{
		
		if (!$id) errorpage("No PM specified.");
		
		
		$post = $sql->fetchq("
			SELECT  p.id, p.name pmname, p.user, p.userto, p.time, p.text, p.nohtml,
					p.nosmilies, p.nolayout, p.avatar, p.new,
					$userfields uid, u.title, u.head, u.sign, u.posts, u.since,
					u.location, u.lastview, u.lastpost, u.rankset, u.class
			FROM pms p
			LEFT JOIN users u ON p.user = u.id
			WHERE p.id = $id 
		");
		
		// A nonexisting PM will trigger this anyway for non-admins since $post is going to be empty
		if (!$isadmin && $post['userto'] != $loguser['id'] && $post['user'] != $loguser['id'])
			errorpage("This private message isn't for you!");
		
		if (!$post['id'])
			errorpage("A private message with ID #$id doesn't exist.");
		
		if (($post['userto'] == 1 || $post['user'] == 1) && !in_array($loguser['id'], [$post['user'],$post['userto']]) )
			errorpage("No.");

		$pmname = htmlspecialchars($post['pmname']);
		$s = "<a href='index.php'>{$config['board-name']}</a> - <a href='private.php'>Private messages</a> - $pmname";
		
		$data = array(
			'ip' 		=> $loguser['lastip'],
			'deleted' 	=> 0,
			'rev' 		=> 0,
			'noob'		=> 0
		);
		
		if ($post['new'] && $post['userto'] == $loguser['id']) {
			$sql->query("UPDATE pms SET new = 0 WHERE id = $id");
		} else {
			$post['new'] = 0; // Don't show 'NEW' marker if you're not the user who received the PM
		}
		
		$ranks 		= doranks($post['user'], true);
		$layouts[$post['user']] = array(
			'head'	=> output_filters($post['head'], false, $post['user']),
			'sign'	=> output_filters($post['sign'], false, $post['user'])
		);
		
		pageheader("Private Messages: $pmname");
		print $s.threadpost(array_merge($post,$data), false, false, false, false, true).$s;
		
		pagefooter();
	}
	/*
		Viewing the sent and received messages is basically the same action.
		Only few strings (and the field in the where/join clause) actually change.
	*/
	else if ($action == 'sent') {
		$pmtype 	= "Outbox";
		$from 		= "To";
		$linkswitch = "<a href='private.php?$id_txt'>View received messages</a>";
		$inorout 	= "p.user";
		$userlink 	= "p.userto";
	}
	else {
		$pmtype 	= "Inbox";
		$from 		= "From";
		$linkswitch = "<a href='private.php?act=sent$id_txt'>View sent messages</a>";
		$inorout 	= "p.userto";
		$userlink 	= "p.user";
	}
	
	if ( (!$isadmin && $loguser['id'] != $id) || ($id == 1 && $loguser['id'] != 1) )
		errorpage("No.");
	
	$pms = $sql->query("
		SELECT  p.id pid, p.name pmname, p.title pmtitle, p.user, p.userto,
				p.time, p.new, $userfields
		FROM pms p
		
		LEFT JOIN users u ON $userlink = u.id
		
		WHERE $inorout = $id
		ORDER by p.id DESC
		
		LIMIT ".($page*$loguser['tpp']).", {$loguser['tpp']}
	");
	
	while($pm = $sql->fetch($pms)){
		$txt .= "
		<tr>
			<td class='light c'>
				".($pm['new'] && $pmtype == "Inbox" ? "<img src='{$IMG['statusfolder']}/new.gif'>" : "")."
			</td>
			<td class='dim lh'>
				<a href='private.php?act=view&id={$pm['pid']}'>
					".htmlspecialchars($pm['pmname'])."
				</a>
				".($pm['pmtitle'] ? "<br><small>".htmlspecialchars($pm['pmtitle'])."</small>" : "")."
			</td>
			<td class='dim c'>".makeuserlink(false, $pm)."</td>
			<td class='dim c'>".printdate($pm['time'])."</td>
		</tr>";
	}
	
	$pmcount  = $sql->resultq("SELECT COUNT(p.id) FROM pms p WHERE $inorout = $id");
	$pagectrl = dopagelist($pmcount, $loguser['tpp'], "private", ($action == 'sent' ? "&act=sent" : ""));
	
	pageheader("Private Messages");
	
	?>
	<table class='w'>
		<tr>
			<td>
				<a href='index.php'><?php echo $config['board-name'] ?></a> - 
				<?php echo "Private messages ".($id_txt ? "for ".makeuserlink($id)." " : "")."- $pmtype: $pmcount" ?>
			</td>
			<td class='fonts' align='right'>
				<?php echo "$linkswitch | <a href='private.php?act=send$id_txt'>Send new message".($id_txt ? " to this user" : "")."</a>" ?>
			</td>
		</tr>
	</table>
	<?php echo $pagectrl ?>
	<table class='main w'>
		<tr class='c'>
			<td class='head' style='width:50px'>&nbsp;</td>
			<td class='head'>Subject</td>
			<td class='head' style='width:15%'>$from</td>
			<td class='head' style='width:180px'>Sent on</td>
		</tr>
		<?php echo $txt ?>
	</table>
	<?php
	echo $pagectrl;

	
	pagefooter();
?>