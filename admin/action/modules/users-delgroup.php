<?php

use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

/* ---  priprava promennych  --- */

$levelconflict = false;
$sysgroups_array = [User::ADMIN_GROUP_ID, User::GUEST_GROUP_ID, User::REGISTERED_GROUP_ID];

// id
$id = (int) Request::get('id');
$systemgroup = in_array($id, $sysgroups_array);
$query = DB::queryRow("SELECT id,title,level FROM " . DB::table('user_group') . " WHERE id=" . $id);

if ($query === false) {
    $output .= Message::error(_lang('global.badinput'));
    return;
}

if (User::getLevel() <= $query['level']) {
    $output .= Message::error(_lang('global.disallowed'));
    return;
}

/* --- spocitani uzivatelu --- */
$user_count = DB::count('user', 'group_id=' . DB::val($id));

/* ---  odstraneni  --- */
$done = false;
if (isset($_POST['doit'])) {
    // smazani uzivatelu
    $users = DB::query("SELECT id FROM " . DB::table('user') . " WHERE group_id=" . $id);
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

    // smazani skupiny
    if (!$systemgroup) {
        DB::delete('user_group', 'id=' . $id);

        // zmena vychozi skupiny
        if ($id == Settings::get('defaultgroup')) {
            Settings::update('defaultgroup', User::REGISTERED_GROUP_ID);
        }
    }

    $output .= Message::ok(_lang('global.done'));
    return;
}

/* ---  vystup  --- */
if ($systemgroup) {
    $output .= Message::ok(_lang('admin.users.groups.specialgroup.delnotice'));
}
if ($user_count > 0) {
    $output .= Message::warning(_lang('admin.users.groups.delwarning', ['%user_count%' => $user_count]));
}

$output .= "
<form class='cform' method='post'>
<input type='hidden' name='doit' value='1'>

<p>" . _lang('admin.users.groups.delconfirm', ['%group%' => $query['title']]) . "</p>
<input type='submit' value='" . _lang('global.confirmdelete') . "'>"
. Xsrf::getInput()
. "</form>";
