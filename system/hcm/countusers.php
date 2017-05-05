<?php

if (!defined('_root')) {
    exit;
}

function _HCM_countusers($group_id = null)
{
    if (isset($group_id)) {
        $cond = " WHERE " . _sqlWhereColumn("group_id", $group_id);
    } else {
        $cond = "";
    }

    return DB::result(DB::query("SELECT COUNT(*) FROM " . _users_table . $cond), 0);
}
