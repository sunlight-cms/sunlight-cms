<?php

use Sunlight\Extend;
use Sunlight\Message;

defined('_root') or exit;

$_index['title'] = _lang('nologin.title');
$_index['output'] = '';
$_index['body_classes'][] = 't-error';
$_index['body_classes'][] = 'e-guest-only';

Extend::call('index.guest_required', [
    'index' => &$_index,
]);

if ($_index['output'] === '') {
    $_index['output'] = Message::ok(_lang('nologin.msg'));
}
