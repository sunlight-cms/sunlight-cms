<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Response;

defined('_root') or exit;

// udalost pred
$continue = true;
Extend::call('index.not_found.before', [
    'index' => &$_index,
    'continue' => &$continue,
]);
if (!$continue) {
    return;
}

// presmerovani
if ($_index['slug'] !== null) {
    $redirect = DB::queryRow('SELECT new,permanent FROM ' . _redirect_table . ' WHERE old=' . DB::val($_index['slug']) . ' AND active=1');
    if ($redirect !== false) {
        Response::redirect(Router::path($redirect['new'], true), $redirect['permanent']);

        return;
    }
}

// hlavicka a vychozi obsah
Response::notFound();

$_index['title'] = _lang('global.error404.title');
$_index['output'] = '';
$_index['body_classes'][] = 't-error';
$_index['body_classes'][] = 'e-not-found';

Extend::call('index.not_found', [
    'index' => &$_index,
]);

if ($_index['output'] === '') {
    $_index['output'] = Message::warning(_lang('global.error404'));
}
