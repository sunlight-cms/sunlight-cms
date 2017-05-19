<?php

if (!defined('_root')) {
    exit;
}

/* ---  akce  --- */

$sysgroups_array = array(_group_admin, _group_guests, _group_registered);
$msg = 0;

// vytvoreni skupiny
if (isset($_POST['type']) && _priv_admingroups) {
    $type = (int) _post('type');
    if ($type == -1) {
        // prazdna skupina
        DB::insert(_groups_table, array(
            'title' => $_lang['admin.users.groups.new.empty'],
            'level' => 0,
            'icon' => ''
        ));
        $msg = 1;
    } else {
        // kopirovat skupinu
        $source_group = DB::queryRow("SELECT * FROM " . _groups_table . " WHERE id=" . $type);
        if ($source_group !== false) {
            $new_group = array();
            $privilege_map = array_flip(_getPrivileges());

            // sesbirani dat
            foreach ($source_group as $column => $val) {
                switch ($column) {
                    case "id":
                        continue 2;

                    case "level":
                        if ($val >= 10000) {
                            $val = 9999;
                        }
                        if ($val >= _priv_level) {
                            $val = _priv_level - 1;
                        }
                        break;
                        
                    case "title":
                        $val = $_lang['global.copy'] . " - " . $val;
                        break;

                    default:
                        if (isset($privilege_map[$column]) && !_userHasRight($column)) {
                            $val = 0;
                        }
                        break;
                }

                $new_group[$column] = $val;
            }

            // sql dotaz
            DB::insert(_groups_table, $new_group);
            $msg = 1;

        } else {
            $msg = 4;
        }
    }
}

// prepnuti uzivatele
if (_super_admin_id == _loginid && isset($_POST['switch_user'])) {
    $user = trim(_post('switch_user'));
    $query = DB::queryRow("SELECT id,password,email FROM " . _users_table . " WHERE username=" . DB::val($user));
    if ($query !== false) {

        _userLogin($query['id'], $query['password'], $query['email']);

        $admin_redirect_to = _linkModule('login');

        return;
    } else {
        $msg = 5;
    }
}

/* ---  priprava promennych  --- */

// vypis skupin
if (_priv_admingroups) {
    $groups = "<table class='list list-hover list-max'>
<thead><tr><td>" . $_lang['global.name'] . "</td><td>" . $_lang['admin.users.groups.level'] . "</td><td>" . $_lang['admin.users.groups.members'] . "</td><td>" . $_lang['global.action'] . "</td></tr></thead>
<tbody>";
    $query = DB::query("SELECT id,title,icon,color,blocked,level,reglist,(SELECT COUNT(*) FROM " . _users_table . " WHERE group_id=" . _groups_table . ".id) AS user_count FROM " . _groups_table . " ORDER BY level DESC");
    while ($item = DB::row($query)) {
        $is_sys = in_array($item['id'], $sysgroups_array);
        $groups .= "
    <tr>
    <td>
        <span class='" . ($is_sys ? 'em' : '') . (($item['blocked'] == 1) ? ' strike' : '') . "'" . (($item['color'] !== '') ? " style='color:" . $item['color'] . ";'" : '') . ">"
            . (($item['reglist'] == 1) ? "<img src='images/icons/action.png' alt='reglist' class='icon' title='" . $_lang['admin.users.groups.reglist'] . "'>" : '')
            . (($item['icon'] != "") ? "<img src='" . _link('images/groupicons/' . $item['icon']) . "' alt='icon' class='groupicon'> " : '')
            . $item['title']
        . "</span>
    </td>
    <td>" . $item['level'] . "</td>
    <td>" . (($item['id'] != _group_guests) ? "<a href='index.php?p=users-list&amp;group_id=" . $item['id'] . "'><img src='images/icons/list.png' alt='list' class='icon'>" . $item['user_count'] . "</a>" : "-") . "</td>
    <td class='actions'>
        <a class='button' href='index.php?p=users-editgroup&amp;id=" . $item['id'] . "'><img src='images/icons/edit.png' alt='edit' class='icon'>" . $_lang['global.edit'] . "</a>
        <a class='button' href='index.php?p=users-delgroup&amp;id=" . $item['id'] . "'><img src='images/icons/delete.png' alt='del' class='icon'>" . $_lang['global.delete'] . "</a>
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
        $message = _msg(_msg_ok, $_lang['global.done']);
        break;
    case 2:
        $message = _msg(_msg_warn, $_lang['admin.users.groups.specialgroup.delnotice']);
        break;
    case 3:
        $message = _msg(_msg_err, $_lang['global.disallowed']);
        break;
    case 4:
        $message = _msg(_msg_err, $_lang['global.badgroup']);
        break;
    case 5:
        $message = _msg(_msg_warn, $_lang['global.baduser']);
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
    <h2>" . $_lang['admin.users.users'] . "</h2>
    <p>
      <a class='button block' href='index.php?p=users-edit'><img src='images/icons/big-new.png' alt='new' class='icon'>" . $_lang['global.create'] . "</a>
      <a class='button block' href='index.php?p=users-list'><img src='images/icons/big-list.png' alt='act' class='icon'>" . $_lang['admin.users.list'] . "</a>
      <a class='button block' href='index.php?p=users-move'><img src='images/icons/big-move.png' alt='act' class='icon'>" . $_lang['admin.users.move'] . "</a>
    </p>

    <h2>" . $_lang['global.action'] . "</h2>

    <form class='cform' action='index.php' method='get' name='edituserform'>
    <input type='hidden' name='p' value='users-edit'>
    <h3>" . $_lang['admin.users.edituser'] . "</h3>
    <input type='text' name='id' class='inputsmall'>
    <input class='button' type='submit' value='" . $_lang['global.do'] . "'>
    </form>

    <form class='cform' action='index.php' method='get' name='deleteuserform'>
    <input type='hidden' name='p' value='users-delete'>
    " . _xsrfProtect() . "
    <h3>" . $_lang['admin.users.deleteuser'] . "</h3>
    <input type='text' name='id' class='inputsmall'>
    <input class='button' type='submit' value='" . $_lang['global.do'] . "'>
    </form>

    " . ((_super_admin_id == _loginid) ? "

    <form action='index.php?p=users' method='post'>
    <h3>" . $_lang['admin.users.switchuser'] . "</h3>
    <input type='text' name='switch_user' class='inputsmall'>
    <input class='button' type='submit' value='" . $_lang['global.do'] . "'>
    " . _xsrfProtect() . "</form>
    " : '') . "

  </td>
    " : '') . "

    " . (_priv_admingroups ? "<td>
    <h2>" . $_lang['admin.users.groups'] . "</h2>
    <form action='index.php?p=users' method='post'>
        <p class='bborder'><strong>" . $_lang['admin.users.groups.new'] . ":</strong> "
        . _adminUserSelect("type", -1, "1", null, $_lang['admin.users.groups.new.empty'], true)
        . " <input class='button' type='submit' value='" . $_lang['global.do'] . "'>
        </p>"
        . _xsrfProtect() . "</form>
    " . $groups . "
    </td>" : '') . "
</tr>
</table>
";
