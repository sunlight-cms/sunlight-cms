<?php

use Sunlight\Comment\CommentService;
use Sunlight\Extend;

defined('_root') or exit;

// vychozi nastaveni
if ($_page['var1'] === null) {
    $_page['var1'] = _topicsperpage;
}

// zobrazit tema?
if ($_index['segment'] !== null) {
    require _root . 'system/action/pages/include/topic.php';
    return;
}

// titulek
$_index['title'] = $_page['title'];

// rss
$_index['rsslink'] = \Sunlight\Router::rss($id, _rss_latest_topics, false);

// obsah
Extend::call('page.forum.content.before', $extend_args);
if ($_page['content'] != "") {
    $output .= Sunlight\Hcm::parse($_page['content']);
}
Extend::call('page.forum.content.after', $extend_args);

// temata
$output .= CommentService::render(CommentService::RENDER_FORUM_TOPIC_LIST, $id, array(
    $_page['var1'],
    \Sunlight\User::checkPublicAccess($_page['var3']),
    $_page['var2'],
    $_page['slug'],
));
