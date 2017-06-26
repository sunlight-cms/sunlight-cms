<?php

use Sunlight\Admin\PageLister;
use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
}

/* ---  priprava  --- */

$message = "";

/* ---  akce  --- */

if (isset($_POST['title']) && is_array($_POST['title'])) {
    foreach ($_POST['title'] as $id => $title) {
        $id = (int) $id;
        $title = _e(trim($title));
        if ($title == "") {
            $title = _lang('global.novalue');
        }
        DB::update(_root_table, 'id=' . DB::val($id), array('title' => $title));
    }

    $message = _msg(_msg_ok, _lang('global.saved'));
}

/* ---  vystup  --- */

$output .= $message . "

<form action='index.php?p=content-titles' method='post'>
";

$output .= PageLister::render(array(
    'mode' => PageLister::MODE_SINGLE_LEVEL,
    'links' => false,
    'actions' => false,
    'breadcrumbs' => false,
    'title_editable' => true,
    'type' => true,
));

$output .= "
    <p>
        <input type='submit' value='" . _lang('global.save') . "'>
        <input type='reset' value='" . _lang('global.reset') . "' onclick='return Sunlight.confirm();'>
    </p>
" . _xsrfProtect() . "</form>";
