<?php

	require "lib/function.php";
	
	/*
		This used to be part of thread.php,
		but it does things differently enough that it got split
	*/
	
	
	$id		= filter_int($_GET['id']);
	$ord	= filter_int($_GET['ord']);
	$page	= filter_int($_GET['page']);
	
	/*
		Sanity check
	*/
	$user 	= $sql->fetchq("
		SELECT 	$userfields uid, u.title, u.since, u.location, u.posts,
				u.lastip ip, u.lastpost, u.lastview, u.rankset, u.class
		FROM users u
		WHERE u.id = $id
	");
	
	if (!$user)	errorpage("This user doesn't exist!");

	
	pageheader("Posts by ".($user['displayname'] ? $user['displayname'] : $user['name']));
	
	$new_check = $loguser['id'] ? "(p.time > n.user{$loguser['id']})" : "(p.time > ".(ctime()-300).")";
	
	$posts = $sql->query("
		SELECT 	p.id, p.text, p.time, p.rev, p.user, p.deleted, p.thread, p.nohtml,
				p.nosmilies, p.nolayout, p.avatar, o.time rtime, p.lastedited, p.noob,
				t.id tid, t.name tname, f.id fid, f.minpower fpowl, $new_check new
		FROM posts p
		
		LEFT JOIN threads      t ON p.thread = t.id
		LEFT JOIN forums       f ON t.forum  = f.id
		LEFT JOIN posts_old    o ON o.time   = (SELECT MIN(o.time) FROM posts_old o WHERE o.pid = p.id)
		LEFT JOIN threads_read n ON t.id	  = n.id
		
		WHERE p.user = $id
		ORDER BY p.id ".($ord ? "DESC" : "ASC")."
		LIMIT ".($page*$loguser['ppp']).", ".$loguser['ppp']."
	");
	
	if ($posts){
		
		$ranks 		= doranks($id, true);
		$layouts	= loadlayouts($id, true);
		
		$pagectrl = dopagelist($user['posts'], $loguser['ppp'], "showposts", "&ord=$ord");
		
		print "
			<table class='w' style='border-spacing: 0'>
				<tr>
					<td>
						<a href='index.php'>".$config['board-name']."</a> - Posts by ".makeuserlink($user['uid'], $user, true)."
					</td>
					<td style='text-align: right'>
						Sorting: <a href='showposts.php?id=$id&ord=0'>From oldest to newest</a> - <a href='showposts.php?id=$id&ord=1'>From newest to oldest</a>
					</td>
				</tr>
			</table>
			$pagectrl
		";


		$c 			= $_GET['page']*$loguser['ppp']+1; // Starting post
		$realpowl	= $loguser['powerlevel'] < 0 ? 0 : $loguser['powerlevel'];
		
		$modlist	= $sql->fetchq("SELECT fid FROM forummods WHERE uid = ".$loguser['id'], true, PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
		
		for (; $post = $sql->fetch($posts); $c++){
			
			
			if 		($post['tid'] && !$post['fid'])								$error_id = 4; # Thread in bad forum
			else if (!$post['tid'] && $post['id'])								$error_id = 3; # post in bad thread
			else if (!$post['tid'])												$error_id = 2; # Thread doesn't exist
			else if ($post['fpowl'] && $loguser['powerlevel'] < $post['fpowl'])	$error_id = 1; # minpower check
			else $error_id = 0;
			
			if ($error_id && !$isadmin){
				print "<table class='main c w fonts'><tr><td class='light' colspan=2><i>(post in restricted forum)</i></td></tr></table>";
				continue;
			}


			$ismod = ($loguser['powerlevel'] > 2) ? true : isset($modlist[$post['fid']]);
			
			// Threadpost requirements
			$post['crev'] =	$post['rev'];
			if (!$post['tname']) $post['tname'] = "[Invalid thread ID #".$post['thread']."]";
			
			$post['postcur'] = $c;
			
			print threadpost(array_merge($post, $user), false, false, false, ", in <a href='thread.php?pid={$post['id']}'>".htmlspecialchars($post['tname'])."</a> ");
		
		}
		
		print $pagectrl;
		pagefooter();
		
	}
	else errorpage("There are no posts to show.", false);
?>