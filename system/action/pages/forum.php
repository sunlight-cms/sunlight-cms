<?php

use Sunlight\Post\PostService;
use Sunlight\Extend;
use Sunlight\Hcm;
use Sunlight\Settings;
use Sunlight\User;

defined('SL_ROOT') or exit;

// defaults
if ($_page['var1'] === null) {
    $_page['var1'] = Settings::get('topicsperpage');
}

// show a topic?
if ($_index->segment !== null) {
    require SL_ROOT . 'system/action/pages/include/topic.php';
    return;
}

// title
$_index->title = $_page['title'];

// content
Extend::call('page.forum.content.before', $extend_args);

if ($_page['content'] != '') {
    $output .= Hcm::parse($_page['content']);
}

Extend::call('page.forum.content.after', $extend_args);

// topics
$output .= PostService::renderList(PostService::RENDER_FORUM_TOPIC_LIST, $id, [
    $_page['var1'],
    User::checkPublicAccess($_page['var3']),
    $_page['var2'],
    $_page['slug'],
]);
