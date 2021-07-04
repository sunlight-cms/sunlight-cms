<?php

use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Paginator;
use Sunlight\Comment\Comment;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\UrlHelper;

defined('_root') or exit;

if (!ctype_digit($_index['segment'])) {
    $_index['type'] = _index_not_found;
    return;
}

// nacteni dat
$id = (int) $_index['segment'];
$userQuery = User::createQuery('p.author');
$query = DB::queryRow("SELECT p.*," . $userQuery['column_list'] . " FROM " . _comment_table . " p " . $userQuery['joins'] . " WHERE p.id=" . $id . " AND p.type=" . _post_forum_topic . " AND p.home=" . $_page['id'] . " AND p.xhome=-1");
if ($query === false) {
    $_index['type'] = _index_not_found;
    return;
}

// drobecek
$_index['crumbs'][] = [
    'title' => $query['subject'],
    'url' => Router::topic($id, $_page['slug'])
];

// extend
$continue = true;

Extend::call('topic.before', Extend::args($output, [
    'topic' => &$query,
    'continue' => &$continue,
    'page' => $_page,
]));

if (!$continue) {
    return;
}

// atributy
$_index['title'] = $_page['title'] . ' ' . Settings::get('titleseparator') . ' ' . $query['subject'];
$_index['heading'] = $_page['title'];
$_index['url'] = Router::topic($id, $_page['slug']);

// priprava zpetneho odkazu
$_index['backlink'] = Router::page($_page['id'], $_page['slug']);
if (!$query['sticky']) {
    $_index['backlink'] = UrlHelper::appendParams($_index['backlink'], 'page=' . Paginator::getItemPage($_page['var1'], _comment_table, "bumptime>" . $query['bumptime'] . " AND xhome=-1 AND type=" . _post_forum_topic . " AND home=" . $_page['id']));
}

// sprava tematu
$topic_access = Comment::checkAccess($userQuery, $query);
$topic_admin = [];

if ($topic_access) {
    if (User::hasPrivilege('locktopics')) {
        $topic_admin[] = "<a class=\"post-action-" . (($query['locked'] == 1) ? 'unlock' : 'lock') . "\" href='" . _e(Router::module('locktopic', 'id=' . $id)) . "'>" . (_lang('mod.locktopic.link' . (($query['locked'] == 1) ? '2' : ''))) . "</a>";
    }
    if (User::hasPrivilege('stickytopics')) {
        $topic_admin[] = "<a class=\"post-action-" . (($query['sticky'] == 1) ? 'unsticky' : 'sticky') . "\"  href='" . _e(Router::module('stickytopic', 'id=' . $id)) . "'>" . (_lang('mod.stickytopic.link' . (($query['sticky'] == 1) ? '2' : ''))) . "</a>";
    }
    if (User::hasPrivilege('movetopics')) {
        $topic_admin[] = "<a class=\"post-action-move\"  href='" . _e(Router::module('movetopic', 'id=' . $id)) . "'>" . (_lang('mod.movetopic.link')) . "</a>";
    }
}

// vystup
$output .= "<div class=\"topic\">\n";
$output .= "<h2>" . _lang('posts.topic') . ": " . $query['subject'] . "</h2>\n";
$output .= CommentService::renderPost($query, $userQuery, [
    'post_link' => false,
    'allow_reply' => false,
    'extra_actions' => $topic_admin,
]);
$output .= "</div>\n";

// odpovedi
$output .= CommentService::render(
    CommentService::RENDER_FORUM_TOPIC,
    $_page['id'],
    [
        Settings::get('commentsperpage'),
        User::checkPublicAccess($_page['var3']),
        $_page['var2'],
        $id
    ],
    $query['locked'] == 1
);
