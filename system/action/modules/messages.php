<?php

use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;

if (!defined('_root')) {
    exit;
}

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
    $a = strval(_get('a'));
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
            $receiver = _post('receiver');
            $subject = _cutHtml(_e(_wsTrim(_post('subject'))), 48);
            $text = _cutHtml(_e(trim(_post('text'))), 16384);

            // kontrola a odeslani
            do {

                /* ---  kontrola  --- */

                // text
                if ($text === '') {
                    $message = _msg(_msg_warn, _lang('mod.messages.error.notext'));
                    break;
                }

                // predmet
                if ($subject === '') {
                    $message = _msg(_msg_warn, _lang('mod.messages.error.nosubject'));
                    break;
                }

                // prijemce
                if ($receiver !== '') {
                    $rq = DB::queryRow('SELECT usr.id AS usr_id,usr.blocked AS usr_blocked, ugrp.blocked AS ugrp_blocked FROM ' . _users_table . ' AS usr JOIN ' . _groups_table . ' AS ugrp ON (usr.group_id=ugrp.id) WHERE usr.username=' . DB::val($receiver) . ' OR usr.publicname=' . DB::val($receiver));
                } else {
                    $rq = false;
                }
                if ($rq === false || $rq['usr_id'] == _user_id) {
                    $message = _msg(_msg_warn, _lang('mod.messages.error.badreceiver'));
                    break;
                } elseif ($rq['usr_blocked'] || $rq['ugrp_blocked']) {
                    $message = _msg(_msg_warn, _lang('mod.messages.error.blockedreceiver'));
                    break;
                }

                // anti spam limit
                if (!_iplogCheck(_iplog_anti_spam)) {
                    $message = _msg(_msg_warn, _lang('misc.requestlimit', array('*postsendexpire*' => _postsendexpire)));
                    break;
                }

                /* ---  vse ok, odeslani  --- */

                // zaznam v logu
                if (!_priv_unlimitedpostaccess) {
                    _iplogUpdate(_iplog_anti_spam);
                }

                // extend
                Extend::call('mod.messages.new', array(
                    'receiver' => $rq['usr_id'],
                    'subject' => &$subject,
                    'text' => &$text,
                ));

                // vlozeni do pm tabulky
                $pm_id = DB::insert(_pm_table, array(
                    'sender' => _user_id,
                    'sender_readtime' => time(),
                    'sender_deleted' => 0,
                    'receiver' => $rq['usr_id'],
                    'receiver_readtime' => 0,
                    'receiver_deleted' => 0,
                    'update_time' => time()
                ), true);

                // vlozeni do posts tabulky
                DB::insert(_posts_table, array(
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
                ));

                // presmerovani a konec
                $_index['redirect_to'] = _linkModule('messages', 'a=list&read=' . $pm_id, false, true);

                return;

            } while (false);

        }

        // formular
        $output .= $message . "<form method='post' name='newmsg'>
<table>

<tr>
    <th>" . _lang('mod.messages.receiver') . "</th>
    <td><input type='text' class='inputsmall' maxlength='24'" . _restorePostValueAndName('receiver', _get('receiver')) . "></td>
</tr>

<tr>
    <th>" . _lang('posts.subject') . "</th>
    <td><input type='text' class='inputmedium' maxlength='48'" . _restorePostValueAndName('subject', _get('subject')) . "></td>
</tr>

<tr class='valign-top'>
    <th>" . _lang('mod.messages.message') . "</th>
    <td><textarea class='areamedium' rows='5' cols='33' name='text'>" . _restorePostValue('text', null, false) . "</textarea></td>
</tr>

<tr>
    <td></td>
    <td>" . _getPostFormControls('newmsg', 'text') . "</td>
</tr>

<tr>
    <td></td>
    <td><input type='submit' value='" . _lang('global.send') . "'> " . _getPostFormPreviewButton('newmsg', 'text') . "</td>
</tr>

</table>

" . _jsLimitLength(16384, 'newmsg', 'text') . "

" . _xsrfProtect() . "</form>\n";

        break;

        /* ---  vypis  --- */
    default:

        // cteni vzkazu
        if (isset($_GET['read'])) {

            // promenne
            $id = (int) _get('read');

            // nacist data
            $senderUserQuery = _userQuery('pm.sender', 'sender_', 'su');
            $receiverUserQuery = _userQuery('pm.receiver', 'receiver_', 'ru');
            $q = DB::queryRow('SELECT pm.*,post.id post_id,post.subject,post.time,post.text,post.guest,post.ip,' . $senderUserQuery['column_list'] . ',' . $receiverUserQuery['column_list'] . ' FROM ' . _pm_table . ' AS pm JOIN ' . _posts_table . ' AS post ON (post.type=' . _post_pm . ' AND post.home=pm.id AND post.xhome=-1) ' . $senderUserQuery['joins'] . ' ' . $receiverUserQuery['joins'] . ' WHERE pm.id=' . $id . ' AND (sender=' . _user_id . ' AND sender_deleted=0 OR receiver=' . _user_id . ' AND receiver_deleted=0)');
            if ($q === false) {
                $output .= _msg(_msg_err, _lang('global.badinput'));
                break;
            }

            // titulek
            $mod_title = 'mod.messages.read';

            // stavy
            $locked = ($q['sender_deleted'] || $q['receiver_deleted']);
            list($role, $role_other) = (($q['sender'] == _user_id) ? array('sender', 'receiver') : array('receiver', 'sender'));

            // spocitat neprectene zpravy
            $unread_count = DB::count(_posts_table, 'home=' . DB::val($q['id']) . ' AND type=' . _post_pm . ' AND time>' . $q[$role_other . '_readtime']);

            // vystup
            $output .= "<div class=\"topic\">\n";
            $output .= "<h2>" . _lang('mod.messages.message') . ": " . $q['subject'] . "</h2>\n";
            $output .= CommentService::renderPost($q, $senderUserQuery, array(
                'post_link' => false,
                'allow_reply' => false,
            ));
            $output .= "</div>\n";

            $output .= CommentService::render(CommentService::RENDER_PM_LIST, $q['id'], array($locked, $unread_count), false, _linkModule('messages', 'a=list&read=' . $q['id'], false));

            // aktualizace casu precteni
            DB::update(_pm_table, 'id=' . DB::val($id), array($role . '_readtime' => time()));

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
            $selected_ids = (array) _post('msg', array(), true);

            // funkce
            $deletePms = function ($cond = null, $sender_cond = null, $receiver_cond = null) {
                $q = DB::query('SELECT id,sender,sender_deleted,receiver,receiver_deleted FROM ' . _pm_table . ' WHERE (sender=' . _user_id . ' AND sender_deleted=0' . (isset($sender_cond) ? ' AND ' . $sender_cond : '') . ' OR receiver=' . _user_id . ' AND receiver_deleted=0' . (isset($receiver_cond) ? ' AND ' . $receiver_cond : '') . ')' . ((isset($cond)) ? ' AND ' . $cond : ''));
                $del_list = array();

                while ($r = DB::row($q)) {
                    // zjisteni roli
                    list($role, $role_other) = (($r['sender'] == _user_id) ? array('sender', 'receiver') : array('receiver', 'sender'));

                    // smazani nebo oznaceni
                    if ($r[$role_other . '_deleted']) {
                        // druha strana jiz smazala, smazat uplne
                        $del_list[] = $r['id'];
                    } else {
                        // pouze oznacit
                        DB::update(_pm_table, 'id=' . $r['id'], array($role . '_deleted' => 1));
                    }
                }

                // fyzicke vymazani
                if (!empty($del_list)) {
                    DB::query('DELETE ' . _pm_table . ',post FROM ' . _pm_table . ' JOIN ' . _posts_table . ' AS post ON (post.type=' . _post_pm . ' AND post.home=' . _pm_table . '.id) WHERE ' . _pm_table . '.id IN(' . DB::arr($del_list) . ')');
                }
            };

            // akce
            switch (_post('action')) {
                case 1:
                    if (!empty($selected_ids)) {
                        $deletePms('id IN(' . DB::arr($selected_ids) . ')');
                        $message = _msg(_msg_ok, _lang('mod.messages.delete.done'));
                    }
                    break;

                case 2:
                    if (!empty($selected_ids)) {
                        $q = DB::query('SELECT id,sender,receiver FROM ' . _pm_table . ' WHERE id IN(' . DB::arr($selected_ids) . ') AND (sender=' . _user_id . ' AND sender_deleted=0 OR receiver=' . _user_id . ' AND receiver_deleted=0)');
                        $changesets = array();
                        $now = time();
                        while ($r = DB::row($q)) {
                            $role = $r['sender'] == _user_id ? 'sender' : 'receiver';
                            $changesets[$r['id']][$role . '_readtime'] = 0;
                        }
                        DB::updateSetMulti(_pm_table, 'id', $changesets);
                        $message = _msg(_msg_ok, _lang('global.done'));
                    }
                    break;

                case 3:
                    $deletePms(null, 'sender_readtime>=update_time', 'receiver_readtime>=update_time');
                    $message = _msg(_msg_ok, _lang('mod.messages.delete.done'));
                    break;

                case 4:
                    $deletePms();
                    $message = _msg(_msg_ok, _lang('mod.messages.delete.done'));
                    break;
            }
        }

        // strankovani
        $paging = _resultPaging($_index['url'], _messagesperpage, _pm_table, 'sender=' . _user_id . ' OR receiver=' . _user_id, '&amp;a=' . $a);
        if (_showPagingAtTop()) {
            $output .= $paging['paging'];
        }

        // tabulka
        $output .= $message . "
        <form method='post' action=''>
<p class='messages-menu'>
    <a class='button' href='" . _linkModule('messages', 'a=new') . "'><img src='" . \Sunlight\Template::image('icons/bubble.png') . "' alt='new' class='icon'>" . _lang('mod.messages.new') . "</a>
</p>

<table class='messages-table'>
<tr><td width='10'><input type='checkbox' name='selector' onchange=\"var that=this;$('table.messages-table input').each(function(){this.checked=that.checked;});\"></td><th>" . _lang('mod.messages.message') . "</th><th>" . _lang('global.user') . "</th><th>" . _lang('mod.messages.time.update') . "</th></tr>\n";
        $senderUserQuery = _userQuery('pm.sender', 'sender_', 'su');
        $receiverUserQuery = _userQuery('pm.receiver', 'receiver_', 'ru');
        $q = DB::query('SELECT pm.id,pm.sender,pm.receiver,pm.sender_readtime,pm.receiver_readtime,pm.update_time,post.subject,' . $senderUserQuery['column_list'] . ',' . $receiverUserQuery['column_list'] . ',(SELECT COUNT(*) FROM ' . _posts_table . ' AS countpost WHERE countpost.home=pm.id AND countpost.type=' . _post_pm . ' AND (pm.sender=' . _user_id . ' AND countpost.time>pm.receiver_readtime OR pm.receiver=' . _user_id . ' AND countpost.time>pm.sender_readtime)) AS unread_counter FROM ' . _pm_table . ' AS pm JOIN ' . _posts_table . ' AS post ON (post.home=pm.id AND post.type=' . _post_pm . ' AND post.xhome=-1) ' . $senderUserQuery['joins'] . ' ' . $receiverUserQuery['joins'] . ' WHERE pm.sender=' . _user_id . ' AND pm.sender_deleted=0 OR pm.receiver=' . _user_id . ' AND pm.receiver_deleted=0 ORDER BY pm.update_time DESC ' . $paging['sql_limit']);
        while ($r = DB::row($q)) {
            $read = ($r['sender'] == _user_id && $r['sender_readtime'] >= $r['update_time'] || $r['receiver'] == _user_id && $r['receiver_readtime'] >= $r['update_time']);
            $output .= "<tr><td><input type='checkbox' name='msg[]' value='" . $r['id'] . "'></td><td><a href='" . _linkModule('messages', 'a=list&read=' . $r['id']) . "'" . ($read ? '' : ' class="notread"') . ">" . $r['subject'] . "</a></td><td>" . _linkUserFromQuery($r['sender'] == _user_id ? $receiverUserQuery : $senderUserQuery, $r) . " <small>(" . $r['unread_counter'] . ")</small></td><td>" . _formatTime($r['update_time'], 'post') . "</td></tr>\n";
        }
        if (!isset($read)) {
            $output .= "<tr><td colspan='4'>" . _lang('mod.messages.nokit') . "</td></tr>\n";
        }

        $output .= "
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

</table>
" . _xsrfProtect() . "</form>\n";

        // strankovani dole
        if (_showPagingAtBottom()) {
            $output .= $paging['paging'];
        }

        break;

}

// zpetny odkaz
if (!$list) {
    $_index['backlink'] = _linkModule('messages', null, false);
}
