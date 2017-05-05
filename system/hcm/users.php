<?php

if (!defined('_root')) {
    exit;
}

function _HCM_users($razeni = 1, $pocet = 5)
{
    $pocet = abs((int) $pocet);

    $rcond = "";
    switch ($razeni) {
        case 2:
            $rorder = "activitytime DESC";
            $rcond = " WHERE " . time() . "-activitytime<1800";
            break;
        case 3:
            $rorder = "(SELECT COUNT(*) FROM " . _posts_table . " WHERE author=" . _users_table . ".id) DESC";
            break;
        case 4:
            $rcond = " WHERE (SELECT COUNT(*) FROM " . _articles_table . " WHERE author=" . _users_table . ".id AND rateon=1 AND ratenum!=0)!=0";
            $rorder = "(SELECT ROUND(SUM(ratesum)/SUM(ratenum)) FROM " . _articles_table . " WHERE rateon=1 AND ratenum!=0 AND author=" . _users_table . ".id) DESC";
            break;
        default:
            $rorder = "id DESC";
            break;
    }

    if ($razeni != 4) {
        $result = "<ul>\n";
    } else {
        $result = "<ol>\n";
    }

    $userQuery = _userQuery(null, '');
    $query = DB::query("SELECT " . $userQuery['column_list'] . " FROM " . _users_table . " u " . $userQuery['joins'] . $rcond . " ORDER BY " . $rorder . " LIMIT " . $pocet);
    while ($item = DB::row($query)) {

        // pridani doplnujicich informaci
        switch ($razeni) {

                // pocet prispevku
            case 3:
                $rvar = DB::result(DB::query("SELECT COUNT(*) FROM " . _posts_table . " WHERE author=" . $item['id']), 0);
                if ($rvar == 0) {
                    continue;
                } else {
                    $rext = " (" . $rvar . ")";
                }
                break;

                // hodnoceni autora
            case 4:
                $rvar = DB::queryRow("SELECT ROUND(SUM(ratesum)/SUM(ratenum)),COUNT(*) FROM " . _articles_table . " WHERE rateon=1 AND ratenum!=0 AND author=" . $item['id']);
                $rext = " - " . $rvar[0] . "%, " . $GLOBALS['_lang']['global.articlesnum'] . ": " . $rvar[1];
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
}
