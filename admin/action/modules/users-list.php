<?php

if (!defined('_root')) {
    exit;
}

$message = '';

/* --- hromadne akce --- */

if (isset($_POST['bulk_action'])) {
    switch (_post('bulk_action')) {
        // smazani
        case 'del':
            $user_ids = (array) _post('user', array(), true);
            $user_delete_counter = 0;
            foreach ($user_ids as $user_id) {
                $user_id = (int) $user_id;
                if (0 !== $user_id && _loginid != $user_id) {
                    if (_deleteUser($user_id)) {
                        ++$user_delete_counter;
                    }
                }
            }

            $message = _msg(
                $user_delete_counter === sizeof($user_ids) ? 1 : 2,
                str_replace(
                    array('%done%', '%total%'),
                    array($user_delete_counter, sizeof($user_ids)),
                    $_lang['admin.users.list.bulkdelete.msg']
                )
            );
            break;
    }
}

/* ---  vystup  --- */

// filtr skupiny
$grouplimit = "";
$list_conds = array();
if (isset($_GET['group_id'])) {
    $group = (int) _get('group_id');
    if ($group != -1) {
        $list_conds[] = 'u.group_id=' . $group;
    }
} else {
    $group = -1;
}

// aktivace vyhledavani
$search = trim(_get('search'));
if ('' !== $search) {
    $wildcard = DB::val('%' . $search . '%');
    $list_conds[] = "(u.id=" . DB::val($search) . " OR u.username LIKE {$wildcard} OR u.publicname LIKE {$wildcard} OR u.email LIKE {$wildcard} OR u.ip LIKE {$wildcard})";
} else {
    $search = false;
}

// priprava podminek vypisu
$list_conds_sql = empty($list_conds) ? '1' : implode(' AND ', $list_conds);

// filtry - vyber skupiny, vyhledavani
$output .= '
<table class="two-columns">
<tr>

<td>
<form class="cform" action="index.php" method="get">
<input type="hidden" name="p" value="users-list">
<input type="hidden" name="search"' . _restoreGetValue('search', '') . '>
<strong>' . $_lang['admin.users.list.groupfilter'] . ':</strong> ' . _adminUserSelect("group_id", $group, "id!=2", null, $_lang['global.all'], true) . '
<input class="button" type="submit" value="' . $_lang['global.apply'] . '">
</form>
</td>

<td>
<form class="cform" action="index.php" method="get">
<input type="hidden" name="p" value="users-list">
<input type="hidden" name="group_id" value="' . $group . '">
<strong>' . $_lang['admin.users.list.search'] . ':</strong> <input type="text" name="search" class="inputsmall"' . _restoreGetValue('search') . '> <input class="button" type="submit" value="' . $_lang['mod.search.submit'] . '">
' . ($search ? ' <a href="index.php?p=users-list&amp;group=' . $group . '">' . $_lang['global.cancel'] . '</a>' : '') . '
</form>
</td>

</tr>
</table>
';

// priprava strankovani
$paging = _resultPaging("index.php?p=users-list&group=" . $group . (false !== $search ? '&search=' . rawurlencode($search) : ''), 50, _users_table . ':u', $list_conds_sql);
$output .= $paging['paging'];

// tabulka
$output .= $message . "
<form method='post'>
<table id='user-list' class='list list-hover list-max'>
<thead><tr>
    <td><input type='checkbox' onclick='Sunlight.checkAll(event, this.checked, \"#user-list\")'></td>
    <td>ID</td><td>" . $_lang['login.username'] . "</td>
    <td>" . $_lang['global.email'] . "</td>
    <td>" . $_lang['mod.settings.publicname'] . "</td>
    <td>" . $_lang['global.group'] . "</td>
    <td>" . $_lang['global.action'] . "</td>
</tr></thead>
<tbody>
";

// dotaz na db
$userQuery = _userQuery(null);
$query = DB::query('SELECT ' . $userQuery['column_list'] . ',u.email user_email FROM ' . _users_table . ' u ' . $userQuery['joins'] . ' WHERE ' . $list_conds_sql . ' ORDER BY ug.level DESC ' . $paging['sql_limit']);

// vypis
if (DB::size($query) != 0) {
    while ($item = DB::row($query)) {
        $output .= "<tr>
            <td><input type='checkbox' name='user[]' value='" . $item['user_id'] . "'></td>
            <td>" . $item['user_id'] . "</td>
            <td>" . _linkUserFromQuery($userQuery, $item, array('new_window' => true, 'publicname' => false)) . "</td>
            <td>" . $item['user_email'] . "</td><td>" . (($item['user_publicname'] != '') ? $item['user_publicname'] : "-") . "</td>
            <td>" . $item['user_group_title'] . "</td>
            <td class='actions'>
                <a class='button' href='index.php?p=users-edit&amp;id=" . $item['user_username'] . "'><img src='images/icons/edit.png' alt='edit' class='icon'>" . $_lang['global.edit'] . "</a>
                <a class='button' href='index.php?p=users-delete&amp;id=" . $item['user_username'] . "'><img src='images/icons/delete.png' alt='del' class='icon'>" . $_lang['global.delete'] . "</a>
            </td>
        </tr>\n";
    }
} else {
    $output .= "<tr><td colspan='5'>" . $_lang['global.nokit'] . "</td></tr>\n";
}

$output .= "</tbody></table>\n";

// pocet uzivatelu
$totalusers = DB::result(DB::query("SELECT COUNT(*) FROM " . _users_table), 0);
$output .= '<p class="right">' . $_lang['admin.users.list.totalusers'] . ": " . $totalusers . '</p>';

// hromadna akce
$output .= "
    <p class='left'>
        " . $_lang['global.bulk'] . ":
        <select name='bulk_action'>
            <option value=''></option>
            <option value='del'>" . $_lang['global.delete'] . "</option>
        </select>
        <input class='button' type='submit' onclick='return Sunlight.confirm()' value='" . $_lang['global.do'] . "'>
    </p>

" . _xsrfProtect() . "</form>";

// strankovani
$output .= $paging['paging'];
