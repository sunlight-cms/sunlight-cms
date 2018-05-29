<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
};

return function ($group_id = null)
{
    if (isset($group_id)) {
        $cond = _sqlWhereColumn("group_id", $group_id);
    } else {
        $cond = "";
    }

    return DB::count(_users_table, $cond);
};
