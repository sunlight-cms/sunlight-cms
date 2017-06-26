<?php

use Sunlight\Admin\PageLister;

if (!defined('_root')) {
    exit;
}
/* ---  vystup  --- */

$output .= "<p class='bborder'>" . _lang('admin.content.sort.p') . "</p>";

$output .= PageLister::render(array(
    'mode' => PageLister::MODE_SINGLE_LEVEL,
    'sortable' => true,
    'actions' => false,
    'type' => true,
));
