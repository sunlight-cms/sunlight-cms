<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

if (!_logged_in && _notpublicsite) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava  --- */

$id = _slugify(_get('id'), false);
$query = DB::queryRow("SELECT u.id,u.username,u.publicname,u.public,g.level FROM " . _users_table . " u JOIN " . _groups_table . " g ON u.group_id=g.id WHERE u.username=" . DB::val($id));

if ($query === false) {
    $_index['is_found'] = false;
    return;
}

if (!$query['public'] && !_levelCheck($query['id'], $query['level'])) {
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
$_index['backlink'] = _linkModule('profile', 'id=' . $id, false);

// tabulka
list($joins, $cond, $count) = _articleFilter('art', array(), "art.author=" . $query['id'], true);

$paging = _resultPaging(_linkModule('profile-arts', 'id=' . $id, false), 10, $count);
if (_showPagingAtTop()) {
    $output .= $paging['paging'];
}
$userQuery = _userQuery('art.author');
$arts = DB::query("SELECT art.id,art.title,art.slug,art.author,art.perex,art.picture_uid,art.time,art.comments,art.public,art.readnum,cat1.slug AS cat_slug," . $userQuery['column_list'] . ",(SELECT COUNT(*) FROM " . _posts_table . " AS post WHERE home=art.id AND post.type=" . _post_article_comment . ") AS comment_count FROM " . _articles_table . " AS art " . $joins . ' ' . $userQuery['joins'] . " WHERE " . $cond . " ORDER BY art.time DESC " . $paging['sql_limit']);
if (DB::size($arts) != 0) {
    while ($art = DB::row($arts)) {
        $output .= _articlePreview($art, $userQuery, true, true, $art['comment_count']);
    }
    if (_showPagingAtBottom()) {
        $output .= $paging['paging'];
    }
} else {
    $output .= '<p>' . _lang('global.nokit') . '</p>';
}
