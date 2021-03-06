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

/* ---  nacteni promennych  --- */

$continue = false;
if (isset($_GET['cat'])) {
    $cid = (int) Request::get('cat');
    if (DB::count('page', 'id=' . DB::val($cid) . ' AND type=' . Page::CATEGORY) !== 0) {
        $catdata = DB::queryRow("SELECT title,var1,var2 FROM " . DB::table('page') . " WHERE id=" . $cid);
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
        $message = Message::ok(_lang('admin.content.articles.delete.done'));
    }

    $cond = "(art.home1=" . $cid . " OR art.home2=" . $cid . " OR art.home3=" . $cid . ")" . Admin::articleAccess('art');
    $paging = Paginator::render("index.php?p=content-articles-list&cat=" . $cid, $catdata['var2'] ?: Settings::get('articlesperpage'), DB::table('article') . ':art', $cond);
    $s = $paging['current'];
    $output .= $paging['paging'] . $message . "\n<table class='list list-hover list-max'>\n<thead><tr><td>" . _lang('global.article') . "</td><td>" . _lang('article.author') . "</td><td>" . _lang('article.posted') . "</td><td>" . _lang('global.action') . "</td></tr></thead>\n<tbody>";
    $userQuery = User::createQuery('art.author');
    $arts = DB::query("SELECT art.id,art.title,art.slug,art.time,art.confirmed,art.visible,art.public,cat.slug AS cat_slug," . $userQuery['column_list'] . " FROM " . DB::table('article') . " AS art JOIN " . DB::table('page') . " AS cat ON(cat.id=art.home1) " . $userQuery['joins'] . " WHERE " . $cond . " ORDER BY " . $artorder . " " . $paging['sql_limit']);
    if (DB::size($arts) != 0) {
        while ($art = DB::row($arts)) {
            $output .= "<tr>
    <td>" . Admin::articleEditLink($art) . "</td>
    <td>" . Router::userFromQuery($userQuery, $art) . "</td>
    <td>" . GenericTemplates::renderTime($art['time']) . "</td>
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
    $output .= Message::error(_lang('global.badinput'));
}
