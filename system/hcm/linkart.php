<?php

use Sunlight\Database\Database as DB;
use Sunlight\Router;

return function ($id = null, $text = null, $nove_okno = false) {
    $query = DB::queryRow('SELECT art.id' . ($text === null ? ',art.title' : '') . ',art.slug,cat.slug AS cat_slug FROM ' . DB::table('article') . ' AS art JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1) WHERE art.' . (is_numeric($id) ? 'id' : 'slug') . '=' . DB::val($id));

    if ($query === false) {
        return '{' . _e($id) . '}';
    }

    $text = $text === null ? $query['title'] : _e($text);

    return '<a href="' . _e(Router::article($query['id'], $query['slug'], $query['cat_slug'])) . '"' . ($nove_okno ? ' target="_blank"' : '') . '>' . $text . '</a>';
};
