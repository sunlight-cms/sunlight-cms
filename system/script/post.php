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
        $tdata = DB::query("SELECT public,var1,var3,level FROM " . _root_table . " WHERE id=" . $posttarget . " AND type=1");
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_publicAccess($tdata['public'], $tdata['level']) && $tdata['var1'] == 1 && $tdata['var3'] != 1) {
                $continue = true;
            }
        }
        break;

        // clanek
    case _post_article_comment:
        $tdata = DB::query("SELECT id,time,confirmed,author,public,home1,home2,home3,comments,commentslocked FROM " . _articles_table . " WHERE id=" . $posttarget);
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_articleAccess($tdata) && $tdata['comments'] == 1 && $tdata['commentslocked'] == 0) {
                $continue = true;
            }
        }
        break;

        // kniha
    case _post_book_entry:
        $tdata = DB::query("SELECT public,var1,var3,level FROM " . _root_table . " WHERE id=" . $posttarget . " AND type=3");
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_publicAccess($tdata['public'], $tdata['level']) && _publicAccess($tdata['var1']) && $tdata['var3'] != 1) {
                $continue = true;
            }
        }

        break;

        // shoutbox
    case _post_shoutbox_entry:
        $tdata = DB::query("SELECT public,locked FROM " . _sboxes_table . " WHERE id=" . $posttarget);
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
            if (_publicAccess($tdata['public']) && $tdata['locked'] != 1) {
                $continue = true;
            }
        }
        break;

        // forum
    case _post_forum_topic:
        $tdata = DB::query("SELECT public,var2,var3,level FROM " . _root_table . " WHERE id=" . $posttarget . " AND type=8");
        if (DB::size($tdata) != 0) {
            $tdata = DB::row($tdata);
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
    $tdata = DB::query("SELECT xhome FROM " . _posts_table . " WHERE id=" . $xhome . " AND home=" . $posttarget . " AND locked=0");
    if (DB::size($tdata) != 0) {
        $tdata = DB::row($tdata);
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
            if ($guest === '' || DB::result(DB::query('SELECT COUNT(*) FROM ' . _users_table . ' WHERE username=' . DB::val($guest) . ' OR publicname=' . DB::val($guest)), 0) == 0) {

                // zpracovani pluginem
                $allow = true;
                Sunlight\Extend::call('posts.submit', array('allow' => &$allow, 'posttype' => $posttype, 'posttarget' => $posttarget, 'xhome' => $xhome, 'subject' => &$subject, 'text' => &$text, 'author' => $author, 'guest' => $guest));
                if ($allow) {

                    // ulozeni
                    DB::query("INSERT INTO " . _posts_table . " (type,home,xhome,subject,text,author,guest,time,ip,bumptime,flag) VALUES (" . $posttype . "," . $posttarget . "," . $xhome . "," . DB::val($subject) . "," . DB::val($text) . "," . $author . ",'" . $guest . "'," . time() . "," . DB::val(_userip) . "," . (($posttype == _post_forum_topic && $xhome == -1) ? 'UNIX_TIMESTAMP()' : '0') . "," . $pluginflag . ")");
                    $insert_id = DB::insertID();
                    if (!_priv_unlimitedpostaccess && $posttype != _post_shoutbox_entry) {
                        _iplogUpdate(_iplog_anti_spam);
                    }
                    $return = 1;
                    Sunlight\Extend::call('posts.new', array('id' => $insert_id, 'posttype' => $posttype));

                    // topicy - aktualizace bumptime
                    if ($posttype == _post_forum_topic && $xhome != -1) {
                        DB::query("UPDATE " . _posts_table . " SET bumptime=UNIX_TIMESTAMP() WHERE id=" . $xhome);
                    }

                    // zpravy - aktualizace casu zmeny a precteni
                    if ($posttype == _post_pm) {
                        $role = (($tdata['sender'] == _loginid) ? 'sender' : 'receiver');
                        DB::query('UPDATE ' . _pm_table . ' SET update_time=UNIX_TIMESTAMP(),' . $role . '_readtime=UNIX_TIMESTAMP() WHERE id=' . $posttarget);
                    }

                    // shoutboxy - odstraneni prispevku za hranici limitu
                    if ($posttype == _post_shoutbox_entry) {
                        $pnum = DB::result(DB::query("SELECT COUNT(*) FROM " . _posts_table . " WHERE type=4 AND home=" . $posttarget), 0);
                        if ($pnum > _sboxmemory) {
                            $dnum = $pnum - _sboxmemory;
                            $dposts = DB::query("SELECT id FROM " . _posts_table . " WHERE type=4 AND home=" . $posttarget . " ORDER BY id LIMIT " . $dnum);
                            while ($dpost = DB::row($dposts)) {
                                DB::query("DELETE FROM " . _posts_table . " WHERE id=" . $dpost['id']);
                            }
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
