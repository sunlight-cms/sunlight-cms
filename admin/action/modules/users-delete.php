<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$levelconflict = false;

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = \Sunlight\Util\Request::get('id');
    $query = DB::queryRow("SELECT u.id,u.username,g.level group_level FROM " . _users_table . " u JOIN " . _groups_table . " g ON(u.group_id=g.id) WHERE u.username=" . DB::val($id));
    if ($query !== false) {
        if (\Sunlight\User::checkLevel($query['id'], $query['group_level'])) {
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
    if ($query['id'] != 0 && $query['id'] != _user_id) {
        if (isset($_POST['confirmed'])) {
            if (\Sunlight\User::delete($id)) {
                $output .= \Sunlight\Message::render(_msg_ok, _lang('global.done'));
            } else {
                $output .= \Sunlight\Message::render(_msg_warn, _lang('global.error'));
            }
        } else {
            $output .= "
<p class='bborder'>" . _lang('admin.users.deleteuser.confirmation', array('%user%' => $query['username'])) . "
<form method='post'>
    <input type='submit' name='confirmed' value='" . _lang('admin.users.deleteuser') . "'>
" . \Sunlight\Xsrf::getInput() . "</form>";
        }
    } else {
        if ($query['id'] == _super_admin_id) {
            $output .= \Sunlight\Message::render(_msg_warn, _lang('global.rootnote'));
        } else {
            $output .= \Sunlight\Message::render(_msg_warn, _lang('admin.users.deleteuser.selfnote'));
        }
    }

} else {
    if ($levelconflict == false) {
        $output .= \Sunlight\Message::render(_msg_err, _lang('global.baduser'));
    } else {
        $output .= \Sunlight\Message::render(_msg_err, _lang('global.disallowed'));
    }
}
