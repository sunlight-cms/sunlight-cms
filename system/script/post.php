<?php

require '../bootstrap.php';
Sunlight\Core::init('../../');

// jmeno hosta nebo ID uzivatele
if (_login) {
    $guest = '';
    $author = _loginid;
} else {
    if (isset($_POST['guest'])) {
        $guest = _slugify(_post('guest'), false);
        if (mb_strlen($guest) > 24) {
            $guest = mb_substr($guest, 0, 24);
        }
    } else {
        $guest = '';
    }

    $author = -1;
}

// typ, domov, text
$posttarget = (int) _post('_posttarget');
$posttype = (int) _post('_posttype');
$text = _cutHtml(_e(trim(_post('text'))), ($posttype != 4) ? 16384 : 255);

// domovsky prispevek
if ($posttype != _post_shoutbox_entry) {
    $xhome = (int) _post('_xhome');
} else {
    $xhome = -1;
}

// predmet
if ($xhome == -1 && in_array($posttype, array(_post_forum_topic, _post_pm))) {
    $subject = _cutHtml(_e(_wsTrim(_post('subject'))), 48);
} else {
    $subject = '';
}

// plugin flag
if ($posttype == _post_plugin) {
    $pluginflag = (int) _post('_pluginflag');
} else {
    $pluginflag = 0;
}

// vyplneni prazdnych poli
if ($guest === '' && !_login) {
    $guest = $_lang['posts.anonym'];
}

//  kontrola cile
$continue = false;
switch ($posttype) {

        // sekce
    case _post_section_comment:
        $tdata = DB::queryRow("SELECT public,var1,var3,level FROM " . _root_table . " WHERE id=" . $posttarget . " AND type=" . _page_section);
        if ($tdata !== false) {
            if (_publicAccess($tdata['public'], $tdata['level']) && $tdata['var1'] == 1 && $tdata['var3'] != 1) {
                $continue = true;
            }
        }
        break;

        // clanek
    case _post_article_comment:
        $tdata = DB::queryRow("SELECT id,time,confirmed,author,public,home1,home2,home3,comments,commentslocked FROM " . _articles_table . " WHERE id=" . $posttarget);
        if ($tdata !== false) {
            if (_articleAccess($tdata) && $tdata['comments'] == 1 && $tdata['commentslocked'] == 0) {
                $continue = true;
            }
        }
        break;

        // kniha
    case _post_book_entry:
        $tdata = DB::queryRow("SELECT public,var1,var3,level FROM " . _root_table . " WHERE id=" . $posttarget . " AND type=" . _page_book);
        if ($tdata !== false) {
            if (_publicAccess($tdata['public'], $tdata['level']) && _publicAccess($tdata['var1']) && $tdata['var3'] != 1) {
                $continue = true;
            }
        }

        break;

        // shoutbox
    case _post_shoutbox_entry:
        $tdata = DB::queryRow("SELECT public,locked FROM " . _sboxes_table . " WHERE id=" . $posttarget);
        if ($tdata !== false) {
            if (_publicAccess($tdata['public']) && $tdata['locked'] != 1) {
                $continue = true;
            }
        }
        break;

        // forum
    case _post_forum_topic:
        $tdata = DB::queryRow("SELECT public,var2,var3,level FROM " . _root_table . " WHERE id=" . $posttarget . " AND type=" . _page_forum);
        if ($tdata !== false) {
            if (_publicAccess($tdata['public'], $tdata['level']) && _publicAccess($tdata['var3']) && $tdata['var2'] != 1) {
                $continue = true;
            }
        }
        break;

        // zprava
    case _post_pm:
        if (_messages && _login) {
            $tdata = DB::queryRow('SELECT sender,receiver FROM ' . _pm_table . ' WHERE id=' . $posttarget . ' AND (sender=' . _loginid . ' OR receiver=' . _loginid . ') AND sender_deleted=0 AND receiver_deleted=0');
            if ($tdata !== false) {
                $continue = true;
                $xhome = $posttarget;
            }
        }
        break;

        // plugin post
    case _post_plugin:
        Sunlight\Extend::call('posts.' . $pluginflag . '.validate', array('home' => $posttarget, 'valid' => &$continue));
        break;

        // blbost
    default:
        exit;

}

//  kontrola prispevku pro odpoved
if ($xhome != -1 && $posttype != _post_pm) {
    $continue2 = false;
    $tdata = DB::queryRow("SELECT xhome FROM " . _posts_table . " WHERE id=" . $xhome . " AND home=" . $posttarget . " AND locked=0");
    if ($tdata !== false) {
        if ($tdata['xhome'] == -1) {
            $continue2 = true;
        }
    }
} else {
    $continue2 = true;
}

//  ulozeni prispevku
if ($continue && $continue2 && $text != '' && ($posttype == _post_shoutbox_entry || _captchaCheck())) {
    if (_xsrfCheck()) {
        if ($posttype == _post_shoutbox_entry || _priv_unlimitedpostaccess || _iplogCheck(_iplog_anti_spam)) {
            if ($guest === '' || DB::count(_users_table, 'username=' . DB::val($guest) . ' OR publicname=' . DB::val($guest)) === 0) {

                // zpracovani pluginem
                $allow = true;
                Sunlight\Extend::call('posts.submit', array('allow' => &$allow, 'posttype' => $posttype, 'posttarget' => $posttarget, 'xhome' => $xhome, 'subject' => &$subject, 'text' => &$text, 'author' => $author, 'guest' => $guest));
                if ($allow) {

                    // ulozeni
                    $insert_id = DB::insert(_posts_table, array(
                        'type' => $posttype,
                        'home' => $posttarget,
                        'xhome' => $xhome,
                        'subject' => $subject,
                        'text' => $text,
                        'author' => $author,
                        'guest' => $guest,
                        'time' => time(),
                        'ip' => _userip,
                        'bumptime' => (($posttype == _post_forum_topic && $xhome == -1) ? time() : '0'),
                        'flag' => $pluginflag
                    ), true);
                    if (!_priv_unlimitedpostaccess && $posttype != _post_shoutbox_entry) {
                        _iplogUpdate(_iplog_anti_spam);
                    }
                    $return = 1;
                    Sunlight\Extend::call('posts.new', array('id' => $insert_id, 'posttype' => $posttype));

                    // topicy - aktualizace bumptime
                    if ($posttype == _post_forum_topic && $xhome != -1) {
                        DB::update(_posts_table, 'id=' . $xhome, array('bumptime' => time()));
                    }

                    // zpravy - aktualizace casu zmeny a precteni
                    if ($posttype == _post_pm) {
                        $role = (($tdata['sender'] == _loginid) ? 'sender' : 'receiver');
                        DB::update(_pm_table, 'id=' . $posttarget, array(
                            'update_time' => time(),
                            $role . '_readtime' => time()
                        ));
                    }

                    // shoutboxy - odstraneni prispevku za hranici limitu
                    if ($posttype == _post_shoutbox_entry) {
                        $pnum = DB::count(_posts_table, 'type=' . _post_shoutbox_entry . ' AND home=' . DB::val($posttarget));
                        if ($pnum > _sboxmemory) {
                            $dnum = $pnum - _sboxmemory;
                            $dposts = DB::queryRows("SELECT id FROM " . _posts_table . " WHERE type=" . _post_shoutbox_entry . " AND home=" . $posttarget . " ORDER BY id LIMIT " . $dnum, null, 'id');
                            DB::deleteSet(_posts_table, 'id', $dpost);
                        }
                    }

                } else {
                    $return = 0;
                }

            } else {
                $return = 3;
            }
        } else {
            $return = 2;
        }
    } else {
        $return = 4;
    }
} else {
    $return = 0;
}

/* ---  presmerovani  --- */

$returnUrl = null;
if ($posttype != _post_shoutbox_entry) {
    $returnUrl = _returnUrl();

    if ($return != 1) {
        $_SESSION['post_form_guest'] = $guest;
        $_SESSION['post_form_subject'] = $subject;
        $_SESSION['post_form_text'] = $text;

        $returnUrl = _addGetToLink($returnUrl, 'replyto=' . $xhome, false) . '&addpost';
    }

    $returnUrl = _addGetToLink(
        $returnUrl,
        "r=" . $return
            . (($posttype == _post_forum_topic) ? '&autolast' : '')
            . (($posttype != _post_shoutbox_entry && isset($insert_id)) ? '#post-' . $insert_id : ((1 != $return) ? '#post-form' : '')),
        false
    );
}

_returnHeader($returnUrl);
