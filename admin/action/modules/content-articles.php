<?php

use Sunlight\Admin\Admin;
use Sunlight\Admin\PageFilter;
use Sunlight\Database\Database as DB;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\Util\Form;

defined('SL_ROOT') or exit;

// output
$output .= '
<p><a class="button" href="' . _e(Router::admin('content-articles-edit')) . '"><img src="' . _e(Router::path('admin/public/images/icons/new.png')) . '" alt="new" class="icon">' . _lang('admin.content.articles.create') . '</a></p>

<table class="list list-noborder list-hover list-half">
<thead>
<tr><th>' . _lang('article.category') . '</th><th>' . _lang('global.articlesnum') . '</th></tr>
</thead>
<tbody>
';

// load category tree
$tree = Page::getFlatTree(null, null, new PageFilter(Page::CATEGORY));

// load article counts
$art_counts = [];
$art_count_query = DB::query(
    'SELECT'
    . ' c.id,'
    . ' (SELECT COUNT(*) FROM ' . DB::table('article') . ' a WHERE a.home1=c.id OR a.home2=c.id OR a.home3=c.id) art_count'
    . ' FROM ' . DB::table('page') . ' c'
    . ' WHERE c.type=' . Page::CATEGORY
);

$art_counts = DB::rows($art_count_query, 'id', 'art_count');

// rows
foreach ($tree as $page) {
    $output .= '<tr><td>';

    if ($page['type'] == Page::CATEGORY && Admin::pageAccess($page)) {
        $output .= '<a class="node-level-m' . $page['node_level'] . '" href="' . _e(Router::admin('content-articles-list', ['query' => ['cat' => $page['id']]])) . '">
    <img src="' . _e(Router::path('admin/public/images/icons/dir.png')) . '" alt="col" class="icon">
    ' . $page['title'] . '
</a>';
    } else {
        $output .= '<span class="node-level-m' . $page['node_level'] . '">' . $page['title'] . '</span>';
    }

    $output .= '</td><td>' . _num($art_counts[$page['id']] ?? 0) . "</td></tr>\n";
}

if (empty($tree)) {
    $output .= '<tr><td colspan="2">' . _lang('admin.content.form.category.nonefound') . '</td></tr>';
}

$output .= '
</tbody>
</table>

<br>
<form class="cform" action="' . _e(Router::admin(null)) . '" method="get">
' . Form::input('hidden', 'p', 'content-articles-edit') . '
' . Form::input('hidden', 'returnid', 'load') . '
' . Form::input('hidden', 'returnpage', '1') . '
' . _lang('admin.content.articles.openid') . ': ' . Form::input('number', 'id', null, ['class' => 'inputmini']) . ' ' . Form::input('submit', null, _lang('global.open'), ['class' => 'button']) . '
</form>
';
