<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Math;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// action
$sysgroups_array = [User::ADMIN_GROUP_ID, User::GUEST_GROUP_ID, User::REGISTERED_GROUP_ID];
$msg = 0;

// create a group
if (isset($_POST['type']) && User::hasPrivilege('admingroups')) {
    $type = (int) Request::post('type');

    if ($type == -1) {
        // empty group
        DB::insert('user_group', [
            'title' => _lang('admin.users.groups.new.empty'),
            'level' => 0,
            'icon' => ''
        ]);
        Logger::notice('user', 'Created new empty user group via admin module', ['group_id' => DB::insertID()]);
        $msg = 1;
    } else {
        // copy existing group
        $source_group = DB::queryRow('SELECT * FROM ' . DB::table('user_group') . ' WHERE id=' . $type);

        if ($source_group !== false) {
            $new_group = [];
            $privilege_map = User::getPrivilegeMap();

            // collect data
            foreach ($source_group as $column => $val) {
                switch ($column) {
                    case 'id':
                        continue 2;

                    case 'level':
                        $val = Math::range($val, 0, min(User::getLevel() - 1, User::MAX_ASSIGNABLE_LEVEL));
                        break;
                        
                    case 'title':
                        $val = _lang('global.copy') . ' - ' . $val;
                        break;

                    default:
                        if (isset($privilege_map[$column]) && !User::hasPrivilege($column)) {
                            $val = 0;
                        }
                        break;
                }

                $new_group[$column] = $val;
            }

            // insert
            DB::insert('user_group', $new_group);
            Logger::notice(
                'user',
                sprintf('Copied existing user group "%s"', $source_group['title']),
                ['source_group_id' => $source_group['id'], 'group_id' => DB::insertID()]
            );
            $msg = 1;
        } else {
            $msg = 4;
        }
    }
}

// user switch
if (User::isSuperAdmin() && isset($_POST['switch_user'])) {
    $user = trim(Request::post('switch_user', ''));
    $query = DB::queryRow('SELECT id,password,email FROM ' . DB::table('user') . ' WHERE username=' . DB::val($user));

    if ($query !== false) {
        User::login($query['id'], $query['password'], $query['email'], false, false);
        $_admin->redirect(Router::module('login', ['absolute' => true]));

        return;
    }

    $msg = 5;
}

// group list
if (User::hasPrivilege('admingroups')) {
    $group_table = '<table class="list list-hover list-max">
<thead><tr><td>' . _lang('global.name') . '</td><td>' . _lang('admin.users.groups.level') . '</td><td>' . _lang('admin.users.groups.members') . '</td><td>' . _lang('global.action') . '</td></tr></thead>
<tbody>';
    $groups = DB::queryRows(
        'SELECT id,title,icon,color,blocked,level,reglist,(SELECT COUNT(*) FROM ' . DB::table('user') . ' WHERE group_id=' . DB::table('user_group') . '.id) AS user_count'
        . ' FROM ' . DB::table('user_group')
        . ' ORDER BY level DESC'
    );
    Extend::call('admin.users.groups', ['groups' => &$groups]);

    foreach ($groups as $group) {
        $is_sys = in_array($group['id'], $sysgroups_array);
        $group_table .= '
    <tr>
    <td>
        <span class="' . ($is_sys ? 'em' : '') . (($group['blocked'] == 1) ? ' strike' : '') . '"' . (($group['color'] !== '') ? ' style="color:' . $group['color'] . ';"' : '') . '>'
            . (($group['reglist'] == 1) ? '<img src="' . _e(Router::path('admin/public/images/icons/action.png')) . '" alt="reglist" class="icon" title="' . _lang('admin.users.groups.reglist') . '">' : '')
            . (($group['icon'] != '') ? '<img src="' . _e(Router::path('images/groupicons/' . $group['icon'])) . '" alt="icon" class="icon"> ' : '')
            . $group['title']
        . '</span>
    </td>
    <td>' . $group['level'] . '</td>
    <td>' . (($group['id'] != User::GUEST_GROUP_ID)
        ? '<a href="' . _e(Router::admin('users-list', ['query' => ['group_id' => $group['id']]])) . '">
            <img src="' . _e(Router::path('admin/public/images/icons/list.png')) . '" alt="list" class="icon">'
            . _num($group['user_count'])
            . '</a>'
            : '-')
    . '</td>
    <td class="actions">
        <a class="button" href="' . _e(Router::admin('users-editgroup', ['query' => ['id' => $group['id']]])) . '"><img src="' . _e(Router::path('admin/public/images/icons/edit.png')) . '" alt="edit" class="icon">' . _lang('global.edit') . '</a>
        <a class="button" href="' . _e(Router::admin('users-delgroup', ['query' => ['id' => $group['id']]])) . '"><img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">' . _lang('global.delete') . "</a>
    </td>
    </tr>\n";
    }

    $group_table .= "</tbody>\n</table>";
} else {
    $group_table = '';
}

// message
switch ($msg) {
    case 1:
        $message = Message::ok(_lang('global.done'));
        break;
    case 2:
        $message = Message::warning(_lang('admin.users.groups.specialgroup.delnotice'));
        break;
    case 3:
        $message = Message::error(_lang('global.disallowed'));
        break;
    case 4:
        $message = Message::error(_lang('global.badgroup'));
        break;
    case 5:
        $message = Message::warning(_lang('global.baduser'));
        break;
    default:
        $message = '';
        break;
}

$modules = [
    'users-edit' => [
        'url' => Router::admin('users-edit'),
        'icon' => Router::path('admin/public/images/icons/big-new.png'),
        'label' => _lang('global.create')
    ],
    'users-list' => [
        'url' => Router::admin('users-list'),
        'icon' => Router::path('admin/public/images/icons/big-list.png'),
        'label' => _lang('admin.users.list')
    ],
    'users-move' => [
        'url' => Router::admin('users-move'),
        'icon' => Router::path('admin/public/images/icons/big-move.png'),
        'label' => _lang('admin.users.move')
    ],
];

Extend::call('admin.users.modules', ['modules' => &$modules]);

$module_links = '';

foreach ($modules as $module) {
    $module_links .= '<a class="button block" href="' . _e($module['url']) . '"><img src="' . _e($module['icon']) . '" alt="new" class="icon">' . _e($module['label']) . "</a>\n";
}

// output
$output .= $message . '

<table class="two-columns">
<tr class="valign-top">

    ' . (User::hasPrivilege('adminusers') ? '
    <td>
    <h2>' . _lang('admin.users.users') . '</h2>
    <p>' . $module_links . '</p>

    <h2>' . _lang('global.action') . '</h2>

    <form class="cform" action="' . _e(Router::admin(null)) . '" method="get" name="edituserform">
    ' . Form::input('hidden', 'p', 'users-edit') . '
    <h3>' . _lang('admin.users.edituser') . '</h3>
    ' . Form::input('text', 'id', null, ['class' => 'inputsmall']) . '
    ' . Form::input('submit', null, _lang('global.do'), ['class' => 'button']) . '
    </form>

    <form class="cform" action="' . _e(Router::admin(null)) . '" method="get" name="deleteuserform">
    ' . Form::input('hidden', 'p', 'users-delete') . '
    ' . Xsrf::getInput() . '
    <h3>' . _lang('admin.users.deleteuser') . '</h3>
    ' . Form::input('text', 'id', null, ['class' => 'inputsmall']) . '
    ' . Form::input('submit', null, _lang('global.do'), ['class' => 'button']) . '
    </form>
    ' . Extend::buffer('admin.users.actions.after') . '
    
    ' . (User::isSuperAdmin() ? '

    <form action="' . _e(Router::admin('users')) . '" method="post">
    <h3>' . _lang('admin.users.switchuser') . '</h3>
    ' . Form::input('text', 'switch_user', null, ['class' => 'inputsmall']) . '
    ' . Form::input('submit', null, _lang('global.do'), ['class' => 'button']) . '
    ' . Xsrf::getInput() . '</form>
    ' : '') . '

  </td>
    ' : '') . '

    ' . (User::hasPrivilege('admingroups') ? '<td>
    <h2>' . _lang('admin.users.groups') . '</h2>
    <form action="' . _e(Router::admin('users')) . '" method="post">
        <p class="bborder"><strong>' . _lang('admin.users.groups.new') . ':</strong> '
        . Admin::userSelect('type', ['extra_option' => _lang('admin.users.groups.new.empty'), 'select_groups' => true])
        . Form::input('submit', null, _lang('global.do'), ['class' => 'button']) . '
        </p>'
        . Xsrf::getInput() . '</form>
    ' . $group_table
        . Extend::buffer('admin.users.groups.after') . '
    </td>' : '') . '
</tr>
</table>
';
