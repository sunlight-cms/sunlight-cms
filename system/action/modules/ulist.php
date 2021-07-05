<?php

use Sunlight\Database\Database as DB;
use Sunlight\Paginator;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
    $_index['type'] = _index_unauthorized;
    return;
}

if (!Settings::get('ulist')) {
    $_index['type'] = _index_not_found;
    return;
}

$_index['title'] = _lang('user.list.title');

$output .= "<p class='bborder'>" . _lang('mod.ulist.p') . "</p>";

// filtry
$cond = 'u.public=1';

if (isset($_REQUEST['group_id'])) {
    $group = (int) $_REQUEST['group_id'];
    if ($group != -1) {
        $cond .= ' AND u.group_id=' . $group;
    }
} else {
    $group = -1;
}

// vyber skupiny
$output .= '
  <form action="' . _e(Router::module('ulist')) . '" method="get">
  ' . (!Settings::get('pretty_urls') ? Form::renderHiddenInputs(Arr::filterKeys($_GET, null, null, ['group_id'])) : '') . '
  <strong>' . _lang('user.list.groupfilter') . ':</strong> <select name="group_id">
  <option value="-1">' . _lang('global.all') . '</option>
  ';
$query = DB::query("SELECT id,title FROM " . DB::table('user_group') . " WHERE id!=2 ORDER BY level DESC");
while ($item = DB::row($query)) {
    if ($item['id'] == $group) {
        $selected = ' selected';
    } else {
        $selected = "";
    }
    $output .= '<option value="' . $item['id'] . '"' . $selected . '>' . $item['title'] . '</option>';
}
$output .= '</select> <input type="submit" value="' . _lang('global.apply') . '"></form>';

// tabulka
$paging = Paginator::render(Router::module('ulist', 'group=' . $group, false), 50, DB::table('user') . ':u', $cond);
if (Paginator::atTop()) {
    $output .= $paging['paging'];
}
if ($paging['count'] > 0) {
    $userQuery = User::createQuery();
    $query = DB::query('SELECT ' . $userQuery['column_list'] . ' FROM ' . DB::table('user') . ' u ' . $userQuery['joins'] . ' WHERE ' . $cond . ' ORDER BY ug.level DESC ' . $paging['sql_limit']);

    $output .= "<table class='widetable'>\n<tr><th>" . _lang('login.username') . "</th><th>" . _lang('global.group') . "</th></tr>\n";
    while ($item = DB::row($query)) {
        $output .= "<tr>
    <td>" . Router::userFromQuery($userQuery, $item) . "</td>
    <td>" . $item['user_group_title'] . "</td>
</tr>";
    }
    $output .= "</table>";
}

if (Paginator::atBottom()) {
    $output .= $paging['paging'];
}

// celkovy pocet uzivatelu
$output .= "<p>" . _lang('user.list.total') . ": " . $paging['count'] . "</p>";
