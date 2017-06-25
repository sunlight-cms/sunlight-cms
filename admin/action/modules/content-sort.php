<?php

if (!defined('_root')) {
    exit;
}
/* ---  vystup  --- */

$output .= "<p class='bborder'>" . _lang('admin.content.sort.p') . "</p>";

$output .= Sunlight\Admin\PageLister::render(array(
    'mode' => Sunlight\Admin\PageLister::MODE_SINGLE_LEVEL,
    'sortable' => true,
    'actions' => false,
    'type' => true,
));
