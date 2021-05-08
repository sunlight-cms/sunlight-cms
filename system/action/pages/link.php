<?php

defined('_root') or exit;

// vystup
$_index['title'] = $_page['title'];

// aktivace presmerovani
if ($_page['link_url'] !== '') {
    $_index['type'] = _index_redir;
    $_index['redirect_to'] = $_page['link_url'];
} else {
    $_index['type'] = _index_not_found;
}
