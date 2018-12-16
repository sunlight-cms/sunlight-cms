<?php

use Sunlight\Extend;
use Sunlight\User;
use Sunlight\Util\Response;

defined('_root') or exit;

$_index['title'] = _lang('login.required.title');
$_index['output'] = '';
$_index['body_classes'][] = 't-error';
$_index['body_classes'][] = 'e-unauthorized';

Response::unauthorized();

Extend::call('index.login_required', array(
    'index' => &$_index,
));

if ($_index['output'] === '') {
    $_index['output'] = User::renderLoginForm(true, true);
}
