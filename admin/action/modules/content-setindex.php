<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava  --- */

$message = "";

/* ---  akce  --- */

if (isset($_POST['index'])) {
    Core::updateSetting('index_page_id', ($index_id = (int) Request::post('index')));
    $message = Message::ok(_lang('global.done'));

} else {
    $index_id = _index_page_id;
}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=content-setindex' method='post'>
" . Admin::pageSelect('index', ['selected' => $index_id, 'maxlength' => null]) . "
<input class='button' type='submit' value='" . _lang('global.do') . "'>
" . Xsrf::getInput() . "</form>
";
