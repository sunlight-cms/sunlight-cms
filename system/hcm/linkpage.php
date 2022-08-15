<?php

use Sunlight\Database\Database as DB;
use Sunlight\Router;

return function ($id = null, $text = null, $nove_okno = false) {
    $is_id = is_numeric($id);
    if ($is_id) {
        $id = (int) $id;
    } else {
        $id = DB::val($id);
    }
    $query = DB::queryRow('SELECT id,title,slug FROM ' . DB::table('page') . ' WHERE ' . ($is_id ? 'id' : 'slug') . '=' . $id);
    if ($nove_okno) {
        $target = " target='_blank'";
    } else {
        $target = '';
    }
    if ($query !== false) {
        if (isset($text) && $text != '') {
            $query['title'] = _e($text);
        }

        return "<a href='" . _e(Router::page($query['id'], $query['slug'])) . "'" . $target . '>' . $query['title'] . '</a>';
    }
};
