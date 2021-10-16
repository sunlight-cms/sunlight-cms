<?php

defined('SL_ROOT') or exit;

// vystup
$_index->title = $_page['title'];

// aktivace presmerovani
if ($_page['link_url'] !== null) {
    $_index->redirect($_page['link_url']);
} else {
    $_index->notFound();
}
