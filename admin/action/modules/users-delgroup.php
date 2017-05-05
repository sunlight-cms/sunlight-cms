<?php

if (!defined('_root')) {
    exit;
}

/* ---  priprava promennych  --- */

$levelconflict = false;
$sysgroups_array = array(_group_admin, _group_guests, _group_registered);

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = (int) _get('id');
    $systemgroup = in_array($id, $sysgroups_array);
    $query = DB::query("SELECT id,title,level FROM " . _groups_table . " WHERE id=" . $id);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (_priv_level > $query['level']) {
            $continue = true;
        } else {
            $levelconflict = true;
        }
    }
}

if ($continue) {

    /* --- spocitani uzivatelu --- */
    $user_count = DB::count(_users_table, 'group_id=' . $id);

    /* ---  odstraneni  --- */
    $done = false;
    if (isset($_POST['doit'])) {

        // smazani skupiny
        if (!$systemgroup) {
            DB::query("DELETE FROM " . _groups_table . " WHERE id=" . $id);
        }

        // zmena vychozi skupiny
        if (!$systemgroup && $id == _defaultgroup) {
            DB::query("UPDATE " . _settings_table . " SET val='3' WHERE var='defaultgroup'");
        }

        // smazani uzivatelu
        $users = DB::query("SELECT id FROM " . _users_table . " WHERE group_id=" . $id . " AND id!=0");
        while ($user = DB::row($users)) {
            _deleteUser($user['id']);
        }

        $done = true;

    }

    /* ---  vystup  --- */
    if (!$done) {
        if ($systemgroup) {
            $output .= _msg(_msg_ok, $_lang['admin.users.groups.specialgroup.delnotice']);
        }
        if ($user_count > 0) {
            $output .= _msg(_msg_warn, str_replace('%user_count%', $user_count, $_lang['admin.users.groups.delwarning']));
        }

        $output .= "
    <form class='cform' method='post'>
    <input type='hidden' name='doit' value='1'>

    <p>" . str_replace('%group%', $query['title'], $_lang['admin.users.groups.delconfirm']) . "</p>
    <input type='submit' value='" . $_lang['global.confirmdelete'] . "'>
    " . _xsrfProtect() . "</form>
    ";
    } else {
        $output .= _msg(_msg_ok, $_lang['global.done']);
    }

} else {
    if ($levelconflict == false) {
        $output .= _msg(_msg_err, $_lang['global.badinput']);
    } else {
        $output .= _msg(_msg_err, $_lang['global.disallowed']);
    }
}
