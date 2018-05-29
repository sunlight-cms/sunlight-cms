<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

/* ---  ulozeni  --- */

$message = "";
if (isset($_POST['sourcegroup'])) {
    $source = (int) \Sunlight\Util\Request::post('sourcegroup');
    $target = (int) \Sunlight\Util\Request::post('targetgroup');
    $source_data = DB::queryRow("SELECT level FROM " . _groups_table . " WHERE id=" . $source);
    $target_data = DB::queryRow("SELECT level FROM " . _groups_table . " WHERE id=" . $target);

    if ($source_data !== false && $target_data !== false && $source != 2 && $target != 2) {
        if ($source != $target) {
            if (_priv_level > $source_data['level'] && _priv_level > $target_data['level']) {
                DB::update(_users_table, 'group_id=' . $source . ' AND id!=0', array('group_id' => $target));
                $message = \Sunlight\Message::render(_msg_ok, _lang('global.done'));
            } else {
                $message = \Sunlight\Message::render(_msg_warn, _lang('admin.users.move.failed'));
            }
        } else {
            $message = \Sunlight\Message::render(_msg_warn, _lang('admin.users.move.same'));
        }
    } else {
        $message = \Sunlight\Message::render(_msg_err, _lang('global.badinput'));
    }

}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=users-move' method='post'>
" . _lang('admin.users.move.text1') . " " . \Sunlight\Admin\Admin::userSelect("sourcegroup", -1, "id!=" . _group_guests, null, null, true) . " " . _lang('admin.users.move.text2') . " " . \Sunlight\Admin\Admin::userSelect("targetgroup", -1, "id!=2", null, null, true) . " <input class='button' type='submit' value='" . _lang('global.do') . "' onclick='return Sunlight.confirm();'>
" . \Sunlight\Xsrf::getInput() . "</form>
";
