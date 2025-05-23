<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

$message = '';

// bulk actions
if (isset($_POST['bulk_action'])) {
    switch (Request::post('bulk_action')) {
        // delete
        case 'del':
            $user_ids = (array) Request::post('user', [], true);
            $user_delete_counter = 0;

            foreach ($user_ids as $user_id) {
                $user_id = (int) $user_id;

                if (!User::equals($user_id) && User::delete($user_id)) {
                    ++$user_delete_counter;
                }
            }

            $message = Message::render(
                $user_delete_counter === count($user_ids) ? Message::OK : Message::WARNING,
                _lang('admin.users.list.bulkdelete.msg', ['%done%' => _num($user_delete_counter), '%total%' => _num(count($user_ids))])
            );
            break;
    }
}

// output

// group filter
$grouplimit = '';
$list_conds = [];

if (isset($_GET['group_id'])) {
    $group = (int) Request::get('group_id');

    if ($group != -1) {
        $list_conds[] = 'u.group_id=' . $group;
    }
} else {
    $group = -1;
}

// search
$search = trim(Request::get('search', ''));

if ($search !== '') {
    $wildcard = DB::val('%' . $search . '%');
    $list_conds[] = '(u.id=' . DB::val($search) . " OR u.username LIKE {$wildcard} OR u.publicname LIKE {$wildcard} OR u.email LIKE {$wildcard} OR u.ip LIKE {$wildcard})";
} else {
    $search = false;
}

// prepare list conditions
$list_conds_sql = empty($list_conds) ? '1' : implode(' AND ', $list_conds);

// filters
$output .= '
<table class="two-columns">
<tr>

<td>
' . Form::start('users-list-groups', ['class' => 'cform', 'action' => Router::admin(null), 'method' => 'get']) . '
' . Form::input('hidden', 'p', 'users-list') . '
' . Form::input('hidden', 'search', Request::get('search', '')) . '
<strong>' . _lang('admin.users.list.groupfilter') . ':</strong><br class="mobile-only"> '
. Admin::userSelect('group_id', ['selected' => $group, 'extra_option' => _lang('global.all'), 'select_groups' => true])
. ' '
. Form::input('submit', null, _lang('global.apply'), ['class' => 'button']) . '
' . Form::end('users-list-groups') . '
</td>

<td>
' . Form::start('users-list-search', ['class' => 'cform', 'action' => Router::admin(null), 'method' => 'get']) . '
' . Form::input('hidden', 'p', 'users-list') . '
' . Form::input('hidden', 'group_id', $group) . '
<strong>' . _lang('admin.users.list.search') . ':</strong><br class="mobile-only"> ' . Form::input('text', 'search', Request::get('search'), ['class' => 'inputsmall']) . ' ' .  Form::input('submit', null, _lang('mod.search.submit'), ['class' => 'button']) . '
' . ($search ? ' <a href="' . _e(Router::admin('users-list', ['query' => ['group' => $group]])) . '">' . _lang('global.cancel') . '</a>' : '') . '
' . Form::end('users-list-search') . '
</td>

</tr>
</table>
';

// prepare paging
$query_params = ['group' => $group];

if ($search !== false) {
    $query_params['search'] = $search;
}

$paging = Paginator::paginateTable(
    Router::admin('users-list', ['query' => $query_params]),
    50,
    DB::table('user'),
    ['alias' => 'u', 'cond' => $list_conds_sql]
);
$output .= $paging['paging'];

// table
$output .= $message . '
' . Form::start('user-list') . '
<div class="horizontal-scroller">
<table id="user-list" class="list list-hover list-max">
<thead><tr>
    <td>' . Form::input('checkbox', null, null, ['onclick' => 'Sunlight.checkAll(event, this.checked, \'#user-list\')']) . '</td>
    <td>ID</td><td>' . _lang('login.username') . '</td>
    <td>' . _lang('global.email') . '</td>
    <td>' . _lang('mod.settings.account.publicname') . '</td>
    <td>' . _lang('global.group') . '</td>
    <td>' . _lang('global.action') . '</td>
</tr></thead>
<tbody>
';

// query
$userQuery = User::createQuery();
$query = DB::query('SELECT ' . $userQuery['column_list'] . ',u.email user_email FROM ' . DB::table('user') . ' u ' . $userQuery['joins'] . ' WHERE ' . $list_conds_sql . ' ORDER BY ug.level DESC ' . $paging['sql_limit']);

// list
if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {
        $output .= '<tr>
            <td>' . Form::input('checkbox', 'user[]', $item['user_id']) . '</td>
            <td>' . $item['user_id'] . '</td>
            <td>' . Router::userFromQuery($userQuery, $item, ['new_window' => true, 'publicname' => false]) . '</td>
            <td>' . $item['user_email'] . '</td><td>' . (($item['user_publicname'] != '') ? $item['user_publicname'] : '-') . '</td>
            <td>' . $item['user_group_title'] . '</td>
            <td class="actions">
                <a class="button" href="' . _e(Router::admin('users-edit', ['query' => ['id' => $item['user_username']]])) . '">
                    <img src="' . _e(Router::path('admin/public/images/icons/edit.png')) . '" alt="edit" class="icon">'
                    . _lang('global.edit')
                . '</a>
                <a class="button" href="' . _e(Router::admin('users-delete', ['query' => ['id' =>$item['user_username']]])) . '">
                    <img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">'
                    . _lang('global.delete')
                . "</a>
            </td>
        </tr>\n";
    }
} else {
    $output .= '<tr><td colspan="5">' . _lang('global.nokit') . "</td></tr>\n";
}

$output .= "</tbody></table></div>\n";

// user count
$totalusers = DB::count('user');
$output .= '<p class="right">' . _lang('admin.users.list.totalusers') . ': ' . _num($totalusers) . '</p>';

// bulk actions
$output .= '
    <p class="left">
        ' . _lang('global.bulk') . ':
        ' . Form::select('bulk_action', ['' => '', 'del' => _lang('global.delete')]) . '
        ' . Form::input('submit', null, _lang('global.do'), ['class' => 'button', 'onclick' => 'return Sunlight.confirm()']) . '
    </p>

' . Form::end('user-list');

// paging
$output .= $paging['paging'];
