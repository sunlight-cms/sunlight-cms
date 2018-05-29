<?php

use Sunlight\Database\Database as DB;
use Sunlight\Database\SimpleTreeFilter;
use Sunlight\Page\PageManager;

defined('_root') or exit;

if (!_logged_in) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava promennych  --- */

$message = "";
$id = (int) \Sunlight\Util\Request::get('id');
$userQuery = \Sunlight\User::createQuery('p.author');
$query = DB::queryRow("SELECT p.id,p.home,p.time,p.subject,p.sticky,r.slug forum_slug," . $userQuery['column_list'] . " FROM " . _posts_table . " p JOIN " . _root_table . " r ON(p.home=r.id) " . $userQuery['joins'] . " WHERE p.id=" . $id . " AND p.type=" . _post_forum_topic . " AND p.xhome=-1");
if ($query !== false) {
    $_index['backlink'] = \Sunlight\Router::topic($query['id'], $query['forum_slug']);
    if (!\Sunlight\Post::checkAccess($userQuery, $query) || !_priv_movetopics) {
        $_index['is_accessible'] = false;
        return;
    }
} else {
    $_index['is_found'] = false;
    return;
}

$forums = PageManager::getFlatTree(null, null, new SimpleTreeFilter(array('type' => _page_forum)));

/* ---  ulozeni  --- */

if (isset($_POST['new_forum'])) {
    $new_forum_id = (int) \Sunlight\Util\Request::post('new_forum');
    if (isset($forums[$new_forum_id]) && $forums[$new_forum_id]['type'] == _page_forum) {
        DB::update(_posts_table, 'id=' . DB::val($id) . ' OR (type=' . _post_forum_topic . ' AND xhome=' . $id . ')', array('home' => $new_forum_id));
        $query['home'] = $new_forum_id;
        $_index['backlink'] = \Sunlight\Router::topic($query['id']);
        $message = \Sunlight\Message::render(_msg_ok, _lang('mod.movetopic.ok'));
    } else {
        $message = \Sunlight\Message::render(_msg_err, _lang('global.badinput'));
    }
}

/* ---  vystup  --- */

$_index['title'] = _lang('mod.movetopic');

// zprava
$output .= $message;

// formular
$furl = \Sunlight\Router::module('movetopic', 'id=' . $id);

$output .= '
<form action="' . $furl . '" method="post">
' . \Sunlight\Message::render(_msg_warn, sprintf(_lang('mod.movetopic.text'), $query['subject'])) . '
<p>
<select name="new_forum"' . (empty($forums) ? " disabled" : '') . '>
';

if (empty($forums)) {
    $output .= "<option value='-1'>" . _lang('mod.movetopic.noforums') . "</option>\n";
} else {
    foreach($forums as $forum_id => $forum) {
        $output .= '<option'
            . " value='" . $forum_id . "'"
            . ($forum['type'] != _page_forum ? " disabled" : '')
            . ($forum_id == $query['home'] ? " selected" : '')
            . ">"
            . str_repeat('&nbsp;&nbsp;&nbsp;│&nbsp;', $forum['node_level'])
            . $forum['title']
            . "</option>\n";
    }
}

$output .= '</select>
<input type="submit" value="' . _lang('mod.movetopic.submit') . '">
</p>
' . \Sunlight\Xsrf::getInput() . '</form>
';
