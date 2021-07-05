<?php

use Sunlight\Admin\Admin;
use Sunlight\Message;
use Sunlight\Settings;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

/* ---  priprava  --- */

$message = "";

/* ---  akce  --- */

if (isset($_POST['index'])) {
    $index_id = (int) Request::post('index');
    Settings::update('index_page_id', $index_id);
    $message = Message::ok(_lang('global.done'));

} else {
    $index_id = Settings::get('index_page_id');
}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=content-setindex' method='post'>
" . Admin::pageSelect('index', ['selected' => $index_id, 'maxlength' => null]) . "
<input class='button' type='submit' value='" . _lang('global.do') . "'>
" . Xsrf::getInput() . "</form>
";
