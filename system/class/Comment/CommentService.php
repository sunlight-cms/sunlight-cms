<?php

namespace Sunlight\Comment;

use Sunlight\Database\Database;
use Sunlight\Extend;

class CommentService
{
    /**
     * Section comments
     *
     * $vars: (bool) locked
     */
    const RENDER_SECTION_COMMENTS = 1;

    /**
     * Article comments
     *
     * $vars: (bool) locked
     */
    const RENDER_ARTICLE_COMMENTS = 2;

    /**
     * Book posts
     *
     * $vars: [
     *      (int) posts per page,
     *      (bool) allow posting,
     *      (bool) locked,
     * ]
     */
    const RENDER_BOOK_POSTS = 3;

    /**
     * Forum topic list
     *
     * $vars: [
     *      (int) posts per page,
     *      (bool) allow posting,
     *      (bool) locked,
     *      (string) forum slug],
     * ]
     */
    const RENDER_FORUM_TOPIC_LIST = 5;

    /**
     * Forum topic
     *
     * $vars: [
     *      (int) posts per page,
     *      (bool) allow posting,
     *      (bool) locked,
     *      (int) topic id,
     * ]
     */
    const RENDER_FORUM_TOPIC = 6;

    /**
     * Private message list
     *
     * $vars: [
     *      (bool) locked,
     * ]
     */
    const RENDER_PM_LIST = 7;

    /**
     * Plugin posts
     *
     * $vars: [
     *      (int) posts per page,
     *      (bool) allow posting,
     *      (bool) locked,
     *      (string) plugin flag,
     *      (bool) desc order,
     *      (?string) title,
     *      (?string) pager param name,
     * ]
     */
    const RENDER_PLUGIN_POSTS = 8;

    /**
     * Render post form
     *
     * $vars structure:
     * ----------------
     * url          return URL
     * posttype     post type (see _post_* constants)
     * posttarget   id_home
     * xhome        id_xhome
     * subject      show subject field 1/0
     * is_topic     the new post is a forum topic 1/0
     * pluginflag   plugin flag (only for posttype == _post_plugin)
     *
     * @param array $vars
     * @return string
     */
    public static function renderForm(array $vars)
    {
        $inputs = array();

        $captcha = _captchaInit();
        $output = _jsLimitLength(16384, "postform", "text");
        if (!_login) {
            $inputs[] = array('label' => _lang('posts.guestname'), 'content' => "<input type='text' name='guest' maxlength='24' class='inputsmall'" . _restoreValue($_SESSION, 'post_form_guest') . ">");
        }
        if ($vars['xhome'] == -1 && $vars['subject']) {
            $inputs[] = array('label' => _lang($vars['is_topic'] ? 'posts.topic' : 'posts.subject'), 'content' => "<input type='text' name='subject' class='input" . ($vars['is_topic'] ? 'medium' : 'small') . "' maxlength='48'" . _restoreValue($_SESSION, 'post_form_subject') . ">");
        }
        $inputs[] = $captcha;
        $inputs[] = array('label' => _lang('posts.text'), 'content' => "<textarea name='text' class='areamedium' rows='5' cols='33'>" . _restoreValue($_SESSION, 'post_form_text', null, false) . "</textarea><input type='hidden' name='_posttype' value='" . $vars['posttype'] . "'><input type='hidden' name='_posttarget' value='" . $vars['posttarget'] . "'><input type='hidden' name='_xhome' value='" . $vars['xhome'] . "'>" . (isset($vars['pluginflag']) ? "<input type='hidden' name='_pluginflag' value='" . $vars['pluginflag'] . "'>" : ''), 'top' => true);
        $inputs[] = array('label' => '', 'content' => _getPostFormControls('postform', 'text'));

        unset(
            $_SESSION['post_form_guest'],
            $_SESSION['post_form_subject'],
            $_SESSION['post_form_text']
        );

        // form
        $output .= _formOutput(
            array(
                'name' => 'postform',
                'action' => _addGetToLink(_link('system/script/post.php'), '_return=' . rawurlencode($vars['url']), false),
                'submit_append' => ' ' . _getPostFormPreviewButton('postform', 'text'),
            ),
            $inputs
        );

        return $output;
    }

    /**
     * Render post list
     *
     * @param int         $style        rendering style (see CommentService::RENDER_* constants)
     * @param int         $home         home ID (depends on underlying post type)
     * @param mixed       $vars         rendering options
     * @param bool        $force_locked force locked state 1/0
     * @param string|null $url          custom URL or null (= automatic)
     * @return string
     */
    public static function render($style, $home, $vars, $force_locked = false, $url = null)
    {
        global $_index;

        /* ---  type  --- */

        // defaults
        $desc = "DESC ";
        $ordercol = 'id';
        $countcond = "type=" . $style . " AND xhome=-1 AND home=" . $home;
        $locked_textid = '';
        $autolast = false;
        $postlink = false;
        $pluginflag = null;
        $subject_enabled = false;
        $form_position = 0;
        $page_param = null;
        $is_topic_list = ($style == static::RENDER_FORUM_TOPIC_LIST);
        $replies_enabled = null;

        // url
        if (!isset($url)) {
            $url = $_index['url'];
        }

        $url_html = _e($url);

        switch ($style) {
            case static::RENDER_SECTION_COMMENTS:
                $posttype = _post_section_comment;
                $xhome = -1;
                $subclass = "comments";
                $title = _lang('posts.comments');
                $addlink = _lang('posts.addcomment');
                $nopostsmessage = _lang('posts.nocomments');
                $postsperpage = _commentsperpage;
                $canpost = _priv_postcomments;
                $locked = (bool) $vars;
                $replynote = true;
                break;

            case static::RENDER_ARTICLE_COMMENTS:
                $posttype = _post_article_comment;
                $xhome = -1;
                $subclass = "comments";
                $title = _lang('posts.comments');
                $addlink = _lang('posts.addcomment');
                $nopostsmessage = _lang('posts.nocomments');
                $postsperpage = _commentsperpage;
                $canpost = _priv_postcomments;
                $locked = (bool) $vars;
                $replynote = true;
                break;

            case static::RENDER_BOOK_POSTS:
                $posttype = _post_book_entry;
                $xhome = -1;
                $subclass = "book";
                $title = null;
                $addlink = _lang('posts.addpost');
                $nopostsmessage = _lang('posts.noposts');
                $postsperpage = $vars[0];
                $canpost = $vars[1];
                $locked = (bool) $vars[2];
                $replynote = true;
                break;

            case static::RENDER_FORUM_TOPIC_LIST:
                $posttype = _post_forum_topic;
                $xhome = -1;
                $subclass = "topic";
                $title = null;
                $addlink = _lang('posts.addtopic');
                $nopostsmessage = _lang('posts.notopics');
                $postsperpage = $vars[0];
                $canpost = $vars[1];
                $locked = (bool) $vars[2];
                $replynote = true;
                $ordercol = 'bumptime';
                $locked_textid = '3';
                $forum_slug = $vars[3];
                $subject_enabled = true;
                break;

            case static::RENDER_FORUM_TOPIC:
                $posttype = _post_forum_topic;
                $xhome = $vars[3];
                $subclass = "topic-replies";
                $title = null;
                $addlink = _lang('posts.addanswer');
                $nopostsmessage = _lang('posts.noanswers');
                $postsperpage = $vars[0];
                $canpost = $vars[1];
                $locked = (bool) $vars[2];
                $replynote = false;
                $desc = "";
                $countcond = "type=" . _post_forum_topic . " AND xhome=" . $xhome . " AND home=" . $home;
                $autolast = isset($_GET['autolast']);
                $postlink = true;
                $replies_enabled = false;
                break;

            case static::RENDER_PM_LIST:
                $posttype = _post_pm;
                $xhome = $home;
                $subclass = "pm";
                $title = null;
                $addlink = _lang('posts.addanswer');
                $nopostsmessage = _lang('posts.noanswers');
                $postsperpage = _messagesperpage;
                $canpost = true;
                $locked = (bool) $vars[0];
                $replynote = false;
                $desc = "";
                $countcond = "type=" . _post_pm . " AND home=" . $home;
                $locked_textid = '4';
                $autolast = true;
                $replies_enabled = false;
                break;

            case static::RENDER_PLUGIN_POSTS:
                $posttype = _post_plugin;
                $xhome = -1;
                $subclass = "plugin";
                $title = (isset($vars[5]) ? $vars[5] : null);
                $addlink = _lang('posts.addpost');
                $nopostsmessage = _lang('posts.noposts');
                $postsperpage = $vars[0];
                $canpost = $vars[1];
                $locked = (bool) $vars[2];
                $replynote = true;
                $pluginflag = $vars[3];
                $countcond = "type=" . _post_plugin . " AND flag=" . $pluginflag;
                if (!$vars[4]) {
                    $desc = '';
                }
                if (isset($vars[6])) {
                    $page_param = $vars[6];
                }
                break;

        }

        // force locked
        if ($force_locked) {
            $locked = true;
        }

        // enable replies?
        if ($replies_enabled === null && !$locked) {
            $replies_enabled = true;
        }

        // extend
        $callback = null;
        $extend_output = Extend::buffer('posts.output', array(
            'type' => $style,
            'home' => $home,
            'xhome' => $xhome,
            'vars' => $vars,
            'post_type' => $posttype,
            'plugin_flag' => $pluginflag,
            'canpost' => &$canpost,
            'locked' => &$locked,
            'autolast' => &$autolast,
            'post_link' => &$postlink,
            'posts_per_page' => &$postsperpage,
            'sql_desc' => &$desc,
            'sql_ordercol' => &$ordercol,
            'sql_countcond' => &$countcond,
            'callback' => &$callback,
            'form_position' => &$form_position,
        ));

        if ($extend_output !== '') {
            return $extend_output;
        }

        /* ---  output  --- */
        $output = "
  <div id='posts' class='posts posts-" . $subclass . "'>
  ";

        if ($title != null) {
            $output .= "<h2>" . $title . ' ' . _templateRssLink(_linkRSS($home, $posttype, false), true) . "</h2>\n";
        }

        $form_output = "<div class='posts-form' id='post-form'>\n";

        /* --- init pager --- */
        $paging = _resultPaging($url, $postsperpage, _posts_table, $countcond, "#posts", $page_param, $autolast);

        /* --- message --- */
        if (isset($_GET['r'])) {
            switch (_get('r')) {
                case 0:
                    $form_output .= _msg(_msg_err, _lang('posts.failed'));
                    break;
                case 1:
                    $form_output .= _msg(_msg_ok, _lang((($style != 5) ? 'posts.added' : 'posts.topicadded')));
                    break;
                case 2:
                    $form_output .= _msg(_msg_warn, _lang('misc.requestlimit', array("*postsendexpire*" => _postsendexpire)));
                    break;
                case 3:
                    $form_output .= _msg(_msg_warn, _lang('posts.guestnamedenied'));
                    break;
                case 4:
                    $form_output .= _msg(_msg_warn, _lang('xsrf.msg'));
                    break;
            }
        }

        /* ---  render post form or link  --- */
        if (!$locked && (isset($_GET['addpost']) || isset($_GET['replyto']))) {

            // fetch reply to ID
            if ($xhome == -1) {
                if (isset($_GET['replyto']) && _get('replyto') != -1) {
                    $reply = (int) _get('replyto');
                    if ($replynote) {
                        $form_output .= "<p>" . _lang('posts.replynote') . " (<a href='" . $url_html . "#posts'>" . _lang('global.cancel') . "</a>).</p>";
                    }
                } else {
                    $reply = -1;
                }
            } else {
                $reply = $xhome;
            }

            // post form or login form
            if ($canpost) {
                $form_output .= static::renderForm(array(
                    'posttype' => $posttype,
                    'pluginflag' => $pluginflag,
                    'posttarget' => $home,
                    'xhome' => $reply,
                    'subject' => $subject_enabled,
                    'is_topic' => static::RENDER_FORUM_TOPIC_LIST == $style,
                    'url' => $url,
                ));
            } else {
                $form_output .= "<p>" . _lang('posts.loginrequired') . "</p>\n";
                $form_output .= _userLoginForm();
            }

        } else {
            if (!$locked) {
                $form_output .= "<a class='button' href='" . _addGetToLink($url_html, "addpost&page=" . $paging['current']) . "#post-form'><img class='icon' src='" . _templateImage('icons/bubble.png') . "' alt='post'>" . $addlink . "</a>";
            } else {
                $form_output .= "<img src='" . _templateImage("icons/lock.png") . "' alt='stop' class='icon'><strong>" . _lang('posts.locked' . $locked_textid) . "</strong>";
            }
        }

        $form_output .= "\n</div>\n";

        if ($form_position === 0) {
            $output .= $form_output;
            $form_output = null;
        }

        /* ---  list  --- */
        if (_showPagingAtTop()) {
            $output .= $paging['paging'];
        }

        // base query
        $userQuery = _userQuery('p.author');
        if ($is_topic_list) {
            $sql = "SELECT p.id,p.author,p.guest,p.subject,p.time,p.ip,p.locked,p.bumptime,p.sticky,(SELECT COUNT(*) FROM " . _posts_table . " WHERE type=" . _post_forum_topic . " AND xhome=p.id) AS answer_count";
        } else {
            $sql = "SELECT p.id,p.xhome,p.subject,p.text,p.author,p.guest,p.time,p.ip" . Extend::buffer('posts.columns');
        }
        $sql .= ',' . $userQuery['column_list'];
        $sql .= " FROM " . _posts_table . " AS p";
        $sql .= ' ' . $userQuery['joins'];

        // conditions and sorting
        $sql .= " WHERE p.type=" . $posttype . (isset($xhome) ? " AND p.xhome=" . $xhome : '') . " AND p.home=" . $home . (isset($pluginflag) ? " AND p.flag=" . $pluginflag : '');
        $sql .= " ORDER BY " . ($is_topic_list ? 'p.sticky DESC,' : '') . $ordercol . ' ' . $desc . $paging['sql_limit'];

        // query
        $query = Database::query($sql);
        unset($sql);

        // load all items into an array
        $items = array();
        if ($is_topic_list) {
            $item_ids_with_answers = array();
        }
        while ($item = Database::row($query)) {
            $items[$item['id']] = $item;
            if ($is_topic_list && $item['answer_count'] != 0) $item_ids_with_answers[] = $item['id'];
        }

        // free query
        Database::free($query);

        if ($is_topic_list) {
            // last post (for topic lists)
            if (!empty($item_ids_with_answers)) {
                $topicextra = Database::query("SELECT * FROM (SELECT p.id,p.xhome,p.author,p.guest," . $userQuery['column_list'] . " FROM " . _posts_table . " AS p " . $userQuery['joins'] . " WHERE p.type=" . _post_forum_topic . " AND p.home=" . $home . " AND p.xhome IN(" . implode(',', $item_ids_with_answers) . ") ORDER BY p.id DESC) AS replies GROUP BY xhome");
                while ($item = Database::row($topicextra)) {
                    if (!isset($items[$item['xhome']])) {
                        if (_dev) {
                            throw new \RuntimeException('Could not find parent post of reply #' . $item['id']);
                        }
                        continue;
                    }
                    $items[$item['xhome']]['_lastpost'] = $item;
                }
            }
        } elseif (!empty($items)) {
            // answers (to comments)
            $answers = Database::query("SELECT p.id,p.xhome,p.text,p.author,p.guest,p.time,p.ip," . $userQuery['column_list'] . " FROM " . _posts_table . " p " . $userQuery['joins'] . " WHERE p.type=" . $posttype . " AND p.home=" . $home . (isset($pluginflag) ? " AND p.flag=" . $pluginflag : '') . " AND p.xhome IN(" . implode(',', array_keys($items)) . ") ORDER BY p.id");
            while ($item = Database::row($answers)) {
                if (!isset($items[$item['xhome']])) {
                    continue;
                }
                if (!isset($items[$item['xhome']]['_answers'])) $items[$item['xhome']]['_answers'] = array();
                $items[$item['xhome']]['_answers'][] = $item;
            }
            Database::free($answers);
        }

        // vypis
        if (!empty($items)) {

            // list posts or topics
            if (!$is_topic_list) {
                $output .= "<div class='post-list'>\n";

                $hl = true;
                foreach ($items as $item) {

                    // fetch author
                    if ($item['guest'] == "") $author = _linkUserFromQuery($userQuery, $item, array('class' => 'post-author'));
                    else $author = "<span class='post-author-guest' title='" . _showIP($item['ip']) . "'>" . $item['guest'] . "</span>";

                    // admin links
                    $post_access = _postAccess($userQuery, $item);
                    if ($replies_enabled || $post_access) {
                        $actlinks = " <span class='post-actions'>";
                        if ($replies_enabled) $actlinks .= "<a class='post-action-reply' href='" . _addGetToLink($url_html, "replyto=" . $item['id']) . "#posts'>" . _lang('posts.reply') . "</a>";
                        if ($post_access) $actlinks .= ($replies_enabled ? ' ' : '') . "<a class='post-action-edit' href='" . _linkModule('editpost', 'id=' . $item['id']) . "'>" . _lang('global.edit') . "</a>";
                        $actlinks .= "</span>";
                    } else {
                        $actlinks = "";
                    }

                    // avatar
                    if (_show_avatars) {
                        $avatar = _getAvatarFromQuery($userQuery, $item);
                    } else {
                        $avatar = null;
                    }

                    // post
                    $hl = !$hl;
                    Extend::call('posts.post', array('item' => &$item, 'avatar' => &$avatar, 'actlinks' => &$actlinks, 'type' => $style));
                    if ($callback === null) {
                        $output .= "<div id='post-" . $item['id'] . "' class='post" . ($hl ? ' post-hl' : '') . (isset($avatar) ? ' post-withavatar' : '') . "'><div class='post-head'>" . $author;
                        $output .= " <span class='post-info'>(" . _formatTime($item['time'], 'post') . ")</span>" . $actlinks . ($postlink ? "<a class='post-postlink' href='" . _addGetToLink($url_html, 'page=' . $paging['current']) . "#post-" . $item['id'] . "'><span>#" . str_pad($item['id'], 6, '0', STR_PAD_LEFT) . "</span></a>" : '') . "</div><div class='post-body" . (isset($avatar) ? ' post-body-withavatar' : '') . "'>" . $avatar . '<div class="post-body-text">' . _parsePost($item['text']) . "</div></div></div>\n";
                    } else {
                        $output .= call_user_func($callback, array(
                            'item' => $item,
                            'avatar' => $avatar,
                            'author' => $author,
                            'actlinks' => $actlinks,
                            'page' => $paging['current'],
                            'postlink' => $postlink,
                        ));
                    }

                    // answers
                    if ($replies_enabled && isset($item['_answers'])) {
                        foreach ($item['_answers'] as $answer) {

                            // author name
                            if ($answer['guest'] == "") $author = _linkUserFromQuery($userQuery, $answer, array('class' => 'post-author'));
                            else $author = "<span class='post-author-guest' title='" . _showIP($answer['ip']) . "'>" . $answer['guest'] . "</span>";

                            // post admin links
                            if (_postAccess($userQuery, $answer)) $actlinks = " <span class='post-actions'><a class='post-action-edit' href='" . _linkModule('editpost', 'id=' . $answer['id']) . "'>" . _lang('global.edit') . "</a></span>";
                            else $actlinks = "";

                            // avatar
                            if (_show_avatars) {
                                $avatar = _getAvatarFromQuery($userQuery, $answer);
                            } else {
                                $avatar = null;
                            }

                            Extend::call('posts.post', array('item' => &$answer, 'avatar' => &$avatar, 'actlinks' => &$actlinks, 'type' => $style));
                            if ($callback === null) {
                                $output .= "<div id='post-" . $answer['id'] . "' class='post-answer" . (isset($avatar) ? ' post-answer-withavatar' : '') . "'><div class='post-head'>" . $author . " <span class='post-info'>(" . _formatTime($answer['time'], 'post') . ")</span>" . $actlinks . "</div><div class='post-body" . (isset($avatar) ? ' post-body-withavatar' : '') . "'>" . $avatar . '<div class="post-body-text">' . _parsePost($answer['text']) . "</div></div></div>\n";
                            } else {
                                $output .= call_user_func($callback, array(
                                    'item' => $answer,
                                    'avatar' => $avatar,
                                    'author' => $author,
                                    'actlinks' => $actlinks,
                                    'page' => $paging['current'],
                                    'postlink' => $postlink,
                                ));
                            }
                        }
                    }

                }

                $output .= "</div>\n";

                if (_showPagingAtBottom()) {
                    $output .= $paging['paging'];
                }

                // form
                if ($form_position === 1) {
                    $output .= $form_output;
                    $form_output = null;
                }

            } else {

                // topic list table
                $hl = false;
                $output .= "\n<table class='topic-table'>\n<thead><tr><th colspan='2'>" . _lang('posts.topic') . "</th><th>" . _lang('global.answersnum') . "</th><th>" . _lang('global.lastanswer') . "</th></tr></thead>\n<tbody>\n";
                foreach ($items as $item) {

                    // fetch author
                    if ($item['guest'] == "") $author = _linkUserFromQuery($userQuery, $item, array('max_len' => 16));
                    else $author = "<span class='post-author-guest' title='" . _showIP($item['ip']) . "'>" . _cutText($item['guest'], 16) . "</span>";

                    // fetch last post author
                    if (isset($item['_lastpost'])) {
                        if ($item['_lastpost']['author'] != -1) $lastpost = _linkUserFromQuery($userQuery, $item['_lastpost'], array('class' => 'post-author', 'max_len' => 16));
                        else $lastpost = "<span class='post-author-guest'>" . _cutText($item['_lastpost']['guest'], 16) . "</span>";
                    } else {
                        $lastpost = "-";
                    }

                    // choose icon
                    if ($item['sticky']) $icon = 'sticky';
                    elseif ($item['locked']) $icon = 'locked';
                    elseif ($item['answer_count'] == 0) $icon = 'new';
                    elseif ($item['answer_count'] < _topic_hot_ratio) $icon = 'normal';
                    else $icon = 'hot';

                    // mini pager
                    $tpages = '';
                    $tpages_num = ceil($item['answer_count'] / _commentsperpage);
                    if ($tpages_num == 0) $tpages_num = 1;
                    if ($tpages_num > 1) {
                        $tpages .= '<span class=\'topic-pages\'>';
                        for ($i = 1; $i <= 3 && $i <= $tpages_num; ++$i) {
                            $tpages .= "<a href='" . _addGetToLink(_linkTopic($item['id'], $forum_slug), 'page=' . $i) . "#posts'>" . $i . '</a>';
                        }
                        if ($tpages_num > 3) $tpages .= "<a href='" . _addGetToLink(_linkTopic($item['id'], $forum_slug), 'page=' . $tpages_num) . "'>" . $tpages_num . ' &rarr;</a>';
                        $tpages .= '</span>';
                    }

                    // render row
                    $output .= "<tr class='topic-" . $icon . ($hl ? ' topic-hl' : '') . "'><td class='topic-icon-cell'><a href='" . _linkTopic($item['id'], $forum_slug) . "'><img src='" . _templateImage('icons/topic-' . $icon . '.png') . "' alt='" . _lang('posts.topic.' . $icon) . "'></a></td><td class='topic-main-cell'><a href='" . _linkTopic($item['id'], $forum_slug) . "'>" . $item['subject'] . "</a>" . $tpages . "<br>" . $author . " <small class='post-info'>(" . _formatTime($item['time'], 'post') . ")</small></td><td>" . $item['answer_count'] . "</td><td>" . $lastpost . (($item['answer_count'] != 0) ? "<br><small class='post-info'>(" . _formatTime($item['bumptime'], 'post') . ")</small>" : '') . "</td></tr>\n";
                    $hl = !$hl;
                }
                $output .= "</tbody></table>\n\n";
                if (_showPagingAtBottom()) {
                    $output .= $paging['paging'];
                }

                // form
                if ($form_position === 1) {
                    $output .= $form_output;
                    $form_output = null;
                }

                // latest answers
                $output .= "\n<div class='post-answer-list'>\n<h3>" . _lang('posts.forum.lastact') . "</h3>\n";
                $query = Database::query("SELECT topic.id AS topic_id,topic.subject AS topic_subject,p.author,p.guest,p.time," . $userQuery['column_list'] . " FROM " . _posts_table . " AS p JOIN " . _posts_table . " AS topic ON(topic.type=" . _post_forum_topic . " AND topic.id=p.xhome) " . $userQuery['joins'] . " WHERE p.type=" . _post_forum_topic . " AND p.home=" . $home . " AND p.xhome!=-1 ORDER BY p.id DESC LIMIT " . _extratopicslimit);
                if (Database::size($query) != 0) {
                    $output .= "<table class='topic-latest'>\n";
                    while ($item = Database::row($query)) {
                        if ($item['guest'] == "") $author = _linkUserFromQuery($userQuery, $item);
                        else $author = "<span class='post-author-guest'>" . $item['guest'] . "</span>";
                        $output .= "<tr><td><a href='" . _linkTopic($item['topic_id'], $forum_slug) . "'>" . $item['topic_subject'] . "</a></td><td>" . $author . "</td><td>" . _formatTime($item['time'], 'post') . "</td></tr>\n";
                    }
                    $output .= "</table>\n\n";

                } else {
                    $output .= "<p>" . _lang('global.nokit') . "</p>";
                }
                $output .= "</div>\n";

            }

        } else {
            $output .= "<p>" . $nopostsmessage . "</p>";
        }

        if ($form_position === 1) {
            $output .= $form_output;
        }

        $output .= "</div>";

        return $output;
    }

    /**
     * Remove specific plugin posts
     *
     * @param int      $flag      plugin post flag
     * @param int|null $home      specific home ID or null (all)
     * @param bool     $get_count do not remove, return count only 1/0
     * @return int|null
     */
    public static function deleteByPluginFlag($flag, $home, $get_count = true)
    {
        // condition
        $cond = "type=" . _post_plugin . " AND flag=" . $flag;
        if (isset($home)) {
            $cond .= " AND home=" . $home;
        }

        // delete or count
        if ($get_count) {
            return Database::count(_posts_table, $cond);
        }

        Database::delete(_posts_table, $cond);
    }
}
