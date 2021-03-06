<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Math;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

/* ---  akce  --- */

$sysgroups_array = [User::ADMIN_GROUP_ID, User::GUEST_GROUP_ID, User::REGISTERED_GROUP_ID];
$msg = 0;

// vytvoreni skupiny
if (isset($_POST['type']) && User::hasPrivilege('admingroups')) {
    $type = (int) Request::post('type');
    if ($type == -1) {
        // prazdna skupina
        DB::insert('user_group', [
            'title' => _lang('admin.users.groups.new.empty'),
            'level' => 0,
            'icon' => ''
        ]);
        $msg = 1;
    } else {
        // kopirovat skupinu
        $source_group = DB::queryRow("SELECT * FROM " . DB::table('user_group') . " WHERE id=" . $type);
        if ($source_group !== false) {
            $new_group = [];
            $privilege_map = User::getPrivilegeMap();

            // sesbirani dat
            foreach ($source_group as $column => $val) {
                switch ($column) {
                    case "id":
                        continue 2;

                    case "level":
                        $val = Math::range($val, 0, min(User::getLevel() - 1, User::MAX_ASSIGNABLE_LEVEL));
                        break;
                        
                    case "title":
                        $val = _lang('global.copy') . " - " . $val;
                        break;

                    default:
                        if (isset($privilege_map[$column]) && !User::hasPrivilege($column)) {
                            $val = 0;
                        }
                        break;
                }

                $new_group[$column] = $val;
            }

            // sql dotaz
            DB::insert('user_group', $new_group);
            $msg = 1;

        } else {
            $msg = 4;
        }
    }
}

// prepnuti uzivatele
if (User::SUPER_ADMIN_ID == User::getId() && isset($_POST['switch_user'])) {
    $user = trim(Request::post('switch_user'));
    $query = DB::queryRow("SELECT id,password,email FROM " . DB::table('user') . " WHERE username=" . DB::val($user));

    if ($query !== false) {
        User::login($query['id'], $query['password'], $query['email']);
        $_admin->redirect(Router::module('login', null, true));

        return;
    }

    $msg = 5;
}

/* ---  priprava promennych  --- */

// vypis skupin
if (User::hasPrivilege('admingroups')) {
    $groups = "<table class='list list-hover list-max'>
<thead><tr><td>" . _lang('global.name') . "</td><td>" . _lang('admin.users.groups.level') . "</td><td>" . _lang('admin.users.groups.members') . "</td><td>" . _lang('global.action') . "</td></tr></thead>
<tbody>";
    $query = DB::query("SELECT id,title,icon,color,blocked,level,reglist,(SELECT COUNT(*) FROM " . DB::table('user') . " WHERE group_id=" . DB::table('user_group') . ".id) AS user_count FROM " . DB::table('user_group') . " ORDER BY level DESC");
    while ($item = DB::row($query)) {
        $is_sys = in_array($item['id'], $sysgroups_array);
        $groups .= "
    <tr>
    <td>
        <span class='" . ($is_sys ? 'em' : '') . (($item['blocked'] == 1) ? ' strike' : '') . "'" . (($item['color'] !== '') ? " style='color:" . $item['color'] . ";'" : '') . ">"
            . (($item['reglist'] == 1) ? "<img src='images/icons/action.png' alt='reglist' class='icon' title='" . _lang('admin.users.groups.reglist') . "'>" : '')
            . (($item['icon'] != "") ? "<img src='" . Router::generate('images/groupicons/' . $item['icon']) . "' alt='icon' class='groupicon'> " : '')
            . $item['title']
        . "</span>
    </td>
    <td>" . $item['level'] . "</td>
    <td>" . (($item['id'] != User::GUEST_GROUP_ID) ? "<a href='index.php?p=users-list&amp;group_id=" . $item['id'] . "'><img src='images/icons/list.png' alt='list' class='icon'>" . $item['user_count'] . "</a>" : "-") . "</td>
    <td class='actions'>
        <a class='button' href='index.php?p=users-editgroup&amp;id=" . $item['id'] . "'><img src='images/icons/edit.png' alt='edit' class='icon'>" . _lang('global.edit') . "</a>
        <a class='button' href='index.php?p=users-delgroup&amp;id=" . $item['id'] . "'><img src='images/icons/delete.png' alt='del' class='icon'>" . _lang('global.delete') . "</a>
    </td>
    </tr>\n";
    }
    $groups .= "</tbody>\n</table>";
} else {
    $groups = "";
}

// zprava
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
        $message = "";
        break;
}

/* ---  vystup  --- */

$output .= $message . "

<table class='two-columns'>
<tr class='valign-top'>

    " . (User::hasPrivilege('adminusers') ? "
    <td>
    <h2>" . _lang('admin.users.users') . "</h2>
    <p>
      <a class='button block' href='index.php?p=users-edit'><img src='images/icons/big-new.png' alt='new' class='icon'>" . _lang('global.create') . "</a>
      <a class='button block' href='index.php?p=users-list'><img src='images/icons/big-list.png' alt='act' class='icon'>" . _lang('admin.users.list') . "</a>
      <a class='button block' href='index.php?p=users-move'><img src='images/icons/big-move.png' alt='act' class='icon'>" . _lang('admin.users.move') . "</a>
    </p>

    <h2>" . _lang('global.action') . "</h2>

    <form class='cform' action='index.php' method='get' name='edituserform'>
    <input type='hidden' name='p' value='users-edit'>
    <h3>" . _lang('admin.users.edituser') . "</h3>
    <input type='text' name='id' class='inputsmall'>
    <input class='button' type='submit' value='" . _lang('global.do') . "'>
    </form>

    <form class='cform' action='index.php' method='get' name='deleteuserform'>
    <input type='hidden' name='p' value='users-delete'>
    " . Xsrf::getInput() . "
    <h3>" . _lang('admin.users.deleteuser') . "</h3>
    <input type='text' name='id' class='inputsmall'>
    <input class='button' type='submit' value='" . _lang('global.do') . "'>
    </form>

    " . ((User::SUPER_ADMIN_ID == User::getId()) ? "

    <form action='index.php?p=users' method='post'>
    <h3>" . _lang('admin.users.switchuser') . "</h3>
    <input type='text' name='switch_user' class='inputsmall'>
    <input class='button' type='submit' value='" . _lang('global.do') . "'>
    " . Xsrf::getInput() . "</form>
    " : '') . "

  </td>
    " : '') . "

    " . (User::hasPrivilege('admingroups') ? "<td>
    <h2>" . _lang('admin.users.groups') . "</h2>
    <form action='index.php?p=users' method='post'>
        <p class='bborder'><strong>" . _lang('admin.users.groups.new') . ":</strong> "
        . Admin::userSelect("type", -1, "1", null, _lang('admin.users.groups.new.empty'), true)
        . " <input class='button' type='submit' value='" . _lang('global.do') . "'>
        </p>"
        . Xsrf::getInput() . "</form>
    " . $groups . "
    </td>" : '') . "
</tr>
</table>
";
