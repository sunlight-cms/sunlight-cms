<?php

use Sunlight\Article;
use Sunlight\Captcha;
use Sunlight\Post\Post;
use Sunlight\Core;
use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\IpLog;
use Sunlight\Page\Page;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Util\StringManipulator;
use Sunlight\Util\UrlHelper;
use Sunlight\Xsrf;

require '../bootstrap.php';
Core::init('../../');

// jmeno hosta nebo ID uzivatele
if (User::isLoggedIn()) {
    $guest = '';
    $author = User::getId();
} else {
    $guest = PostService::normalizeGuestName(Request::post('guest', ''));
    $author = -1;
}

// typ, domov, text
$posttarget = (int) Request::post('_posttarget');
$posttype = (int) Request::post('_posttype');
$text = Html::cut(_e(trim(Request::post('text', ''))), ($posttype != Post::SHOUTBOX_ENTRY) ? 16384 : 255);

// domovsky prispevek
if ($posttype != Post::SHOUTBOX_ENTRY) {
    $xhome = (int) Request::post('_xhome');
} else {
    $xhome = -1;
}

// predmet
if ($xhome == -1 && in_array($posttype, [Post::FORUM_TOPIC, Post::PRIVATE_MSG])) {
    $subject = Html::cut(_e(StringManipulator::trimExtraWhitespace(Request::post('subject'))), 48);
} else {
    $subject = '';
}

// plugin flag
if ($posttype == Post::PLUGIN) {
    $pluginflag = (int) Request::post('_pluginflag');
} else {
    $pluginflag = 0;
}

//  kontrola cile
$continue = false;
switch ($posttype) {

    // sekce
    case Post::SECTION_COMMENT:
        $tdata = DB::queryRow('SELECT public,var1,var3,level FROM ' . DB::table('page') . ' WHERE id=' . $posttarget . ' AND type=' . Page::SECTION);
        if ($tdata !== false && User::checkPublicAccess($tdata['public'], $tdata['level']) && $tdata['var1'] == 1 && $tdata['var3'] != 1) {
            $continue = true;
        }
        break;

    // clanek
    case Post::ARTICLE_COMMENT:
        $tdata = DB::queryRow('SELECT id,time,confirmed,author,public,home1,home2,home3,comments,commentslocked FROM ' . DB::table('article') . ' WHERE id=' . $posttarget);
        if ($tdata !== false && Article::checkAccess($tdata) && $tdata['comments'] == 1 && $tdata['commentslocked'] == 0) {
            $continue = true;
        }
        break;

    // kniha
    case Post::BOOK_ENTRY:
        $tdata = DB::queryRow('SELECT public,var1,var3,level FROM ' . DB::table('page') . ' WHERE id=' . $posttarget . ' AND type=' . Page::BOOK);
        if ($tdata !== false && User::checkPublicAccess($tdata['public'], $tdata['level']) && User::checkPublicAccess($tdata['var1']) && $tdata['var3'] != 1) {
            $continue = true;
        }

        break;

    // shoutbox
    case Post::SHOUTBOX_ENTRY:
        $tdata = DB::queryRow('SELECT public,locked FROM ' . DB::table('shoutbox') . ' WHERE id=' . $posttarget);
        if ($tdata !== false && User::checkPublicAccess($tdata['public']) && $tdata['locked'] != 1) {
            $continue = true;
        }
        break;

    // forum
    case Post::FORUM_TOPIC:
        $tdata = DB::queryRow('SELECT public,var2,var3,level FROM ' . DB::table('page') . ' WHERE id=' . $posttarget . ' AND type=' . Page::FORUM);
        if ($tdata !== false && User::checkPublicAccess($tdata['public'], $tdata['level']) && User::checkPublicAccess($tdata['var3']) && $tdata['var2'] != 1) {
            $continue = true;
        }
        break;

    // zprava
    case Post::PRIVATE_MSG:
        if (Settings::get('messages') && User::isLoggedIn()) {
            $tdata = DB::queryRow('SELECT sender,receiver FROM ' . DB::table('pm') . ' WHERE id=' . $posttarget . ' AND (sender=' . User::getId() . ' OR receiver=' . User::getId() . ') AND sender_deleted=0 AND receiver_deleted=0');
            if ($tdata !== false) {
                $continue = true;
                $xhome = $posttarget;
            }
        }
        break;

    // plugin post
    case Post::PLUGIN:
        Extend::call('posts.' . $pluginflag . '.validate', ['home' => $posttarget, 'valid' => &$continue]);
        break;

    // blbost
    default:
        exit;

}

//  kontrola prispevku pro odpoved
if ($xhome != -1 && $posttype != Post::PRIVATE_MSG) {
    $continue2 = false;
    $tdata = DB::queryRow('SELECT xhome FROM ' . DB::table('post') . ' WHERE id=' . $xhome . ' AND home=' . $posttarget . ' AND locked=0');
    if ($tdata !== false && $tdata['xhome'] == -1) {
        $continue2 = true;
    }
} else {
    $continue2 = true;
}

//  ulozeni prispevku
if ($continue && $continue2 && $text != '' && ($posttype == Post::SHOUTBOX_ENTRY || Captcha::check())) {
    if (Xsrf::check()) {
        if ($posttype == Post::SHOUTBOX_ENTRY || User::hasPrivilege('unlimitedpostaccess') || IpLog::check(IpLog::ANTI_SPAM)) {
            if ($guest === '' || User::isNameAvailable($guest)) {

                // zpracovani pluginem
                $allow = true;
                Extend::call('posts.submit', [
                    'allow' => &$allow,
                    'posttype' => $posttype,
                    'posttarget' => $posttarget,
                    'xhome' => $xhome,
                    'subject' => &$subject,
                    'text' => &$text,
                    'author' => $author,
                    'guest' => $guest
                ]);

                if ($allow) {
                    // ulozeni
                    $insert_id = DB::insert('post', $post_data = [
                        'type' => $posttype,
                        'home' => $posttarget,
                        'xhome' => $xhome,
                        'subject' => $subject,
                        'text' => $text,
                        'author' => $author,
                        'guest' => $guest,
                        'time' => time(),
                        'ip' => Core::getClientIp(),
                        'bumptime' => (($posttype == Post::FORUM_TOPIC && $xhome == -1) ? time() : '0'),
                        'flag' => $pluginflag
                    ], true);
                    if (!User::hasPrivilege('unlimitedpostaccess') && $posttype != Post::SHOUTBOX_ENTRY) {
                        IpLog::update(IpLog::ANTI_SPAM);
                    }
                    $return = 1;
                    Extend::call('posts.new', ['id' => $insert_id, 'posttype' => $posttype, 'post' => $post_data]);

                    // topicy - aktualizace bumptime
                    if ($posttype == Post::FORUM_TOPIC && $xhome != -1) {
                        DB::update('post', 'id=' . $xhome, ['bumptime' => time()]);
                    }

                    // zpravy - aktualizace casu zmeny a precteni
                    if ($posttype == Post::PRIVATE_MSG) {
                        $role = (User::equals($tdata['sender']) ? 'sender' : 'receiver');
                        DB::update('pm', 'id=' . $posttarget, [
                            'update_time' => time(),
                            $role . '_readtime' => time()
                        ]);
                    }

                    // shoutboxy - odstraneni prispevku za hranici limitu
                    if ($posttype == Post::SHOUTBOX_ENTRY) {
                        $pnum = DB::count('post', 'type=' . Post::SHOUTBOX_ENTRY . ' AND home=' . DB::val($posttarget));
                        if ($pnum > Settings::get('sboxmemory')) {
                            $dnum = $pnum - Settings::get('sboxmemory');
                            $dposts = DB::queryRows('SELECT id FROM ' . DB::table('post') . ' WHERE type=' . Post::SHOUTBOX_ENTRY . ' AND home=' . $posttarget . ' ORDER BY id LIMIT ' . $dnum, null, 'id');
                            DB::deleteSet('post', 'id', $dpost);
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
if ($posttype != Post::SHOUTBOX_ENTRY) {
    $returnUrl = Response::getReturnUrl();

    if ($return != 1) {
        $_SESSION['post_form_guest'] = $guest;
        $_SESSION['post_form_subject'] = $subject;
        $_SESSION['post_form_text'] = $text;

        $returnUrl = UrlHelper::appendParams($returnUrl, 'replyto=' . $xhome) . '&addpost';
    }

    $returnUrl = UrlHelper::appendParams(
        $returnUrl,
        'r=' . $return
            . (($posttype == Post::FORUM_TOPIC) ? '&autolast' : '')
            . (($posttype != Post::SHOUTBOX_ENTRY && isset($insert_id)) ? '#post-' . $insert_id : (($return != 1) ? '#post-form' : ''))
    );
}

Response::redirectBack($returnUrl);
