<?php

use Sunlight\Extend;
use Sunlight\User;
use Sunlight\Util\Response;

defined('SL_ROOT') or exit;

$_index->title = _lang('login.required.title');
$_index->output = '';
$_index->bodyClasses[] = 't-error';
$_index->bodyClasses[] = 'e-unauthorized';

Response::unauthorized();

Extend::call('index.login_required', [
    'index' => $_index,
]);

if ($_index->output === '') {
    $_index->output = User::renderLoginForm(true, true);
}
