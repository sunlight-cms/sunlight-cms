<?php

use Sunlight\Extend;
use Sunlight\Message;

defined('_root') or exit;

$_index['title'] = _lang('nologin.title');
$_index['output'] = '';

Extend::call('index.guest_required', array(
    'index' => &$_index,
));

if ($_index['output'] === '') {
    $_index['output'] = Message::render(_msg_ok, _lang('nologin.msg'));
}
