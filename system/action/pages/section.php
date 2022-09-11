<?php

use Sunlight\Post\PostService;
use Sunlight\Extend;
use Sunlight\Hcm;
use Sunlight\Settings;

defined('SL_ROOT') or exit;

// title
$_index->title = $_page['title'];

// content
Extend::call('page.section.content.before', $extend_args);
$output .= Hcm::parse($_page['content']);
Extend::call('page.section.content.after', $extend_args);

// comments
if ($_page['var1'] == 1 && Settings::get('comments')) {
    $output .= PostService::renderList(PostService::RENDER_SECTION_COMMENTS, $id, $_page['var3']);
}
