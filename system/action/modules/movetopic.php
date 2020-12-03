<?php

use Sunlight\Database\Database as DB;
use Sunlight\Database\SimpleTreeFilter;
use Sunlight\Message;
use Sunlight\Page\PageManager;
use Sunlight\Comment\Comment;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

if (!_logged_in) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava promennych  --- */

$message = "";
$id = (int) Request::get('id');
$userQuery = User::createQuery('p.author');
$query = DB::queryRow("SELECT p.id,p.home,p.time,p.subject,p.sticky,r.slug forum_slug,r.layout forum_layout," . $userQuery['column_list'] . " FROM " . _comment_table . " p JOIN " . _page_table . " r ON(p.home=r.id) " . $userQuery['joins'] . " WHERE p.id=" . $id . " AND p.type=" . _post_forum_topic . " AND p.xhome=-1");
if ($query !== false) {
    if (isset($query['forum_layout'])) {
        Template::change($query['forum_layout']);
    }

    $_index['backlink'] = Router::topic($query['id'], $query['forum_slug']);
    if (!Comment::checkAccess($userQuery, $query) || !_priv_movetopics) {
        $_index['is_accessible'] = false;
        return;
    }
} else {
    $_index['is_found'] = false;
    return;
}

$forums = PageManager::getFlatTree(null, null, new SimpleTreeFilter(['type' => _page_forum]));

/* ---  ulozeni  --- */

if (isset($_POST['new_forum'])) {
    $new_forum_id = (int) Request::post('new_forum');
    if (isset($forums[$new_forum_id]) && $forums[$new_forum_id]['type'] == _page_forum) {
        DB::update(_comment_table, 'id=' . DB::val($id) . ' OR (type=' . _post_forum_topic . ' AND xhome=' . $id . ')', ['home' => $new_forum_id]);
        $query['home'] = $new_forum_id;
        $_index['backlink'] = Router::topic($query['id']);
        $message = Message::ok(_lang('mod.movetopic.ok'));
    } else {
        $message = Message::error(_lang('global.badinput'));
    }
}

/* ---  vystup  --- */

$_index['title'] = _lang('mod.movetopic');

// zprava
$output .= $message;

// formular
$output .= '
<form action="' . _e(Router::module('movetopic', 'id=' . $id)) . '" method="post">
' . Message::warning(sprintf(_lang('mod.movetopic.text'), $query['subject']), true) . '
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
' . Xsrf::getInput() . '</form>
';
