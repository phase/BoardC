<?php

	require "lib/function.php";
	
	if (!powlcheck(4))
		errorpage("You wouldn't know how to fix threads.");
	
	function lazy($text){
		global $sql, $c, $count;
		if ($count){
			print "\nFound $count error(s).\nSaving changes...\n";
			
			if ($sql->finish($c)) print "Operation completed successfully!";
			else print "Couldn't save!";
		}
		else{
			print "\n$text counters are correct.";
			$sql->undo();
		}		
	}
	
	if (filter_string($_POST['go'])){
		print "<!doctype html><title>Thread Fix</title><body style='background: #008; color: #fff;'>
		<pre><b style='background: #fff; color: #008'>Thread Counter Fixer v2</b>\n\n=Thread Replies=\n";

		$sql->start();
		$threads = $sql->query("SELECT id, replies, forum FROM threads");
		
		$fix = $sql->prepare("UPDATE threads SET replies=? WHERE id = ?");
		$count = 0;

		//Save time on the second phase. Build these in the first
		$posts = array();
		
		while ($thread = $sql->fetch($threads)){
			
			$real = ($sql->resultq("SELECT COUNT('id') FROM posts WHERE thread = ".$thread['id']))-1;
			
			if (!isset($posts[$thread['forum']]))
				$posts[$thread['forum']] = 0;
			
			$posts[$thread['forum']]+=($real+1);
			
			if ($thread['replies'] != $real){
				print "Thread ID ".$thread['id']." [Replies: ".$thread['replies']."; Expected: $real]\n";
				$c[] = 	$sql->execute($fix, array($real, $thread['id']));
			
				$count++;
			}

		}
		
		lazy("Thread reply");
		unset($thread, $threads, $real, $c, $fix);
		
		
		
		
		print "\n\n=Forum Counters=\n";
		
		$sql->start();
		$count = 0;
		
		$ncheckp = 0; // Post
		$ncheckt = 0; // Thread
		

		$forums = $sql->query("SELECT id, threads, posts FROM forums ORDER BY id ASC");
		
		$fixp = $sql->prepare("UPDATE forums SET posts=? WHERE id=?");
		$fixt = $sql->prepare("UPDATE forums SET threads=? WHERE id=?");
		
		

		
		while ($forum = $sql->fetch($forums)){
			
			$real = filter_int($sql->resultq("SELECT COUNT('id') FROM threads WHERE forum = ".$forum['id']));
			
			$ncheckt+=$real;
			$ncheckp+=filter_int($posts[$forum['id']]);
			
			if ($forum['threads'] != $real){
				print "Forum ID ".$forum['id']." [Threads: ".$forum['threads']."; Expected: $real]\n";
				$c[] = $sql->execute($fixt, array($real, $forum['id']));
				$count++;
			}	
			
			if ($forum['posts'] != filter_int($posts[$forum['id']])){
				print "Forum ID ".$forum['id']." [Posts: ".$forum['posts']."; Expected: ".filter_int($posts[$forum['id']])."]\n";
				$c[] = $sql->execute($fixp, array($posts[$forum['id']], $forum['id']));
				$count++;
			}	
		}
		
		lazy("Forum");
		
		
		unset($count, $forums, $forum, $fixp, $fixt, $real, $c);
		
		
		
		print "\n\n=Misc Counters=\n";
		
		$data = $sql->fetchq("SELECT threads, posts FROM misc");
		

		
		if ($data['threads'] != $ncheckt){
			print "Total threads [Current: ".$data['threads'].", Expected: $ncheckt]\n";
			$sql->query("UPDATE misc SET threads=$ncheckt");
			$x = true;
		}
		if ($data['posts'] != $ncheckp){
			print "Total posts [Current: ".$data['posts'].", Expected: $ncheckp]\n";
			$sql->query("UPDATE misc SET posts=$ncheckp");
			$x = true;
		}
		
		if (isset($x)) print "\nFixed!";
		else print "\nTotal counters are correct.\n";
		
		x_die("\n<a href='index.php' style='background: #fff;'>Click here to return</a>");
		
	}
	
	pageheader("Thread Fix");
	
	print adminlinkbar()."<br/>
	<form method='POST' action='admin-threadfix.php'>
	<table class='main w'>
		<tr><td class='head c'>Thread fix</td></tr>
		
		<tr><td class='light c'>
			This page is meant to fix broken thread/post/reply counters.<br/>
			Currently, it's needed after moving posts to different threads.
		</tr></tr>
		<tr><td class='dim'>Press the button to start -> <input type='submit' value='Start' name='go'></td></tr></table>
	</form>	
	";
	
	
	
	pagefooter();

?>