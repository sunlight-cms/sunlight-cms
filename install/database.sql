CREATE TABLE IF NOT EXISTS `sunlight_articles` (
`id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `keywords` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  `perex` text NOT NULL,
  `picture_uid` varchar(13) DEFAULT NULL,
  `content` longtext NOT NULL,
  `author` int(11) NOT NULL,
  `home1` int(11) NOT NULL,
  `home2` int(11) NOT NULL DEFAULT '-1',
  `home3` int(11) NOT NULL DEFAULT '-1',
  `time` int(11) NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `public` tinyint(1) NOT NULL DEFAULT '1',
  `comments` tinyint(1) NOT NULL DEFAULT '1',
  `commentslocked` tinyint(1) NOT NULL DEFAULT '0',
  `confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `showinfo` tinyint(1) NOT NULL DEFAULT '1',
  `readnum` int(11) NOT NULL DEFAULT '0',
  `rateon` tinyint(1) NOT NULL DEFAULT '1',
  `ratenum` int(11) NOT NULL DEFAULT '0',
  `ratesum` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `sunlight_boxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ord` int(11) NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT '',
  `content` text NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `public` tinyint(1) NOT NULL DEFAULT '1',
  `level` int(11) NOT NULL DEFAULT '0',
  `template` varchar(255) NOT NULL,
  `layout` varchar(255) NOT NULL,
  `slot` varchar(64) NOT NULL,
  `page_ids` text NOT NULL,
  `page_children` tinyint(1) NOT NULL DEFAULT '0',
  `class` varchar(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ord` (`ord`),
  KEY `visible` (`visible`),
  KEY `public` (`public`),
  KEY `slot` (`slot`),
  KEY `level` (`level`),
  KEY `template` (`template`),
  KEY `layout` (`layout`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `sunlight_boxes` (`id`, `ord`, `title`, `content`, `visible`, `public`, `level`, `template`, `layout`, `slot`, `page_ids`, `page_children`, `class`) VALUES
(1,	1,	'Menu',	'[hcm]menu_tree[/hcm]',	1,	1,	0,	'default',	'default',	'right',	'',	0,	''),
(2,	2,	'Vyhledávání',	'[hcm]search[/hcm]',	1,	1,	0,	'default',	'default',	'right',	'',	0,	''),
(3,	3,	'',	'<br /><p class=\"center\"><a href=\'https://sunlight-cms.org/\' title=\'SunLight CMS - open source redakční systém zdarma\'><img src=\'http://sunlight-cms.org/icon.png\' alt=\'SunLight CMS - open source redakční systém zdarma\' style=\'width:88px;height:31px;border:0;\' /></a></p>',	1,	1,	0,	'default',	'default',	'right',	'',	0,	'');

CREATE TABLE IF NOT EXISTS `sunlight_groups` (
`id` int(11) NOT NULL,
  `title` varchar(128) NOT NULL,
  `descr` varchar(255) NOT NULL DEFAULT '',
  `level` int(11) NOT NULL DEFAULT '0',
  `icon` varchar(16) NOT NULL DEFAULT '',
  `color` varchar(16) NOT NULL DEFAULT '',
  `blocked` tinyint(1) NOT NULL DEFAULT '0',
  `reglist` tinyint(1) NOT NULL DEFAULT '0',
  `administration` tinyint(1) NOT NULL DEFAULT '0',
  `adminsettings` tinyint(1) NOT NULL DEFAULT '0',
  `adminplugins` tinyint(1) NOT NULL DEFAULT '0',
  `adminusers` tinyint(1) NOT NULL DEFAULT '0',
  `admingroups` tinyint(1) NOT NULL DEFAULT '0',
  `admincontent` tinyint(1) NOT NULL DEFAULT '0',
  `adminother` tinyint(1) NOT NULL DEFAULT '0',
  `adminroot` tinyint(1) NOT NULL DEFAULT '0',
  `adminsection` tinyint(1) NOT NULL DEFAULT '0',
  `admincategory` tinyint(1) NOT NULL DEFAULT '0',
  `adminbook` tinyint(1) NOT NULL DEFAULT '0',
  `adminseparator` tinyint(1) NOT NULL DEFAULT '0',
  `admingallery` tinyint(1) NOT NULL DEFAULT '0',
  `adminlink` tinyint(1) NOT NULL DEFAULT '0',
  `admingroup` tinyint(1) NOT NULL DEFAULT '0',
  `adminforum` tinyint(1) NOT NULL DEFAULT '0',
  `adminpluginpage` tinyint(1) NOT NULL DEFAULT '0',
  `adminart` tinyint(1) NOT NULL DEFAULT '0',
  `adminallart` tinyint(1) NOT NULL DEFAULT '0',
  `adminchangeartauthor` tinyint(1) NOT NULL DEFAULT '0',
  `adminconfirm` tinyint(1) NOT NULL DEFAULT '0',
  `adminautoconfirm` tinyint(1) NOT NULL DEFAULT '0',
  `adminpoll` tinyint(1) NOT NULL DEFAULT '0',
  `adminpollall` tinyint(1) NOT NULL DEFAULT '0',
  `adminsbox` tinyint(1) NOT NULL DEFAULT '0',
  `adminbox` tinyint(1) NOT NULL DEFAULT '0',
  `fileaccess` tinyint(1) NOT NULL DEFAULT '0',
  `fileglobalaccess` tinyint(1) NOT NULL DEFAULT '0',
  `fileadminaccess` tinyint(1) NOT NULL DEFAULT '0',
  `adminhcm` varchar(255) NOT NULL DEFAULT '',
  `adminhcmphp` tinyint(1) NOT NULL DEFAULT '0',
  `adminbackup` tinyint(1) NOT NULL DEFAULT '0',
  `adminmassemail` tinyint(1) NOT NULL DEFAULT '0',
  `adminposts` tinyint(1) NOT NULL,
  `changeusername` tinyint(1) NOT NULL DEFAULT '0',
  `postcomments` tinyint(1) NOT NULL DEFAULT '0',
  `unlimitedpostaccess` tinyint(1) NOT NULL DEFAULT '0',
  `locktopics` tinyint(1) NOT NULL DEFAULT '0',
  `stickytopics` tinyint(1) NOT NULL DEFAULT '0',
  `movetopics` tinyint(1) NOT NULL DEFAULT '0',
  `artrate` tinyint(1) NOT NULL DEFAULT '0',
  `pollvote` tinyint(1) NOT NULL DEFAULT '0',
  `selfremove` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

INSERT INTO `sunlight_groups` (`id`, `title`, `descr`, `level`, `icon`, `color`, `blocked`, `reglist`, `administration`, `adminsettings`, `adminplugins`, `adminusers`, `admingroups`, `admincontent`, `adminother`, `adminroot`, `adminsection`, `admincategory`, `adminbook`, `adminseparator`, `admingallery`, `adminlink`, `admingroup`, `adminforum`, `adminpluginpage`, `adminart`, `adminallart`, `adminchangeartauthor`, `adminconfirm`, `adminautoconfirm`, `adminpoll`, `adminpollall`, `adminsbox`, `adminbox`, `fileaccess`, `fileglobalaccess`, `fileadminaccess`, `adminhcm`, `adminhcmphp`, `adminbackup`, `adminmassemail`, `adminposts`, `changeusername`, `postcomments`, `unlimitedpostaccess`, `locktopics`, `stickytopics`, `movetopics`, `artrate`, `pollvote`, `selfremove`) VALUES
(1, 'SUPER_ADMIN', '', 10000, 'redstar.png', '', 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, '*', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1),
(2, 'GUESTS', '', 0, '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 1, 0),
(3, 'REGISTERED', '', 1, '', '', 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0, '', 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 1, 1),
(4, 'ADMINISTRATORS', '', 1000, 'orangestar.png', '', 0, 0, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, '*', 0, 0, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 0),
(5, 'MODERATORS', '', 600, 'greenstar.png', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0, '', 0, 0, 0, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0),
(6, 'EDITOR', '', 500, 'bluestar.png', '', 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 'poll, gallery, linkart, linkroot', 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 1, 0);

CREATE TABLE IF NOT EXISTS `sunlight_images` (
`id` int(11) NOT NULL,
  `home` int(11) NOT NULL,
  `ord` int(11) NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT '',
  `prev` varchar(255) NOT NULL DEFAULT '',
  `full` varchar(255) NOT NULL DEFAULT '',
  `in_storage` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sunlight_iplog` (
`id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `time` int(11) NOT NULL,
  `var` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sunlight_pm` (
`id` int(11) NOT NULL,
  `sender` int(11) NOT NULL,
  `sender_readtime` int(11) NOT NULL DEFAULT '0',
  `sender_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `receiver` int(11) NOT NULL,
  `receiver_readtime` int(11) NOT NULL DEFAULT '0',
  `receiver_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sunlight_polls` (
`id` int(11) NOT NULL,
  `author` int(11) NOT NULL,
  `question` varchar(96) NOT NULL,
  `answers` text NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `votes` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sunlight_posts` (
`id` int(11) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `home` int(11) NOT NULL,
  `xhome` int(11) NOT NULL DEFAULT '-1',
  `subject` varchar(48) NOT NULL DEFAULT '',
  `text` text NOT NULL,
  `author` int(11) NOT NULL DEFAULT '-1',
  `guest` varchar(24) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `bumptime` int(11) NOT NULL DEFAULT '0',
  `sticky` tinyint(1) NOT NULL DEFAULT '0',
  `flag` int(10) unsigned NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sunlight_redir` (
`id` int(11) NOT NULL,
  `old` varchar(255) NOT NULL,
  `new` varchar(255) NOT NULL,
  `permanent` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sunlight_root` (
`id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `heading` varchar(255) NOT NULL DEFAULT '',
  `slug` text NOT NULL,
  `slug_abs` tinyint(1) NOT NULL DEFAULT '0',
  `keywords` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  `type` tinyint(4) NOT NULL,
  `type_idt` varchar(16) DEFAULT NULL,
  `node_parent` int(11) DEFAULT NULL,
  `node_level` int(11) NOT NULL DEFAULT '0',
  `node_depth` int(11) NOT NULL DEFAULT '0',
  `perex` text NOT NULL,
  `ord` int(11) NOT NULL DEFAULT '0',
  `content` longtext NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `public` tinyint(1) NOT NULL DEFAULT '1',
  `level` int(11) NOT NULL DEFAULT '0',
  `level_inherit` tinyint(1) NOT NULL DEFAULT '0',
  `show_heading` tinyint(1) NOT NULL DEFAULT '1',
  `events` varchar(255) DEFAULT NULL,
  `link_new_window` tinyint(1) NOT NULL DEFAULT '0',
  `link_url` varchar(255) DEFAULT NULL,
  `layout` varchar(255) DEFAULT NULL,
  `layout_inherit` tinyint(1) NOT NULL DEFAULT '0',
  `var1` int(11) DEFAULT NULL,
  `var2` int(11) DEFAULT NULL,
  `var3` int(11) DEFAULT NULL,
  `var4` int(11) DEFAULT NULL
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO `sunlight_root` (`id`, `title`, `heading`, `slug`, `slug_abs`, `keywords`, `description`, `type`, `type_idt`, `node_parent`, `node_level`, `node_depth`, `perex`, `ord`, `content`, `visible`, `public`, `level`, `level_inherit`, `show_heading`, `events`, `link_new_window`, `link_url`, `layout`, `layout_inherit`, `var1`, `var2`, `var3`, `var4`) VALUES
(1, '', '', 'index', 0, '', '', 1, NULL, NULL, 0, 0, '', 1, '', 1, 1, 0, 1, 1, NULL, 0, NULL, '', 0, 0, 0, 0, 0);

CREATE TABLE IF NOT EXISTS `sunlight_sboxes` (
`id` int(11) NOT NULL,
  `title` varchar(64) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `public` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sunlight_settings` (
  `var` varchar(24) NOT NULL,
  `val` text NOT NULL,
  `format` varchar(16) DEFAULT NULL,
  `constant` tinyint(1) NOT NULL DEFAULT '0',
  `preload` tinyint(1) NOT NULL DEFAULT '0',
  `web` tinyint(1) NOT NULL,
  `admin` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `sunlight_settings` (`var`, `val`, `format`, `constant`, `preload`, `web`, `admin`) VALUES
('postsendexpire', '50', 'int', 1, 1, 1, 1),
('pollvoteexpire', '604800', 'int', 1, 1, 1, 1),
('artreadexpire', '18000', 'int', 1, 1, 1, 1),
('maxloginexpire', '900', 'int', 1, 1, 1, 1),
('maxloginattempts', '20', 'int', 1, 1, 1, 1),
('pagingmode', '2', 'int', 1, 1, 1, 1),
('profileemail', '0', 'bool', 1, 1, 1, 1),
('captcha', '1', 'bool', 1, 1, 1, 1),
('default_template', 'default', NULL, 1, 1, 1, 1),
('title', '', 'html', 1, 1, 1, 1),
('description', '', 'html', 1, 1, 1, 1),
('commentsperpage', '10', 'int', 1, 1, 1, 1),
('smileys', '1', 'bool', 1, 1, 1, 1),
('postadmintime', '172800', 'int', 1, 1, 1, 1),
('keywords', '', 'html', 1, 1, 1, 1),
('adminscheme', '0', 'int', 1, 1, 0, 1),
('dbversion', '8.0.0', NULL, 1, 1, 1, 1),
('atreplace', '', 'html', 1, 1, 1, 1),
('bbcode', '1', 'bool', 1, 1, 1, 1),
('defaultgroup', '3', 'int', 1, 1, 1, 1),
('mailerusefrom', '0', 'bool', 1, 1, 1, 1),
('showpages', '4', 'int', 1, 1, 1, 1),
('ulist', '0', 'bool', 1, 1, 1, 1),
('registration', '1', 'bool', 1, 1, 1, 1),
('language', 'cs', NULL, 0, 1, 1, 1),
('pretty_urls', '0', 'bool', 1, 1, 1, 1),
('titleseparator', '-', 'html', 1, 1, 1, 1),
('notpublicsite', '0', 'bool', 1, 1, 1, 1),
('comments', '1', 'bool', 1, 1, 1, 1),
('artrateexpire', '604800', 'int', 1, 1, 1, 1),
('rss', '1', 'bool', 1, 1, 1, 1),
('messages', '1', 'bool', 1, 1, 1, 1),
('messagesperpage', '30', 'int', 1, 1, 1, 1),
('search', '1', 'bool', 1, 1, 1, 1),
('author', '', 'html', 1, 1, 1, 1),
('titletype', '2', 'int', 1, 1, 1, 1),
('adminlinkprivate', '0', 'bool', 1, 1, 1, 1),
('language_allowcustom', '0', 'bool', 1, 1, 1, 1),
('lostpass', '1', 'bool', 1, 1, 1, 1),
('registration_grouplist', '0', 'bool', 1, 1, 1, 1),
('favicon', '0', 'bool', 1, 1, 1, 1),
('rules', '', NULL, 0, 0, 1, 1),
('galdefault_per_row', '3', 'int', 1, 1, 1, 1),
('extratopicslimit', '12', 'int', 1, 1, 1, 1),
('rsslimit', '30', 'int', 1, 1, 1, 1),
('sboxmemory', '20', 'int', 1, 1, 1, 1),
('ratemode', '2', 'int', 1, 1, 1, 1),
('time_format', 'j.n.Y G:i', 'html', 1, 1, 1, 1),
('uploadavatar', '1', 'bool', 1, 1, 1, 1),
('galuploadresize_w', '750', 'int', 1, 1, 1, 1),
('galuploadresize_h', '565', 'int', 1, 1, 1, 1),
('show_avatars', '1', 'bool', 1, 1, 1, 1),
('accactexpire', '1200', 'int', 1, 1, 1, 1),
('registration_confirm', '0', 'bool', 1, 1, 1, 1),
('sysmail', '', NULL, 1, 1, 1, 1),
('lostpassexpire', '1800', 'int', 1, 1, 1, 1),
('cacheid', '0', 'int', 1, 1, 1, 1),
('admin_index_custom', '', NULL, 0, 0, 0, 1),
('admin_index_custom_pos', '1', NULL, 0, 0, 0, 1),
('index_page_id', '1', NULL, 1, 1, 1, 1),
('adminscheme_mode', '0', 'int', 1, 1, 0, 1),
('article_pic_w', '600', 'int', 1, 1, 1, 1),
('article_pic_h', '600', 'int', 1, 1, 1, 1),
('topic_hot_ratio', '20', 'int', 1, 1, 1, 1),
('install_check', '1', NULL, 1, 1, 1, 1),
('proxy_mode', '0', 'bool', 1, 1, 1, 1),
('cron_times', '', NULL, 0, 1, 1, 1),
('maintenance_interval', '259200', 'int', 1, 1, 1, 1),
('cron_auto', '1', 'bool', 1, 1, 1, 1),
('thumb_cleanup_threshold', '604800', 'int', 1, 1, 1, 1),
('thumb_touch_threshold', '43200', 'int', 1, 1, 1, 1),
('cron_auth', '', NULL, 0, 0, 1, 1),
('adminpagelist_mode', '0', NULL, 1, 1, 0, 1),
('galdefault_per_page', '9', 'int', 1, 1, 1, 1),
('galdefault_thumb_w', '147', 'int', 1, 1, 1, 1),
('galdefault_thumb_h', '110', 'int', 1, 1, 1, 1),
('articlesperpage', '15', 'int', 1, 1, 1, 1),
('topicsperpage', '30', 'int', 1, 1, 1, 1),
('article_pic_thumb_h', '200', 'int', 1, 1, 1, 1),
('article_pic_thumb_w', '200', 'int', 1, 1, 1, 1);

CREATE TABLE IF NOT EXISTS `sunlight_users` (
`id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `levelshift` tinyint(1) NOT NULL DEFAULT '0',
  `username` varchar(24) NOT NULL,
  `publicname` varchar(24) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `security_hash` varchar(64) DEFAULT NULL,
  `security_hash_expires` int(11) NOT NULL DEFAULT '0',
  `logincounter` int(11) NOT NULL DEFAULT '0',
  `registertime` int(11) NOT NULL DEFAULT '0',
  `activitytime` int(11) NOT NULL DEFAULT '0',
  `blocked` tinyint(1) NOT NULL DEFAULT '0',
  `massemail` tinyint(1) NOT NULL DEFAULT '1',
  `wysiwyg` tinyint(1) NOT NULL DEFAULT '1',
  `language` varchar(12) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL,
  `avatar` varchar(13) DEFAULT NULL,
  `web` varchar(255) NOT NULL DEFAULT '',
  `skype` varchar(255) NOT NULL DEFAULT '',
  `icq` varchar(255) NOT NULL DEFAULT '',
  `note` text NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO `sunlight_users` (`id`, `group_id`, `levelshift`, `username`, `publicname`, `password`, `security_hash`, `security_hash_expires`, `logincounter`, `registertime`, `activitytime`, `blocked`, `massemail`, `wysiwyg`, `language`, `ip`, `email`, `avatar`, `web`, `skype`, `icq`, `note`) VALUES
(0, 1, 1, '', NULL, '', NULL, 0, 0, 0, 0, 0, 1, 0, '', '', '', NULL, '', '', '', '');

CREATE TABLE IF NOT EXISTS `sunlight_user_activation` (
`id` int(11) NOT NULL,
  `code` varchar(48) NOT NULL,
  `expire` int(11) NOT NULL,
  `data` mediumblob NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


ALTER TABLE `sunlight_articles`
 ADD PRIMARY KEY (`id`), ADD KEY `author` (`author`), ADD KEY `home1` (`home1`), ADD KEY `home2` (`home2`), ADD KEY `home3` (`home3`), ADD KEY `time` (`time`), ADD KEY `visible` (`visible`), ADD KEY `public` (`public`), ADD KEY `confirmed` (`confirmed`), ADD KEY `ratenum` (`ratenum`), ADD KEY `ratesum` (`ratesum`), ADD KEY `slug` (`slug`);

ALTER TABLE `sunlight_boxes`
 ADD PRIMARY KEY (`id`), ADD KEY `ord` (`ord`), ADD KEY `visible` (`visible`), ADD KEY `public` (`public`), ADD KEY `slot` (`slot`);

ALTER TABLE `sunlight_groups`
 ADD PRIMARY KEY (`id`), ADD KEY `level` (`level`), ADD KEY `blocked` (`blocked`), ADD KEY `reglist` (`reglist`);

ALTER TABLE `sunlight_images`
 ADD PRIMARY KEY (`id`), ADD KEY `home` (`home`), ADD KEY `full` (`full`(8)), ADD KEY `in_storage` (`in_storage`), ADD KEY `ord` (`ord`);

ALTER TABLE `sunlight_iplog`
 ADD PRIMARY KEY (`id`), ADD KEY `ip` (`ip`), ADD KEY `type` (`type`), ADD KEY `time` (`time`), ADD KEY `var` (`var`);

ALTER TABLE `sunlight_pm`
 ADD PRIMARY KEY (`id`), ADD KEY `sender` (`sender`), ADD KEY `receiver` (`receiver`), ADD KEY `update_time` (`update_time`), ADD KEY `sender_deleted` (`sender_deleted`), ADD KEY `receiver_deleted` (`receiver_deleted`);

ALTER TABLE `sunlight_polls`
 ADD PRIMARY KEY (`id`), ADD KEY `author` (`author`);

ALTER TABLE `sunlight_posts`
 ADD PRIMARY KEY (`id`), ADD KEY `bumptime` (`bumptime`), ADD KEY `type` (`type`), ADD KEY `home` (`home`), ADD KEY `xhome` (`xhome`), ADD KEY `author` (`author`), ADD KEY `time` (`time`), ADD KEY `sticky` (`sticky`), ADD KEY `flag` (`flag`);

ALTER TABLE `sunlight_redir`
 ADD PRIMARY KEY (`id`), ADD KEY `old` (`old`), ADD KEY `active` (`active`), ADD KEY `permanent` (`permanent`);

ALTER TABLE `sunlight_root`
 ADD PRIMARY KEY (`id`), ADD KEY `level` (`level`), ADD KEY `type` (`type`), ADD KEY `ord` (`ord`), ADD KEY `visible` (`visible`), ADD KEY `public` (`public`), ADD KEY `show_heading` (`show_heading`), ADD KEY `var1` (`var1`), ADD KEY `var2` (`var2`), ADD KEY `var3` (`var3`), ADD KEY `var4` (`var4`), ADD KEY `slug_seo_abs` (`slug_abs`), ADD KEY `slug_seo` (`slug`(16)), ADD KEY `node_parent` (`node_parent`);

ALTER TABLE `sunlight_sboxes`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `sunlight_settings`
 ADD PRIMARY KEY (`var`), ADD KEY `constant` (`constant`), ADD KEY `preload` (`preload`), ADD KEY `web` (`web`), ADD KEY `admin` (`admin`);

ALTER TABLE `sunlight_users`
 ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`), ADD UNIQUE KEY `email` (`email`), ADD UNIQUE KEY `publicname` (`publicname`), ADD KEY `group` (`group_id`), ADD KEY `logincounter` (`logincounter`), ADD KEY `registertime` (`registertime`), ADD KEY `activitytime` (`activitytime`), ADD KEY `blocked` (`blocked`), ADD KEY `massemail` (`massemail`);

ALTER TABLE `sunlight_user_activation`
 ADD PRIMARY KEY (`id`), ADD KEY `code` (`code`), ADD KEY `expire` (`expire`);

ALTER TABLE `sunlight_articles`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sunlight_boxes`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
ALTER TABLE `sunlight_groups`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=7;
ALTER TABLE `sunlight_images`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sunlight_iplog`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sunlight_pm`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sunlight_polls`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sunlight_posts`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sunlight_redir`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sunlight_root`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
ALTER TABLE `sunlight_sboxes`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sunlight_users`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
ALTER TABLE `sunlight_user_activation`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
