<?php

use Sunlight\Comment\CommentService;

if (!defined('_root')) {
    exit;
}

// titulek
$_index['title'] = $_page['title'];

// obsah
Sunlight\Extend::call('page.section.content.before', $extend_args);
$output .= _parseHCM($_page['content']);
Sunlight\Extend::call('page.section.content.after', $extend_args);

// komentare
if ($_page['var1'] == 1 && _comments) {
    $output .= CommentService::render(CommentService::RENDER_SECTION_COMMENTS, $id, $_page['var3']);
}
