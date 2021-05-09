<?php

use Sunlight\Database\Database as DB;
use Sunlight\Hcm;

return function ($group_id = null) {
    if (isset($group_id)) {
        $cond = Hcm::createColumnInSqlCondition("group_id", $group_id);
    } else {
        $cond = "1";
    }

    return DB::count(_user_table, $cond);
};
