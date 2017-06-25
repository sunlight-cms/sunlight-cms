<?php

use Sunlight\Extend;

if (!defined('_root')) {
    exit;
}

$_index['title'] = _lang('login.required.title');
$_index['output'] = '';

Extend::call('index.login_required', array(
    'index' => &$_index,
));

if ($_index['output'] === '') {
    $_index['output'] = _userLoginForm(true, true);
}
