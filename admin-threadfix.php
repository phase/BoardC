<?php
	
	require_once "lib/function.php";
	
	admincheck();
	
	/*
		Hopefully the thread fixing is a lot more straightforward now
	*/
	
	$windowtitle = "Thread Fix";
	
	if (isset($_POST['go'])){
		checktoken();
		
		pageheader("$windowtitle - Now Running");
		
		print adminlinkbar();
		
		?>
		<center>
		<table class='main'>
			<tr>
				<td class='head c' colspan=3>
					Thread Repair System - Now running
				</td>
			<tr>
				<td class='dark c'>
					Thread errors
				</td>
				<td class='dark c'>
					Forum Errors
				</td>
				<td class='dark c'>
					Global Counters
				</td>
			</tr>
			<tr>
				<td class='dim' style='padding: 5px'>
					<pre><?php
		
		$sql->start();
		
		$threads = $sql->query("
			SELECT t.id, (t.replies + 1) posts, COUNT(p.id) realposts
			FROM threads t
			LEFT JOIN posts p ON p.thread = t.id
			GROUP BY t.id
			ORDER BY t.id ASC
		");
		

		$fix 	= $sql->prepare("UPDATE threads SET replies = ? WHERE id = ?");
		$del	= $sql->prepare("DELETE FROM threads WHERE id = ?");
		$count 	= 0;


		while ($thread = $sql->fetch($threads)){
			
			// EXTRA: Delete threads with no posts
			if (!$thread['realposts']){
				print "ID #{$thread['id']} [Thread DELETED - It had 0 posts]\n";
				$c[] = $sql->execute($del, [$thread['id']]);
				$count++;
			}
			else if ($thread['posts'] != $thread['realposts']){
				print "ID #{$thread['id']} [Posts: {$thread['posts']}; Real: {$thread['realposts']}]\n";
				$c[] = 	$sql->execute($fix, [$thread['realposts'] - 1, $thread['id']]);
				$count++;
			}

		}
		
		savechanges();
		unset($threads, $fix, $del);	
					?></pre>
				</td>
				<td class='dim' style='padding: 5px'>
					<pre><?php

		
		$sql->start();
		
		$forums = $sql->query("
			SELECT f.id, f.name, f.threads, f.posts, (SUM(t.replies) + COUNT(t.id)) realposts, COUNT(t.id) realthreads
			FROM forums f
			LEFT JOIN threads t ON t.forum = f.id
			GROUP BY f.id
			ORDER BY id ASC
		");
		
		
		$count = 0;
		
		
		$fixp = $sql->prepare("UPDATE forums SET posts   = ? WHERE id = ?");
		$fixt = $sql->prepare("UPDATE forums SET threads = ? WHERE id = ?");
		
		

		
		while ($forum = $sql->fetch($forums)){
			
			$forum['realposts'] = intval($forum['realposts']);
			
			if ($forum['threads'] != $forum['realthreads']){
				print "ID #{$forum['id']} [Threads: {$forum['threads']}; Real: {$forum['realthreads']}]\n";
				$c[] = $sql->execute($fixt, [$forum['realthreads'], $forum['id']]);
				$count++;
			}	
			
			if ($forum['posts'] != $forum['realposts']){
				print "ID #{$forum['id']} [Posts: {$forum['threads']}; Real: {$forum['realposts']}]\n";
				$c[] = $sql->execute($fixp, [$forum['realposts'], $forum['id']]);
				$count++;
			}	
		}
		
		
		savechanges();
		unset($forums, $fixp, $fixt);	
					?></pre>
				</td>
				<td class='dim' style='padding: 5px'>
					<pre><?php
		
		
		$sql->start();
		
		$count = 0;
		
		$realposts 		= $sql->resultq("SELECT COUNT(id) FROM posts");
		$realthreads 	= $sql->resultq("SELECT COUNT(id) FROM threads");
		
		if ($miscdata['threads'] != $realthreads){
			print "Total threads [Current: {$miscdata['threads']}; Real: $realthreads]\n";
			$c[] = $sql->query("UPDATE misc SET threads = $realthreads");
			$count++;
		}
		if ($miscdata['posts'] != $realposts){
			print "Total posts [Current: {$miscdata['posts']}; Real: $realposts]\n";
			$c[] = $sql->query("UPDATE misc SET posts = $realposts");
			$count++;
		}
		
		savechanges();
		
				?></pre>
				</td>
			</tr>
		</table>
		</center>
		<br>
		<?php
		
		
	}
	else{
		
		pageheader("$windowtitle");
		
		
		print adminlinkbar();
		
		?>
		<br>
		<form method='POST' action='admin-threadfix.php'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
		<table class='main w'>
		
			<tr><td class='head c'>Thread Repair System</td></tr>
			
			<tr>
				<td class='light c'>
					<br>This page is intended to repair threads and forums with broken reply counts. Please don't flood it with requests.
					<br>This problem causes "phantom pages" (e.g., too few or too many pages displayed).
					<br>&nbsp;
					<br><input type='submit' value='Start' name='go'>
					<br>&nbsp;
				</td>
			</tr>
			
		</table>
		</form>	
		<?php
	}
	
	
	pagefooter();
	
	
	function savechanges(){
		global $sql, $c, $count;
		if ($count){
			print "\nFound $count error".($count == 1 ? "" : "s").".\n";
			
			if ($sql->finish($c)) 	print "The errors have been fixed.";
			else 					print "[WARNING] The errors couldn't be fixed.";
			
			unset($GLOBALS['c']);
		}
		else{
			print "\nNo errors found.";
			$sql->undo();
		}		
	}

?>