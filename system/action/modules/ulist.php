<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

if (!_logged_in && _notpublicsite) {
    $_index['is_accessible'] = false;
    return;
}

if (!_ulist) {
    $_index['is_found'] = false;
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
  <form action="' . \Sunlight\Router::module('ulist') . '" method="get">
  ' . (!_pretty_urls ? \Sunlight\Util\Form::renderHiddenInputs(\Sunlight\Util\Arr::filterKeys($_GET, null, null, array('group_id'))) : '') . '
  <strong>' . _lang('user.list.groupfilter') . ':</strong> <select name="group_id">
  <option value="-1">' . _lang('global.all') . '</option>
  ';
$query = DB::query("SELECT id,title FROM " . _groups_table . " WHERE id!=2 ORDER BY level DESC");
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
$paging = \Sunlight\Paginator::render(\Sunlight\Router::module('ulist', 'group=' . $group, false), 50, _users_table . ':u', $cond);
if (\Sunlight\Paginator::atTop()) {
    $output .= $paging['paging'];
}
if ($paging['count'] > 0) {
    $userQuery = \Sunlight\User::createQuery(null);
    $query = DB::query('SELECT ' . $userQuery['column_list'] . ' FROM ' . _users_table . ' u ' . $userQuery['joins'] . ' WHERE ' . $cond . ' ORDER BY ug.level DESC ' . $paging['sql_limit']);

    $output .= "<table class='widetable'>\n<tr><th>" . _lang('login.username') . "</th><th>" . _lang('global.group') . "</th></tr>\n";
    while ($item = DB::row($query)) {
        $output .= "<tr>
    <td>" . \Sunlight\Router::userFromQuery($userQuery, $item) . "</td>
    <td>" . $item['user_group_title'] . "</td>
</tr>";
    }
    $output .= "</table>";
}

if (\Sunlight\Paginator::atBottom()) {
    $output .= $paging['paging'];
}

// celkovy pocet uzivatelu
$output .= "<p>" . _lang('user.list.total') . ": " . $paging['count'] . "</p>";
