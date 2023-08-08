<?php

use Sunlight\Database\Database as DB;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$levelconflict = false;
$sysgroups_array = [User::ADMIN_GROUP_ID, User::GUEST_GROUP_ID, User::REGISTERED_GROUP_ID];

// load group
$id = (int) Request::get('id');
$systemgroup = in_array($id, $sysgroups_array);
$query = DB::queryRow('SELECT id,title,level FROM ' . DB::table('user_group') . ' WHERE id=' . $id);

if ($query === false) {
    $output .= Message::error(_lang('global.badinput'));
    return;
}

if (User::getLevel() <= $query['level']) {
    $output .= Message::error(_lang('global.disallowed'));
    return;
}

// count users
$user_count = DB::count('user', 'group_id=' . DB::val($id));

// delete
$done = false;

if (isset($_POST['doit'])) {
    // delete users
    $users = DB::query('SELECT id FROM ' . DB::table('user') . ' WHERE group_id=' . $id);
    $user_delete_failcount = 0;

    while ($user = DB::row($users)) {
        if (!User::delete($user['id'])) {
            ++$user_delete_failcount;
        }
    }

    if ($user_delete_failcount > 0) {
        $output .= Message::warning(_lang('admin.users.groups.delpartial', ['%failcount%' => $user_delete_failcount]));
        return;
    }

    // delete group
    if (!$systemgroup) {
        DB::delete('user_group', 'id=' . $id);

        Logger::notice(
            'user',
            sprintf('User group "%s" deleted via admin module', $query['title']),
            ['group_id' => $id]
        );

        // update default group
        if ($id == Settings::get('defaultgroup')) {
            Settings::update('defaultgroup', User::REGISTERED_GROUP_ID);
        }
    }

    $output .= Message::ok(_lang('global.done'));
    return;
}

// output
if ($systemgroup) {
    $output .= Message::ok(_lang('admin.users.groups.specialgroup.delnotice'));
}

if ($user_count > 0) {
    $output .= Message::warning(_lang('admin.users.groups.delwarning', ['%user_count%' => _num($user_count)]));
}

$output .= '
<form class="cform" method="post">
<input type="hidden" name="doit" value="1">

<p>' . _lang('admin.users.groups.delconfirm', ['%group%' => $query['title']]) . '</p>
<input type="submit" value="' . _lang('global.confirmdelete') . '">'
. Xsrf::getInput()
. '</form>';
