<?php

	require "lib/function.php";
	
	if (!powlcheck(4))
		errorpage("You wouldn't know how to fix threads.");
	
	if (filter_string($_POST['go'])){
		print "<!doctype html><title>User Fix</title><body style='background: #008; color: #fff;'>
		<pre><b style='background: #fff; color: #008'>User Post Counter Fixer</b>\n\n=Posts=\n";

		$sql->start();

		$all = $sql->query("
		SELECT u.id, u.posts, COUNT(p.id) pcount
        FROM users u
		LEFT JOIN posts p
        ON u.id=p.user
		GROUP BY u.id
		");
		
		$fix = $sql->prepare("UPDATE users SET posts=? WHERE id=?");
		$count = 0;
		
		while ($data = $sql->fetch($all)){
			
			$posts = filter_int($data['posts']);
			$real = filter_int($data['pcount']);
			
			if ($posts != $real){
				print "\nUser ID ".$data['id']." [Posts: $posts; Expected: $real]";
				$c[] = $sql->execute($fix, array($real, $data['id']));
				$count++;
			}
		}
		
		if ($count){
			if ($sql->finish($c)) print "\n$count issues fixed.\n";
			else print "\nCouldn't fix the issues.\n";
		}
		else print "\nNo problems found.\n";
		
		
		
		print "\n\n=Threads=\n";

		$all = $sql->query("
		SELECT u.id, u.threads, COUNT(t.id) tcount
        FROM users u
		LEFT JOIN threads t
        ON u.id=t.user
		GROUP BY u.id
		");
		
		$fix = $sql->prepare("UPDATE users SET threads=? WHERE id=?");
		$count = 0;
		
		while ($data = $sql->fetch($all)){
			
			$threads = filter_int($data['threads']);
			$real = filter_int($data['tcount']);
			
			if ($threads != $real){
				print "\nUser ID ".$data['id']." [Threads: $threads; Expected: $real]";
				$c[] = $sql->execute($fix, array($real, $data['id']));
				$count++;
			}
		}
		
		if ($count){
			if ($sql->finish($c)) print "\n$count issues fixed.\n";
			else print "\nCouldn't fix the issues.\n";
		}
		else print "\nNo problems found.\n";
		
		
		x_die("\n<a href='index.php' style='background: #fff;'>Click here to return</a>");
		
	}
	
	pageheader("Thread Fix");
	
	print adminlinkbar()."<br/>
	<form method='POST' action='admin-userfix.php'>
	<table class='main w'>
		<tr><td class='head c'>Post Count Fix</td></tr>
		
		<tr><td class='light c'>
			This will count the actual number of posts/threads for each user, then save it on the per-user post counter.<br/>
			Generally needed after erasing complete threads as the user's post counts aren't updated.
		</tr></tr>
		<tr><td class='dim'>Press the button to start -> <input type='submit' value='Start' name='go'></td></tr></table>
	</form>	
	";
	
	
	
	pagefooter();

?>