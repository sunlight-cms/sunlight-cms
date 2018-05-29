<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
};

return function ($kategorie = null)
{
    if (!empty($kategorie)) {
        $kategorie = _arrayRemoveValue(explode('-', $kategorie), '');
    } else {
        $kategorie = array();
    }

    list($joins, $cond) = _articleFilter('art', $kategorie);

    return DB::result(DB::query("SELECT COUNT(*) FROM " . _articles_table . " AS art " . $joins . " WHERE " . $cond), 0);
};
