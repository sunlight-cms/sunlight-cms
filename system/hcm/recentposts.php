<?php

use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Comment\Comment;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\StringManipulator;

defined('_root') or exit;

return function ($limit = null, $stranky = "", $typ = null) {
    // priprava
    $result = "";
    if (isset($limit) && (int) $limit >= 1) {
        $limit = abs((int) $limit);
    } else {
        $limit = 10;
    }
    $post_types =  array(
        'section' => _post_section_comment,
        'article' => _post_article_comment,
        'book' => _post_book_entry,
        'topic' => _post_forum_topic,
        'plugin' => _post_plugin,
    );

    // nastaveni filtru
    if (!empty($stranky)) {
        $homes = Arr::removeValue(explode('-', $stranky), '');
    } else {
        $homes = array();
    }

    if (!empty($typ)) {
        if (isset($post_types[$typ])) {
            $typ = $post_types[$typ];
        } elseif (!in_array($typ, $post_types)) {
            $typ = _post_section_comment;
        }
        $types = array($typ);
    } else {
        $types = $post_types;
    }

    // dotaz
    list($columns, $joins, $cond) = Comment::createFilter('post', $types, $homes);
    $userQuery = User::createQuery('post.author');
    $columns .= ',' . $userQuery['column_list'];
    $joins .= ' ' . $userQuery['joins'];
    $query = DB::query("SELECT " . $columns . " FROM " . _comment_table . " post " . $joins . " WHERE " . $cond . " ORDER BY id DESC LIMIT " . $limit);

    while ($item = DB::row($query)) {
        list($homelink, $hometitle) = Router::post($item);

        if ($item['author'] != -1) {
            $authorname = Router::userFromQuery($userQuery, $item);
        } else {
            $authorname = $item['guest'];
        }

        $result .= "
<div class='list-item'>
<h2 class='list-title'><a href='" . $homelink . "'>" . $hometitle . "</a></h2>
<p class='list-perex'>" . StringManipulator::ellipsis(strip_tags(Comment::render($item['text'])), 256) . "</p>
" . GenericTemplates::renderInfos(array(
    array(_lang('global.postauthor'), $authorname),
    array(_lang('global.time'), GenericTemplates::renderTime($item['time'], 'post')),
)) . "</div>\n";
    }

    return $result;
};
