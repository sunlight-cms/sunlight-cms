<?php

use Sunlight\Core;

if (!defined('_root')) {
    exit;
}

/* ---  priprava  --- */

$message = "";

/* ---  akce  --- */

if (isset($_POST['index'])) {
    Core::updateSetting('index_page_id', ($index_id = (int) _post('index')));
    $message = _msg(_msg_ok, _lang('global.done'));

} else {
    $index_id = _index_page_id;
}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=content-setindex' method='post'>
" . _adminRootSelect('index', array('selected' => $index_id, 'maxlength' => null)) . "
<input class='button' type='submit' value='" . _lang('global.do') . "'>
" . _xsrfProtect() . "</form>
";
