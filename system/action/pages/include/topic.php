<?php

use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;

defined('_root') or exit;

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

Extend::call('topic.before', Extend::args($output, array(
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
    $_index['backlink'] = _addParamsToUrl($_index['backlink'], 'page=' . _resultPagingGetItemPage($_page['var1'], _posts_table, "bumptime>" . $query['bumptime'] . " AND xhome=-1 AND type=" . _post_forum_topic . " AND home=" . $_page['id']), false);
}

// sprava tematu
$topic_access = _postAccess($userQuery, $query);
$topic_admin = array();

if ($topic_access) {
    if (_priv_locktopics) {
        $topic_admin[] = "<a href='" . _linkModule('locktopic', 'id=' . $id) . "'>" . (_lang('mod.locktopic.link' . (($query['locked'] == 1) ? '2' : ''))) . "</a>";
    }
    if (_priv_stickytopics) {
        $topic_admin[] = "<a href='" . _linkModule('stickytopic', 'id=' . $id) . "'>" . (_lang('mod.stickytopic.link' . (($query['sticky'] == 1) ? '2' : ''))) . "</a>";
    }
    if (_priv_movetopics) {
        $topic_admin[] = "<a href='" . _linkModule('movetopic', 'id=' . $id) . "'>" . (_lang('mod.movetopic.link')) . "</a>";
    }
}

// vystup
$output .= "<div class=\"topic\">\n";
$output .= "<h2>" . _lang('posts.topic') . ": " . $query['subject'] . ' ' . Sunlight\Template::rssLink(_linkRSS($id, 6, false), true) . "</h2>\n";
$output .= CommentService::renderPost($query, $userQuery, array(
    'post_link' => false,
    'allow_reply' => false,
    'extra_actions' => $topic_admin,
));
$output .= "</div>\n";

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
