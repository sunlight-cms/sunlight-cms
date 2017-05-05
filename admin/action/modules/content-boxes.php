<?php

if (!defined('_root')) {
    exit;
}

$boxes = array();
$query = DB::query('SELECT id, ord, title, visible, public, level, template, layout, slot, page_ids, page_children, class FROM ' . _boxes_table . ' ORDER BY ord ASC');

while ($box = DB::row($query)) {

}

