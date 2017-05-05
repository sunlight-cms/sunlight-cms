<?php

if (!defined('_root')) {
    exit;
}

/* ---  ulozeni  --- */

$message = "";
if (isset($_POST['sourcegroup'])) {
    $source = (int) _post('sourcegroup');
    $target = (int) _post('targetgroup');
    $source_data = DB::query("SELECT level FROM " . _groups_table . " WHERE id=" . $source);
    $target_data = DB::query("SELECT level FROM " . _groups_table . " WHERE id=" . $target);

    if (DB::size($source_data) != 0 && DB::size($target_data) != 0 && $source != 2 && $target != 2) {
        if ($source != $target) {
            $source_data = DB::row($source_data);
            $target_data = DB::row($target_data);
            if (_priv_level > $source_data['level'] && _priv_level > $target_data['level']) {
                DB::query("UPDATE " . _users_table . " SET group_id=" . $target . " WHERE group_id=" . $source . " AND id!=0");
                $message = _msg(_msg_ok, $_lang['global.done']);
            } else {
                $message = _msg(_msg_warn, $_lang['admin.users.move.failed']);
            }
        } else {
            $message = _msg(_msg_warn, $_lang['admin.users.move.same']);
        }
    } else {
        $message = _msg(_msg_err, $_lang['global.badinput']);
    }

}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=users-move' method='post'>
" . $_lang['admin.users.move.text1'] . " " . _adminUserSelect("sourcegroup", -1, "id!=2", null, null, true) . " " . $_lang['admin.users.move.text2'] . " " . _adminUserSelect("targetgroup", -1, "id!=2", null, null, true) . " <input class='button' type='submit' value='" . $_lang['global.do'] . "' onclick='return Sunlight.confirm();'>
" . _xsrfProtect() . "</form>
";
