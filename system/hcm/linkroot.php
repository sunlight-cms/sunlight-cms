<?php

use Sunlight\Database\Database as DB;
use Sunlight\Router;

defined('_root') or exit;

return function ($id = null, $text = null, $nove_okno = false) {
    $is_id = is_numeric($id);
    if ($is_id) {
        $id = (int) $id;
    } else {
        $id = DB::val($id);
    }
    $query = DB::queryRow("SELECT title,slug FROM " . _page_table . " WHERE " . ($is_id ? 'id' : 'slug') . "=" . $id);
    if ($nove_okno) {
        $target = " target='_blank'";
    } else {
        $target = "";
    }
    if ($query !== false) {
        if (isset($text) && $text != "") {
            $query['title'] = _e($text);
        }

        return "<a href='" . Router::page($id, $query['slug']) . "'" . $target . ">" . $query['title'] . "</a>";
    }
};
