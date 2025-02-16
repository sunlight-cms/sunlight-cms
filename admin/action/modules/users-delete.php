<?php

use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

$levelconflict = false;
$continue = false;

// load user
if (isset($_GET['id'])) {
    $id = Request::get('id');
    $query = DB::queryRow('SELECT u.id,u.username,g.level group_level FROM ' . DB::table('user') . ' u JOIN ' . DB::table('user_group') . ' g ON(u.group_id=g.id) WHERE u.username=' . DB::val($id));

    if ($query !== false) {
        if (User::checkLevel($query['id'], $query['group_level'])) {
            $continue = true;
        } else {
            $levelconflict = true;
        }

        $id = $query['id'];
    }
}

// action and output
if ($continue) {
    if (!User::equals($query['id'])) {
        if (isset($_POST['confirmed'])) {
            if (User::delete($id)) {
                $output .= Message::ok(_lang('global.done'));
            } else {
                $output .= Message::warning(_lang('global.error'));
            }
        } else {
            $output .= '
<p class="bborder">' . _lang('admin.users.deleteuser.confirmation', ['%user%' => $query['username']]) . '
' . Form::start('user-delete') . '
    ' . Form::input('submit', 'confirmed', _lang('admin.users.deleteuser')) . '
' . Form::end('user-delete');
        }
    } else {
        $output .= Message::warning(_lang('admin.users.deleteuser.selfnote'));
    }
} elseif (!$levelconflict) {
    $output .= Message::error(_lang('global.baduser'));
} else {
    $output .= Message::error(_lang('global.disallowed'));
}
