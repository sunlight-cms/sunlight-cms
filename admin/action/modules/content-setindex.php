<?php

if (!defined('_root')) {
    exit;
}

/* ---  priprava  --- */

$message = "";

/* ---  akce  --- */

if (isset($_POST['index'])) {
    DB::query("UPDATE " . _settings_table . " SET val=" . ($index_id = (int) _post('index')) . ' WHERE var=\'index_page_id\'');
    $message = _msg(_msg_ok, $_lang['global.done']);

} else {
    $index_id = _index_page_id;
}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=content-setindex' method='post'>
" . _adminRootSelect('index', array('selected' => $index_id, 'maxlength' => null)) . "
<input class='button' type='submit' value='" . $_lang['global.do'] . "'>
" . _xsrfProtect() . "</form>
";
