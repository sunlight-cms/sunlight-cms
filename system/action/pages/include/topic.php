<?php

use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;

if (!defined('_root')) {
    exit;
}

if (!ctype_digit($_index['segment'])) {
    $_index['is_found'] = false;
    return;
}

// nacteni dat
$id = (int) $_index['segment'];
$userQuery = _userQuery('p.author');
$query = DB::queryRow("SELECT p.*," . $userQuery['column_list'] . " FROM " . _posts_table . " p " . $userQuery['joins'] . " WHERE p.id=" . $id . " AND p.type=" . _post_forum_topic . " AND p.home=" . $_page['id'] . " AND p.xhome=-1");
if ($query === false) {
    $_index['is_found'] = false;
    return;
}

// drobecek
$_index['crumbs'][] = array(
    'title' => $query['subject'],
    'url' => _linkTopic($id, $_page['slug'])
);

// extend
$continue = true;

Extend::call('topic.pre', Extend::args($output, array(
    'topic' => &$query,
    'continue' => &$continue,
    'page' => $_page,
)));

if (!$continue) {
    return;
}

// atributy
$_index['title'] = $_page['title'] . ' ' . _titleseparator . ' ' . $query['subject'];
$_index['heading'] = $_page['title'];
$_index['url'] = _linkTopic($id, $_page['slug']);

// priprava zpetneho odkazu
$_index['backlink'] = _linkRoot($_page['id'], $_page['slug']);
if (!$query['sticky']) {
    $_index['backlink'] = _addGetToLink($_index['backlink'], 'page=' . _resultPagingGetItemPage($_page['var1'], _posts_table, "bumptime>" . $query['bumptime'] . " AND xhome=-1 AND type=" . _post_forum_topic . " AND home=" . $_page['id']), false);
}

// sprava tematu
$topic_access = _postAccess($userQuery, $query);
$topic_admin = array();

if ($topic_access) {
    if (_priv_locktopics) {
        $topic_admin[] = "<a class='button' href='" . _linkModule('locktopic', 'id=' . $id) . "'><img src='" . _templateImage("icons/" . (($query['locked'] == 1) ? 'un' : '') . "lock.png") . "' alt='lock' class='icon'>" . (_lang('mod.locktopic' . (($query['locked'] == 1) ? '2' : ''))) . "</a>";
    }
    if (_priv_stickytopics) {
        $topic_admin[] = "<a class='button' href='" . _linkModule('stickytopic', 'id=' . $id) . "'><img src='" . _templateImage("icons/" . (($query['sticky'] == 1) ? 'un' : '') . "stick.png") . "' alt='sticky' class='icon'>" . (_lang('mod.stickytopic' . (($query['sticky'] == 1) ? '2' : ''))) . "</a>";
    }
    if (_priv_movetopics) {
        $topic_admin[] = "<a class='button' href='" . _linkModule('movetopic', 'id=' . $id) . "'><img src='" . _templateImage("icons/move.png") . "' alt='move' class='icon'>" . (_lang('mod.movetopic')) . "</a>";
    }
}

// nacteni autora a avataru
$avatar = '';
if ($query['guest'] == "") {
    $author = _linkUserFromQuery($userQuery, $query, array('class' => 'post-author'));
    if (_show_avatars) {
        $avatar = _getAvatarFromQuery($userQuery, $query, array('default' => false));
    }
} else {
    $author = "<span class='post-author-guest' title='" . _showIP($query['ip']) . "'>" . $query['guest'] . "</span>";
}

// vystup
$extend_buffer = Extend::buffer('topic.render', array(
    'topic' => &$query,
    'access' => $topic_access,
    'admin' => &$topic_admin,
));
if ($extend_buffer === '') {
    $output .= (!empty($topic_admin) ? "<p class='topic-admin'>\n" . implode(' ', $topic_admin) . "</p>\n" : '') . "
<div id='post-" . $id . "' class='topic" . ($avatar !== '' ? ' topic-withavatar' : '') . "'>
<h2>" . _lang('posts.topic') . ": " . $query['subject'] . ' ' . _templateRssLink(_linkRSS($id, 6, false), true) . "</h2>
<p class='topic-info'>"
    . _lang('global.postauthor')
    . ' ' . $author
    . ' <span class="post-info">(' . _formatTime($query['time'], 'post') . ')</span>'
    . ($topic_access ? " <span class='post-actions'><a class='post-action-edit' href='" . _linkModule('editpost', 'id=' . $id) . "'>" . _lang('global.edit') . "</a></span>" : '')
    . "</p>
" . $avatar . "
<p class='topic-body'>" . _parsePost($query['text']) . "</p>
</div>
<div class='cleaner'></div>
";
} else {
    $output .= $extend_buffer;
}

// odpovedi
$output .= CommentService::render(
    CommentService::RENDER_FORUM_TOPIC,
    $_page['id'],
    array(
        _commentsperpage,
        _publicAccess($_page['var3']),
        $_page['var2'],
        $id
    ),
    $query['locked'] == 1
);
