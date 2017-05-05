<?php

/**
 * IDE hints
 *
 * Dynamically defined constants, global variables, etc.
 */

/** @global array */
$_lang = [];


// settings

/** Enforced delay between posts for a single IP (seconds) */
const _postsendexpire = 50;
/** Poll vote lockout for a single IP (seconds) */
const _pollvoteexpire = 604800;
/** Delay between article read counter update for a single IP (seconds) */
const _artreadexpire = 18000;
/** Expiration time of the failed login attempt database entry (seconds) */
const _maxloginexpire = 900;
/** Max number of failed login attempts in _maxloginexpire seconds */
const _maxloginattempts = 20;
/** Paging mode (1 = top, 2 = top & bottom, 3 = bottom) */
const _pagingmode = 2;
/** Publicly show user emails 1/0 */
const _profileemail = 0;
/** Enable CAPTCHA 1/0 */
const _captcha = 1;
/** Default template ID */
const _default_template = 'default';
/** Site title */
const _title = '';
/** Site description */
const _description = '';
/** Number of comments per page */
const _commentsperpage = 10;
/** Enable smileys in comments 1/0 */
const _smileys = 1;
/** Allow editing/removing comments this number of seconds after they're created by the author */
const _postadmintime = 172800;
/** Site keywords */
const _keywords = '';
/** Admin skin variation */
const _adminscheme = 0;
/** Database version */
const _dbversion = '8.0.0';
/** At-symbol replacement */
const _atreplace = '';
/** Enable BBCode in comments 1/0 */
const _bbcode = 1;
/** Default user group */
const _defaultgroup = 3;
/** Set "From" header when sending emails 1/0 */
const _mailerusefrom = 0;
/** Number of pages to show in the paginator (always odd and >= 3) */
const _showpages = 4;
/** Enable the user list module 1/0 */
const _ulist = 0;
/** Enable user registration 1/0 */
const _registration = 1;
/** Enable pretty URLs 1/0 */
const _pretty_urls = 0;
/** Site title separator */
const _titleseparator = '-';
/** Require login to access site content 1/0 */
const _notpublicsite = 0;
/** Enable comments 1/0 */
const _comments = 1;
/** Article rate lockout for single IP */
const _artrateexpire = 604800;
/** Enable RSS generation 1/0 */
const _rss = 1;
/** Enable private messaging 1/0 */
const _messages = 1;
/** Number of private messages per page */
const _messagesperpage = 30;
/** Enable search module 1/0 */
const _search = 1;
/** Site author */
const _author = '';
/** Site title format (1 = <site name> - <page>, 2 = <page> - <site name>) */
const _titletype = 2;
/** Do not display administration link to unauthorized users 1/0 */
const _adminlinkprivate = 0;
/** Allow users to switch localization in their settings 1/0 */
const _language_allowcustom = 0;
/** Enable password recovery module 1/0 */
const _lostpass = 1;
/** Allow users to choose group upon registration 1/0 */
const _registration_grouplist = 0;
/** Link to favicon.ico */
const _favicon = 0;
/** Number of latest answers to show after list of forum topics */
const _extratopicslimit = 12;
/** Maximum number of RSS items */
const _rsslimit = 30;
/** Number of shoutbox posts to keep */
const _sboxmemory = 20;
/** Article rate mode (0 = disabled, 1 = percentages, 2 = marks) */
const _ratemode = 2;
/** Time format */
const _time_format = 'j.n.Y G:i';
/** Allow users to upload their avatar 1/0 */
const _uploadavatar = 1;
/** Resize uploaded gallery images to this max width */
const _galuploadresize_w = 750;
/** Resize uploaded gallery images to this max height */
const _galuploadresize_h = 565;
/** Show user avatars near posts 1/0 */
const _show_avatars = 1;
/** Account activation expiration time (seconds) */
const _accactexpire = 1200;
/** Require registration confirmation 1/0 */
const _registration_confirm = 0;
/** System e-mail address */
const _sysmail = '';
/** Lost password confirmation expiration (seconds) */
const _lostpassexpire = 1800;
/** Cache-busting ID */
const _cacheid = 0;
/** Index page ID */
const _index_page_id = 1;
/** Admin skin mode (0 = light, 1 = dark, 2 = determined by sunrise/sunset) */
const _adminscheme_mode = 0;
/** Resize uploaded article images to this max width */
const _article_pic_w = 600;
/** Resize uploaded article images to this max height */
const _article_pic_h = 600;
/** Number of thread answers required for the topic to become hot */
const _topic_hot_ratio = 20;
/** Perform installation check next time the core starts 1/0 */
const _install_check = 1;
/** Trust proxy headers 1/0 */
const _proxy_mode = 0;
/** How often to perform system maintenance (seconds) */
const _maintenance_interval = 259200;
/** Automatically check for and run CRON tasks during the main web requests ("poor man's CRON") */
const _cron_auto = 1;
/** Remove generated thumbnails that have been unused for this long (seconds) */
const _thumb_cleanup_threshold = 604800;
/** Touch generated thumbnials this often */
const _thumb_touch_threshold = 43200;
/** Default admin page lister mode (see PageLister::MODE_* constants) */
const _adminpagelist_mode = 0;
/** Default number of images per row setting for galleries */
const _galdefault_per_row = 3;
/** Default number of images per page setting for galleries */
const _galdefault_per_page = 9;
/** Default thumbnail width setting for galleries */
const _galdefault_thumb_w = 147;
/** Default thumbnail height setting for galleries */
const _galdefault_thumb_h = 110;
/** Number of articles per page */
const _articlesperpage = 15;
/** Number of topics per page */
const _topicsperpage = 30;
/** Article thumbnail height */
const _article_pic_thumb_h = 200;
/** Article thumbnail width */
const _article_pic_thumb_w = 200;


// user privileges

/** Privilege level (0 - 10001) */
const _priv_level = 1;
/** The user is the super admin */
const _priv_super_admin = true;
/** Allow access to the administration */
const _priv_administration = true;
/** Allow access to the admin settings module */
const _priv_adminsettings = true;
/** Allow access to the admin plugins module */
const _priv_adminplugins = true;
/** Allow access to the admin users module */
const _priv_adminusers = true;
/** Allow managing groups within the admin users module */
const _priv_admingroups = true;
/** Allow access to the admin content module */
const _priv_admincontent = true;
/** Allow access to the admin other module */
const _priv_adminother = true;
/** Allow managing pages within the admin content module */
const _priv_adminroot = true;
/** Allow managing section pages within the admin content module */
const _priv_adminsection = true;
/** Allow managing category pages within the admin content module */
const _priv_admincategory = true;
/** Allow managing book pages within the admin content module */
const _priv_adminbook = true;
/** Allow managing separator pages within the admin content module */
const _priv_adminseparator =true;
/** Allow managing gallery pages within the admin content module */
const _priv_admingallery = true;
/** Allow managing link pages within the admin content module */
const _priv_adminlink = true;
/** Allow managing group pages within the admin content module */
const _priv_admingroup = true;
/** Allow managing forum pages within the admin content module */
const _priv_adminforum = true;
/** Allow managing plugin pages within the admin content module */
const _priv_adminpluginpage = true;
/** Allow managing articles within the admin content module */
const _priv_adminart = true;
/** Allow managing articles of other people within the admin content module */
const _priv_adminallart = true;
/** Allow changing authors within the admin content module */
const _priv_adminchangeartauthor = true;
/** Allow approving articles within the admin content module */
const _priv_adminconfirm = true;
/** Do not require confirmation for new articles within the admin content module */
const _priv_adminautoconfirm = true;
/** Allow managing polls within the admin content module */
const _priv_adminpoll = true;
/** Allow managing polls of other users within the admin content module */
const _priv_adminpollall = true;
/** Allow managing shoutboxes within the admin content module */
const _priv_adminsbox = true;
/** Allow managing boxes within the admin content module */
const _priv_adminbox = true;
/** Allow access to the file system */
const _priv_fileaccess = true;
/** Allow access to the whole upload directory instead of just the user's directory */
const _priv_fileglobalaccess = true;
/** Allow access outside of the upload directory and allow working with "unsafe" file types (e.g. PHP scripts) */
const _priv_fileadminaccess = true;
/** Comma-separated list of allowed HCM modules or "*" (all) */
const _priv_adminhcm = '';
/** Allow usage of HCM modules that allow executing arbitrary PHP code */
const _priv_adminhcmphp = true;
/** Allow access to the admin backup module */
const _priv_adminbackup = true;
/** Allow access to the admin mass email module */
const _priv_adminmassemail = true;
/** Allow managing posts of other user */
const _priv_adminposts = true;
/** Allow changing own username */
const _priv_changeusername = true;
/** Allow posting comments */
const _priv_postcomments = true;
/** Bypass post creation and management time limits */
const _priv_unlimitedpostaccess = true;
/** Allow locking topics */
const _priv_locktopics = true;
/** Allow making topics sticky */
const _priv_stickytopics = true;
/** Allow moving topics */
const _priv_movetopics = true;
/** Allow rating articles */
const _priv_artrate = true;
/** Allow poll voting */
const _priv_pollvote = true;
/** Allow deletion of own user account */
const _priv_selfremove = true;
