<?php

use Sunlight\Article;
use Sunlight\Database\Database as DB;
use Sunlight\Paginator;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;

defined('_root') or exit;

if (!_logged_in && _notpublicsite) {
    $_index['type'] = _index_unauthorized;
    return;
}

/* ---  priprava  --- */

$id = StringManipulator::slugify(Request::get('id'), false);
$query = DB::queryRow("SELECT u.id,u.username,u.publicname,u.public,g.level FROM " . _user_table . " u JOIN " . _user_group_table . " g ON u.group_id=g.id WHERE u.username=" . DB::val($id));

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
    _lang('mod.profile.arts')
);

// odkaz zpet na profil
$_index['backlink'] = Router::module('profile', 'id=' . $id);

// tabulka
[$joins, $cond, $count] = Article::createFilter('art', [], "art.author=" . $query['id'], true);

$paging = Paginator::render(Router::module('profile-arts', 'id=' . $id), 10, $count);
if (Paginator::atTop()) {
    $output .= $paging['paging'];
}
$userQuery = User::createQuery('art.author');
$arts = DB::query("SELECT art.id,art.title,art.slug,art.author,art.perex,art.picture_uid,art.time,art.comments,art.public,art.readnum,cat1.slug AS cat_slug," . $userQuery['column_list'] . ",(SELECT COUNT(*) FROM " . _comment_table . " AS post WHERE home=art.id AND post.type=" . _post_article_comment . ") AS comment_count FROM " . _article_table . " AS art " . $joins . ' ' . $userQuery['joins'] . " WHERE " . $cond . " ORDER BY art.time DESC " . $paging['sql_limit']);
if (DB::size($arts) != 0) {
    while ($art = DB::row($arts)) {
        $output .= Article::renderPreview($art, $userQuery, true, true, $art['comment_count']);
    }
    if (Paginator::atBottom()) {
        $output .= $paging['paging'];
    }
} else {
    $output .= '<p>' . _lang('global.nokit') . '</p>';
}
