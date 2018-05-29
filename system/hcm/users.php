<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
};

return function ($razeni = 'new', $pocet = 5)
{
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
            $rorder = "(SELECT COUNT(*) FROM " . _posts_table . " WHERE author=" . _users_table . ".id) DESC";
            break;
        case 'article-rating':
        case 4:
            $rcond .= " AND (SELECT COUNT(*) FROM " . _articles_table . " WHERE author=" . _users_table . ".id AND rateon=1 AND ratenum!=0)!=0";
            $rorder = "(SELECT ROUND(SUM(ratesum)/SUM(ratenum)) FROM " . _articles_table . " WHERE rateon=1 AND ratenum!=0 AND author=" . _users_table . ".id) DESC";
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

    $userQuery = _userQuery(null, '');
    $query = DB::query("SELECT " . $userQuery['column_list'] . " FROM " . _users_table . " u " . $userQuery['joins'] . ' WHERE ' . $rcond . " ORDER BY " . $rorder . " LIMIT " . $pocet);
    while ($item = DB::row($query)) {

        // pridani doplnujicich informaci
        switch ($razeni) {

            case 'comment-count':
            case 3:
                $rvar = DB::count(_posts_table, 'author=' . DB::val($item['id']));
                if ($rvar == 0) {
                    continue;
                } else {
                    $rext = " (" . $rvar . ")";
                }
                break;

            case 'article-rating':
            case 4:
                $rvar = DB::queryRow("SELECT ROUND(SUM(ratesum)/SUM(ratenum)),COUNT(*) FROM " . _articles_table . " WHERE rateon=1 AND ratenum!=0 AND author=" . $item['id']);
                $rext = " - " . $rvar[0] . "%, " . _lang('global.articlesnum') . ": " . $rvar[1];
                break;

                // nic
            default:
                $rext = "";
                break;

        }

        $result .= "<li>" . _linkUserFromQuery($userQuery, $item) . $rext . "</li>\n";
    }
    if ($razeni != 4) {
        $result .= "</ul>\n";
    } else {
        $result .= "</ol>\n";
    }

    return $result;
};
