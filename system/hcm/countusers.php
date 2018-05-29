<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

return function ($group_id = null)
{
    if (isset($group_id)) {
        $cond = _sqlWhereColumn("group_id", $group_id);
    } else {
        $cond = "";
    }

    return DB::count(_users_table, $cond);
};
