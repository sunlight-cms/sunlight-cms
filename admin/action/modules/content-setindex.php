<?php

use Sunlight\Core;

defined('_root') or exit;

/* ---  priprava  --- */

$message = "";

/* ---  akce  --- */

if (isset($_POST['index'])) {
    Core::updateSetting('index_page_id', ($index_id = (int) \Sunlight\Util\Request::post('index')));
    $message = \Sunlight\Message::render(_msg_ok, _lang('global.done'));

} else {
    $index_id = _index_page_id;
}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=content-setindex' method='post'>
" . \Sunlight\Admin\Admin::rootSelect('index', array('selected' => $index_id, 'maxlength' => null)) . "
<input class='button' type='submit' value='" . _lang('global.do') . "'>
" . \Sunlight\Xsrf::getInput() . "</form>
";
