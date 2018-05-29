<?php

use Sunlight\Database\Database as DB;

defined('_root') or exit;

return function ($id = null, $text = null, $nove_okno = false)
{
    if ($text === null) {
        $query = DB::queryRow('SELECT art.title,art.slug,cat.slug AS cat_slug FROM ' . _articles_table . ' AS art JOIN ' . _root_table . ' AS cat ON(cat.id=art.home1) WHERE art.' . (is_numeric($id) ? 'id' : 'slug') . '=' . DB::val($id));
        if ($query === false) {
            return '{' . _e($id) . '}';
        }
        $text = $query['title'];
    } else {
        $text = _e($text);
        $query = array('slug' => null, 'cat_slug' => null);
    }

    return "<a href='" . \Sunlight\Router::article($id, $query['slug'], $query['cat_slug']) . "'" . ($nove_okno ? ' target="_blank"' : '') . ">" . $text . "</a>";
};
