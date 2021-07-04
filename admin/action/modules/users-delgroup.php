<?php

use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$levelconflict = false;
$sysgroups_array = [_group_admin, _group_guests, _group_registered];

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = (int) Request::get('id');
    $systemgroup = in_array($id, $sysgroups_array);
    $query = DB::queryRow("SELECT id,title,level FROM " . _user_group_table . " WHERE id=" . $id);
    if ($query !== false) {
        if (User::getLevel() > $query['level']) {
            $continue = true;
        } else {
            $levelconflict = true;
        }
    }
}

if ($continue) {

    /* --- spocitani uzivatelu --- */
    $user_count = DB::count(_user_table, 'group_id=' . DB::val($id));

    /* ---  odstraneni  --- */
    $done = false;
    if (isset($_POST['doit'])) {

        // smazani skupiny
        if (!$systemgroup) {
            DB::delete(_user_group_table, 'id=' . $id);
        }

        // zmena vychozi skupiny
        if (!$systemgroup && $id == Settings::get('defaultgroup')) {
            Settings::update('defaultgroup', _group_registered);
        }

        // smazani uzivatelu
        $users = DB::query("SELECT id FROM " . _user_table . " WHERE group_id=" . $id . " AND id!=0");
        while ($user = DB::row($users)) {
            User::delete($user['id']);
        }

        $done = true;

    }

    /* ---  vystup  --- */
    if (!$done) {
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
    <input type='submit' value='" . _lang('global.confirmdelete') . "'>
    " . Xsrf::getInput() . "</form>
    ";
    } else {
        $output .= Message::ok(_lang('global.done'));
    }

} elseif ($levelconflict == false) {
    $output .= Message::error(_lang('global.badinput'));
} else {
    $output .= Message::error(_lang('global.disallowed'));
}
