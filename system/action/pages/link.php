<?php

defined('SL_ROOT') or exit;

$_index->title = $_page['title'];

if ($_page['link_url'] !== null) {
    $_index->redirect($_page['link_url']);
} else {
    $_index->notFound();
}
