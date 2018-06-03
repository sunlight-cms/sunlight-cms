<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Request;

defined('_root') or exit;

/* ---  schvaleni zvoleneho clanku  --- */

$message = "";
if (isset($_GET['id'])) {
    DB::update(_articles_table, 'id=' . DB::val(Request::get('id')), array('confirmed' => 1));
    $message = Message::ok(_lang('global.done'));
}

/* ---  vystup  --- */

// nacteni filtru
if (isset($_GET['limit'])) {
    $catlimit = (int) Request::get('limit');
    $condplus = " AND (art.home1=" . $catlimit . " OR art.home2=" . $catlimit . " OR art.home3=" . $catlimit . ")";
} else {
    $catlimit = -1;
    $condplus = "";
}

$output .= "
<form class='cform' action='index.php' method='get'>
    <input type='hidden' name='p' value='content-confirm'>"
    . _lang('admin.content.confirm.filter') . ": "
    . Admin::rootSelect("limit", array('type' => _page_category, 'selected' => $catlimit, 'empty_item' => _lang('global.all')))
    . "
    <input type='submit' value='" . _lang('global.do') . "'>
</form>
<div class='hr'><hr></div>

" . $message . "

<table class='list list-hover list-max'>
<thead><tr><td>" . _lang('global.article') . "</td><td>" . _lang('article.category') . "</td><td>" . _lang('article.posted') . "</td><td>" . _lang('article.author') . "</td><td>" . _lang('global.action') . "</td></tr></thead>
<tbody>";

// vypis
$userQuery = User::createQuery('art.author');
$query = DB::query("SELECT art.id,art.title,art.slug,art.home1,art.home2,art.home3,art.time,art.visible,art.confirmed,art.public,cat.slug AS cat_slug," . $userQuery['column_list'] . " FROM " . _articles_table . " AS art JOIN " . _root_table . " AS cat ON(cat.id=art.home1) " . $userQuery['joins'] . " WHERE art.confirmed=0" . $condplus . " ORDER BY art.time DESC");
if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {

        // seznam kategorii
        $cats = "";
        for ($i = 1; $i <= 3; $i++) {
            if ($item['home' . $i] != -1) {
                $hometitle = DB::queryRow("SELECT title FROM " . _root_table . " WHERE id=" . $item['home' . $i]);
                $cats .= $hometitle['title'];
            }
            if ($i != 3 && $item['home' . ($i + 1)] != -1) {
                $cats .= ", ";
            }
        }

        $output .= "<tr>
            <td>" . Admin::articleEditLink($item, false) . "</td>
            <td>" . $cats . "</td><td>" . GenericTemplates::renderTime($item['time']) . "</td>
            <td>" . Router::userFromQuery($userQuery, $item) . "</td>
            <td class='actions'>
                <a class='button' href='index.php?p=content-confirm&amp;id=" . $item['id'] . "&amp;limit=" . $catlimit . "'><img src='images/icons/check.png' alt='confirm' class='icon'>" . _lang('admin.content.confirm.confirm') . "</a>
                <a class='button' href='index.php?p=content-articles-edit&amp;id=" . $item['id'] . "&amp;returnid=load&amp;returnpage=1'><img src='images/icons/edit.png' alt='edit' class='icon'>" . _lang('global.edit') . "</a>"
            . "</td>"
            . "</tr>\n";
    }
} else {
    $output .= "<tr><td colspan='5'>" . _lang('global.nokit') . "</td></tr>";
}

$output .= "</tbody></table>";
