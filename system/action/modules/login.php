<?php

defined('_root') or exit;

/* ---  vystup  --- */

$_index['title'] = _lang('login.title');

$output .= _userLoginForm(true);

// moznosti
if (_logged_in) {
    $output .= "<h2>" . _lang('login.links') . "</h2>\n<ul>\n";

    // pole polozek (adresa, titulek, podminky pro zobrazeni)
    $items = array(
        array("admin/", _lang('global.admintitle'), _priv_administration),
        array(_linkModule('profile', 'id=' . _user_name), _lang('mod.profile'), true),
        array(_linkModule('settings'), _lang('mod.settings'), true),
        array(_linkModule('messages'), _lang('mod.messages') . " [" . _userGetUnreadPmCount() . "]", _messages),
    );

    // vypis
    foreach ($items as $item) {
        if ($item[2]) {
            $output .= "<li><a href='" . $item[0] . "'>" . $item[1] . "</a></li>\n";
        }
    }

    $output .= "</ul>\n";
}
