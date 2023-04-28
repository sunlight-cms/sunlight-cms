<?php

use Sunlight\Database\Database as DB;
use Sunlight\Hcm;
use Sunlight\Router;

return function ($id = '', $text = null, $new_window = false) {
    Hcm::normalizeArgument($id, 'string');
    Hcm::normalizeArgument($text, 'string', true);
    Hcm::normalizeArgument($new_window, 'bool');

    $query = DB::queryRow(
        'SELECT art.id' . ($text === null ? ',art.title' : '') . ',art.slug,cat.slug AS cat_slug'
        . ' FROM ' . DB::table('article') . ' AS art'
        . ' JOIN ' . DB::table('page') . ' AS cat ON(cat.id=art.home1)'
        . ' WHERE art.' . (ctype_digit($id) ? 'id' : 'slug') . '=' . DB::val($id)
    );

    if ($query === false) {
        return '{' . _e($id) . '}';
    }

    $text = $text === null ? $query['title'] : _e($text);

    return '<a href="' . _e(Router::article($query['id'], $query['slug'], $query['cat_slug'])) . '"' . ($new_window ? ' target="_blank"' : '') . '>'
        . $text
        . '</a>';
};
