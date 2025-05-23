<?php

use Sunlight\Database\Database as DB;
use Sunlight\Database\SimpleTreeFilter;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Post\Post;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\SelectOption;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn()) {
    $_index->unauthorized();
    return;
}

$message = '';
$id = (int) Request::get('id');
$userQuery = User::createQuery('p.author');
$query = DB::queryRow(
    'SELECT p.id,p.home,p.time,p.subject,p.sticky,r.slug forum_slug,r.layout forum_layout,' . $userQuery['column_list']
    . ' FROM ' . DB::table('post') . ' p'
    . ' JOIN ' . DB::table('page') . ' r ON(p.home=r.id)'
    . ' ' . $userQuery['joins']
    . ' WHERE p.id=' . $id . ' AND p.type=' . Post::FORUM_TOPIC . ' AND p.xhome=-1'
);

if ($query !== false) {
    if (isset($query['forum_layout'])) {
        $_index->changeTemplate($query['forum_layout']);
    }

    $_index->backlink = Router::topic($query['id'], $query['forum_slug']);

    if (!Post::checkAccess($userQuery, $query) || !User::hasPrivilege('movetopics')) {
        $_index->unauthorized();
        return;
    }
} else {
    $_index->notFound();
    return;
}

$forums = Page::getFlatTree(null, null, new SimpleTreeFilter(['type' => Page::FORUM]));

// save
if (isset($_POST['new_forum'])) {
    $new_forum_id = (int) Request::post('new_forum');

    if (isset($forums[$new_forum_id]) && $forums[$new_forum_id]['type'] == Page::FORUM) {
        DB::update('post', 'id=' . DB::val($id) . ' OR (type=' . Post::FORUM_TOPIC . ' AND xhome=' . $id . ')', ['home' => $new_forum_id], null);
        $query['home'] = $new_forum_id;
        $_index->backlink = Router::topic($query['id']);
        $message = Message::ok(_lang('mod.movetopic.ok'));
    } else {
        $message = Message::error(_lang('global.badinput'));
    }
}

// output
$_index->title = _lang('mod.movetopic');

// message
$output .= $message;

// form
$output .= '
' . Form::start('movetopic', ['action' => Router::module('movetopic', ['query' => ['id' => $id]])]) . '
' . Message::warning(_lang('mod.movetopic.text', ['%topic%' => $query['subject']]), true) . '
<p>
';

$choices = [];
if (empty($forums)) {
    $choices[] = new SelectOption('-1', _lang('mod.movetopic.noforums'));
} else {
    foreach ($forums as $forum_id => $forum) {
        $choices[] = new SelectOption(
            $forum_id,
            str_repeat('&nbsp;&nbsp;&nbsp;│&nbsp;', $forum['node_level']) . $forum['title'],
            ['disabled' => $forum['type'] != Page::FORUM],
            false
        );
    }
}

$output .= Form::select('new_forum', $choices, $query['home'], ['disabled' => empty($forums)]) . '
' . Form::input('submit', null, _lang('mod.movetopic.submit')) . '
</p>
' . Form::end('movetopic') . '
';
