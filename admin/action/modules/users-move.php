<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  ulozeni  --- */

$message = "";
if (isset($_POST['sourcegroup'])) {
    $source = (int) Request::post('sourcegroup');
    $target = (int) Request::post('targetgroup');
    $source_data = DB::queryRow("SELECT level FROM " . _groups_table . " WHERE id=" . $source);
    $target_data = DB::queryRow("SELECT level FROM " . _groups_table . " WHERE id=" . $target);

    if ($source_data !== false && $target_data !== false && $source != 2 && $target != 2) {
        if ($source != $target) {
            if (_priv_level > $source_data['level'] && _priv_level > $target_data['level']) {
                DB::update(_users_table, 'group_id=' . $source . ' AND id!=0', array('group_id' => $target));
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
<form class='cform' action='index.php?p=users-move' method='post'>
" . _lang('admin.users.move.text1') . " " . Admin::userSelect("sourcegroup", -1, "id!=" . _group_guests, null, null, true) . " " . _lang('admin.users.move.text2') . " " . Admin::userSelect("targetgroup", -1, "id!=2", null, null, true) . " <input class='button' type='submit' value='" . _lang('global.do') . "' onclick='return Sunlight.confirm();'>
" . Xsrf::getInput() . "</form>
";
