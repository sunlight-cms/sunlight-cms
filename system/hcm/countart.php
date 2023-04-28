<?php

use Sunlight\Article;
use Sunlight\Database\Database as DB;
use Sunlight\Hcm;
use Sunlight\Util\Arr;

return function ($category = null) {
    Hcm::normalizeArgument($category, 'string', true);

    if ($category !== null) {
        $category = Arr::removeValue(explode('-', $category), '');
    } else {
        $category = [];
    }

    [$joins, $cond] = Article::createFilter('art', $category);

    return DB::result(DB::query('SELECT COUNT(*) FROM ' . DB::table('article') . ' AS art ' . $joins . ' WHERE ' . $cond));
};
