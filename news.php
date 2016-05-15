<?php

	/*
		News Engine v0.00 -- 15/05/16
		
		DESCRIPTION:
		A news engine (read: alternate announcements page) that everybody can read, but only privileged or up can write.
		The permission settings are stored in news_config.php
		(this is a test for forum integration)
		
		TODO: 
		- Sorting options and possibly a search box
	*/
	
	require "lib/function.php";
	require "lib/news_function.php";
	
	$id			= filter_int($_GET['id']);
	$page		= filter_int($_GET['page']);
	$filter		= filter_string($_GET['cat']);
	

	if ($id)
		$q_filter = "AND n.id = $id";
	else if ($filter){
		// As only alphanumeric characters (and space) are allowed in tag names, never accept anything which also contains other special characters
		// If it does, redirect to the "Suspicious request detected" page (or maybe actually edit $fw->banflags() here to force log the attempt)
		if (alphanumeric($filter) !== $filter)
			header("Location: ../index.php?sec=1");
		$q_filter = "AND n.cat REGEXP ';?$filter'";
	}
	else $q_filter = "";


	
	/* 	Table name 	: news
		Columns		: id, user, name, text, cat, hide
	 "Cat" value is an *alphanumeric string* only used for filtering - this isn't normally used the main page
	 as such, using the \0 merge trick isn't necessary. multiple categories are delimited by ;
	 
	 "Hide" marks deleted news. These can only be seen by users with write privileges, not by guests
	*/
	
	pageheader("Main page");
	
	// Notice: This does NOT store old news revisions yet. Maybe it will in the future...
	
	$q_where = 	"WHERE ".($canwrite ? "1" : "n.hide = 0")." $q_filter";
	
	$news = $sql->query("
		SELECT n.id, n.time, n.name newsname, n.text, n.lastedituser, n.lastedittime, n.cat, n.hide, $userfields uid
		FROM news n
		LEFT JOIN users u ON n.user = u.id
		$q_where
		ORDER BY n.time DESC
		LIMIT ".$page*$loguser['ppp'].", ".$loguser['ppp']."
	");
	
	// Better than staring at a blank page if you can't create new news
	if (!$news && !$canwrite)
		errorpage("There are no news to show.<br/>Click <a href='index.php'>here</a> to return to the forums...", false);
	
	
	if ($canwrite)
		print "<br/><table class='main w fonts'><tr><td class='dark'>News options: <a href='editnews.php?new'>New post</a></td></tr></table>";
	
	$news_count	= $sql->resultq("SELECT COUNT(n.id) FROM news n $q_where");
	$pagectrl	= dopagelist($news_count, $loguser['ppp'], "news");
	

	print $pagectrl;
	while ($post = $sql->fetch($news)){
		print news_format($post, (!$id)); // Don't show the preview if you're viewing a specific post ID
	}
	print $pagectrl;

	pagefooter();

?>
