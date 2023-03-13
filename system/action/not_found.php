<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Response;

defined('SL_ROOT') or exit;

// extend
$continue = true;
Extend::call('index.not_found.before', [
    'index' => $_index,
    'continue' => &$continue,
]);

if (!$continue) {
    return;
}

// redirection
if ($_index->slug !== null) {
    $redirect = DB::queryRow('SELECT new,permanent FROM ' . DB::table('redirect') . ' WHERE old=' . DB::val($_index->slug) . ' AND active=1');

    if ($redirect !== false) {
        Response::redirect(Router::slug($redirect['new'], ['absolute' => true]), $redirect['permanent']);

        return;
    }
}

// output
http_response_code(404);

$_index->title = _lang('global.error404.title');
$_index->output = '';
$_index->bodyClasses[] = 't-error';
$_index->bodyClasses[] = 'e-not-found';

Extend::call('index.not_found', [
    'index' => $_index,
]);

if ($_index->output === '') {
    $_index->output = Message::warning(_lang('global.error404'));
}
