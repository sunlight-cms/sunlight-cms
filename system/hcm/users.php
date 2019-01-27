<?php

use Sunlight\Database\Database as DB;
use Sunlight\Router;
use Sunlight\User;

defined('_root') or exit;

return function ($razeni = 'new', $pocet = 5) {
    $pocet = abs((int) $pocet);

    $rcond = "public=1";
    switch ($razeni) {
        case 'activity':
        case 2:
            $rorder = "activitytime DESC";
            $rcond .= " AND " . time() . "-activitytime<1800";
            break;
        case 'comment-count':
        case 3:
            $rorder = "(SELECT COUNT(*) FROM " . _comment_table . " WHERE author=u.id) DESC";
            break;
        case 'article-rating':
        case 4:
            $rcond .= " AND (SELECT COUNT(*) FROM " . _article_table . " WHERE author=u.id AND rateon=1 AND ratenum!=0)!=0";
            $rorder = "(SELECT ROUND(SUM(ratesum)/SUM(ratenum)) FROM " . _article_table . " WHERE rateon=1 AND ratenum!=0 AND author=u.id) DESC";
            break;
        case 'new':
        default:
            $rorder = "registertime DESC";
            break;
    }

    if ($razeni != 4) {
        $result = "<ul>\n";
    } else {
        $result = "<ol>\n";
    }

    $userQuery = User::createQuery(null, '');
    $query = DB::query("SELECT " . $userQuery['column_list'] . " FROM " . _user_table . " u " . $userQuery['joins'] . ' WHERE ' . $rcond . " ORDER BY " . $rorder . " LIMIT " . $pocet);
    while ($item = DB::row($query)) {

        // pridani doplnujicich informaci
        switch ($razeni) {

            case 'comment-count':
            case 3:
                $rvar = DB::count(_comment_table, 'author=' . DB::val($item['id']));
                if ($rvar == 0) {
                    continue;
                } else {
                    $rext = " (" . $rvar . ")";
                }
                break;

            case 'article-rating':
            case 4:
                $rvar = DB::queryRow("SELECT ROUND(SUM(ratesum)/SUM(ratenum)) AS pct,COUNT(*) AS cnt FROM " . _article_table . " WHERE rateon=1 AND ratenum!=0 AND author=" . $item['id']);
                $rext = " - " . $rvar['pct'] . "%, " . _lang('global.articlesnum') . ": " . $rvar['cnt'];
                break;

                // nic
            default:
                $rext = "";
                break;

        }

        $result .= "<li>" . Router::userFromQuery($userQuery, $item) . $rext . "</li>\n";
    }
    if ($razeni != 4) {
        $result .= "</ul>\n";
    } else {
        $result .= "</ol>\n";
    }

    return $result;
};
