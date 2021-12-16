<?php

use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Post\Post;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn()) {
    $_index->unauthorized();
    return;
}

/* ---  priprava promennych  --- */

$success = false;
$message = '';
$unlock = '';
$id = (int) Request::get('id');
$userQuery = User::createQuery('p.author');
$query = DB::queryRow("SELECT p.id,p.time,p.subject,p.locked,r.slug forum_slug,r.layout forum_layout," . $userQuery['column_list'] . " FROM " . DB::table('post') . " p JOIN " . DB::table('page') . " r ON(p.home=r.id) " . $userQuery['joins'] . " WHERE p.id=" . $id . " AND p.type=" . Post::FORUM_TOPIC . " AND p.xhome=-1");
if ($query !== false) {
    if (isset($query['forum_layout'])) {
        Template::change($query['forum_layout']);
    }

    $_index->backlink = Router::topic($query['id'], $query['forum_slug']);
    if ($query['locked']) {
        $unlock = '2';
    }
    if (!Post::checkAccess($userQuery, $query) || !User::hasPrivilege('locktopics')) {
        $_index->unauthorized();
        return;
    }
} else {
    $_index->notFound();
    return;
}

/* ---  ulozeni  --- */

if (isset($_POST['doit'])) {
    DB::update('post', 'id=' . DB::val($id), ['locked' => (($query['locked'] == 1) ? 0 : 1)]);
    $message = Message::ok(_lang('mod.locktopic.ok' . $unlock));
    $success = true;
}

/* ---  vystup  --- */

$_index->title = _lang('mod.locktopic' . $unlock);

// zprava
$output .= $message;

// formular
if (!$success) {
    $output .= '
    <form action="' . _e(Router::module('locktopic', ['query' => ['id' => $id]])) . '" method="post">
    ' . Message::warning(_lang('mod.locktopic.text' . $unlock, ['%topic%' => $query['subject']]), true) . '
    <input type="submit" name="doit" value="' . _lang('mod.locktopic.submit' . $unlock) . '">
    ' . Xsrf::getInput() . '</form>
    ';
}
