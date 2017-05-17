<?php

/**
 * Static constant values
 */

// Database tables

/** Articles table name */
define('_articles_table', _dbprefix . 'articles');

/** Boxes table name */
define('_boxes_table', _dbprefix . 'boxes');

/** Groups table name */
define('_groups_table', _dbprefix . 'groups');

/** Images table name */
define('_images_table', _dbprefix . 'images');

/** IP log table name */
define('_iplog_table', _dbprefix . 'iplog');

/** Private messages table name */
define('_pm_table', _dbprefix . 'pm');

/** Polls table name */
define('_polls_table', _dbprefix . 'polls');

/** Posts table name */
define('_posts_table', _dbprefix . 'posts');

/** Redirection table name */
define('_redir_table', _dbprefix . 'redir');

/** Root table name (pages) */
define('_root_table', _dbprefix . 'root');

/** Shoutboxes table name */
define('_sboxes_table', _dbprefix . 'sboxes');

/** Settings table name */
define('_settings_table', _dbprefix . 'settings');

/** Users table name */
define('_users_table', _dbprefix . 'users');

/** User activation table name */
define('_user_activation_table', _dbprefix . 'user_activation');


// Message types

/** OK message (success) */
const _msg_ok = 'ok';

/** Warning message */
const _msg_warn = 'warn';

/** * Error message */
const _msg_err = 'err';


// RSS feed types

/**
 * RSS feed for section comments
 *
 * Identifier: page ID (section)
 */
const _rss_section_comments = 1;

/**
 * RSS feed for article comments
 *
 * Identifier: article ID
 */
const _rss_article_comments = 2;

/**
 * RSS feed for book posts
 *
 * Identifier: page ID (book)
 */
const _rss_book_posts = 3;

/**
 * RSS feed for latest articles
 *
 * Identifier: page ID (category) or -1 (all)
 */
const _rss_latest_articles = 4;

/**
 * RSS feed for latest topics
 *
 * Identifier: page ID (forum)
 */
const _rss_latest_topics = 5;

/**
 * RSS feed for latest answers in a topic
 *
 * Identifier: post ID (topic)
 */
const _rss_latest_topic_answers = 6;

/**
 * RSS feed for latest comments (global)
 */
const _rss_latest_comments = 7;


// IP log entry types

/**
 * IP log entry for failed login attempt
 *
 * var: none
 */
const _iplog_failed_login_attempt = 1;

/**
 * IP log entry for article read counter cooldown
 *
 * var: article ID
 */
const _iplog_article_read = 2;

/**
 * IP log entry for article rating cooldown
 *
 * var: article ID
 */
const _iplog_article_rated = 3;

/**
 * IP log entry for poll vote cooldown
 *
 * var: poll ID
 */
const _iplog_poll_vote = 4;

/**
 * IP log entry anti-spam cooldown
 *
 * var: none
 */
const _iplog_anti_spam = 5;

/**
 * IP log entry for failed account activation attempt
 *
 * var: none
 */
const _iplog_failed_account_activation = 6;

/**
 * IP log entry for password reset request
 */
const _iplog_password_reset_requested = 7;


// Page types

/**
 * Section page type
 *
 * var1:    comments enabled 1/0
 * var2:    *unused*
 * var3:    lockec comments 1/0
 * var4:    *unused*
 */
const _page_section = 1;

/**
 * Category page type
 *
 * var1:    article order type (1 = time DESC, 2 = id DESC, 3 = title ASC, 4 = title DESC)
 * var2:    number of articles per page
 * var3:    show article info 1/0
 * var4:    show article thumbnails 1/0
 */
const _page_category = 2;

/**
 * Book page type
 *
 * var1:    allow guest posts 1/0
 * var2:    number of posts per page
 * var3:    locked 1/0
 * var4:    *unused*
 */
const _page_book = 3;

/**
 * Separator page type
 *
 * var1:    *unused*
 * var2:    *unused*
 * var3:    *unused*
 * var4:    *unused*
 */
const _page_separator = 4;

/**
 * Gallery page type
 *
 * var1:    number images per row (-1 = don't make a table)
 * var2:    number of images per page
 * var3:    thumbnail height
 * var4:    thumbnail width
 */
const _page_gallery = 5;

/**
 * Link page type
 *
 * var1:    open in new window 1/0
 * var2:    *unused*
 * var3:    *unused*
 * var4:    *unused*
 */
const _page_link = 6;

/**
 * Group page type
 *
 * var1:    show item info 1/0
 * var2:    *unused*
 * var3:    *unused*
 * var4:    *unused*
 */
const _page_group = 7;

/**
 * Forum page type
 *
 * var1:    number of topics per page
 * var2:    locked 1/0
 * var3:    allow guest topics 1/0
 * var4:    *unused*
 */
const _page_forum = 8;

/**
 * Plugin page type
 *
 * var1:    *plugin-implementation dependent*
 * var2:    *plugin-implementation dependent*
 * var3:    *plugin-implementation dependent*
 * var4:    *plugin-implementation dependent*
 */
const _page_plugin = 9;


// Post types

/**
 * Section comment
 *
 * home:    page ID (section)
 * xhome:   post ID (if comment is an answer) or -1
 */
const _post_section_comment = 1;

/**
 * Article comment:
 *
 * home:    article ID
 * xhome:   post ID (if comment is an answer) or -1
 */
const _post_article_comment = 2;

/**
 * Book entry
 *
 * home:    page ID (book)
 * xhome:   post ID ID (if comment is an answer) or -1
 */
const _post_book_entry = 3;

/**
 * Shoutbox entry:
 *
 * home:    shoutbox ID
 * xhome:   always -1
 */
const _post_shoutbox_entry = 4;

/**
 * Forum topic
 *
 * home:    page ID (forum)
 * xhome:   post ID (if post is a reply) or -1 (if it is the main post)
 */
const _post_forum_topic = 5;

/**
 * Private message
 *
 * home:    pm ID
 * xhome:   pm ID (reply) or -1 (main post)
 */
const _post_pm = 6;

/**
 * Plugin post
 *
 * home:    *plugin-implementation dependent*
 * xhome:   post ID (if post is an answer) or -1
 */
const _post_plugin = 7;


// System user and group IDs

/** Super admin user ID */
const _super_admin_id = 0;

/** Admin group ID */
const _group_admin = 1;

/** Guest group ID (anonymous users) */
const _group_guests = 2;

/** Default registered user group ID */
const _group_registered = 3;


// Privilege level constraints

/** Max user level */
const _priv_max_level = 10001;

/** Max assignable level */
const _priv_max_assignable_level = 9999;
