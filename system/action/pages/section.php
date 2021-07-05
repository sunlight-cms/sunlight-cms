<?php

use Sunlight\Post\PostService;
use Sunlight\Extend;
use Sunlight\Hcm;
use Sunlight\Settings;

defined('SL_ROOT') or exit;

// titulek
$_index['title'] = $_page['title'];

// obsah
Extend::call('page.section.content.before', $extend_args);
$output .= Hcm::parse($_page['content']);
Extend::call('page.section.content.after', $extend_args);

// komentare
if ($_page['var1'] == 1 && Settings::get('comments')) {
    $output .= PostService::render(PostService::RENDER_SECTION_COMMENTS, $id, $_page['var3']);
}
