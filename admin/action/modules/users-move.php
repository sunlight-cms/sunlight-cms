<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
}

/* ---  ulozeni  --- */

$message = "";
if (isset($_POST['sourcegroup'])) {
    $source = (int) _post('sourcegroup');
    $target = (int) _post('targetgroup');
    $source_data = DB::queryRow("SELECT level FROM " . _groups_table . " WHERE id=" . $source);
    $target_data = DB::queryRow("SELECT level FROM " . _groups_table . " WHERE id=" . $target);

    if ($source_data !== false && $target_data !== false && $source != 2 && $target != 2) {
        if ($source != $target) {
            if (_priv_level > $source_data['level'] && _priv_level > $target_data['level']) {
                DB::update(_users_table, 'group_id=' . $source . ' AND id!=0', array('group_id' => $target));
                $message = _msg(_msg_ok, _lang('global.done'));
            } else {
                $message = _msg(_msg_warn, _lang('admin.users.move.failed'));
            }
        } else {
            $message = _msg(_msg_warn, _lang('admin.users.move.same'));
        }
    } else {
        $message = _msg(_msg_err, _lang('global.badinput'));
    }

}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=users-move' method='post'>
" . _lang('admin.users.move.text1') . " " . _adminUserSelect("sourcegroup", -1, "id!=" . _group_guests, null, null, true) . " " . _lang('admin.users.move.text2') . " " . _adminUserSelect("targetgroup", -1, "id!=2", null, null, true) . " <input class='button' type='submit' value='" . _lang('global.do') . "' onclick='return Sunlight.confirm();'>
" . _xsrfProtect() . "</form>
";
