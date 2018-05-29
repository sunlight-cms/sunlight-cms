<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

return function ($kategorie = null)
{
    if (!empty($kategorie)) {
        $kategorie = \Sunlight\Util\Arr::removeValue(explode('-', $kategorie), '');
    } else {
        $kategorie = array();
    }

    list($joins, $cond) = \Sunlight\Article::createFilter('art', $kategorie);

    return DB::result(DB::query("SELECT COUNT(*) FROM " . _articles_table . " AS art " . $joins . " WHERE " . $cond), 0);
};
