<?php

	require_once "lib/function.php";
	
	admincheck();
	
	if (isset($_POST['go'])) {
		
		checktoken();
		
		pageheader("User Fix");
		print adminlinkbar();
		
		?>
		<center>
		<table class='main'>
			<tr>
				<td class='head c' colspan=3>
					User Repair System - Now running
				</td>
			<tr>
				<td class='dark c'>
					Post count
				</td>
				<td class='dark c'>
					Thread count
				</td>
				<td class='dark c'>
					Last posts
				</td>
			</tr>
			<tr>
				<td class='dim' style='padding: 5px'>
					<pre><?php

		/*
			Fix inconsistent post counts
		*/
		$sql->start();

		$counters = $sql->query("
			SELECT u.id, u.name, u.posts, COUNT(p.id) pcount
			FROM users u
			LEFT JOIN posts p ON u.id = p.user
			GROUP BY u.id HAVING pcount != u.posts
		");
		
		$fix 	= $sql->prepare("UPDATE users SET posts = ? WHERE id = ?");
		$count 	= 0;
		
		
		while ($x = $sql->fetch($counters)) {
			
			print "ID #{$x['id']} ({$x['name']}) Wrong post count [Posts: {$x['posts']}; Real: {$x['pcount']}]\n";
			$c[] = $sql->execute($fix, [$x['pcount'], $x['id']]);
			$count++;
			
		}
		
		savechanges();
		
					?></pre>
				</td>
				<td class='dim' style='padding: 5px'>
					<pre><?php

		/*
			Fix inconsistent thread counts
		*/
		
		$sql->start();

		$counters = $sql->query("
			SELECT u.id, u.name, u.threads, COUNT(t.id) tcount
			FROM users u
			LEFT JOIN threads t ON u.id = t.user
			GROUP BY u.id HAVING tcount != u.threads
		");
		
		$fix 	= $sql->prepare("UPDATE users SET threads = ? WHERE id = ?");
		$count 	= 0;
		
		
		while ($x = $sql->fetch($counters)) {
			
			print "ID #{$x['id']} ({$x['name']}) Wrong thread count [Threads: {$x['threads']}; Real: {$x['tcount']}]\n";
			$c[] = $sql->execute($fix, [$x['tcount'], $x['id']]);
			$count++;
			
		}
		
		savechanges();
		
					?></pre>
				</td>
				<td class='dim' style='padding: 5px'>
					<pre><?php
				

					
		/*
			And last post counts for users' profiles
		*/

		$time = $sql->query("
			SELECT u.id, u.name, p.id pid, p.user, p.time, o.time origtime, u.lastpost
			FROM users u
			
			LEFT JOIN posts     p ON u.id = p.user
			LEFT JOIN posts_old o ON p.id = o.pid
			
			WHERE p.id = (SELECT MAX(p.id) FROM posts p WHERE p.user = u.id)
			GROUP BY u.id
		");
		
		$fix 	= $sql->prepare("UPDATE users SET lastpost = ? WHERE id = ?");
		$count 	= 0;
		
		$sql->start();

		while ($x = $sql->fetch($time)) {

			$real = $x['origtime'] ? (int) $x['origtime'] : (int) $x['time'];
			
			if ($x['lastpost'] != $real) {
				print "ID #{$x['id']} ({$x['name']}) [Last Post: {$x['lastpost']}; Real: $real]\n";
				$c[] = $sql->execute($fix, [$real, $x['id']]);
				$count++;
			}
			
		}
		
		savechanges();
		
					?></pre>
				</td>
			</tr>
		</table>
		</center>
		<?php
		
	}
	else {
	
		pageheader("User Fix");
		
		print adminlinkbar();
	
		?>
		<br>
		<form method='POST' action='admin-userfix.php'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		
		<table class='main w'>
		
			<tr><td class='head c'><s>Thread</s> User Repair System</td></tr>
			
			<tr>
				<td class='light c'>
					<br>This page is intended to repair broken user counters.
					<br>This problem can cause the last post field to be invalid in users' profiles or inconsistencies between post counts.
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