<?php

use Sunlight\Database\Database as DB;
use Sunlight\Hcm;
use Sunlight\Router;
use Sunlight\User;

return function ($order = 'new', $limit = 5) {
    Hcm::normalizeArgument($order, 'string');
    Hcm::normalizeArgument($limit, 'int');

    $limit = abs($limit);

    $rcond = 'public=1';
    $ordered = true;

    switch ($order) {
        case 'activity':
            $rorder = 'activitytime DESC';
            $rcond .= ' AND ' . time() . '-activitytime<1800';
            break;
        case 'comment-count':
            $rorder = '(SELECT COUNT(*) FROM ' . DB::table('post') . ' WHERE author=u.id) DESC';
            break;
        case 'article-rating':
            $rcond .= ' AND (SELECT COUNT(*) FROM ' . DB::table('article') . ' WHERE author=u.id AND rateon=1 AND ratenum!=0)!=0';
            $rorder = '(SELECT ROUND(SUM(ratesum)/SUM(ratenum)) FROM ' . DB::table('article') . ' WHERE rateon=1 AND ratenum!=0 AND author=u.id) DESC';
            break;
        case 'new':
        default:
            $rorder = 'registertime DESC';
            $ordered = false;
            break;
    }

    if ($ordered) {
        $result = "<ol>\n";
    } else {
        $result = "<ul>\n";
    }

    $userQuery = User::createQuery(null, '');
    $query = DB::query(
        'SELECT ' . $userQuery['column_list']
        . ' FROM ' . DB::table('user') . ' u ' . $userQuery['joins']
        . ' WHERE ' . $rcond
        . ' ORDER BY ' . $rorder
        . ' LIMIT ' . $limit
    );

    while ($item = DB::row($query)) {
        // add additional info
        switch ($order) {
            case 'comment-count':
                $rvar = DB::count('post', 'author=' . DB::val($item['id']));

                if ($rvar == 0) {
                    continue 2;
                }

                $rext = ' (' . $rvar . ')';
                break;

            case 'article-rating':
                $rvar = DB::queryRow(
                    'SELECT ROUND(SUM(ratesum)/SUM(ratenum)) AS pct,COUNT(*) AS cnt'
                    . ' FROM ' . DB::table('article')
                    . ' WHERE rateon=1 AND ratenum!=0 AND author=' . $item['id']
                );
                $rext = ' - ' . $rvar['pct'] . '%, ' . _lang('global.articlesnum') . ': ' . $rvar['cnt'];
                break;

            default:
                $rext = '';
                break;
        }

        $result .= '<li>' . Router::userFromQuery($userQuery, $item) . $rext . "</li>\n";
    }

    if ($order != 4) {
        $result .= "</ul>\n";
    } else {
        $result .= "</ol>\n";
    }

    return $result;
};
