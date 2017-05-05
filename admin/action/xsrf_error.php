<?php

if (!defined('_root')) {
    exit;
}

/* --- vystup --- */

$admin_title = $_lang['xsrf.title'];

$admin_output .= "<h1>" . $_lang['xsrf.title'] . "</h1>\n";
$admin_output .= _msg(_msg_err, $_lang['xsrf.msg']);
$admin_output .= _postRepeatForm();
