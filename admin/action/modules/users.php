<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Math;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  akce  --- */

$sysgroups_array = [_group_admin, _group_guests, _group_registered];
$msg = 0;

// vytvoreni skupiny
if (isset($_POST['type']) && _priv_admingroups) {
    $type = (int) Request::post('type');
    if ($type == -1) {
        // prazdna skupina
        DB::insert(_user_group_table, [
            'title' => _lang('admin.users.groups.new.empty'),
            'level' => 0,
            'icon' => ''
        ]);
        $msg = 1;
    } else {
        // kopirovat skupinu
        $source_group = DB::queryRow("SELECT * FROM " . _user_group_table . " WHERE id=" . $type);
        if ($source_group !== false) {
            $new_group = [];
            $privilege_map = array_flip(User::listPrivileges());

            // sesbirani dat
            foreach ($source_group as $column => $val) {
                switch ($column) {
                    case "id":
                        continue 2;

                    case "level":
                        $val = Math::range($val, 0, min(_priv_level - 1, _priv_max_assignable_level));
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
            DB::insert(_user_group_table, $new_group);
            $msg = 1;

        } else {
            $msg = 4;
        }
    }
}

// prepnuti uzivatele
if (_super_admin_id == _user_id && isset($_POST['switch_user'])) {
    $user = trim(Request::post('switch_user'));
    $query = DB::queryRow("SELECT id,password,email FROM " . _user_table . " WHERE username=" . DB::val($user));
    if ($query !== false) {

        User::login($query['id'], $query['password'], $query['email']);

        $admin_redirect_to = Router::module('login', null, true);

        return;
    } else {
        $msg = 5;
    }
}

/* ---  priprava promennych  --- */

// vypis skupin
if (_priv_admingroups) {
    $groups = "<table class='list list-hover list-max'>
<thead><tr><td>" . _lang('global.name') . "</td><td>" . _lang('admin.users.groups.level') . "</td><td>" . _lang('admin.users.groups.members') . "</td><td>" . _lang('global.action') . "</td></tr></thead>
<tbody>";
    $query = DB::query("SELECT id,title,icon,color,blocked,level,reglist,(SELECT COUNT(*) FROM " . _user_table . " WHERE group_id=" . _user_group_table . ".id) AS user_count FROM " . _user_group_table . " ORDER BY level DESC");
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
    <td>" . (($item['id'] != _group_guests) ? "<a href='index.php?p=users-list&amp;group_id=" . $item['id'] . "'><img src='images/icons/list.png' alt='list' class='icon'>" . $item['user_count'] . "</a>" : "-") . "</td>
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

    " . (_priv_adminusers ? "
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

    " . ((_super_admin_id == _user_id) ? "

    <form action='index.php?p=users' method='post'>
    <h3>" . _lang('admin.users.switchuser') . "</h3>
    <input type='text' name='switch_user' class='inputsmall'>
    <input class='button' type='submit' value='" . _lang('global.do') . "'>
    " . Xsrf::getInput() . "</form>
    " : '') . "

  </td>
    " : '') . "

    " . (_priv_admingroups ? "<td>
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
