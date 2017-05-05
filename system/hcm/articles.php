<?php

if (!defined('_root')) {
    exit;
}

function _HCM_articles($typ = 1, $pocet = null, $perex = true, $info = true, $kategorie = null)
{
    // priprava
    $result = "";
    $typ = (int) $typ;
    if ($typ < 1 || $typ > 9) {
        $typ = 1;
    }
    $pocet = (int) $pocet;
    if ($pocet < 1) {
        $pocet = 1;
    }
    $perex = (int) $perex;
    $info = (bool) $info;

    // priprava casti sql dotazu
    switch ($typ) {
        case 2:
            $rorder = "art.readnum DESC";
            $rcond = "art.readnum!=0";
            break;
        case 3:
            $rorder = "art.ratesum/art.ratenum DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 4:
            $rorder = "art.ratenum DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 5:
            $rorder = "RAND()";
            $rcond = "";
            break;
        case 6:
            $rorder = "(SELECT time FROM " . _iplog_table . " WHERE type=2 AND var=art.id AND art.visible=1 AND art.time<=" . time() . " AND art.confirmed=1 ORDER BY id DESC LIMIT 1) DESC";
            $rcond = "art.readnum!=0";
            break;
        case 7:
            $rorder = "(SELECT time FROM " . _iplog_table . " WHERE type=3 AND var=art.id AND art.visible=1 AND art.time<=" . time() . " AND art.confirmed=1 ORDER BY id DESC LIMIT 1) DESC";
            $rcond = "art.ratenum!=0";
            break;
        case 8:
            $rorder = "(SELECT time FROM " . _posts_table . " WHERE home=art.id AND type=2 ORDER BY time DESC LIMIT 1) DESC";
            $rcond = "(SELECT COUNT(*) FROM " . _posts_table . " WHERE home=art.id AND type=2)!=0";
            break;
        case 9:
            $rorder = "(SELECT COUNT(*) FROM " . _posts_table . " WHERE home=art.id AND type=2) DESC";
            $rcond = "(SELECT COUNT(*) FROM " . _posts_table . " WHERE home=art.id AND type=2)!=0";
            break;
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
    $query = DB::query("SELECT art.id,art.title,art.slug,art.perex," . (($perex === 2) ? 'art.picture_uid,' : '') . "art.time,art.readnum,art.comments,cat1.slug AS cat_slug," . $userQuery['column_list'] . (($info !== 0) ? ",(SELECT COUNT(*) FROM " . _posts_table . " AS post WHERE home=art.id AND post.type=2) AS comment_count" : '') . " FROM " . _articles_table . " AS art " . $joins . ' ' . $userQuery['joins'] . " WHERE " . $cond . " ORDER BY " . $rorder . " LIMIT " . $pocet);
    while ($item = DB::row($query)) {
        $result .= _articlePreview($item, $userQuery, $info, $perex !== 0, (($info !== 0) ? $item['comment_count'] : null));
    }

    return $result;
}
