<?php

use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Paginator;
use Sunlight\Post\Post;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\UrlHelper;

defined('SL_ROOT') or exit;

if (!ctype_digit($_index->segment)) {
    $_index->notFound();
    return;
}

// load topic
$id = (int) $_index->segment;
$userQuery = User::createQuery('p.author');
$query = DB::queryRow('SELECT p.*,' . $userQuery['column_list'] . ' FROM ' . DB::table('post') . ' p ' . $userQuery['joins'] . ' WHERE p.id=' . $id . ' AND p.type=' . Post::FORUM_TOPIC . ' AND p.home=' . $_page['id'] . ' AND p.xhome=-1');

if ($query === false) {
    $_index->notFound();
    return;
}

// add breadcrumb
$_index->crumbs[] = [
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

// meta
$_index->title = $_page['title'] . ' ' . Settings::get('titleseparator') . ' ' . $query['subject'];
$_index->heading = $_page['title'];
$_index->url = Router::topic($id, $_page['slug']);

// backlink
$_index->backlink = Router::page($_page['id'], $_page['slug']);

if (!$query['sticky']) {
    $_index->backlink = UrlHelper::appendParams($_index->backlink, 'page=' . Paginator::getItemPage($_page['var1'], DB::table('post'), 'bumptime>' . $query['bumptime'] . ' AND xhome=-1 AND type=' . Post::FORUM_TOPIC . ' AND home=' . $_page['id']));
}

// admin links
$topic_access = Post::checkAccess($userQuery, $query);
$topic_admin = [];

if ($topic_access) {
    if (User::hasPrivilege('locktopics')) {
        $topic_admin[] = '<a class="post-action-' . (($query['locked'] == 1) ? 'unlock' : 'lock') . '" href="' . _e(Router::module('locktopic', ['query' => ['id' => $id]])) . '">'
            . (_lang('mod.locktopic.link' . (($query['locked'] == 1) ? '2' : '')))
            . '</a>';
    }

    if (User::hasPrivilege('stickytopics')) {
        $topic_admin[] = '<a class="post-action-' . (($query['sticky'] == 1) ? 'unsticky' : 'sticky') . '"  href="' . _e(Router::module('stickytopic', ['query' => ['id' => $id]])) . '">'
            . (_lang('mod.stickytopic.link' . (($query['sticky'] == 1) ? '2' : '')))
            . '</a>';
    }

    if (User::hasPrivilege('movetopics')) {
        $topic_admin[] = '<a class="post-action-move"  href="' . _e(Router::module('movetopic', ['query' => ['id' => $id]])) . '">'
            . _lang('mod.movetopic.link')
            . '</a>';
    }
}

// output
$output .= "<div class=\"topic\">\n";
$output .= '<h2>' . _lang('posts.topic') . ': ' . $query['subject'] . "</h2>\n";
$output .= PostService::renderPost($query, $userQuery, [
    'post_link' => false,
    'allow_reply' => false,
    'extra_actions' => $topic_admin,
]);
$output .= "</div>\n";

// replies
$output .= PostService::renderList(
    PostService::RENDER_FORUM_TOPIC,
    $_page['id'],
    [
        Settings::get('commentsperpage'),
        User::checkPublicAccess($_page['var3']),
        $_page['var2'],
        $id
    ],
    $query['locked'] == 1
);
