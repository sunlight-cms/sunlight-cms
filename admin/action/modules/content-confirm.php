<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

// confirm selected article
if (isset($_POST['confirm'])) {
    $article = DB::queryRow(
        'SELECT art.id FROM ' . DB::table('article') . ' art'
        . ' LEFT JOIN ' . DB::table('page') . ' cat1 ON art.home1=cat1.id'
        . ' LEFT JOIN ' . DB::table('page') . ' cat2 ON art.home2=cat2.id'
        . ' LEFT JOIN ' . DB::table('page') . ' cat3 ON art.home3=cat3.id'
        . ' WHERE art.id=' . DB::val((int) Request::post('confirm'))
        . ' AND (cat1.level<=' . User::getLevel() . ' OR cat2.level<=' . User::getLevel() . ' OR cat3.level<=' . User::getLevel() . ')'
    );

    if ($article !== false) {
        DB::update('article', 'id=' . $article['id'], ['confirmed' => 1]);
        $message = Message::ok(_lang('global.done'));
    }
}

// load filters
if (isset($_GET['category'])) {
    $category = (int) Request::get('category');
    $cond = ' AND (art.home1=' . $category . ' OR art.home2=' . $category . ' OR art.home3=' . $category . ')';
} else {
    $category = -1;
    $cond = '';
}

// output
$output .= '
<form class="cform" action="' . _e(Router::admin(null)) . '" method="get">
    ' . Form::input('hidden', 'p', 'content-confirm')
    . _lang('admin.content.confirm.filter') . ': '
    . Admin::pageSelect('category', ['type' => Page::CATEGORY, 'selected' => $category, 'empty_item' => _lang('global.all')])
    . '
    ' . Form::input('submit', null, _lang('global.do')) . '
</form>
<div class="hr"><hr></div>

' . $message . '

<form method="post">
<table class="list list-hover list-max">
<thead><tr><td>' . _lang('global.article') . '</td><td>' . _lang('article.category') . '</td><td>' . _lang('article.posted') . '</td><td>' . _lang('article.author') . '</td><td>' . _lang('global.action') . '</td></tr></thead>
<tbody>';

// list
$userQuery = User::createQuery('art.author');
$query = DB::query(
    'SELECT art.id,art.title,art.slug,art.home1,art.home2,art.home3,art.time,art.visible,art.confirmed,art.public,cat.slug AS cat_slug,' . $userQuery['column_list']
    . ',(' . Admin::articleAccessSql('art') . ') art_access'
    . ' FROM ' . DB::table('article') . ' AS art JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1) '
    . $userQuery['joins']
    . ' LEFT JOIN ' . DB::table('page') . ' cat1 ON art.home1=cat1.id'
    . ' LEFT JOIN ' . DB::table('page') . ' cat2 ON art.home2=cat2.id'
    . ' LEFT JOIN ' . DB::table('page') . ' cat3 ON art.home3=cat3.id'
    . ' WHERE'
        . ' art.confirmed=0'
        . ' AND (cat1.level<=' . User::getLevel() . ' OR cat2.level<=' . User::getLevel() . ' OR cat3.level<=' . User::getLevel() . ')'
        . $cond
    . ' ORDER BY art.time DESC'
);

if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {
        // category list
        $cats = '';

        for ($i = 1; $i <= 3; $i++) {
            if ($item['home' . $i] != -1) {
                $hometitle = DB::queryRow('SELECT title FROM ' . DB::table('page') . ' WHERE id=' . $item['home' . $i]);
                $cats .= $hometitle['title'];
            }

            if ($i != 3 && $item['home' . ($i + 1)] != -1) {
                $cats .= ', ';
            }
        }

        $output .= '<tr>
            <td>' . Admin::articleEditLink($item, false) . '</td>
            <td>' . $cats . '</td><td>' . GenericTemplates::renderTime($item['time'], 'article_admin') . '</td>
            <td>' . Router::userFromQuery($userQuery, $item) . '</td>
            <td class="actions">
                <button class="button" type="submit" name="confirm" value="' . $item['id'] . '">
                    <img src="' . _e(Router::path('admin/public/images/icons/check.png')) . '" alt="confirm" class="icon">' . _lang('admin.content.confirm.confirm') . '
                </button>
                ' . ($item['art_access'] ? '<a class="button" href="' . _e(Router::admin('content-articles-edit', ['query' => ['id' => $item['id'], 'returnid' => 'load', 'returnpage' => 1]])) . '">
                        <img src="' . _e(Router::path('admin/public/images/icons/edit.png')) . '" alt="edit" class="icon">' . _lang('global.edit') . '
                    </a>' : '')
            . '</td>'
            . "</tr>\n";
    }
} else {
    $output .= '<tr><td colspan="5">' . _lang('global.nokit') . '</td></tr>';
}

$output .= '</tbody>
</table>
' . Xsrf::getInput() . '
</form>';
