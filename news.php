<?php

	/*
		News Engine v0.02 -- 30/08/16
		
		DESCRIPTION:
		A news engine (read: alternate announcements page) that everybody can read, but only privileged or up can write.
		The permission settings are stored in config.php
		(this is a test for forum integration)
		
		TODO:
		Change the layout of this page. The current one is a placeholder that looks cramped.
	*/
	
	require "lib/function.php";
	require "lib/news_function.php";
	
	$id			= filter_int($_GET['id']);
	$page		= filter_int($_GET['page']);
	$usersort	= filter_int($_GET['user']);
	$ord		= filter_int($_GET['ord']);
	$filter		= filter_string($_GET['cat'], true);
	
	if (filter_string($_GET['search'])){
		$search		= filter_string($_GET['search'], true);
	} else if (isset($_POST['search'])){
		// Refreshing with _POST is bad
		header("Location: ?search=".urlencode($_POST['search']));
		x_die();
	} else {
		$search		= "";
	}
	
	$q_filter = "";
	if ($id) {
		$q_filter = "AND n.id = $id";
	} else{
		if ($filter){
			if (alphanumeric($filter) !== $filter){
				errorpage("Invalid characters in tag.");
				//header("Location: index.php?sec=1");
			}
			$q_filter = "AND n.cat REGEXP '(;|^)$filter(;|$)' "; // Changed to check first and last characters
		}
		if ($usersort){
			// Sort by user ID
			$q_filter .= "AND n.user = $usersort";
		}
	}

	
	/* 	Table name 	: news
		Columns		: id, user, name, text, cat, hide
	 "Cat" value is an *alphanumeric string* only used for filtering - this isn't normally used the main page
	 as such, using the \0 merge trick isn't necessary. multiple categories are delimited by ;
	 
	 "Hide" marks deleted news. These can only be seen by users with write privileges, not by guests
	*/
	
	pageheader("Main page");
	
	// Notice: This does NOT store old news revisions yet. Maybe it will in the future...
	
	$q_where 	= 	"WHERE ".($canwrite ? "1" : "n.hide = 0")." $q_filter";
	$offset		= $page * $loguser['ppp'];
	
	$news = $sql->queryp("
		SELECT n.id, n.time, n.name newsname, n.text, n.lastedituser, n.lastedittime, n.cat, n.hide, $userfields uid
		FROM news n
		LEFT JOIN users u ON n.user = u.id
		$q_where AND n.text LIKE ?
		ORDER BY n.time ".($ord ? "ASC" : "DESC")."
		LIMIT $offset, {$loguser['ppp']}
	", ["%$search%"]);
	
	// Better than staring at a blank page if you can't create new news
	if (!$news && !$canwrite){
		errorpage("
			There are no news to show.<br>
			Click <a href='index.php'>here</a> to return to the forums...
		", false);
	}
	
	
	$newpost = $canwrite ? "Options: <a href='editnews.php?new'>New post</a> |" : "";
	$news_count	= $sql->resultp("SELECT COUNT(n.id) FROM news n $q_where AND INSTR(n.text, ?) > 0 ", [$search]);
	
	/*
		Number of posts (on this page)
	*/
	if (!$id){
		print "<small>Showing $news_count post".($news_count == 1 ? "" : "s")." in total".
			( /* all those little details I put here (that are making this code block bloated) are making me sad */
				$news_count > $loguser['ppp'] ?
				", from ".($offset + 1)." to ".($offset + $loguser['ppp'] > $news_count ? $news_count : $offset + $loguser['ppp'])." on this page" :
				""
			).".</small>";
	}
	
	/*
		Header
	*/
	print "
	<br>
	<form method='POST' action='?'>
	<table class='main w'>
		<tr>
			<td class='dark fonts' colspan=2>".
				"$newpost ".
				"Sorting: <a href='?ord=0&cat=$filter&user=$usersort'>From newest to oldest</a> ".
				"- <a href='?ord=1&cat=$filter&user=$usersort'>From oldest to newest</a> ".
			"</td>
		</tr>
		<tr>
			<td class='light c' style='width: 100px'>
				Search
			</td>
			<td class='dim'>
				<input type='text' name='search' value=\"".htmlspecialchars($search)."\">&nbsp;<input type='submit' name='dosearch' value='Search'>
			</td>
		</tr>
	</table>
	</form>
	<br>
	";
	
	/*
		Posts
	*/
	$pagectrl	= dopagelist($news_count, $loguser['ppp'], "news", "&cat=$filter&user=$usersort&search=$search");
	
	print $pagectrl;
	while ($post = $sql->fetch($news)){
		print news_format($post, (!$id)); // Don't show the preview if you're viewing a specific post ID
	}
	print $pagectrl;

	pagefooter();

?>
