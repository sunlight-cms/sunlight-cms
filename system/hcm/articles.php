<?php

use Sunlight\Article;
use Sunlight\Hcm;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\IpLog;
use Sunlight\User;
use Sunlight\Util\Arr;

return function ($type = 'new', $limit = null, $perex = 'perex', $info = true, $category = '') {
    Hcm::normalizeArgument($type, 'string');
    Hcm::normalizeArgument($limit, 'int');
    Hcm::normalizeArgument($perex, 'string');
    Hcm::normalizeArgument($info, 'bool');
    Hcm::normalizeArgument($category, 'string');

    $result = '';

    if ($limit < 1) {
        $limit = 1;
    }

    if ($perex === '0') {
        $perex = 'no-perex';
    }

    switch ($perex) {
        case 'no-perex':
            $show_perex = false;
            $show_image = false;
            break;
        case 'perex':
        default:
            $show_perex = true;
            $show_image = false;
            break;
        case 'perex-image':
            $show_perex = true;
            $show_image = true;
            break;
    }

    // prepare SQL parts
    switch ($type) {
        case 'views':
            $rorder = 'art.view_count DESC';
            $rcond = 'art.view_count!=0';
            break;
        case 'rating':
            $rorder = 'art.ratesum/art.ratenum DESC';
            $rcond = 'art.ratenum!=0';
            break;
        case 'ratenum':
            $rorder = 'art.ratenum DESC';
            $rcond = 'art.ratenum!=0';
            break;
        case 'random':
            $rorder = 'RAND()';
            $rcond = '';
            break;
        case 'rated':
            $rorder = '(SELECT time FROM ' . DB::table('iplog')
                . ' WHERE type=' . IpLog::ARTICLE_RATED . ' AND var=art.id AND art.visible=1 AND art.time<=' . time() . ' AND art.confirmed=1'
                . ' ORDER BY id DESC LIMIT 1) DESC';
            $rcond = 'art.ratenum!=0';
            break;
        case 'commented':
            $rorder = '(SELECT time FROM ' . DB::table('post')
                . ' WHERE home=art.id AND type=' . Post::ARTICLE_COMMENT
                . ' ORDER BY time DESC LIMIT 1) DESC';
            $rcond = '(SELECT COUNT(*) FROM ' . DB::table('post')
                . ' WHERE home=art.id AND type=' . Post::ARTICLE_COMMENT . ')!=0';
            break;
        case 'most-comments':
            $rorder = '(SELECT COUNT(*) FROM ' . DB::table('post')
                . ' WHERE home=art.id AND type=' . Post::ARTICLE_COMMENT . ') DESC';
            $rcond = '(SELECT COUNT(*) FROM ' . DB::table('post')
                . ' WHERE home=art.id AND type=' . Post::ARTICLE_COMMENT . ')!=0';
            break;
        case 'new':
        default:
            $rorder = 'art.time DESC';
            $rcond = '';
            break;
    }

    [$joins, $cond] = Article::createFilter(
        'art',
        Arr::removeValue(explode('-', $category ?? ''), ''),
        $rcond
    );

    if ($rcond != '') {
        $cond .= ' AND ' . $cond;
    }

    // list
    $userQuery = User::createQuery('art.author');
    $query = DB::query(
        'SELECT art.id,art.title,art.slug,art.perex,' . ($show_image ? 'art.picture_uid,' : '') . 'art.time,art.view_count,art.comments,cat1.slug AS cat_slug,'
        . $userQuery['column_list']
        . ($info ? ',(SELECT COUNT(*) FROM ' . DB::table('post') . ' AS post WHERE home=art.id AND post.type=' . Post::ARTICLE_COMMENT . ') AS comment_count' : '')
        . ' FROM ' . DB::table('article') . ' AS art '
        . $joins . ' ' . $userQuery['joins']
        . ' WHERE ' . $cond . ' ORDER BY ' . $rorder . ' LIMIT ' . $limit
    );

    while ($item = DB::row($query)) {
        $result .= Article::renderPreview($item, $userQuery, $info, $show_perex);
    }

    return $result;
};
