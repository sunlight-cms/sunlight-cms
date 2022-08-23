<?php

use Sunlight\Database\Database as DB;
use Sunlight\Hcm;

return function ($group_ids = null) {
    Hcm::normalizeArgument($group_ids, 'string');

    if ($group_ids !== null && !empty($group_ids = explode('-', $group_ids))) {
        $cond = 'group_id IN(' . DB::arr($group_ids) . ')';
    } else {
        $cond = '1';
    }

    return DB::count('user', $cond);
};
