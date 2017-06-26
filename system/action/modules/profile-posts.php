<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
}

if (!_login && _notpublicsite) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava  --- */

$id = _slugify(_get('id'), false);
$query = DB::queryRow("SELECT id,username,publicname FROM " . _users_table . " WHERE username=" . DB::val($id));
if ($query === false) {
    $_index['is_found'] = false;
    return;
}

/* ---  modul  --- */

$_index['title'] = str_replace(
    '*user*',
    $query[$query['publicname'] !== null ? 'publicname' : 'username'],
    _lang('mod.profile.posts')
);

// odkaz zpet na profil
$_index['backlink'] = _linkModule('profile', 'id=' . $id, false);

// tabulka
list($columns, $joins, $cond, $count) = _postFilter('post', array(_post_section_comment, _post_article_comment, _post_book_entry, _post_forum_topic, _post_plugin), array(), "post.author=" . $query['id'], true);

$paging = _resultPaging(_linkModule('profile-posts', 'id=' . $id, false), 15, $count);
if (_showPagingAtTop()) {
    $output .= $paging['paging'];
}

$posts = DB::query("SELECT " . $columns . " FROM " . _posts_table . " post " . $joins . " WHERE " . $cond . " ORDER BY post.time DESC " . $paging['sql_limit']);
if (DB::size($posts) != 0) {
    while ($post = DB::row($posts)) {
        list($homelink, $hometitle) = _linkPost($post);
        $output .= "<div class='post'>
<div class='post-head'>
    <a href='" . $homelink . "#post-" . $post['id'] . "' class='post-author'>" . $hometitle . "</a>
    <span class='post-info'>(" . _formatTime($post['time'], 'post') . ")</span>
</div>
<div class='post-body'>" . _parsePost($post['text']) . "</div>
</div>";
    }
    if (_showPagingAtBottom()) {
        $output .= $paging['paging'];
    }
} else {
    $output .= '<p>' . _lang('global.nokit') . '</p>';
}
