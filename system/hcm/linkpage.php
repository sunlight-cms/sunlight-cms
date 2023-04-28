<?php

use Sunlight\Database\Database as DB;
use Sunlight\Hcm;
use Sunlight\Router;

return function ($id = '', $text = null, $new_window = false) {
    Hcm::normalizeArgument($id, 'string');
    Hcm::normalizeArgument($text, 'string', true);
    Hcm::normalizeArgument($new_window, 'bool');

    $is_id = ctype_digit($id);

    if ($is_id) {
        $id = (int) $id;
    } else {
        $id = DB::val($id);
    }

    $query = DB::queryRow('SELECT id,title,slug FROM ' . DB::table('page') . ' WHERE ' . ($is_id ? 'id' : 'slug') . '=' . $id);

    if ($new_window) {
        $target = ' target="_blank"';
    } else {
        $target = '';
    }

    if ($query !== false) {
        return '<a href="' . _e(Router::page($query['id'], $query['slug'])) . '"' . $target . '>' . ($text ?? $query['title']) . '</a>';
    }
};
