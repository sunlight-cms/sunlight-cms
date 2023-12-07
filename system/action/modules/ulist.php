<?php

use Sunlight\Database\Database as DB;
use Sunlight\Paginator;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
    $_index->unauthorized();
    return;
}

if (!Settings::get('ulist')) {
    $_index->notFound();
    return;
}

$_index->title = _lang('user.list.title');

$output .= '<p class="bborder">' . _lang('mod.ulist.p') . '</p>';

// filters
$cond = 'u.public=1';

if (isset($_REQUEST['group_id'])) {
    $group = (int) $_REQUEST['group_id'];

    if ($group != -1) {
        $cond .= ' AND u.group_id=' . $group;
    }
} else {
    $group = -1;
}

$group_choices = [-1 => _lang('global.all')]
    + DB::queryRows('SELECT id,title FROM ' . DB::table('user_group') . ' WHERE id!=' . User::GUEST_GROUP_ID . ' ORDER BY level DESC', 'id', 'title');

// group select
$output .= '
  <form action="' . _e(Router::module('ulist')) . '" method="get">
    <strong>' . _lang('user.list.groupfilter') . ':</strong>
    ' . Form::select('group_id', $group_choices, $group, [], false) . '
  ';

$output .= '
    ' . Form::input('submit', null, _lang('global.apply')) . '
</form>';

// table
$paging = Paginator::paginateTable(
    Router::module('ulist', ['query' => ['group' => $group]]),
    50,
    DB::table('user'),
    ['alias' => 'u', 'cond' => $cond]
);

if (Paginator::atTop()) {
    $output .= $paging['paging'];
}

if ($paging['count'] > 0) {
    $userQuery = User::createQuery();
    $query = DB::query('SELECT ' . $userQuery['column_list'] . ' FROM ' . DB::table('user') . ' u ' . $userQuery['joins'] . ' WHERE ' . $cond . ' ORDER BY ug.level DESC ' . $paging['sql_limit']);

    $output .= "<table class=\"widetable\">\n<tr><th>" . _lang('login.username') . '</th><th>' . _lang('global.group') . "</th></tr>\n";

    while ($item = DB::row($query)) {
        $output .= '<tr>
    <td>' . Router::userFromQuery($userQuery, $item) . '</td>
    <td>' . $item['user_group_title'] . '</td>
</tr>';
    }

    $output .= '</table>';
}

if (Paginator::atBottom()) {
    $output .= $paging['paging'];
}

// total number of users
$output .= '<p>' . _lang('user.list.total') . ': ' . _num($paging['count']) . '</p>';
