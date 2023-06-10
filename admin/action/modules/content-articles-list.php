<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Paginator;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

$continue = false;

if (isset($_GET['cat'])) {
    $cid = (int) Request::get('cat');
    $catdata = DB::queryRow('SELECT type,level,title,var1,var2 FROM ' . DB::table('page') . ' WHERE id=' . $cid . ' AND type=' . Page::CATEGORY);
    $continue = ($catdata !== false && Admin::pageAccess($catdata));
}

// output
if ($continue) {
    // set order depending on category
    $artsperpage = $catdata['var2'];

    switch ($catdata['var1']) {
        case 1:
            $artorder = 'art.time DESC';
            break;
        case 2:
            $artorder = 'art.id DESC';
            break;
        case 3:
            $artorder = 'art.title';
            break;
        case 4:
            $artorder = 'art.title DESC';
            break;
    }

    // category title
    $output .= '<h2 class="bborder">'
        . $catdata['title']
        . ' <a class="button" href="' . _e(Router::admin('content-articles-edit', ['query' => ['new_cat' => $cid]])) . '"><img src="' . _e(Router::path('admin/public/images/icons/new.png')) . '" alt="new" class="icon">'
        . _lang('admin.content.articles.create')
        . "</a></h2>\n";

    // message
    $message = '';

    if (isset($_GET['artdeleted'])) {
        $message = Message::ok(_lang('admin.content.articles.delete.done'));
    }

    // list articles
    $cond = '(art.home1=' . $cid . ' OR art.home2=' . $cid . ' OR art.home3=' . $cid . ') AND ' . Admin::articleAccessSql('art');
    $paging = Paginator::paginateTable(
        Router::admin('content-articles-list', ['query' => ['cat' => $cid]]),
        $catdata['var2'] ?: Settings::get('articlesperpage'),
        DB::table('article'),
        ['alias' => 'art', 'cond' => $cond]
    );
    $s = $paging['current'];
    $output .= $paging['paging']
        . $message
        . '<table class="list list-hover list-max">
    <thead>
        <tr>
            <td>' . _lang('global.article') . '</td>
            <td>' . _lang('article.author') . '</td>
            <td>' . _lang('article.posted') . '</td>
            <td>' . _lang('global.action') . '</td>
        </tr>
    </thead>
    <tbody>';
    $userQuery = User::createQuery('art.author');
    $arts = DB::query(
        'SELECT art.id,art.title,art.slug,art.time,art.confirmed,art.visible,art.public,cat.slug AS cat_slug,' . $userQuery['column_list']
        . ' FROM ' . DB::table('article') . ' AS art'
        . ' JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1) '
        . $userQuery['joins']
        . ' WHERE ' . $cond
        . ' ORDER BY ' . $artorder
        . ' ' . $paging['sql_limit']
    );

    if (DB::size($arts) != 0) {
        while ($art = DB::row($arts)) {
            $output .= '<tr>
    <td>' . Admin::articleEditLink($art) . '</td>
    <td>' . Router::userFromQuery($userQuery, $art) . '</td>
    <td>' . GenericTemplates::renderTime($art['time']) . '</td>
    <td class="actions">
        <a class="button" href="' . _e(Router::admin('content-articles-edit', ['query' => ['id' => $art['id'], 'returnid' => $cid,  'returnpage' => $s]])) . '">
            <img src="' . _e(Router::path('admin/public/images/icons/edit.png')) . '" alt="edit" class="icon">' . _lang('global.edit') . '
        </a>
        <a class="button" href="' . _e(Router::admin('content-articles-delete', ['query' => ['id' => $art['id'], 'returnid' => $cid, 'returnpage' => $s]])) . '">
            <img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">' . _lang('global.delete') . '
        </a>
    </td>
</tr>';
        }
    } else {
        $output .= '<tr><td colspan="4">' . _lang('global.nokit') . '</td></tr>';
    }

    $output .= '</tbody></table>';
    $output .= $paging['paging'];
} else {
    $output .= Message::error(_lang('global.badinput'));
}
