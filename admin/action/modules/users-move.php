<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$excluded_group_ids = [User::ADMIN_GROUP_ID, User::GUEST_GROUP_ID];

/* ---  ulozeni  --- */

$message = "";
if (isset($_POST['sourcegroup'])) {
    $source = (int) Request::post('sourcegroup');
    $target = (int) Request::post('targetgroup');
    $source_data = DB::queryRow("SELECT level FROM " . DB::table('user_group') . " WHERE id=" . $source);
    $target_data = DB::queryRow("SELECT level FROM " . DB::table('user_group') . " WHERE id=" . $target);

    if ($source_data !== false && $target_data !== false && !in_array($source, $excluded_group_ids) && !in_array($target, $excluded_group_ids)) {
        if ($source != $target) {
            if (User::getLevel() > $source_data['level'] && User::getLevel() > $target_data['level']) {
                DB::update('user', 'group_id=' . $source . ' AND id!=' . User::getId(), ['group_id' => $target], null);
                $message = Message::ok(_lang('global.done'));
            } else {
                $message = Message::warning(_lang('admin.users.move.failed'));
            }
        } else {
            $message = Message::warning(_lang('admin.users.move.same'));
        }
    } else {
        $message = Message::error(_lang('global.badinput'));
    }
}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='" . _e(Router::admin('users-move')) . "' method='post'>
" . _lang('admin.users.move.text1')
. " " . Admin::userSelect('sourcegroup', -1, 'id NOT IN(' . DB::arr($excluded_group_ids) . ')', null, null, true)
. " " . _lang('admin.users.move.text2') . " " . Admin::userSelect('targetgroup', -1, 'id NOT IN(' . DB::arr($excluded_group_ids) . ')', null, null, true)
. " <input class='button' type='submit' value='" . _lang('global.do') . "' onclick='return Sunlight.confirm();'>
" . Xsrf::getInput() . "</form>";
