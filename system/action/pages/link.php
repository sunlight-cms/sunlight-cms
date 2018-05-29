<?php

defined('_root') or exit;

// vystup
$_index['title'] = $_page['title'];

// aktivace presmerovani
if ($_page['link_url'] !== '') {
    $_index['redirect_to'] = $_page['link_url'];
} else {
    $_index['is_found'] = false;
}
