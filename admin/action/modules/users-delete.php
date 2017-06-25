<?php

if (!defined('_root')) {
    exit;
}

/* ---  priprava promennych  --- */

$levelconflict = false;

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = _get('id');
    $query = DB::queryRow("SELECT u.id,u.username,g.level group_level FROM " . _users_table . " u JOIN " . _groups_table . " g ON(u.group_id=g.id) WHERE u.username=" . DB::val($id));
    if ($query !== false) {
        if (_levelCheck($query['id'], $query['group_level'])) {
            $continue = true;
        } else {
            $continue = false;
            $levelconflict = true;
        }
        $id = $query['id'];
    }
}

if ($continue) {

    /* ---  odstraneni  --- */
    if ($query['id'] != 0 && $query['id'] != _loginid) {
        if (isset($_POST['confirmed'])) {
            if (_deleteUser($id)) {
                $output .= _msg(_msg_ok, _lang('global.done'));
            } else {
                $output .= _msg(_msg_warn, _lang('global.error'));
            }
        } else {
            $output .= "
<p class='bborder'>" . _lang('admin.users.deleteuser.confirmation', array('%user%' => $query['username'])) . "
<form method='post'>
    <input type='submit' name='confirmed' value='" . _lang('admin.users.deleteuser') . "'>
" . _xsrfProtect() . "</form>";
        }
    } else {
        if ($query['id'] == _super_admin_id) {
            $output .= _msg(_msg_warn, _lang('global.rootnote'));
        } else {
            $output .= _msg(_msg_warn, _lang('admin.users.deleteuser.selfnote'));
        }
    }

} else {
    if ($levelconflict == false) {
        $output .= _msg(_msg_err, _lang('global.baduser'));
    } else {
        $output .= _msg(_msg_err, _lang('global.disallowed'));
    }
}
