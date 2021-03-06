<?php

use Sunlight\Database\Database as DB;
use Sunlight\Database\SimpleTreeFilter;
use Sunlight\Extend;
use Sunlight\Page\Page;

defined('SL_ROOT') or exit;

/* ---  vystup  --- */

$output .= "
<p><a class='button' href='index.php?p=content-articles-edit'><img src='images/icons/new.png' alt='new' class='icon'>" . _lang('admin.content.articles.create') . "</a></p>

<table class='list list-noborder list-hover list-half'>
<thead>
<tr><th>" . _lang('article.category') . "</th><th>" . _lang('global.articlesnum') . "</th></tr>
</thead>
<tbody>
";

// nacist strom kategorii
$filter = new SimpleTreeFilter(['type' => Page::CATEGORY]);
Extend::call('admin.article.catfilter', ['filter' => &$filter]);
$tree = Page::getFlatTree(null, null, $filter);

// nacist pocty clanku
$art_counts = [];
$art_count_query = DB::query('SELECT
    c.id,
    (SELECT COUNT(*) FROM ' . DB::table('article') . ' a WHERE a.home1=c.id OR a.home2=c.id OR a.home3=c.id) art_count
FROM ' . DB::table('page') . ' c
WHERE c.type=' . Page::CATEGORY);
while ($art_count = DB::row($art_count_query)) {
    $art_counts[$art_count['id']] = $art_count['art_count'];
}

// radky
foreach ($tree as $page) {
    $output .= "<tr><td>";
    if ($page['type'] == Page::CATEGORY) {
        $output .= "<a class='node-level-m{$page['node_level']}' href='index.php?p=content-articles-list&amp;cat={$page['id']}'>
    <img src='images/icons/dir.png' alt='col' class='icon'>
    {$page['title']}
</a>";
    } else {
        $output .= "<span class='node-level-m{$page['node_level']}'>{$page['title']}</span>";
    }
    $output .= "</td><td>" . ($art_counts[$page['id']] ?? '') . "</td></tr>\n";
}

if (empty($tree)) {
    $output .= '<tr><td colspan="2">' . _lang('admin.content.form.category.nonefound') . '</td></tr>';
}

$output .= "
</tbody>
</table>

<br>
<form class='cform' action='index.php' method='get'>
<input type='hidden' name='p' value='content-articles-edit'>
<input type='hidden' name='returnid' value='load'>
<input type='hidden' name='returnpage' value='1'>
" . _lang('admin.content.articles.openid') . ": <input type='number' name='id' class='inputmini'> <input class='button' type='submit' value='" . _lang('global.open') . "'>
</form>
";
