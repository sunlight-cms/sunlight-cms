<?php

use Sunlight\Post\PostService;
use Sunlight\Extend;
use Sunlight\Hcm;
use Sunlight\Settings;
use Sunlight\User;

defined('SL_ROOT') or exit;

// vychozi nastaveni
if ($_page['var2'] === null) {
    $_page['var2'] = Settings::get('commentsperpage');
}

// titulek
$_index->title = $_page['title'];

// obsah
Extend::call('page.book.content.before', $extend_args);
if ($_page['content'] != "") {
    $output .= Hcm::parse($_page['content']);
}
Extend::call('page.book.content.after', $extend_args);

// prispevky
$output .= PostService::render(PostService::RENDER_BOOK_POSTS, $id, [
    $_page['var2'],
    User::checkPublicAccess($_page['var1']),
    $_page['var3'],
]);
