<?php

	/*
		News editor v0.01 -- 30/08/16
		Edits the contents in the news table
		
		Currently contains:
			- Editor  (update name, text and tags)
			- Delete  (hides posts unless you're an admin)
			- Erase   (removes from the database - requres admin privileges)
			- Creator (Create new news)
	*/
	
	require "lib/function.php";
	require "lib/news_function.php";
	
	if (!$canwrite){
		errorpage("
			Sorry, but you're not allowed to edit the news.<br>
			Click <a href='news.php'>here</a> to return to the news main page.
		");	
	}
	
	$id	= filter_int($_GET['id']);
	
	
	if (isset($_GET['edit'])){
		
		if (!$id) errorpage("No news ID specified.");
		
		$news = $sql->fetchq("
			SELECT n.id, n.name newsname, text, time, user, hide, cat, hide, lastedituser, lastedittime, $userfields uid
			FROM news n
			LEFT JOIN users u ON n.user = u.id
			WHERE n.id = $id
		");
		
		if (!$news) 										errorpage("The post doesn't exist!");
		if (!$isadmin && $loguser['id'] != $news['user'])	errorpage("You have no permission to do this!");
		
		$name = isset($_POST['nname'])	? $_POST['newsname'] : $news['newsname'];
		$text = isset($_POST['text'])	? $_POST['text']	 : $news['text'];
		$tags = isset($_POST['cat'])	? $_POST['cat']		 : $news['cat'];
		
		if (isset($_POST['submit'])){
			checktoken();
			
			if (!$name || !$text) errorpage("You have left one of the required fields blank!");
			
			// Prevent creation of tags without alphanumeric characters
			$taglist = explode(";", $tags);
			foreach($taglist as $tag)
				if (alphanumeric($tag) != $tag)
					errorpage("One of the tags contains non-alphanumeric characters.");
			
			// Here we go
			$sql->queryp(
				"UPDATE news SET name = ?, text = ?, cat = ?, lastedituser = ?, lastedittime = ? WHERE id = $id",
				[$name, $text, $tags, $loguser['id'], ctime()]
			);
			header("Location: news.php?id=$id");
			x_die();
		}
		
		
		pageheader("News editor");	
		
		if (isset($_POST['preview'])){
			print "<br>
				<table class='main w'><tr><td class='head c'>Message preview</td></tr>
				<tr><td class='dim'>".news_format(array_merge($news, $_POST))."</td></tr></table>";
		}
		
		print "<a href='news.php'>".$config['board-name']."</a> - News editor
		<form method='POST' action='editnews.php?id=$id&edit'>
		<input type='hidden' name='auth' value='$token'>
		<center><table class='main'>
			<tr><td class='head c' colspan='2'>News editor</td></tr>
			<!-- <tr>
				<td class='light c'><b>Post options:</b></td>
				<td class='dim'>[nothing yet]</td>
			</tr> -->			
			<tr>
				<td class='light c'><b>Heading</b></td>
				<td class='dim'><input type='text' name='newsname' style='width: 580px' value=\"$name\"></td>
			</tr>
			<tr>
				<td class='light c'><b>Contents</b></td>
				<td class='dim'><textarea name='text' rows='21' cols='80' width='800px' style='resize:both;' wrap='virtual'>".htmlspecialchars($text)."</textarea></td>
			</tr>
			<tr>
				<td class='light c'>
					<b>Tags:</b><small><br>
					Only alphanumeric characters and spaces allowed<br>
					Multiple tags should be separated by ;
					</small>
				</td>
				<td class='dim'><textarea name='cat' rows='3' cols='80' width='800px' style='resize:both;' wrap='virtual'>".htmlspecialchars($tags)."</textarea></td>
			</tr>	
			<tr>
				<td class='dim' colspan='2'><input type='submit' name='submit' value='Save changes'> <input type='submit' name='preview' value='Preview'></td>
			</tr>
		</table></center>
		</form>
		";
		
	}
	else if (isset($_GET['new'])){
		// ACTION : New news
		
		$name = filter_string($_POST['newsname']);
		$text = filter_string($_POST['text']);
		$tags = filter_string($_POST['cat']);
		
		// hack hack
		$_POST['uid'] 	= false;
		$_POST['time']	= ctime();
		$_POST['hide']	= 0;
		
		if (isset($_POST['submit'])){
			checktoken();
			
			if (!$name || !$text)
				errorpage("You have left one of the required fields blank!");
			// Prevent creation of tags without alphanumeric characters
			$taglist = explode(";", $tags);
			foreach($taglist as $tag)
				if (alphanumeric($tag) != $tag)
					errorpage("One of the tags contains non-alphanumeric characters.");
			
			// Here we go
			$sql->queryp(
				"INSERT INTO news (name, text, cat, user, time) VALUES (?, ?, ?, ?, ?)",
				array($name, $text, $tags, $loguser['id'], ctime())
			);
			
			$id = $sql->resultq("SELECT LAST_INSERT_ID()");
			header("Location: news.php?id=$id");
			x_die();
		}
		
		
		pageheader("News editor [New]");	
		
		if (isset($_POST['preview']))
			print "<br>
				<table class='main w'><tr><td class='head c'>Message preview</td></tr>
				<tr><td class='dim'>".news_format(array_merge($loguser,$_POST))."</td></tr></table>";

		
		print "<a href='news.php'>".$config['board-name']."</a> - Add news
		<form method='POST' action='editnews.php?new'>
		<input type='hidden' name='auth' value='$token'>
		<center><table class='main'>
			<tr><td class='head c' colspan='2'>News editor</td></tr>
			<!-- <tr>
				<td class='light c'><b>Post options:</b></td>
				<td class='dim'>[nothing yet]</td>
			</tr> -->			
			<tr>
				<td class='light c'><b>Heading</b></td>
				<td class='dim'><input type='text' name='newsname' style='width: 580px' value=\"$name\"></td>
			</tr>
			<tr>
				<td class='light c'><b>Contents</b></td>
				<td class='dim'><textarea name='text' rows='21' cols='80' width='800px' style='resize:both;' wrap='virtual'>".htmlspecialchars($text)."</textarea></td>
			</tr>
			<tr>
				<td class='light c'>
					<b>Tags:</b><small><br>
					Only alphanumeric characters and spaces allowed<br>
					Multiple tags should be separated by ;
					</small>
				</td>
				<td class='dim'><textarea name='cat' rows='3' cols='80' width='800px' style='resize:both;' wrap='virtual'>".htmlspecialchars($tags)."</textarea></td>
			</tr>	
			<tr>
				<td class='dim' colspan='2'><input type='submit' name='submit' value='Create'> <input type='submit' name='preview' value='Preview'></td>
			</tr>
		</table></center>
		</form>
		";
		
	}
	else if (isset($_GET['del'])){
		checktoken(true); // ?
		// ACTION: Hide/Unhide from normal users and guests
		if (!$id) errorpage("No news ID specified.");
		
		// Sanity check. Don't allow this unless you're the news author or an admin
		$news = $sql->resultq("SELECT user FROM news WHERE id = $id");
		
		if (!$news) 					errorpage("The post doesn't exist!");
		if ($loguser['id'] != $news)	errorpage("You have no permission to do this!");
		
		$sql->query("UPDATE news SET hide = IF (hide, 0, 1) WHERE id = $id");
		
		header("Location: news.php");
	}
	else if (isset($_GET['kill'])){
		checktoken(true);
		// ACTION: Delete from database
		if (!$id) 		errorpage("No news ID specified.");
		if (!$isadmin)  errorpage("You're not allowed to do this!");
		$news = $sql->resultq("SELECT 1 FROM news WHERE id = $id");
		if (!$news) 	errorpage("The post doesn't exist!");
		
		$sql->query("DELETE FROM news WHERE id = $id");
		header("Location: news.php");
	}
	else errorpage("No action specified.");

	pagefooter();
	
?>
