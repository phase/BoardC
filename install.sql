CREATE TABLE `announcements` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` text NOT NULL,
  `title` text,
  `user` int(32) NOT NULL,
  `time` int(32) NOT NULL,
  `text` text NOT NULL,
  `nohtml` tinyint(1) NOT NULL DEFAULT '0',
  `nosmilies` tinyint(1) NOT NULL DEFAULT '0',
  `nolayout` tinyint(1) NOT NULL DEFAULT '0',
  `avatar` int(32) NOT NULL DEFAULT '0',
  `forum` int(32) NOT NULL DEFAULT '0',
  `lastedited` int(32) NOT NULL DEFAULT '0',
  `rev` int(5) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `announcements_old` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `aid` int(32) NOT NULL,
  `name` text NOT NULL,
  `title` text NOT NULL,
  `text` text NOT NULL,
  `time` int(32) NOT NULL,
  `rev` int(4) NOT NULL,
  `nohtml` tinyint(1) NOT NULL DEFAULT '0',
  `nosmilies` tinyint(1) NOT NULL DEFAULT '0',
  `nolayout` tinyint(1) NOT NULL DEFAULT '0',
  `avatar` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `announcements_read` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user0` int(32) NOT NULL DEFAULT '0',
  `user1` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `categories` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `minpower` tinyint(1) NOT NULL DEFAULT '0',
  `ord` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `categories` (`id`, `name`, `minpower`, `ord`) VALUES
(1, 'Main', 0, 2),
(2, 'Special', 2, 1),
(3, 'Game Over', 0, 99);
CREATE TABLE `events` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user` int(32) NOT NULL,
  `time` int(32) NOT NULL,
  `text` varchar(255) NOT NULL,
  `private` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `failed_logins` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `ip` varchar(32) NOT NULL,
  `attempt` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `forummods` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `fid` int(32) NOT NULL,
  `uid` int(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `forums` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(256) NOT NULL,
  `title` varchar(256) NOT NULL,
  `minpower` tinyint(1) NOT NULL DEFAULT '0',
  `minpowerreply` tinyint(1) NOT NULL DEFAULT '0',
  `minpowerthread` tinyint(1) NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `threads` int(32) NOT NULL DEFAULT '0',
  `posts` int(32) NOT NULL DEFAULT '0',
  `category` int(32) NOT NULL DEFAULT '0',
  `ord` int(32) NOT NULL DEFAULT '0',
  `theme` int(32) DEFAULT NULL,
  `lastpostid` int(32) DEFAULT NULL,
  `lastpostuser` int(32) DEFAULT NULL,
  `lastposttime` int(32) DEFAULT NULL,
  `pollstyle` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `forums` (`id`, `name`, `title`, `minpower`, `minpowerreply`, `minpowerthread`, `category`, `ord`) VALUES
(1, 'General forum', 'For everybody!', 0, 0, 0, 1, 1),
(2, 'General staff forum', 'Not for everybody.', 2, 2, 2, 2, 1),
(3, 'The trash', 'Definitely not for everybody,', 0, 0, 0, 3, 99);
CREATE TABLE `hits` (
  `ip` varchar(32) NOT NULL,
  `time` int(32) NOT NULL,
  `page` mediumtext NOT NULL,
  `useragent` mediumtext NOT NULL,
  `user` int(32) NOT NULL DEFAULT '0',
  `forum` int(32) NOT NULL DEFAULT '0',
  `thread` int(32) NOT NULL DEFAULT '0',
  `referer` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `ipbans` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `ip` varchar(32) DEFAULT NULL,
  `time` int(32) DEFAULT NULL,
  `ban_expire` int(32) NOT NULL DEFAULT '0',
  `reason` varchar(255) DEFAULT NULL,
  `userfrom` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `jstrap` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user` int(32) NOT NULL,
  `ip` varchar(32) NOT NULL,
  `source` text NOT NULL,
  `filtered` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `misc` (
  `disable` tinyint(1) NOT NULL DEFAULT '0',
  `views` int(32) NOT NULL DEFAULT '0',
  `theme` int(32) DEFAULT NULL,
  `threads` int(32) NOT NULL DEFAULT '0',
  `posts` int(32) NOT NULL DEFAULT '0',
  `noposts` tinyint(1) NOT NULL DEFAULT '0',
  `regmode` int(1) NOT NULL DEFAULT '0',
  `regkey` text DEFAULT NULL,
  `threshold` int(32) NOT NULL DEFAULT '20',
  `private` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `misc` () VALUES ();
CREATE TABLE `news` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` text NOT NULL,
  `text` text NOT NULL,
  `user` int(32) NOT NULL,
  `time` int(32) NOT NULL,
  `cat` text,
  `hide` bit(1) NOT NULL DEFAULT b'0',
  `lastedituser` int(32) NOT NULL DEFAULT '0',
  `lastedittime` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Used by the external "plugin" news.php';
CREATE TABLE `pms` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` text NOT NULL,
  `title` text NOT NULL,
  `user` int(32) NOT NULL,
  `userto` int(32) NOT NULL,
  `time` int(32) NOT NULL,
  `text` text NOT NULL,
  `nohtml` tinyint(1) NOT NULL DEFAULT '0',
  `nosmilies` tinyint(1) NOT NULL DEFAULT '0',
  `nolayout` tinyint(1) NOT NULL DEFAULT '0',
  `avatar` int(32) NOT NULL DEFAULT '0',
  `new` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `poll_choices` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `thread` int(32) NOT NULL,
  `name` text NOT NULL,
  `color` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `poll_votes` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user` int(32) NOT NULL,
  `thread` int(32) NOT NULL,
  `vote` int(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `polls` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `thread` int(32) NOT NULL,
  `question` text NOT NULL,
  `briefing` text NOT NULL,
  `multivote` tinyint(1) NOT NULL DEFAULT '0',
  `closed` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `posts` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `text` text NOT NULL,
  `time` int(32) NOT NULL,
  `thread` int(32) NOT NULL,
  `user` int(32) NOT NULL,
  `rev` int(4) NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `nohtml` tinyint(1) NOT NULL DEFAULT '0',
  `nosmilies` tinyint(1) NOT NULL DEFAULT '0',
  `nolayout` tinyint(1) NOT NULL DEFAULT '0',
  `lastedited` int(32) NOT NULL DEFAULT '0',
  `avatar` int(32) NOT NULL DEFAULT '0',
  `noob` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `posts_old` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `pid` int(32) NOT NULL,
  `text` text NOT NULL,
  `time` int(32) NOT NULL,
  `rev` int(4) NOT NULL,
  `nohtml` tinyint(1) NOT NULL DEFAULT '0',
  `nosmilies` tinyint(1) NOT NULL DEFAULT '0',
  `nolayout` tinyint(1) NOT NULL DEFAULT '0',
  `avatar` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `radar` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user` int(32) NOT NULL,
  `sel` int(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `ranks` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `rankset` int(32) NOT NULL,
  `posts` int(32) NOT NULL,
  `text` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `ranks` (`id`, `rankset`, `posts`, `text`) VALUES
(1, 1, 0, 'Nobody'),
(2, 1, 1, 'Random nobody'),
(3, 1, 10, 'User'),
(4, 1, 25, 'Member'),
(5, 1, 1000, 'Catgirl'),
(6, 1, 2500, 'Common spammer'),
(7, 2, 0, '<img src=''images/ranks/tgm/9.png''>'),
(8, 2, 10, '<img src=''images/ranks/tgm/8.png''>'),
(9, 2, 25, '<img src=''images/ranks/tgm/7.png''>'),
(10, 2, 50, '<img src=''images/ranks/tgm/6.png''>'),
(11, 2, 100, '<img src=''images/ranks/tgm/5.png''>'),
(12, 2, 150, '<img src=''images/ranks/tgm/4.png''>'),
(13, 2, 200, '<img src=''images/ranks/tgm/3.png''>'),
(14, 2, 250, '<img src=''images/ranks/tgm/2.png''>'),
(15, 2, 350, '<img src=''images/ranks/tgm/1.png''>'),
(16, 2, 500, '<img src=''images/ranks/tgm/s1.png''>'),
(17, 2, 750, '<img src=''images/ranks/tgm/s2.png''>'),
(18, 2, 1000, '<img src=''images/ranks/tgm/s3.png''>'),
(19, 2, 1250, '<img src=''images/ranks/tgm/s4.png''>'),
(20, 2, 1500, '<img src=''images/ranks/tgm/s5.png''>'),
(21, 2, 2000, '<img src=''images/ranks/tgm/s6.png''>'),
(22, 2, 2500, '<img src=''images/ranks/tgm/s7.png''>'),
(23, 2, 3250, '<img src=''images/ranks/tgm/s8.png''>'),
(24, 2, 4000, '<img src=''images/ranks/tgm/s9.png''>'),
(25, 2, 5000, '<img src=''images/ranks/tgm/gm.png''>');
CREATE TABLE `ranksets` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `ranksets` (`id`, `name`) VALUES
(1, 'Default'),
(2, 'TGM');
CREATE TABLE `ratings` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `userfrom` int(32) NOT NULL,
  `userto` int(32) NOT NULL,
  `rating` tinyint(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `rpg_classes` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `require_exp` int(32) NOT NULL DEFAULT '0',
  `require_powl` smallint(4) NOT NULL DEFAULT '0',
  `bonus_exp` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `rpg_classes` (`id`, `name`, `require_exp`, `require_powl`, `bonus_exp`) VALUES
(1, 'Evil Overlord', 0, 4, 25000);
CREATE TABLE `shop_categories` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `title` varchar(128) NOT NULL,
  `ord` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `shop_categories` (`id`, `name`, `title`, `ord`) VALUES
(1, 'Sample category', 'This is a sample description', 0);
CREATE TABLE `shop_items` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `title` mediumtext NOT NULL,
  `cat` int(32) NOT NULL,
  `sHP` varchar(32) NOT NULL,
  `sMP` varchar(32) NOT NULL,
  `sAtk` varchar(32) NOT NULL,
  `sDef` varchar(32) NOT NULL,
  `sInt` varchar(32) NOT NULL,
  `sMDf` varchar(32) NOT NULL,
  `sDex` varchar(32) NOT NULL,
  `sLck` varchar(32) NOT NULL,
  `sSpd` varchar(32) NOT NULL,
  `coins` varchar(32) NOT NULL DEFAULT '0',
  `gcoins` varchar(32) NOT NULL DEFAULT '0',
  `special` int(32) NOT NULL DEFAULT '0',
  `ord` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `shop_items` (`id`, `name`, `title`, `cat`, `sHP`, `sMP`, `sAtk`, `sDef`, `sInt`, `sMDf`, `sDex`, `sLck`, `sSpd`, `coins`, `gcoins`, `special`, `ord`) VALUES
(1, 'Test item?', 'It does not actually do anything! (or does it?)', 1, '+1000', '-10', 'x45', '/2', '+2', '+0', '+56', '+9999', '+1', '0', '0', 1, 0);
CREATE TABLE `themes` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `file` varchar(64) NOT NULL,
  `special` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `themes` (`id`, `name`, `file`, `special`) VALUES
(1, 'Night (Jul)', 'night', 0),
(2, 'Hydra''s Blue Thing (Alternate)', 'hbluealt', 0),
(3, 'The Zen', 'spec-zen', 1),
(4, 'Daily Cycle', 'dailycycle', 0);
CREATE TABLE `threads` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `time` int(32) NOT NULL,
  `forum` int(32) NOT NULL,
  `user` int(32) NOT NULL,
  `sticky` tinyint(1) NOT NULL DEFAULT 0,
  `closed` tinyint(1) NOT NULL DEFAULT 0,
  `views` int(32) NOT NULL DEFAULT '0',
  `replies` int(32) NOT NULL DEFAULT '0',
  `icon` text,
  `ispoll` tinyint(1) NOT NULL DEFAULT '0',
  `lastpostid` int(32) DEFAULT NULL,
  `lastpostuser` int(32) DEFAULT NULL,
  `lastposttime` int(32) DEFAULT NULL,
  `noob` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `threads_read` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user0` int(32) NOT NULL DEFAULT '0',
  `user1` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Timestamp method to track last thread view';
CREATE TABLE `users` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `displayname` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `powerlevel` int(1) NOT NULL DEFAULT '0',
  `sex` int(1) NOT NULL DEFAULT '2',
  `namecolor` varchar(6) DEFAULT NULL,
  `lastip` varchar(32) DEFAULT NULL,
  `ban_expire` int(32) DEFAULT '0',
  `since` int(32) NOT NULL DEFAULT '0',
  `ppp` int(3) NOT NULL DEFAULT '25',
  `tpp` int(3) NOT NULL DEFAULT '25',
  `head` text,
  `sign` text,
  `dateformat` varchar(32) DEFAULT NULL,
  `timeformat` varchar(32) DEFAULT NULL,
  `lastpost` int(32) NOT NULL DEFAULT '0',
  `lastview` int(32) NOT NULL DEFAULT '0',
  `lastforum` int(32) NOT NULL DEFAULT '0',
  `bio` text,
  `posts` int(32) NOT NULL DEFAULT '0',
  `threads` int(32) NOT NULL DEFAULT '0',
  `email` varchar(64) NOT NULL DEFAULT '',
  `homepage` varchar(64) NOT NULL DEFAULT '',
  `youtube` varchar(64) NOT NULL DEFAULT '',
  `twitter` varchar(64) NOT NULL DEFAULT '',
  `facebook` varchar(64) NOT NULL DEFAULT '',
  `homepage_name` varchar(64) NOT NULL DEFAULT '',
  `tzoff` int(2) NOT NULL DEFAULT '0',
  `realname` varchar(64) NOT NULL DEFAULT '',
  `location` varchar(64) NOT NULL DEFAULT '',
  `birthday` int(32) DEFAULT NULL,
  `theme` int(8) NOT NULL DEFAULT '1',
  `showhead` tinyint(1) NOT NULL DEFAULT '1',
  `signsep` int(3) NOT NULL DEFAULT '1',
  `icon` text,
  `spent` int(32) NOT NULL DEFAULT '0',
  `gcoins` int(32) NOT NULL DEFAULT '0',
  `gspent` int(32) NOT NULL DEFAULT '0',
  `radar_mode` int(4) NOT NULL DEFAULT '0',
  `profile_locked` tinyint(1) NOT NULL DEFAULT '0',
  `editing_locked` int(1) NOT NULL DEFAULT '0',
  `title_status` int(1) NOT NULL DEFAULT '0',
  `rankset` int(4) NOT NULL DEFAULT '1',
  `publicemail` tinyint(1) NOT NULL DEFAULT '2',
  `class` smallint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `users_rpg` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `eq1` int(32) NOT NULL DEFAULT '0',
  `eq2` int(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `users_rpg` (`id`, `eq1`, `eq2`) VALUES
(1, 0, 0),
(2, 0, 0);
CREATE TABLE `user_avatars` (
  `id` int(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user` int(32) NOT NULL,
  `file` int(16) NOT NULL,
  `title` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `forummods` ADD UNIQUE KEY `unimod` (`fid`,`uid`);
ALTER TABLE `hits` ADD UNIQUE KEY `ip` (`ip`);
ALTER TABLE `polls` ADD UNIQUE KEY `thread` (`thread`);
ALTER TABLE `rpg_classes` ADD UNIQUE KEY `uniname` (`name`);
ALTER TABLE `users` ADD UNIQUE KEY `name` (`name`);
