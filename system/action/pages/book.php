<?php

use Sunlight\Comment\CommentService;
use Sunlight\Extend;

defined('_root') or exit;

// vychozi nastaveni
if ($_page['var2'] === null) {
    $_page['var2'] = _commentsperpage;
}

// titulek
$_index['title'] = $_page['title'];

// rss
$_index['rsslink'] = _linkRSS($id, _rss_book_posts, false);

// obsah
Extend::call('page.book.content.before', $extend_args);
if ($_page['content'] != "") {
    $output .= Sunlight\Hcm::parse($_page['content']);
}
Extend::call('page.book.content.after', $extend_args);

// prispevky
$output .= CommentService::render(CommentService::RENDER_BOOK_POSTS, $id, array(
    $_page['var2'],
    _publicAccess($_page['var1']),
    $_page['var3'],
));
