<?php

use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$levelconflict = false;

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = Request::get('id');
    $query = DB::queryRow("SELECT u.id,u.username,g.level group_level FROM " . _user_table . " u JOIN " . _user_group_table . " g ON(u.group_id=g.id) WHERE u.username=" . DB::val($id));
    if ($query !== false) {
        if (User::checkLevel($query['id'], $query['group_level'])) {
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
            if (User::delete($id)) {
                $output .= Message::ok(_lang('global.done'));
            } else {
                $output .= Message::warning(_lang('global.error'));
            }
        } else {
            $output .= "
<p class='bborder'>" . _lang('admin.users.deleteuser.confirmation', array('%user%' => $query['username'])) . "
<form method='post'>
    <input type='submit' name='confirmed' value='" . _lang('admin.users.deleteuser') . "'>
" . Xsrf::getInput() . "</form>";
        }
    } else {
        if ($query['id'] == _super_admin_id) {
            $output .= Message::warning(_lang('global.rootnote'));
        } else {
            $output .= Message::warning(_lang('admin.users.deleteuser.selfnote'));
        }
    }

} else {
    if ($levelconflict == false) {
        $output .= Message::error(_lang('global.baduser'));
    } else {
        $output .= Message::error(_lang('global.disallowed'));
    }
}
