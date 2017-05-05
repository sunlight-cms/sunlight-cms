<?php

if (!defined('_root')) {
    exit;
}

if (!_login) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava promennych  --- */

$message = "";
$id = (int) _get('id');
$userQuery = _userQuery('p.author');
$query = DB::query("SELECT p.id,p.home,p.time,p.subject,p.sticky,r.slug forum_slug," . $userQuery['column_list'] . " FROM " . _posts_table . " p JOIN " . _root_table . " r ON(p.home=r.id) " . $userQuery['joins'] . " WHERE p.id=" . $id . " AND p.type=5 AND p.xhome=-1");
if (DB::size($query) != 0) {
    $query = DB::row($query);
    $_index['backlink'] = _linkTopic($query['id'], $query['forum_slug']);
    if (!_postAccess($userQuery, $query) || !_priv_movetopics) {
        $_index['is_accessible'] = false;
        return;
    }
} else {
    $_index['is_found'] = false;
    return;
}

$forums = Sunlight\Page\PageManager::getFlatTree(null, null, new Sunlight\Database\SimpleTreeFilter(array('type' => _page_forum)));

/* ---  ulozeni  --- */

if (isset($_POST['new_forum'])) {
    $new_forum_id = (int) _post('new_forum');
    if (isset($forums[$new_forum_id]) && $forums[$new_forum_id]['type'] == _page_forum) {
        DB::query("UPDATE " . _posts_table . " SET home=" . $new_forum_id . " WHERE id=" . $id . " OR (type=5 AND xhome=" . $id . ")");
        $query['home'] = $new_forum_id;
        $_index['backlink'] = _linkTopic($query['id']);
        $message = _msg(_msg_ok, $_lang['mod.movetopic.ok']);
    } else {
        $message = _msg(_msg_err, $_lang['global.badinput']);
    }
}

/* ---  vystup  --- */

$_index['title'] = $_lang['mod.movetopic'];

// zprava
$output .= $message;

// formular
$furl = _linkModule('movetopic', 'id=' . $id);

$output .= '
<form action="' . $furl . '" method="post">
' . _msg(_msg_warn, sprintf($_lang['mod.movetopic.text'], $query['subject'])) . '
<p>
<select name="new_forum"' . (empty($forums) ? " disabled" : '') . '>
';

if (empty($forums)) {
    $output .= "<option value='-1'>" . $_lang['mod.movetopic.noforums'] . "</option>\n";
} else {
    foreach($forums as $forum_id => $forum) {
        $output .= '<option'
            . " value='" . $forum_id . "'"
            . (_page_forum != $forum['type'] ? " disabled" : '')
            . ($forum_id == $query['home'] ? " selected" : '')
            . ">"
            . str_repeat('&nbsp;&nbsp;&nbsp;│&nbsp;', $forum['node_level'])
            . $forum['title']
            . "</option>\n"
        ;
    }
}

$output .= '</select>
<input type="submit" value="' . $_lang['mod.movetopic.submit'] . '">
</p>
' . _xsrfProtect() . '</form>
';
