<?php

	require_once "lib/function.php";

	admincheck();
	
	$windowtitle = "Thread Fix II";
	
	if (isset($_POST['go'])) {
		checktoken();
		
		pageheader("$windowtitle - Now Running");
		
		print adminlinkbar();
		
		?>
		<center>
		<table class='main'>
			<tr>
				<td class='head c' colspan=2>
					Thread Repair System II - Now running
				</td>
			<tr>
				<td class='dark c'>
					Thread Errors
				</td>
				<td class='dark c'>
					Forum Errors
				</td>
			</tr>
			<tr>
				<td class='dim' style='padding: 5px'>
					<pre><?php
		
		$sql->start();
		
		$count = 0;
		
		//t.lastposttime, t.lastpostuser,
		/*
			For each thread check for incorrect last post information
			(yes, this does account the last post time correctly for edited posts)
		*/
		$threads = $sql->query("
			SELECT  t.id tid, t.lastpostid, 
			        p.id pid, p.user, p.time, o.time origtime
			FROM threads t
			
			LEFT JOIN posts     p ON t.id = p.thread
			LEFT JOIN posts_old o ON p.id = o.pid
			
			WHERE t.lastpostid != p.id AND p.id = (SELECT MAX(p.id) FROM posts p WHERE p.thread = t.id)
			GROUP BY t.id
			ORDER BY p.time DESC
		");
		
		while ($x = $sql->fetch($threads)){
				
			if (!$x['pid']) {
				$c[] = $sql->query("DELETE FROM threads WHERE id = {$x['tid']}");
				print "ID #{$x['tid']} Invalid thread with 0 posts deleted\n";
				$count++;
			}
			else {
				if ($x['origtime']) $x['time'] = $x['origtime'];
				print "ID #{$x['tid']} Wrong last post ID [Current: {$x['lastpostid']}; Fixed: {$x['pid']}]\n";
				$c[] = $sql->query("
					UPDATE threads SET
						lastpostid = {$x['id']},
						lastposttime = {$x['time']},
						lastpostuser = {$x['user']}
					WHERE id = {$x['tid']}
				");
				$count++;
			}
			
		}
		
		savechanges();
		
					?></pre>
				</td>
				<td class='dim' style='padding: 5px'>
					<pre><?php
		
		/*
			Fixing forums
		*/
		
		$sql->start();
		
		$count = 0;
		
		
		$forums = $sql->query("
			SELECT f.id, f.lastpostid lastid, MAX(t.lastpostid) realid
			FROM forums f
			LEFT JOIN threads t ON t.forum = f.id
			GROUP BY f.id ASC
		");
		
		while ($x = $sql->fetch($forums)){
			if (intval($x['lastid']) != intval($x['realid'])) {
				print "ID #{$x['id']} Wrong last post ID [Current: {$x['lastid']}; Fixed: {$x['realid']}]\n";
				$c[] = update_last_post($x['id'], false, true);
				$count++;
			}
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
	else {
		
		pageheader($windowtitle);
		
		print adminlinkbar();
		
		?>
		<br>
		<form method='POST' action='admin-threadfix2.php'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
		<table class='main w'>
		
			<tr><td class='head c'>Thread Repair System II</td></tr>
			
			<tr>
				<td class='light c'>
					<br>This page is intended to repair threads with broken 'last reply' times/users.
					<br>This problem causes bumped threads that shouldn't be, especially with badly deleted posts.
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