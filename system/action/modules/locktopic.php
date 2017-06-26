<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
}

if (!_login) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava promennych  --- */

$success = false;
$message = '';
$unlock = '';
$id = (int) _get('id');
$userQuery = _userQuery('p.author');
$query = DB::queryRow("SELECT p.id,p.time,p.subject,p.locked,r.slug forum_slug," . $userQuery['column_list'] . " FROM " . _posts_table . " p JOIN " . _root_table . " r ON(p.home=r.id) " . $userQuery['joins'] . " WHERE p.id=" . $id . " AND p.type=" . _post_forum_topic . " AND p.xhome=-1");
if ($query !== false) {
    $_index['backlink'] = _linkTopic($query['id'], $query['forum_slug']);
    if ($query['locked']) {
        $unlock = '2';
    }
    if (!_postAccess($userQuery, $query) || !_priv_locktopics) {
        $_index['is_accessible'] = false;
        return;
    }
} else {
    $_index['is_found'] = false;
    return;
}

/* ---  ulozeni  --- */

if (isset($_POST['doit'])) {
    DB::update(_posts_table, 'id=' . DB::val($id), array('locked' => (($query['locked'] == 1) ? 0 : 1)));
    $message = _msg(_msg_ok, _lang('mod.locktopic.ok' . $unlock));
    $success = true;
}

/* ---  vystup  --- */

$_index['title'] = _lang('mod.locktopic' . $unlock);

// zprava
$output .= $message;

// formular
if (!$success) {
    $furl = _linkModule('locktopic', 'id=' . $id);

    $output .= '
    <form action="' . $furl . '" method="post">
    ' . _msg(_msg_warn, sprintf(_lang('mod.locktopic.text' . $unlock), $query['subject'])) . '
    <input type="submit" name="doit" value="' . _lang('mod.locktopic.submit' . $unlock) . '">
    ' . _xsrfProtect() . '</form>
    ';
}
