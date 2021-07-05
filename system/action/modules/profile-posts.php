<?php

use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Paginator;
use Sunlight\Post\Post;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;

defined('_root') or exit;

if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
    $_index['type'] = _index_unauthorized;
    return;
}

/* ---  priprava  --- */

$id = StringManipulator::slugify(Request::get('id'), false);
$query = DB::queryRow("SELECT u.id,u.username,u.publicname,u.public,g.level FROM " . DB::table('user') . " u JOIN " . DB::table('user_group') . " g ON u.group_id=g.id WHERE u.username=" . DB::val($id));

if ($query === false) {
    $_index['type'] = _index_not_found;
    return;
}

if (!$query['public'] && !User::checkLevel($query['id'], $query['level'])) {
    $_index['type'] = _index_unauthorized;
    return;
}

/* ---  modul  --- */

$_index['title'] = str_replace(
    '%user%',
    $query[$query['publicname'] !== null ? 'publicname' : 'username'],
    _lang('mod.profile.posts')
);

// odkaz zpet na profil
$_index['backlink'] = Router::module('profile', 'id=' . $id);

// tabulka
[$columns, $joins, $cond, $count] = Post::createFilter('post', [Post::SECTION_COMMENT, Post::ARTICLE_COMMENT, Post::BOOK_ENTRY, Post::FORUM_TOPIC, Post::PLUGIN], [], "post.author=" . $query['id'], true);

$paging = Paginator::render(Router::module('profile-posts', 'id=' . $id), 15, $count);
if (Paginator::atTop()) {
    $output .= $paging['paging'];
}

$posts = DB::query("SELECT " . $columns . " FROM " . DB::table('post') . " post " . $joins . " WHERE " . $cond . " ORDER BY post.time DESC " . $paging['sql_limit']);
if (DB::size($posts) != 0) {
    while ($post = DB::row($posts)) {
        [$homelink, $hometitle] = Router::post($post);
        $output .= "<div class='post'>
<div class='post-head'>
    <a href='" . _e($homelink) . "#post-" . $post['id'] . "' class='post-author'>" . $hometitle . "</a>
    <span class='post-info'>(" . GenericTemplates::renderTime($post['time'], 'post') . ")</span>
</div>
<div class='post-body'>" . Post::render($post['text']) . "</div>
</div>";
    }
    if (Paginator::atBottom()) {
        $output .= $paging['paging'];
    }
} else {
    $output .= '<p>' . _lang('global.nokit') . '</p>';
}
