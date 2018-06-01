<?php

use Sunlight\Database\Database as DB;
use Sunlight\Generic;
use Sunlight\Paginator;
use Sunlight\Post;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;

defined('_root') or exit;

if (!_logged_in && _notpublicsite) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava  --- */

$id = StringManipulator::slugify(Request::get('id'), false);
$query = DB::queryRow("SELECT u.id,u.username,u.publicname,u.public,g.level FROM " . _users_table . " u JOIN " . _groups_table . " g ON u.group_id=g.id WHERE u.username=" . DB::val($id));

if ($query === false) {
    $_index['is_found'] = false;
    return;
}

if (!$query['public'] && !User::checkLevel($query['id'], $query['level'])) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  modul  --- */

$_index['title'] = str_replace(
    '*user*',
    $query[$query['publicname'] !== null ? 'publicname' : 'username'],
    _lang('mod.profile.posts')
);

// odkaz zpet na profil
$_index['backlink'] = Router::module('profile', 'id=' . $id, false);

// tabulka
list($columns, $joins, $cond, $count) = Post::createFilter('post', array(_post_section_comment, _post_article_comment, _post_book_entry, _post_forum_topic, _post_plugin), array(), "post.author=" . $query['id'], true);

$paging = Paginator::render(Router::module('profile-posts', 'id=' . $id, false), 15, $count);
if (Paginator::atTop()) {
    $output .= $paging['paging'];
}

$posts = DB::query("SELECT " . $columns . " FROM " . _posts_table . " post " . $joins . " WHERE " . $cond . " ORDER BY post.time DESC " . $paging['sql_limit']);
if (DB::size($posts) != 0) {
    while ($post = DB::row($posts)) {
        list($homelink, $hometitle) = Router::post($post);
        $output .= "<div class='post'>
<div class='post-head'>
    <a href='" . $homelink . "#post-" . $post['id'] . "' class='post-author'>" . $hometitle . "</a>
    <span class='post-info'>(" . Generic::renderTime($post['time'], 'post') . ")</span>
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
