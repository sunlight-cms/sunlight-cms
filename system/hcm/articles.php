<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

return function ($typ = 'new', $pocet = null, $perex = 'perex', $info = true, $kategorie = null)
{
    // priprava
    $result = "";
    $pocet = (int) $pocet;
    if ($pocet < 1) {
        $pocet = 1;
    }

    if ($perex === '0' || $perex === 0) {
        $perex = 'no-perex';
    }

    switch ($perex) {
        case 'no-perex':
            $show_perex = false;
            $show_image = false;
            break;
        case 'perex':
        case 1:
        default:
            $show_perex = true;
            $show_image = false;
            break;
        case 'perex-image':
            $show_perex = true;
            $show_image = true;
            break;
    }
    $info = (bool) $info;

    // priprava casti sql dotazu
    switch ($typ) {
        case 'readnum':
        case 2:
            $rorder = "art.readnum DESC";
            $rcond = "art.readnum!=0";
            break;
        case 'rating':
        case 3:
            $rorder = "art.ratesum/art.ratenum DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 'ratenum':
        case 4:
            $rorder = "art.ratenum DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 'random':
        case 5:
            $rorder = "RAND()";
            $rcond = "";
            break;
        case 'read':
        case 6:
            $rorder = "(SELECT time FROM " . _iplog_table . " WHERE type=" . _iplog_article_read . " AND var=art.id AND art.visible=1 AND art.time<=" . time() . " AND art.confirmed=1 ORDER BY id DESC LIMIT 1) DESC";
            $rcond = "art.readnum!=0";
            break;
        case 'rated':
        case 7:
            $rorder = "(SELECT time FROM " . _iplog_table . " WHERE type=" . _iplog_article_rated . " AND var=art.id AND art.visible=1 AND art.time<=" . time() . " AND art.confirmed=1 ORDER BY id DESC LIMIT 1) DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 'commented':
        case 8:
            $rorder = "(SELECT time FROM " . _posts_table . " WHERE home=art.id AND type=" . _post_article_comment . " ORDER BY time DESC LIMIT 1) DESC";
            $rcond = "(SELECT COUNT(*) FROM " . _posts_table . " WHERE home=art.id AND type=" . _post_article_comment . ")!=0";
            break;
        case 'most-comments':
        case 9:
            $rorder = "(SELECT COUNT(*) FROM " . _posts_table . " WHERE home=art.id AND type=" . _post_article_comment . ") DESC";
            $rcond = "(SELECT COUNT(*) FROM " . _posts_table . " WHERE home=art.id AND type=" . _post_article_comment . ")!=0";
            break;
        case 'new':
        default:
            $rorder = "art.time DESC";
            $rcond = "";
            break;
    }

    // omezeni vypisu
    list($joins, $cond) = _articleFilter(
        'art',
        _arrayRemoveValue(explode('-', $kategorie), ''),
        $rcond
    );

    // pripojeni casti
    if ($rcond != "") {
        $cond .= ' AND ' . $cond;
    }

    // vypis
    $userQuery = _userQuery('art.author');
    $query = DB::query("SELECT art.id,art.title,art.slug,art.perex," . ($show_image ? 'art.picture_uid,' : '') . "art.time,art.readnum,art.comments,cat1.slug AS cat_slug," . $userQuery['column_list'] . (($info !== 0) ? ",(SELECT COUNT(*) FROM " . _posts_table . " AS post WHERE home=art.id AND post.type=" . _post_article_comment . ") AS comment_count" : '') . " FROM " . _articles_table . " AS art " . $joins . ' ' . $userQuery['joins'] . " WHERE " . $cond . " ORDER BY " . $rorder . " LIMIT " . $pocet);
    while ($item = DB::row($query)) {
        $result .= _articlePreview($item, $userQuery, $info, $show_perex, (($info !== 0) ? $item['comment_count'] : null));
    }

    return $result;
};
