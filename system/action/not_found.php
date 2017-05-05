<?php

use Sunlight\Extend;

if (!defined('_root')) {
    exit;
}

// udalost pred
$continue = true;
Extend::call('index.not_found.pre', array(
    'index' => &$_index,
    'continue' => &$continue,
));
if (!$continue) {
    return;
}

// presmerovani
if ($_index['is_page'] && null !== $_index['slug']) {
    $redirect = DB::queryRow('SELECT new,permanent FROM ' . _redir_table . ' WHERE old=' . DB::val($_index['slug']) . ' AND active=1');
    if (false !== $redirect) {
        header('HTTP/1.1 ' . ($redirect['permanent'] ? '301 Moved Permanently' : '302 Found'));
        header('Location: ' . _linkPage($redirect['new'], true));

        return;
    }
}

// hlavicka a vychozi obsah
_notFoundHeader();

$_index['title'] = $_lang['global.error404.title'];
$_index['output'] = '';

Extend::call('index.not_found', array(
    'index' => &$_index,
));

if ('' === $_index['output']) {
    $_index['output'] = _msg(_msg_warn, $_lang['global.error404']);
}
