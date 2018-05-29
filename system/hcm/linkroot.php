<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
};

return function ($id = null, $text = null, $nove_okno = false)
{
    $is_id = is_numeric($id);
    if ($is_id) {
        $id = (int) $id;
    } else {
        $id = DB::val($id);
    }
    $query = DB::queryRow("SELECT title,slug FROM " . _root_table . " WHERE " . ($is_id ? 'id' : 'slug') . "=" . $id);
    if (isset($nove_okno) && (bool) $nove_okno) {
        $target = " target='_blank'";
    } else {
        $target = "";
    }
    if ($query !== false) {
        if (isset($text) && $text != "") {
            $query['title'] = _e($text);
        }

        return "<a href='" . _linkRoot($id, $query['slug']) . "'" . $target . ">" . $query['title'] . "</a>";
    }
};
