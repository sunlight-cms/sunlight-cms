<?php

if (!defined('_root')) {
    exit;
}

if (!_login) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava promennych  --- */

$message = '';
$form = true;
$id = (int) _get('id');
list($columns, $joins, $cond) = _postFilter('post');
$userQuery = _userQuery('post.author');
$columns .= ',' . $userQuery['column_list'];
$joins .= ' ' . $userQuery['joins'];
$query = DB::query("SELECT " . $columns . " FROM " . _posts_table . " post " . $joins . " WHERE post.id=" . $id . " AND " . $cond);
if (DB::size($query) != 0) {
    $query = DB::row($query);
    if (_postAccess($userQuery, $query)) {
        $bbcode = true;
        Sunlight\Extend::call('mod.editpost.backlink', array('backlink' => &$_index['backlink'], 'post' => $query));

        if (null === $_index['backlink']) {
            list($url) = _linkPost($query, false);

            switch ($query['type']) {
                case _post_section_comment:
                    $_index['backlink'] = _addGetToLink($url, "page=" . _resultPagingGetItemPage(_commentsperpage, _posts_table, "id>" . $query['id'] . " AND type=1 AND xhome=-1 AND home=" . $query['home']), false) . "#post-" . $query['id'];
                    break;
                case _post_article_comment:
                    $_index['backlink'] = _addGetToLink($url, "page=" . _resultPagingGetItemPage(_commentsperpage, _posts_table, "id>" . $query['id'] . " AND type=2 AND xhome=-1 AND home=" . $query['home']), false) . "#post-" . $query['id'];
                    break;
                case _post_book_entry:
                    $postsperpage = DB::queryRow("SELECT var2 FROM " . _root_table . " WHERE id=" . $query['home']);
                    if (null === $postsperpage['var2']) {
                        $postsperpage['var2'] = _commentsperpage;
                    }
                    $_index['backlink'] = _addGetToLink($url, "page=" . _resultPagingGetItemPage($postsperpage['var2'], _posts_table, "id>" . $query['id'] . " AND type=3 AND xhome=-1 AND home=" . $query['home']), false) . "#post-" . $query['id'];
                    break;
                case _post_shoutbox_entry:
                    $bbcode = false;
                    break;
                case _post_forum_topic:
                    if ($query['xhome'] == -1) {
                        if (!_checkboxLoad("delete")) {
                            $_index['backlink'] = $url;
                        } else {
                            $_index['backlink'] = _linkRoot($query['home'], $query['root_slug']);
                        }
                    } else {
                        $_index['backlink'] = _addGetToLink($url, "page=" . _resultPagingGetItemPage(_commentsperpage, _posts_table, "id<" . $query['id'] . " AND type=5 AND xhome=" . $query['xhome'] . " AND home=" . $query['home']), false) . "#post-" . $query['id'];
                    }
                    break;

                case _post_pm:
                    $_index['backlink'] = _addGetToLink($url, 'page=' . _resultPagingGetItemPage(_messagesperpage, _posts_table, 'id<' . $query['id'] . ' AND type=6 AND home=' . $query['home']), false) . '#post-' . $query['id'];
                    break;

                case _post_plugin:
                    if ('' === $url) {
                        $output .= _msg(_msg_err, sprintf($_lang['plugin.error'], $query['flag']));

                        return;
                    }
                    break;
                default:
                    $_index['backlink'] = Sunlight\Core::$url;
                    break;
            }
        }

    } else {
        $_index['is_accessible'] = false;
        return;
    }
} else {
    $_index['is_found'] = false;
    return;
}

/* ---  ulozeni  --- */

if (isset($_POST['text'])) {

    if (!_checkboxLoad("delete")) {

        /* -  uprava  - */

        // nacteni promennych

        // jmeno hosta
        if ($query['guest'] != '') {
            $guest = _slugify(_post('guest'), false);
            if (mb_strlen($guest) > 24) {
                $guest = mb_substr($guest, 0, 24);
            }
        } else {
            $guest = "";
        }

        $text = _cutHtml(_e(trim(_post('text'))), ($query['type'] != _post_shoutbox_entry) ? 16384 : 255);
        if ($query['xhome'] == -1 && in_array($query['type'], array(_post_forum_topic, _post_pm))) {
            $subject = _cutHtml(_e(_wsTrim(_post('subject'))), 48);
            if ('' === $subject)  {
                $subject = '-';
            }
        } else {
            $subject = '';
        }

        // vyplneni prazdnych poli
        if ($guest == null && $query['guest'] != "") {
            $guest = $_lang['posts.anonym'];
        }

        // ulozeni
        if ($text != "") {
            Sunlight\Extend::call('posts.edit', array(
                'id' => $id,
                'post' => $query,
                'message' => &$message,
            ));
            if ('' === $message) {
                DB::query("UPDATE " . _posts_table . " SET text=" . DB::val($text) . ",subject=" . DB::val($subject) . (isset($guest) ? ",guest=" . DB::val($guest) : '') . " WHERE id=" . DB::val($id));
                $_index['redirect_to'] = _linkModule('editpost', 'id=' . $id . '&saved', false, true);

                return;
            }
        } else {
            $message = _msg(_msg_warn, $_lang['mod.editpost.failed']);
        }

    } else {

        /* -  odstraneni  - */
        if ($query['type'] != _post_pm || $query['xhome'] != -1) {

            Sunlight\Extend::call('posts.delete', array(
                'id' => $id,
                'post' => $query,
            ));

            // debump topicu
            if ($query['type'] == _post_forum_topic && $query['xhome'] != -1) {
                // kontrola, zda se jedna o posledni odpoved
                $chq = DB::query('SELECT id,time FROM ' . _posts_table . ' WHERE type=5 AND xhome=' . $query['xhome'] . ' ORDER BY id DESC LIMIT 2');
                $chr = DB::row($chq);
                if ($chr !== false && $chr['id'] == $id) {
                    // ano, debump podle casu predchoziho postu nebo samotneho topicu (pokud se smazala jedina odpoved)
                    $chr = DB::row($chq);
                    DB::query('UPDATE ' . _posts_table . ' SET bumptime=' . (($chr !== false) ? $chr['time'] : 'time') . ' WHERE id=' . $query['xhome']);
                }
            }

            // smazani prispevku a odpovedi
            DB::query("DELETE FROM " . _posts_table . " WHERE id=" . $id);
            if ($query['xhome'] == -1) {
                DB::query("DELETE FROM " . _posts_table . " WHERE xhome=" . $id . " AND home=" . $query['home'] . " AND type=" . $query['type']);
            }

            // info
            $message = _msg(_msg_ok, $_lang['mod.editpost.deleted']);
            $form = false;

       }

    }

}

/* ---  vystup  --- */

$_index['title'] = $_lang['mod.editpost'];

// zprava
if (isset($_GET['saved']) && $message == '') {
    $message = _msg(_msg_ok, $_lang['global.saved']);
}
$output .= $message;

// formular
if ($form) {
    $inputs = array();

    if ($query['guest'] != '') {
        $inputs[] = array('label' => $_lang['posts.guestname'], 'content' => "<input type='text' name='guest' class='inputsmall' value='" . $query['guest'] . "'>");
    }
    if ($query['xhome'] == -1 && in_array($query['type'], array(_post_forum_topic, _post_pm))) {
        $inputs[] = array('label' => $_lang[(($query['type'] != _post_forum_topic) ? 'posts.subject' : 'posts.topic')], 'content' => "<input type='text' name='subject' class='inputmedium' maxlength='48' value='" . $query['subject'] . "'>");
    }
    $inputs[] = array('label' => $_lang['posts.text'], 'content' => "<textarea name='text' class='areamedium' rows='5' cols='33'>" . $query['text'] . "</textarea>", 'top' => true);
    $inputs[] = array('label' => '', 'content' => _getPostFormControls('postform', 'text', $bbcode));

    $output .= _formOutput(
        array(
            'name' => 'postform',
            'action' => _linkModule('editpost', 'id=' . $id, false),
            'submit_text' => $_lang['global.save'],
            'submit_append' => ' ' . _getPostFormPreviewButton('postform', 'text')
                . (($query['type'] != _post_pm || $query['xhome'] != -1) ? "<br><br><label><input type='checkbox' name='delete' value='1'> " . $_lang['mod.editpost.delete'] . "</label>" : ''),
        ),
        $inputs
    );

    $output .= _jsLimitLength((($query['type'] != _post_shoutbox_entry) ? 16384 : 255), "postform", "text");
}
