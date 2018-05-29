<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

/* ---  nacteni promennych  --- */

$continue = false;
if (isset($_GET['cat'])) {
    $cid = (int) \Sunlight\Util\Request::get('cat');
    if (DB::count(_root_table, 'id=' . DB::val($cid) . ' AND type=' . _page_category) !== 0) {
        $catdata = DB::queryRow("SELECT title,var1,var2 FROM " . _root_table . " WHERE id=" . $cid);
        $continue = true;
    }
}

/* ---  vystup --- */

if ($continue) {
    
    // nastaveni strankovani podle kategorie
    $artsperpage = $catdata['var2'];
    switch ($catdata['var1']) {
        case 1:
            $artorder = "art.time DESC";
            break;
        case 2:
            $artorder = "art.id DESC";
            break;
        case 3:
            $artorder = "art.title";
            break;
        case 4:
            $artorder = "art.title DESC";
            break;
    }

    // titulek kategorie
    $output .= "<h2 class='bborder'>" . $catdata['title'] . " <a class='button' href='index.php?p=content-articles-edit&amp;new_cat=" . $cid . "'><img src='images/icons/new.png' alt='new' class='icon'>" . _lang('admin.content.articles.create') . "</a></h2>\n";

    // vypis clanku

    // zprava
    $message = "";
    if (isset($_GET['artdeleted'])) {
        $message = \Sunlight\Message::render(_msg_ok, _lang('admin.content.articles.delete.done'));
    }

    $cond = "(art.home1=" . $cid . " OR art.home2=" . $cid . " OR art.home3=" . $cid . ")" . \Sunlight\Admin\Admin::articleAccess('art');
    $paging = \Sunlight\Paginator::render("index.php?p=content-articles-list&cat=" . $cid, $catdata['var2'] ?: _articlesperpage, _articles_table . ':art', $cond);
    $s = $paging['current'];
    $output .= $paging['paging'] . $message . "\n<table class='list list-hover list-max'>\n<thead><tr><td>" . _lang('global.article') . "</td><td>" . _lang('article.author') . "</td><td>" . _lang('article.posted') . "</td><td>" . _lang('global.action') . "</td></tr></thead>\n<tbody>";
    $userQuery = \Sunlight\User::createQuery('art.author');
    $arts = DB::query("SELECT art.id,art.title,art.slug,art.time,art.confirmed,art.visible,art.public,cat.slug AS cat_slug," . $userQuery['column_list'] . " FROM " . _articles_table . " AS art JOIN " . _root_table . " AS cat ON(cat.id=art.home1) " . $userQuery['joins'] . " WHERE " . $cond . " ORDER BY " . $artorder . " " . $paging['sql_limit']);
    if (DB::size($arts) != 0) {
        while ($art = DB::row($arts)) {
            $output .= "<tr>
    <td>" . \Sunlight\Admin\Admin::articleEditLink($art) . "</td>
    <td>" . \Sunlight\Router::userFromQuery($userQuery, $art) . "</td>
    <td>" . \Sunlight\Generic::renderTime($art['time']) . "</td>
    <td class='actions'>
            <a class='button' href='index.php?p=content-articles-edit&amp;id=" . $art['id'] . "&amp;returnid=" . $cid . "&amp;returnpage=" . $s . "'><img src='images/icons/edit.png' alt='edit' class='icon'>" . _lang('global.edit') . "</a>
        <a class='button' href='index.php?p=content-articles-delete&amp;id=" . $art['id'] . "&amp;returnid=" . $cid . "&amp;returnpage=" . $s . "'><img src='images/icons/delete.png' alt='del' class='icon'>" . _lang('global.delete') . "</a>
    </td>
</tr>\n";
        }
    } else {
        $output .= "<tr><td colspan='4'>" . _lang('global.nokit') . "</td></tr>";
    }
    $output .= "</tbody></table>";
    $output .= $paging['paging'];

} else {
    $output .= \Sunlight\Message::render(_msg_err, _lang('global.badinput'));
}
