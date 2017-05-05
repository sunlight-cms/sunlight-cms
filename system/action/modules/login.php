<?php

if (!defined('_root')) {
    exit;
}

/* ---  vystup  --- */

$_index['title'] = $_lang['login.title'];

$output .= _userLoginForm(true);

// moznosti
if (_login) {
    $output .= "<h2>" . $_lang['global.choice'] . "</h2>\n<ul>\n";

    // pole polozek (adresa, titulek, podminky pro zobrazeni)
    $items = array(
        array(_linkModule('settings'), $_lang['mod.settings'], true),
        array(_linkModule('profile', 'id=' . _loginname), $_lang['mod.settings.profilelink'], true),
        array(_linkModule('messages'), $_lang['mod.messages'] . " [" . _userGetUnreadPmCount() . "]", _messages),
        array("admin/", $_lang['global.admintitle'], _priv_administration)
    );

    // vypis
    foreach ($items as $item) {
        if ($item[2]) {
            $output .= "<li><a href='" . $item[0] . "'>" . $item[1] . "</a></li>\n";
        }
    }

    $output .= "</ul>\n";
}
