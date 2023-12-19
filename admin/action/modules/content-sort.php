<?php

use Sunlight\Admin\PageLister;

defined('SL_ROOT') or exit;

// output
$output .= '<p class="bborder">' . _lang('admin.content.sort.p') . '</p>';

$output .= PageLister::render([
    'mode' => PageLister::MODE_SINGLE_LEVEL,
    'sortable' => true,
    'actions' => false,
    'links' => false,
    'type' => true,
]);
