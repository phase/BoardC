<?php
	
	/*
		Functions used by the news "plugin"
	*/
	
	if (!$config['enable-news'])
		header("Location: index.php");
	// Apply config.php news settings
	$config['board-name'] 	= $config['news-name'];
	$config['board-title'] 	= $config['news-title'];
	$config['board-url']	= "news.php";
	// Load permissions
	$isadmin	= powlcheck($config['news-admin-perm']);
	$canwrite	= powlcheck($config['news-write-perm']);
	
	// Not truly alphanumeric as it also allows spaces
	function alphanumeric($text){return preg_replace('/[^\da-z ]/i', '', $text);}
	
	function news_format($data, $preview = false){
		/*
			threadpost() replacement as the original function obviously wouldn't work for this
		*/
		global $loguser, $config, $isadmin;
		
		// Get message length to shrink it if it's a preview
		if ($preview){
			$charcount = strlen($data['text']);
			if ($charcount > $config['max-preview-length']){
				$data['text'] = news_preview($data['text'], $charcount)."...";//substr($data['text'], 0, $config['max-preview-length']-3)."...";
				$text_shrunk = TRUE;
			}
		}
		
		$viewfull = isset($text_shrunk) ? "<tr><td class='fonts'>To read the full text, click <a href='news.php?id=".$data['id']."'>here</a>.</td></tr>" : "";
		
		$data['id'] = filter_int($data['id']);
		
		if ($data['id']){
			if ($isadmin || $loguser['id'] == $data['uid'])
				$editlink = " | Actions : <a href='editnews.php?id=".$data['id']."&edit'>Edit</a> - <a href='editnews.php?id=".$data['id']."&del'>".($data['hide'] ? "Und" : "D")."elete</a>";
			else $editlink = "";
			
			if ($isadmin) $editlink .= " - <a class='danger' href='editnews.php?id=".$data['id']."&kill'>Erase</a>";
		}
		else $editlink = "";
		
		$lastedit = filter_int($data['lastedituser']) 
			? " (Last edited by ".makeuserlink($data['lastedituser'])." at ".printdate($data['lastedittime']).")" 
			: "";
		
		$usersort = "<a href='news.php?user=".$data['uid']."'>View all news by this user</a>";
		
		return "
		<table class='main w'>
			<tr>
				<td class='head'>
					<a href='news.php?id=".$data['id']."'>".$data['newsname']."</a>	
					-- Posted by ".makeuserlink($data['uid'], $data)." on ".printdate($data['time'])."$lastedit $editlink
					<!--<hr/>-->
				</td>
			</tr>
			
			<tr><td class='dim'>".nl2br($data['text'])."</td></tr>
			$viewfull
			<tr><td class='fonts dark'>$usersort | Tags: ".tag_format($data['cat'])."</td></tr>
		</table>
		";
		
	}
	
	function news_preview($text, $length = NULL){
		/*
			news_preview: shrinks a string without leaving open HTML tags
			currently this doesn't allow to use < signs, made worse by the board unescaping &lt; entities
		*/
		global $config;
		if (!isset($length)) $length = strlen($text);
		
		/*
			Reference:
				$i 			- character index
				$res 		- result that will be returned
				$buffer 	- contains the text. if a space is found and the text isn't inside a tag it will append its contents to $res
				$opentags 	- keeps count of open HTML tags
				$intag		- marks if a text is inside a tag
		
		*/
		
		for($i = 0, $res = "", $buffer = "", $opentags = 0, $intag = false; $i < $length && $i < $config['max-preview-length']; $i++){
			
			$buffer .= $text[$i];
			
			if ($text[$i] == " " && !$opentags && !$intag){
				$res 	.= $buffer;
				$buffer  = "";
			}
			// only change the $opentags count when the tag starts
			else if ($text[$i] == "<"){
				if (!$intag) $opentags++;
				$intag = true;
			}
			else if ($text[$i] == ">"){
				if (!$intag) $opentags--;
				$intag = false;
			}
			
		}

		return $res;

	}
	
	function tag_format($list){
		$tags = explode(";", $list);
		foreach($tags as $tag)
			$text[] = "<a href='news.php?cat=$tag'>$tag</a>";
		return implode(", ", $text);
	}
	
?>