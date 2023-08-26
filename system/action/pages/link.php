<?php

use Sunlight\Core;
use Sunlight\Util\UrlHelper;

defined('SL_ROOT') or exit;

$_index->title = $_page['title'];

if ($_page['link_url'] !== null) {
    if (UrlHelper::isAbsolute($_page['link_url'])) {
        $abs_link_url = $_page['link_url'];
    } else {
        $abs_link_url = Core::getBaseUrl()->getPath() . '/' . $_page['link_url'];
    }

    $_index->redirect($abs_link_url);
} else {
    $_index->notFound();
}
