<?php

use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\PostForm;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Xsrf;

defined('_root') or exit;

if (!_logged_in) {
    $_index['is_accessible'] = false;
    return;
}

if (!_messages) {
    $_index['is_found'] = false;
    return;
}

/* ---  priprava promennych  --- */

if (isset($_GET['a'])) {
    $a = strval(Request::get('a'));
} else {
    $a = 'list';
}

/* ---  modul  --- */

$_index['title'] = _lang('mod.messages');
$list = false;
$mod_title = 'mod.messages';
$message = '';

// obsah
switch ($a) {

        /* ---  nova zprava  --- */
    case 'new':

        // titulek
        $mod_title = 'mod.messages.new';

        // odeslani
        if (isset($_POST['receiver'])) {

            // nacteni dat
            $receiver = Request::post('receiver');
            $subject = Html::cut(_e(StringManipulator::trimExtraWhitespace(Request::post('subject'))), 48);
            $text = Html::cut(_e(trim(Request::post('text'))), 16384);

            // kontrola a odeslani
            do {

                /* ---  kontrola  --- */

                // text
                if ($text === '') {
                    $message = Message::warning(_lang('mod.messages.error.notext'));
                    break;
                }

                // predmet
                if ($subject === '') {
                    $message = Message::warning(_lang('mod.messages.error.nosubject'));
                    break;
                }

                // prijemce
                if ($receiver !== '') {
                    $rq = DB::queryRow('SELECT usr.id AS usr_id,usr.blocked AS usr_blocked, ugrp.blocked AS ugrp_blocked FROM ' . _user_table . ' AS usr JOIN ' . _user_group_table . ' AS ugrp ON (usr.group_id=ugrp.id) WHERE usr.username=' . DB::val($receiver) . ' OR usr.publicname=' . DB::val($receiver));
                } else {
                    $rq = false;
                }
                if ($rq === false || $rq['usr_id'] == _user_id) {
                    $message = Message::warning(_lang('mod.messages.error.badreceiver'));
                    break;
                } elseif ($rq['usr_blocked'] || $rq['ugrp_blocked']) {
                    $message = Message::warning(_lang('mod.messages.error.blockedreceiver'));
                    break;
                }

                // anti spam limit
                if (!IpLog::check(_iplog_anti_spam)) {
                    $message = Message::warning(_lang('misc.requestlimit', ['%postsendexpire%' => _postsendexpire]));
                    break;
                }

                /* ---  vse ok, odeslani  --- */

                // zaznam v logu
                if (!_priv_unlimitedpostaccess) {
                    IpLog::update(_iplog_anti_spam);
                }

                // extend
                Extend::call('mod.messages.new', [
                    'receiver' => $rq['usr_id'],
                    'subject' => &$subject,
                    'text' => &$text,
                ]);

                // vlozeni do pm tabulky
                $pm_id = DB::insert(_pm_table, [
                    'sender' => _user_id,
                    'sender_readtime' => time(),
                    'sender_deleted' => 0,
                    'receiver' => $rq['usr_id'],
                    'receiver_readtime' => 0,
                    'receiver_deleted' => 0,
                    'update_time' => time()
                ], true);

                // vlozeni do posts tabulky
                $insert_id = DB::insert(_comment_table, $post_data = [
                    'type' => _post_pm,
                    'home' => $pm_id,
                    'xhome' => -1,
                    'subject' => $subject,
                    'text' => $text,
                    'author' => _user_id,
                    'guest' => '',
                    'time' => time(),
                    'ip' => _user_ip,
                    'bumptime' => 0
                ], true);
                Extend::call('posts.new', ['id' => $insert_id, 'posttype' => _post_pm, 'post' => $post_data]);

                // presmerovani a konec
                $_index['redirect_to'] = Router::module('messages', 'a=list&read=' . $pm_id, true);

                return;

            } while (false);

        }

        // formular
        $inputs = [];
        $inputs[] = ['label' => _lang('mod.messages.receiver'), 'content' => "<input type='text' class='inputsmall' maxlength='24'" . Form::restorePostValueAndName('receiver', Request::get('receiver')) . ">"];
        $inputs[] = ['label' => _lang('posts.subject'), 'content' => "<input type='text' class='inputmedium' maxlength='48'" . Form::restorePostValueAndName('subject', Request::get('subject')) . ">"];
        $inputs[] = ['label' => _lang('mod.messages.message'), 'content' => "<textarea class='areamedium' rows='5' cols='33' name='text'>" . Form::restorePostValue('text', null, false) . "</textarea>", 'top' => true];
        $inputs[] = ['label' => '', 'content' => PostForm::renderControls('newmsg', 'text')];

        // form
        $output .= $message. Form::render(
            [
                'name' => 'newmsg',
                'action' => '',
                'submit_append' => ' ' . PostForm::renderPreviewButton('newmsg', 'text'),
                'form_append' => GenericTemplates::jsLimitLength(16384, 'newmsg', 'text')
            ],
            $inputs
        );

        break;

        /* ---  vypis  --- */
    default:

        // cteni vzkazu
        if (isset($_GET['read'])) {

            // promenne
            $id = (int) Request::get('read');

            // nacist data
            $senderUserQuery = User::createQuery('pm.sender', 'sender_', 'su');
            $receiverUserQuery = User::createQuery('pm.receiver', 'receiver_', 'ru');
            $q = DB::queryRow('SELECT pm.*,p.id post_id,p.subject,p.time,p.text,p.guest,p.ip' . Extend::buffer('posts.columns') . ',' . $senderUserQuery['column_list'] . ',' . $receiverUserQuery['column_list'] . ' FROM ' . _pm_table . ' AS pm JOIN ' . _comment_table . ' AS p ON (p.type=' . _post_pm . ' AND p.home=pm.id AND p.xhome=-1) ' . $senderUserQuery['joins'] . ' ' . $receiverUserQuery['joins'] . ' WHERE pm.id=' . $id . ' AND (sender=' . _user_id . ' AND sender_deleted=0 OR receiver=' . _user_id . ' AND receiver_deleted=0)');
            if ($q === false) {
                $output .= Message::error(_lang('global.badinput'));
                break;
            }

            // titulek
            $mod_title = 'mod.messages.read';

            // stavy
            $locked = ($q['sender_deleted'] || $q['receiver_deleted']);
            [$role, $role_other] = (($q['sender'] == _user_id) ? ['sender', 'receiver'] : ['receiver', 'sender']);

            // spocitat neprectene zpravy
            $unread_count = DB::count(_comment_table, 'home=' . DB::val($q['id']) . ' AND type=' . _post_pm . ' AND author=' . _user_id . ' AND time>' . $q[$role_other . '_readtime']);

            // vystup
            $output .= "<div class=\"topic\">\n";
            $output .= "<h2>" . _lang('mod.messages.message') . ": " . $q['subject'] . "</h2>\n";
            $output .= CommentService::renderPost(['id' => $q['post_id'], 'author' => $q['sender']] + $q, $senderUserQuery, [
                'post_link' => false,
                'allow_reply' => false,
            ]);
            $output .= "</div>\n";

            $output .= CommentService::render(CommentService::RENDER_PM_LIST, $q['id'], [$locked, $unread_count], false, Router::module('messages', 'a=list&read=' . $q['id']));

            // aktualizace casu precteni
            DB::update(_pm_table, 'id=' . DB::val($id), [$role . '_readtime' => time()]);

            break;
        }

        // je vypis
        $list = true;

        // smazani vzkazu
        if (isset($_POST['action'])) {

            // promenne
            $do_delete = false;
            $delcond = null;
            $delcond_sadd = null;
            $delcond_radd = null;
            $selected_ids = (array) Request::post('msg', [], true);

            // funkce
            $deletePms = function ($cond = null, $sender_cond = null, $receiver_cond = null) {
                $q = DB::query('SELECT id,sender,sender_deleted,receiver,receiver_deleted FROM ' . _pm_table . ' WHERE (sender=' . _user_id . ' AND sender_deleted=0' . (isset($sender_cond) ? ' AND ' . $sender_cond : '') . ' OR receiver=' . _user_id . ' AND receiver_deleted=0' . (isset($receiver_cond) ? ' AND ' . $receiver_cond : '') . ')' . ((isset($cond)) ? ' AND ' . $cond : ''));
                $del_list = [];

                while ($r = DB::row($q)) {
                    // zjisteni roli
                    [$role, $role_other] = (($r['sender'] == _user_id) ? ['sender', 'receiver'] : ['receiver', 'sender']);

                    // smazani nebo oznaceni
                    if ($r[$role_other . '_deleted']) {
                        // druha strana jiz smazala, smazat uplne
                        $del_list[] = $r['id'];
                    } else {
                        // pouze oznacit
                        DB::update(_pm_table, 'id=' . $r['id'], [$role . '_deleted' => 1]);
                    }
                }

                // fyzicke vymazani
                if (!empty($del_list)) {
                    DB::query('DELETE ' . _pm_table . ',post FROM ' . _pm_table . ' JOIN ' . _comment_table . ' AS post ON (post.type=' . _post_pm . ' AND post.home=' . _pm_table . '.id) WHERE ' . _pm_table . '.id IN(' . DB::arr($del_list) . ')');
                }
            };

            // akce
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
                            . ' FROM ' . _pm_table . ' AS pm'
                            . ' JOIN ' . _comment_table . ' AS last_post ON (last_post.id = (SELECT id FROM ' . _comment_table . ' WHERE type=' . _post_pm . ' AND home=pm.id ORDER BY id DESC LIMIT 1))'
                            . ' WHERE pm.id IN(' . DB::arr($selected_ids) . ') AND (pm.sender=' . _user_id . ' AND pm.sender_deleted=0 OR pm.receiver=' . _user_id . ' AND pm.receiver_deleted=0)'
                            . ' AND last_post.author!=' . _user_id
                        );
                        $changesets = [];
                        $now = time();
                        while ($r = DB::row($q)) {
                            $role = $r['sender'] == _user_id ? 'sender' : 'receiver';
                            $changesets[$r['id']][$role . '_readtime'] = $r['last_post_time'] - 1;
                        }
                        DB::updateSetMulti(_pm_table, 'id', $changesets);
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

        // strankovani
        $paging = Paginator::render($_index['url'], _messagesperpage, _pm_table, 'sender=' . _user_id . ' OR receiver=' . _user_id, '&amp;a=' . $a);
        if (Paginator::atTop()) {
            $output .= $paging['paging'];
        }

        // tabulka
        $output .= $message . "
        <form method='post' action=''>
<p class='messages-menu'>
    <a class='button' href='" . _e(Router::module('messages', 'a=new')) . "'><img src='" . Template::image('icons/bubble.png') . "' alt='new' class='icon'>" . _lang('mod.messages.new') . "</a>
</p>

<table class='messages-table'>
<thead>
<tr>
    <td><input type='checkbox' name='selector' onchange=\"var that=this;$('table.messages-table input').each(function(){this.checked=that.checked;});\"></td>
    <th>" . _lang('mod.messages.message') . "</th>
    <th>" . _lang('global.user') . "</th>
    <th>" . _lang('mod.messages.time.update') . "</th>
</tr>
</thead>
<tbody>\n";
        $senderUserQuery = User::createQuery('pm.sender', 'sender_', 'su');
        $receiverUserQuery = User::createQuery('pm.receiver', 'receiver_', 'ru');
        $q = DB::query(
            'SELECT pm.id,pm.sender,pm.receiver,pm.sender_readtime,pm.receiver_readtime,pm.update_time,post.subject'
            . ',' . $senderUserQuery['column_list'] . ',' . $receiverUserQuery['column_list']
            . ',(SELECT COUNT(*) FROM ' . _comment_table . ' AS countpost WHERE countpost.home=pm.id AND countpost.type=' . _post_pm . ' AND countpost.author=' . _user_id . ' AND (pm.sender=' . _user_id . ' AND countpost.time>pm.receiver_readtime OR pm.receiver=' . _user_id . ' AND countpost.time>pm.sender_readtime)) AS unread_counter'
            . ' FROM ' . _pm_table . ' AS pm'
            . ' JOIN ' . _comment_table . ' AS post ON (post.home=pm.id AND post.type=' . _post_pm . ' AND post.xhome=-1)'
            . ' ' . $senderUserQuery['joins']
            . ' ' . $receiverUserQuery['joins']
            . ' WHERE pm.sender=' . _user_id . ' AND pm.sender_deleted=0 OR pm.receiver=' . _user_id . ' AND pm.receiver_deleted=0'.
            ' ORDER BY pm.update_time DESC '
            . $paging['sql_limit']
        );
        while ($r = DB::row($q)) {
            $read = ($r['sender'] == _user_id && $r['sender_readtime'] >= $r['update_time'] || $r['receiver'] == _user_id && $r['receiver_readtime'] >= $r['update_time']);
            $output .= "<tr><td><input type='checkbox' name='msg[]' value='" . $r['id'] . "'></td><td><a href='" . _e(Router::module('messages', 'a=list&read=' . $r['id'])) . "'" . ($read ? '' : ' class="notread"') . ">" . $r['subject'] . "</a></td><td>" . Router::userFromQuery($r['sender'] == _user_id ? $receiverUserQuery : $senderUserQuery, $r) . " <small>(" . $r['unread_counter'] . ")</small></td><td>" . GenericTemplates::renderTime($r['update_time'], 'post') . "</td></tr>\n";
        }
        if (!isset($read)) {
            $output .= "<tr><td colspan='4'>" . _lang('mod.messages.nokit') . "</td></tr>\n";
        }

        $output .= "</tbody><tfoot>
<tr><td colspan='4'>
    <div class='hr messages-hr'><hr></div>
    <select name='action'>
    <option value='1'>" . _lang('mod.messages.delete.selected') . "</option>
    <option value='2'>" . _lang('mod.messages.mark.selected') . "</option>
    <option value='3'>" . _lang('mod.messages.delete.read') . "</option>
    <option value='4'>" . _lang('mod.messages.delete.all') . "</option>
    </select>
    <input type='submit' value='" . _lang('global.do') . "' onclick='return Sunlight.confirm();'>
</td></tr>
</tfoot>
</table>
" . Xsrf::getInput() . "</form>\n";

        // strankovani dole
        if (Paginator::atBottom()) {
            $output .= $paging['paging'];
        }

        break;

}

// zpetny odkaz
if (!$list) {
    $_index['backlink'] = Router::module('messages');
}
