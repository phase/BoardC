<?php
	
	/*
	replacing the wall of arguments with something different could (or not) be a better idea
	as the arg list is getting ridiculous [as of $annmode]
	
	and on top of this the function itself is messy
	*/
	
	function threadpost($post, $mini = false, $merge = false, $nocontrols = false, $extra = "", $pmmode = false, $annmode = false){
		global $ismod, $loguser, $config, $error_id;
		
		static $theme = false;
		// Reverse post color scheme
		$theme = ($theme == "light") ? "dim" : "light";
		
		if ((!isset($ismod) || isset($ismod_a)) && !$pmmode && !$annmode){
			static $ismod_a;
			if (!isset($ismod_a[$post['thread']])) $ismod_a[$post['thread']] = ismod($post['thread']); //useful when printing posts from different threads (ie: list posts)
		
			$ismod = $ismod_a[$post['thread']];
		}
		
		$controls = "";
		$uid = $post['user'];
		
		if ($pmmode || $annmode){
			// snip
			if ($post['nohtml'])
				$post['text'] = htmlspecialchars($post['text']);
			if (!$post['nosmilies'])
				$post['text'] = dosmilies($post['text']);
			
			$post['text'] = output_filters($post['text']);
			
			if ($post['nolayout'] || !$loguser['showhead'])
				$post['head'] = $post['sign'] = "";
			else{
				
				switch ($loguser['signsep']){
					case 1: $sep = "</br>--------------------<br/>"; break;
					case 2: $sep = "</br>____________________</br>"; break;
					case 3: $sep = "</br><hr/><br/>"; break;
					default: $sep = "";
				}
				
				$post['head'] = output_filters($post['head']);
				$post['sign'] = $sep.output_filters($post['sign']);
			}
			
			if (isset($post['avatar']) && is_file("userpic/$uid/".$post['avatar'])) $avatar = "<img src='userpic/$uid/".$post['avatar']."'>";
			else $avatar = "";
			// end snip
			if ($pmmode)
				$controls = "<a href='private.php?act=send&quote=".$post['id']."'>Reply</a>";
			else if ($ismod)
				$controls = "
					<a href='announcement.php?act=new&id=".filter_int($_GET['id'])."&quote=".$post['id']."'>Reply</a> -
					<a href='announcement.php?act=edit&id=".$post['id']."'>Edit</a>
					";
			
			$sidebar = "<small>
			".($post['title'] ? $post['title']."<br/>" : "")."
			".($avatar ? "$avatar<br/>" : "")."
			Posts: ".$post['posts']."<br/>
			EXP: [NUM]<br/>
			For Next: [NUM]<br/>
			<br/>
			Since: ".printdate($post['since'], true, false)."<br/>
			".($post['location'] ? "From: ".$post['location']."<br/>" : "")."
			<br/>
			Since last post: ".($post['lastpost'] ? choosetime(ctime()-$post['lastpost']) : "None")."<br/>
			Last activity: ".choosetime(ctime()-$post['lastview'])."</small>";
			
			$post['text'] = nl2br($post['text']);
			$height = "style='height: 220px'";
		}
		else if ($post['deleted']){
			$post['text'] = "(Post deleted)";
			$post['head'] = $post['sign'] = "";
			
			// Topbar
			if ($ismod)
				$controls = "
			<a href=\"thread.php?id=".$post['thread']."&pin=".$post['id']."\">Peek</a>
			<a href=\"thread.php?id=".$post['thread']."&unhide=".$post['id']."\">Undelete</a>
			";
			
			$sidebar = "&nbsp;";
			
			$height = "";
			$avatar = "";
		}
		else {
			if ($post['nohtml'])
				$post['text'] = htmlspecialchars($post['text']);
			if (!$post['nosmilies'])
				$post['text'] = dosmilies($post['text']);
			
			$post['text'] = output_filters($post['text']);
			
			if ($post['nolayout'] || !$loguser['showhead'])
				$post['head'] = $post['sign'] = "";
			else{
				
				switch ($loguser['signsep']){
					case 1: $sep = "</br>--------------------<br/>"; break;
					case 2: $sep = "</br>____________________</br>"; break;
					case 3: $sep = "</br><hr/><br/>"; break;
					default: $sep = "";
				}
				
				$post['head'] = output_filters($post['head']);
				$post['sign'] = $sep.output_filters($post['sign']);
			}
			
			if (isset($post['avatar']) && is_file("userpic/$uid/".$post['avatar'])) $avatar = "<img src='userpic/$uid/".$post['avatar']."'>";
			else $avatar = "";
			
			$controls .= "<a href=\"thread.php?pid=".$post['id']."#".$post['id']."\">Link</a> | <a href=\"new.php?act=newreply&id=".$post['thread']."&quote=".$post['id']."\">Quote</a>";
			
			if (($ismod || $post['user'] == $loguser['id']) && powlcheck(0))
				$controls .= " | <a href=\"new.php?act=editpost&id=".$post['id']."\">Edit</a>";
			
			if ($ismod)
				$controls .= " | <a href=\"thread.php?id=".$post['thread']."&hide=".$post['id']."\">Delete</a>";
			
			
			if (!$mini)
				$sidebar = "<small>
				".($post['title'] ? $post['title']."<br/>" : "")."
				".($avatar ? "$avatar<br/>" : "")."
				Posts: ".$post['postcur']."/".$post['posts']."<br/>
				EXP: [NUM]<br/>
				For Next: [NUM]<br/>
				<br/>
				Since: ".printdate($post['since'], true, false)."<br/>
				".($post['location'] ? "From: ".$post['location']."<br/>" : "")."
				<br/>
				Since last post: ".($post['lastpost'] ? choosetime(ctime()-$post['lastpost']) : "None")."<br/>
				Last activity: ".choosetime(ctime()-$post['lastview'])."</small>";
			else $sidebar = "";
			
			$post['text'] = nl2br($post['text']);
			$height = "style='height: 220px'";
		}
		
		
		
		if (powlcheck(5) && !$pmmode)
			$controls .= $annmode ? 
				" - <a class='danger' href=\"announcement.php?id=".filter_int($_GET['id'])."&del=".$post['id']."\">Delete</a>" :
				" | <a class='danger' href=\"thread.php?id=".$post['thread']."&del=".$post['id']."\">Erase post</a>";
		
		if (powlcheck(4))
			$controls .= " | IP: <a href='admin-ipsearch.php?ip=".$post['ip']."'>".$post['ip']."</a>";
		
		$controls .= " | ID: ".$post['id'];
		
		/*
		before:
		trev - total revision
		rev - current revision (used to be replaced with this)
		
		now:
		rev - total revision
		crev - current revision (doesn't replace rev anymore)
		*/
		
		if (filter_int($post['rev'])){
			if (!isset($post['crev'])) $post['crev'] = $post['rev']; // imply max revision if it isn't set
			
			if ($annmode){
				$script = "announcement";
				$sdfgrklh = "&id=".filter_int($_GET['id']);
			}
			else{
				$script = "thread";
				$sdfgrklh = "";
			}
			// post revision jump
			if ($ismod){
				for($i=0, $revjump="Revision: ";$i<$post['rev'];$i++){
					$a = ($post['crev'] == $i) ? "z" : "a"; 
					$revjump .= "<$a href='$script.php?pid=".$post['id']."&pin=".$post['id']."$sdfgrklh&rev=$i#".$post['id']."'>".($i+1)."</$a> ";
				}
				$revjump .= "<a href='$script.php?pid=".$post['id']."$sdfgrklh#".$post['id']."'>".($i+1)."</a>";
			}
			else $revjump = "";
			
			$datetxt = "Posted on ".printdate($post['rtime'])."$extra Revision ".($post['crev']+1)." (Last edited by ".makeuserlink($post['lastedited']).": ".printdate($post['time']).") $revjump";
		}
		else $datetxt = "Posted on ".printdate($post['time']).$extra;
		
		$inputmerge = $merge ? "<input type='checkbox' name='c_merge[]' value=".$post['id'].">" : "";
		
		if ($nocontrols) $controls = "";
		
		$new = $post['new'] ? "<img src='images/status/new.gif'> - " : "";
		
		if (isset($_GET['lol'])){
			return "<table class='main'><tr><td class='$theme'>
			USER: ".makeuserlink($uid, $post)."<br/>
			MESSAGE: ".$post['text']."<br/>
			</td></tr></table>
			";
		}
		else if (!$mini)
		return "
		<table id='".$post['id']."' class='main content_$uid'>
			<tr>
				<td class='topbar1_$uid $theme' style='min-width: 200px; border-bottom: none'>$inputmerge".makeuserlink($uid, $post)."</td>
				<td class='topbar2_$uid $theme w fonts' style='text-align: right'>
				<table class='fonts' style='margin: 0px; border-spacing: 0px;'><tr><td><nobr>$new$datetxt</nobr></td><td class='w'></td><td><nobr>$controls</nobr></td></tr></table></td>
			</tr>
			<tr>
				<td class='sidebar_$uid $theme' valign='top'>$sidebar</td>
				<td class='mainbar_$uid $theme' valign='top' $height>".$post['head'].$post['text'].$post['sign']."</td>
			</tr>
		</table>
		";
		
		else return "
			<tr id='".$post['id']."'>
				<td class='head $theme' style='min-width: 200px;'>".makeuserlink($uid, $post)."</td>
				<td class='head $theme w' style='text-align: right'>
				<table class='fonts' style='margin: 0px; border-spacing: 0px;'><tr><td><nobr>$new$datetxt</nobr></td><td class='w'></td><td><nobr>$controls</nobr></td></tr></table></td>
			</tr>
			<tr>
				<td colspan=2 class='$theme'>".$post['text']."</td>
			</tr>
		";	
	}
	function minipostlist($thread_id){
		global $loguser, $sql, $userfields;
		
		$posts = $sql->query("
		SELECT p.id, p.text, p.time, p.rev, p.user, p.deleted, p.thread, u.lastip as ip, 1 nolayout, p.nohtml, p.nosmilies, p.lastedited, o.time rtime,
		NULL title, $userfields welpwelp, n.user".$loguser['id']." new
		FROM posts p
		LEFT JOIN users u ON p.user = u.id
		LEFT JOIN posts_old o ON o.time = (SELECT MIN(o.time) FROM posts_old o WHERE o.pid = p.id)
		LEFT JOIN new_posts n ON p.id = n.id
		WHERE p.thread = $thread_id
		ORDER BY p.id DESC
		LIMIT ".$loguser['ppp']."
		");// offset, limit
		
		$txt = "<br/><table class='main w'><tr><td colspan=2 class='dark c'>Latest posts in the thread:</td></tr>";
		
		
		if ($posts)
			while($post = $sql->fetch($posts))
				$txt .= threadpost($post, true);
			
		else $txt .= "<tr><td class='light'>There are no posts in this thread</td></tr>";
		
		return $txt."</table>";
	}
		
	function getthreadinfo($lookup, $pid = false){
		global $sql;
		
		if ($lookup){
			
			
			// I don't know why there were three different queries here
			// Query redone to start from post id and allow thread controls to work on invalid threads
			// (which can have no valid thread id, with the invalid one is post.thread)
			
			$data = $sql->fetchq("
				SELECT p.id pid, p.thread rthread,
				t.id, t.name, t.title, t.time, t.forum, t.user, t.views, t.replies, t.sticky, t.closed, t.icon, t.ispoll,
				t.lastpostid, t.lastpostuser, t.lastposttime,
				f.id fid, f.name fname, f.powerlevel fpowl, f.theme
				FROM posts p
				
				LEFT JOIN threads t	ON p.thread = t.id
				LEFT JOIN forums f ON t.forum = f.id
				
				WHERE p.thread = $lookup
				".($pid ? "OR p.id = $pid" : "")."
			");
			
			if (!$pid)
				$pid = $sql->resultq("SELECT id FROM posts WHERE thread = $lookup");

			if (!$pid) $pid = filter_int($data['pid']);
			
			
			if (filter_int($data['ispoll'])){
				$poll = split_null($data['title']);
				$data['title'] = $poll[0];
			}
			
			
			
			// Rebuild $forum and $thread since everything expects it this way
			
			$forum = array(
				'id'		=> filter_int($data['fid']),
				'name' 		=> filter_string($data['fname']),
				'powerlevel'=> filter_int($data['fpowl']),
				'theme' 	=> &$data['theme'],
				
			);
			$thread = array(
				'id'			=> filter_int($data['id']),
				'name'			=> filter_string($data['name']),
				'title'			=> filter_string($data['title']),
				'time'			=> filter_int($data['time']),
				'forum'			=> filter_int($data['forum']),
				'user'			=> filter_int($data['user']),
				'views'			=> filter_int($data['views']),
				'replies'		=> filter_int($data['replies']),
				'sticky'		=> filter_int($data['sticky']),
				'closed'		=> filter_int($data['closed']),
				'rthread'		=> filter_int($data['rthread']),
				'icon'			=> filter_string($data['icon']),
				'lastpostid'	=> filter_int($data['lastpostid']),
				'lastpostuser'	=> filter_int($data['lastpostuser']),
				'lastposttime'	=> filter_int($data['lastposttime']),
				'ispoll'		=> filter_bool($data['ispoll']),
				'polldata' 		=> isset($poll) ? $poll : false
			);

			// Error Handling
			
			if 		($thread['id'] && !$forum['id'])				$error_id = 4; # Thread in bad forum
			else if (!$thread['id'] && $pid)						$error_id = 3; # post in bad thread
			else if (!$thread['id'])								$error_id = 2; # Thread doesn't exist
			else if ($forum['id'] && !canviewforum($forum['id']))	$error_id = 1; # minpower check
			else $error_id = 0;
		}
		else{
			// Account for error id 5
			$error_id = 5;
			$thread = false;
			$forum = false;
		}
		
		if ($error_id){
				switch ($error_id){
					case 3:{
						$thread['id'] = $lookup;
						$thread['name'] = "Invalid thread #$lookup";
						$forum['name'] = "(No forum)";
						
						break;
					}
					case 4:{
						$forum['id'] = $thread['forum'];
						$forum['name'] = "Invalid forum #".$thread['forum'];
						break;
					}
//					case 1:
					case 2:{
						$thread['id'] = $lookup;
						break;
					}
//					case 5:
					default:
				}
		}
		
		return array($thread, $forum, $error_id, filter_int($pid));
	
	}
	
	function poll_print($p){
		
		global $loguser, $sql, $lookup;
		

		$votes = $sql->query("SELECT vote FROM poll_votes WHERE thread = $lookup");
		
		$total = 0;
		$votedb = array(0);
		
		while ($vote = $sql->fetch($votes)){
			$votedb[$vote['vote']] = filter_int($votedb[$vote['vote']]) + 1;
			$total++;
		}
		
		//d($votedb);
		
		$max = max($votedb);
		if ($max != 0) $mul = 100/$max;
		else $mul = 0;


		$title = $p[0];
		$briefing = $p[1];
		$multivote = $p[2];
		
		for($i=3,$n=1,$choice_out="";isset($p[$i]);$i+=2,$n++){
			
			if (!$loguser['id']) $name = $p[$i];
			else $name = "<a href='thread.php?".$_SERVER['QUERY_STRING']."&vote=$n'>".$p[$i]."</a>";
			
			$votes_num = filter_int($votedb[$n]);
			$width = sprintf("%.1f", $votes_num/$total*100);
			
			$choice_out .= "
			<tr>
				<td class='light' width='20%'>$name</td>
				<td class='dim' width='60%'><table bgcolor='".$p[$i+1]."' cellpadding='0' cellspacing='0' width='$width%'><tr><td>&nbsp;</td></tr></table></td>
				<td class='light c' width='20%'>$votes_num vote".($votes_num==1 ? "" : "s").", $width%</td>
			</tr>
			";
		}
		
		return "
			<table class='main w'>
				<tr><td colspan='3' class='dark c'><b>$title</b></td></tr>
				
				<tr><td class='dim fonts' colspan='3'>$briefing</td></tr>
				
				$choice_out
				
				<tr>
					<td class='dim fonts' colspan='3'>Multi-voting is ".($multivote ? "enabled" : "disabled")." - $total votes in total. ".(powlcheck(4) ? "<a href='thread.php?".$_SERVER['QUERY_STRING']."&votes'>(View votes)</a>" : "")."</td>
				</tr>
			</table><br/>";
	}
?>