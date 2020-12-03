<?php

use Sunlight\Article;
use Sunlight\Database\Database as DB;
use Sunlight\Util\Arr;

defined('_root') or exit;

return function ($kategorie = null) {
    if (!empty($kategorie)) {
        $kategorie = Arr::removeValue(explode('-', $kategorie), '');
    } else {
        $kategorie = [];
    }

    list($joins, $cond) = Article::createFilter('art', $kategorie);

    return DB::result(DB::query("SELECT COUNT(*) FROM " . _article_table . " AS art " . $joins . " WHERE " . $cond), 0);
};
