<?php

use Sunlight\Extend;

if (!defined('_root')) {
    exit;
}

$_index['title'] = $_lang['nologin.title'];
$_index['output'] = '';

Extend::call('index.guest_required', array(
    'index' => &$_index,
));

if ($_index['output'] === '') {
    $_index['output'] =_msg(_msg_ok, $_lang['nologin.msg']);
}
