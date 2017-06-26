<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
}

function _HCM_recentposts($limit = null, $stranky = "", $typ = null)
{
    // priprava
    $result = "";
    if (isset($limit) && (int) $limit >= 1) {
        $limit = abs((int) $limit);
    } else {
        $limit = 10;
    }
    $post_types =  array(_post_section_comment, _post_article_comment, _post_book_entry, _post_forum_topic, _post_plugin);

    // nastaveni filtru
    if (isset($stranky) && isset($typ)) {
        $typ = (int) $typ;
        if (!in_array($typ, $post_types)) {
            $typ = _post_section_comment;
        }
        $types = array($typ);
        $homes = _arrayRemoveValue(explode('-', $stranky), '');
    } else {
        $types = $post_types;
        $homes = array();
    }

    // dotaz
    list($columns, $joins, $cond) = _postFilter('post', $types, $homes);
    $userQuery = _userQuery('post.author');
    $columns .= ',' . $userQuery['column_list'];
    $joins .= ' ' . $userQuery['joins'];
    $query = DB::query("SELECT " . $columns . " FROM " . _posts_table . " post " . $joins . " WHERE " . $cond . " ORDER BY id DESC LIMIT " . $limit);

    while ($item = DB::row($query)) {
        list($homelink, $hometitle) = _linkPost($item);

        if ($item['author'] != -1) {
            $authorname = _linkUserFromQuery($userQuery, $item);
        } else {
            $authorname = $item['guest'];
        }

        $result .= "
<div class='list-item'>
<h2 class='list-title'><a href='" . $homelink . "'>" . $hometitle . "</a></h2>
<p class='list-perex'>" . _cutText(strip_tags(_parsePost($item['text'])), 256) . "</p>
" . _renderInfos(array(
    array(_lang('global.postauthor'), $authorname),
    array(_lang('global.time'), _formatTime($item['time'], 'post')),
)) . "</div>\n";
    }

    return $result;
}
