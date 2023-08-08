<?php

use Sunlight\Core;
use Sunlight\Post\Post;
use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\PostForm;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

if (!Settings::get('messages')) {
    $_index->notFound();
    return;
}

if (!User::isLoggedIn()) {
    $_index->unauthorized();
    return;
}

if (isset($_GET['a'])) {
    $a = strval(Request::get('a'));
} else {
    $a = 'list';
}

$list = false;
$message = '';

switch ($a) {
    // new message
    case 'new':
        // title
        $_index->title = _lang('mod.messages.new');

        // send message
        if (isset($_POST['receiver'])) {
            $receiver = Request::post('receiver');
            $subject = Html::cut(_e(StringHelper::trimExtraWhitespace(Request::post('subject'))), 48);
            $text = Html::cut(_e(trim(Request::post('text', ''))), Post::getMaxLength(Post::PRIVATE_MSG));

            // check variables
            do {
                // text
                if ($text === '') {
                    $message = Message::warning(_lang('mod.messages.error.notext'));
                    break;
                }

                // subject
                if ($subject === '') {
                    $message = Message::warning(_lang('mod.messages.error.nosubject'));
                    break;
                }

                // receiver
                if ($receiver !== '') {
                    $rq = DB::queryRow(
                        'SELECT usr.id AS usr_id,usr.blocked AS usr_blocked, ugrp.blocked AS ugrp_blocked'
                        . ' FROM ' . DB::table('user') . ' AS usr'
                        . ' JOIN ' . DB::table('user_group') . ' AS ugrp ON (usr.group_id=ugrp.id)'
                        . ' WHERE usr.username=' . DB::val($receiver) . ' OR usr.publicname=' . DB::val($receiver)
                    );
                } else {
                    $rq = false;
                }

                if ($rq === false || User::equals($rq['usr_id'])) {
                    $message = Message::warning(_lang('mod.messages.error.badreceiver'));
                    break;
                }

                if ($rq['usr_blocked'] || $rq['ugrp_blocked']) {
                    $message = Message::warning(_lang('mod.messages.error.blockedreceiver'));
                    break;
                }

                // check IP log
                if (!User::hasPrivilege('unlimitedpostaccess') && !IpLog::check(IpLog::ANTI_SPAM)) {
                    $message = Message::warning(_lang('error.antispam', ['%antispamtimeout%' => Settings::get('antispamtimeout')]));
                    break;
                }

                // update IP log
                if (!User::hasPrivilege('unlimitedpostaccess')) {
                    IpLog::update(IpLog::ANTI_SPAM);
                }

                // extend
                Extend::call('mod.messages.new', [
                    'receiver' => $rq['usr_id'],
                    'subject' => &$subject,
                    'text' => &$text,
                ]);

                // create PM
                $pm_id = DB::insert('pm', [
                    'sender' => User::getId(),
                    'sender_readtime' => time(),
                    'sender_deleted' => 0,
                    'receiver' => $rq['usr_id'],
                    'receiver_readtime' => 0,
                    'receiver_deleted' => 0,
                    'update_time' => time()
                ], true);

                // create post
                $insert_id = DB::insert('post', $post_data = [
                    'type' => Post::PRIVATE_MSG,
                    'home' => $pm_id,
                    'xhome' => -1,
                    'subject' => $subject,
                    'text' => $text,
                    'author' => User::getId(),
                    'guest' => '',
                    'time' => time(),
                    'ip' => Core::getClientIp(),
                    'bumptime' => 0
                ], true);
                Extend::call('posts.new', ['id' => $insert_id, 'posttype' => Post::PRIVATE_MSG, 'post' => $post_data]);

                // redirect
                $_index->redirect(Router::module('messages', ['query' => ['a' => 'list', 'read' => $pm_id], 'absolute' => true]));

                return;
            } while (false);
        }

        // form
        $inputs = [];
        $inputs[] = ['label' => _lang('mod.messages.receiver'), 'content' => '<input type="text" class="inputsmall" maxlength="24"' . Form::restorePostValueAndName('receiver', Request::get('receiver')) . '>'];
        $inputs[] = ['label' => _lang('posts.subject'), 'content' => '<input type="text" class="inputmedium" maxlength="48"' . Form::restorePostValueAndName('subject', Request::get('subject')) . '>'];
        $inputs[] = ['label' => _lang('mod.messages.message'), 'content' => '<textarea class="areamedium" rows="5" cols="33" name="text">' . Form::restorePostValue('text', null, false) . '</textarea>', 'top' => true];
        $inputs[] = ['label' => '', 'content' => PostForm::renderControls('newmsg', 'text')];
        $inputs[] = Form::getSubmitRow(['append' => ' ' . PostForm::renderPreviewButton('newmsg', 'text')]);

        $output .= $message. Form::render(
            [
                'name' => 'newmsg',
                'action' => '',
                'form_append' => GenericTemplates::jsLimitLength(Post::getMaxLength(Post::PRIVATE_MSG), 'newmsg', 'text'),
            ],
            $inputs
        );
        break;

    // list
    default:
        // show a single message?
        if (isset($_GET['read'])) {
            // load data
            $id = (int) Request::get('read');
            $senderUserQuery = User::createQuery('pm.sender', 'sender_', 'su');
            $receiverUserQuery = User::createQuery('pm.receiver', 'receiver_', 'ru');
            $q = DB::queryRow(
                'SELECT pm.*,p.id post_id,p.subject,p.time,p.text,p.guest,p.ip' . Extend::buffer('posts.columns') . ',' . $senderUserQuery['column_list'] . ',' . $receiverUserQuery['column_list']
                . ' FROM ' . DB::table('pm') . ' AS pm'
                . ' JOIN ' . DB::table('post') . ' AS p ON (p.type=' . Post::PRIVATE_MSG . ' AND p.home=pm.id AND p.xhome=-1)'
                . ' ' . $senderUserQuery['joins']
                . ' ' . $receiverUserQuery['joins']
                . ' WHERE pm.id=' . $id . ' AND (sender=' . User::getId() . ' AND sender_deleted=0 OR receiver=' . User::getId() . ' AND receiver_deleted=0)'
            );

            if ($q === false) {
                $_index->notFound();
                break;
            }

            // states
            $locked = ($q['sender_deleted'] || $q['receiver_deleted']);
            [$role, $role_other] = (User::equals($q['sender']) ? ['sender', 'receiver'] : ['receiver', 'sender']);

            // count unread messages
            $unread_count = DB::count('post', 'home=' . DB::val($q['id']) . ' AND type=' . Post::PRIVATE_MSG . ' AND author=' . User::getId() . ' AND time>' . $q[$role_other . '_readtime']);

            // output
            $_index->title = _lang('mod.messages.message') . ': ' . $q['subject'];
            $output .= "<div class=\"topic\">\n";
            $output .= PostService::renderPost(['id' => $q['post_id'], 'author' => $q['sender']] + $q, $senderUserQuery, [
                'post_link' => false,
                'allow_reply' => false,
            ]);
            $output .= "</div>\n";

            $output .= PostService::renderList(PostService::RENDER_PM_LIST, $q['id'], [$locked, $unread_count], false, Router::module('messages', ['query' => ['a' => 'list', 'read' => $q['id']]]));

            // update read time
            DB::update('pm', 'id=' . DB::val($id), [$role . '_readtime' => time()]);

            break;
        }

        // list messages
        $_index->title = _lang('mod.messages');
        $list = true;

        // delete messages action
        if (isset($_POST['action'])) {
            $do_delete = false;
            $delcond = null;
            $delcond_sadd = null;
            $delcond_radd = null;
            $selected_ids = (array) Request::post('msg', [], true);

            // functions
            $deletePms = function ($cond = null, $sender_cond = null, $receiver_cond = null) {
                $q = DB::query(
                    'SELECT id,sender,sender_deleted,receiver,receiver_deleted'
                    . ' FROM ' . DB::table('pm')
                    . ' WHERE'
                        . '('
                            . 'sender=' . User::getId() . ' AND sender_deleted=0' . (isset($sender_cond) ? ' AND ' . $sender_cond : '')
                            . ' OR receiver=' . User::getId() . ' AND receiver_deleted=0' . (isset($receiver_cond) ? ' AND ' . $receiver_cond : '')
                        . ')'
                    . ((isset($cond)) ? ' AND ' . $cond : '')
                );
                $del_list = [];

                while ($r = DB::row($q)) {
                    // determine roles
                    [$role, $role_other] = (User::equals($r['sender']) ? ['sender', 'receiver'] : ['receiver', 'sender']);

                    // delete or flag
                    if ($r[$role_other . '_deleted']) {
                        // other side has deleted the message too, delete it for real
                        $del_list[] = $r['id'];
                    } else {
                        // only flag as deleted
                        DB::update('pm', 'id=' . $r['id'], [$role . '_deleted' => 1]);
                    }
                }

                // delete messages
                if (!empty($del_list)) {
                    DB::query(
                        'DELETE ' . DB::table('pm') . ',post FROM ' . DB::table('pm')
                        . ' JOIN ' . DB::table('post') . ' AS post ON (post.type=' . Post::PRIVATE_MSG . ' AND post.home=' . DB::table('pm') . '.id)'
                        . ' WHERE ' . DB::table('pm') . '.id IN(' . DB::arr($del_list) . ')'
                    );
                }
            };

            // action
            switch (Request::post('action')) {
                case 1:
                    if (!empty($selected_ids)) {
                        $deletePms('id IN(' . DB::arr($selected_ids) . ')');
                        $message = Message::ok(_lang('mod.messages.delete.done'));
                    }
                    break;

                case 2:
                    if (!empty($selected_ids)) {
                        $q = DB::query(
                            'SELECT pm.id,pm.sender,pm.receiver,last_post.time AS last_post_time'
                            . ' FROM ' . DB::table('pm') . ' AS pm'
                            . ' JOIN ' . DB::table('post') . ' AS last_post ON (last_post.id = (SELECT id FROM ' . DB::table('post') . ' WHERE type=' . Post::PRIVATE_MSG . ' AND home=pm.id ORDER BY id DESC LIMIT 1))'
                            . ' WHERE pm.id IN(' . DB::arr($selected_ids) . ') AND (pm.sender=' . User::getId() . ' AND pm.sender_deleted=0 OR pm.receiver=' . User::getId() . ' AND pm.receiver_deleted=0)'
                            . ' AND last_post.author!=' . User::getId()
                        );
                        $changesets = [];
                        $now = time();

                        while ($r = DB::row($q)) {
                            $role = User::equals($r['sender']) ? 'sender' : 'receiver';
                            $changesets[$r['id']][$role . '_readtime'] = $r['last_post_time'] - 1;
                        }

                        DB::updateSetMulti('pm', 'id', $changesets);
                        $message = Message::ok(_lang('global.done'));
                    }
                    break;

                case 3:
                    $deletePms(null, 'sender_readtime>=update_time', 'receiver_readtime>=update_time');
                    $message = Message::ok(_lang('mod.messages.delete.done'));
                    break;

                case 4:
                    $deletePms();
                    $message = Message::ok(_lang('mod.messages.delete.done'));
                    break;
            }
        }

        // paging
        $paging = Paginator::paginateTable(
            $_index->url,
            Settings::get('messagesperpage'),
            DB::table('pm'),
            [
                'cond' => '(sender=' . User::getId() . ' AND sender_deleted=0) OR (receiver=' . User::getId() . ' AND receiver_deleted=0)',
                'link_suffix' => '&a=' . $a,
            ]
        );

        if (Paginator::atTop()) {
            $output .= $paging['paging'];
        }

        // table
        $output .= $message . '
        <form method="post" action="">
<p class="messages-menu">
    <a class="button" href="' . _e(Router::module('messages', ['query' => ['a' => 'new']])) . '"><img src="' . _e(Template::asset('images/icons/bubble.png')) . '" alt="new" class="icon">' . _lang('mod.messages.new') . '</a>
</p>

<table class="messages-table">
<thead>
<tr>
    <td><input type="checkbox" name="selector" onchange="var that=this;$(\'table.messages-table input\').each(function() {this.checked=that.checked;});"></td>
    <th>' . _lang('mod.messages.message') . '</th>
    <th>' . _lang('global.user') . '</th>
    <th>' . _lang('mod.messages.time.update') . "</th>
</tr>
</thead>
<tbody>\n";
        $senderUserQuery = User::createQuery('pm.sender', 'sender_', 'su');
        $receiverUserQuery = User::createQuery('pm.receiver', 'receiver_', 'ru');
        $q = DB::query(
            'SELECT pm.id,pm.sender,pm.receiver,pm.sender_readtime,pm.receiver_readtime,pm.update_time,post.subject'
            . ',' . $senderUserQuery['column_list'] . ',' . $receiverUserQuery['column_list']
            . ',(SELECT COUNT(*) FROM ' . DB::table('post') . ' AS countpost WHERE countpost.home=pm.id AND countpost.type=' . Post::PRIVATE_MSG . ' AND countpost.author=' . User::getId() . ' AND (pm.sender=' . User::getId() . ' AND countpost.time>pm.receiver_readtime OR pm.receiver=' . User::getId() . ' AND countpost.time>pm.sender_readtime)) AS unread_counter'
            . ' FROM ' . DB::table('pm') . ' AS pm'
            . ' JOIN ' . DB::table('post') . ' AS post ON (post.home=pm.id AND post.type=' . Post::PRIVATE_MSG . ' AND post.xhome=-1)'
            . ' ' . $senderUserQuery['joins']
            . ' ' . $receiverUserQuery['joins']
            . ' WHERE pm.sender=' . User::getId() . ' AND pm.sender_deleted=0 OR pm.receiver=' . User::getId() . ' AND pm.receiver_deleted=0'.
            ' ORDER BY pm.update_time DESC '
            . $paging['sql_limit']
        );

        while ($r = DB::row($q)) {
            $read = (User::equals($r['sender']) && $r['sender_readtime'] >= $r['update_time'] || User::equals($r['receiver']) && $r['receiver_readtime'] >= $r['update_time']);
            $output .= '<tr>
    <td><input type="checkbox" name="msg[]" value="' . $r['id'] . '"></td>
    <td><a href="' . _e(Router::module('messages', ['query' => ['a' => 'list', 'read' => $r['id']]])) . '"' . ($read ? '' : ' class="notread"') . '>' . $r['subject'] . '</a></td>
    <td>' . Router::userFromQuery(User::equals($r['sender']) ? $receiverUserQuery : $senderUserQuery, $r) . ' <small>(' . _num($r['unread_counter']) . ')</small></td>
    <td>' . _num($r['update_time'], 'post') . "</td>
</tr>\n";
        }

        if (!isset($read)) {
            $output .= '<tr><td colspan="4">' . _lang('mod.messages.nokit') . "</td></tr>\n";
        }

        $output .= '</tbody><tfoot>
<tr><td colspan="4">
    <div class="hr messages-hr"><hr></div>
    <select name="action">
    <option value="1">' . _lang('mod.messages.delete.selected') . '</option>
    <option value="2">' . _lang('mod.messages.mark.selected') . '</option>
    <option value="3">' . _lang('mod.messages.delete.read') . '</option>
    <option value="4">' . _lang('mod.messages.delete.all') . '</option>
    </select>
    <input type="submit" value="' . _lang('global.do') . '" onclick="return Sunlight.confirm();">
</td></tr>
</tfoot>
</table>
' . Xsrf::getInput() . "</form>\n";

        // paging at bottom
        if (Paginator::atBottom()) {
            $output .= $paging['paging'];
        }

        break;
}

// backlink
if (!$list) {
    $_index->backlink = Router::module('messages');
}
