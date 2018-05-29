<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

if (!_logged_in && _notpublicsite) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava  --- */

$id = \Sunlight\Util\StringManipulator::slugify(\Sunlight\Util\Request::get('id'), false);
$query = DB::queryRow("SELECT u.id,u.username,u.publicname,u.public,g.level FROM " . _users_table . " u JOIN " . _groups_table . " g ON u.group_id=g.id WHERE u.username=" . DB::val($id));

if ($query === false) {
    $_index['is_found'] = false;
    return;
}

if (!$query['public'] && !\Sunlight\User::checkLevel($query['id'], $query['level'])) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  modul  --- */

$_index['title'] = str_replace(
    '*user*',
    $query[$query['publicname'] !== null ? 'publicname' : 'username'],
    _lang('mod.profile.arts')
);

// odkaz zpet na profil
$_index['backlink'] = \Sunlight\Router::module('profile', 'id=' . $id, false);

// tabulka
list($joins, $cond, $count) = \Sunlight\Article::createFilter('art', array(), "art.author=" . $query['id'], true);

$paging = \Sunlight\Paginator::render(\Sunlight\Router::module('profile-arts', 'id=' . $id, false), 10, $count);
if (\Sunlight\Paginator::atTop()) {
    $output .= $paging['paging'];
}
$userQuery = \Sunlight\User::createQuery('art.author');
$arts = DB::query("SELECT art.id,art.title,art.slug,art.author,art.perex,art.picture_uid,art.time,art.comments,art.public,art.readnum,cat1.slug AS cat_slug," . $userQuery['column_list'] . ",(SELECT COUNT(*) FROM " . _posts_table . " AS post WHERE home=art.id AND post.type=" . _post_article_comment . ") AS comment_count FROM " . _articles_table . " AS art " . $joins . ' ' . $userQuery['joins'] . " WHERE " . $cond . " ORDER BY art.time DESC " . $paging['sql_limit']);
if (DB::size($arts) != 0) {
    while ($art = DB::row($arts)) {
        $output .= \Sunlight\Article::renderPreview($art, $userQuery, true, true, $art['comment_count']);
    }
    if (\Sunlight\Paginator::atBottom()) {
        $output .= $paging['paging'];
    }
} else {
    $output .= '<p>' . _lang('global.nokit') . '</p>';
}
