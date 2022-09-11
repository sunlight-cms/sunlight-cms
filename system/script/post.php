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

// get guest name or author ID
if (User::isLoggedIn()) {
    $guest = '';
    $author = User::getId();
} else {
    $guest = PostService::normalizeGuestName(Request::post('guest', ''));
    $author = -1;
}

// home, type, text
$home = (int) Request::post('_posttarget');
$type = (int) Request::post('_posttype');
$text = Html::cut(_e(trim(Request::post('text', ''))), ($type != Post::SHOUTBOX_ENTRY) ? 16384 : 255);

// xhome
if ($type != Post::SHOUTBOX_ENTRY) {
    $xhome = (int) Request::post('_xhome');
} else {
    $xhome = -1;
}

// subject
if ($xhome == -1 && in_array($type, [Post::FORUM_TOPIC, Post::PRIVATE_MSG])) {
    $subject = Html::cut(_e(StringManipulator::trimExtraWhitespace(Request::post('subject'))), 48);
} else {
    $subject = '';
}

// plugin flag
if ($type == Post::PLUGIN) {
    $pluginflag = (int) Request::post('_pluginflag');
} else {
    $pluginflag = 0;
}

// check home
$continue = false;
switch ($type) {
    // section
    case Post::SECTION_COMMENT:
        $tdata = DB::queryRow('SELECT public,var1,var3,level FROM ' . DB::table('page') . ' WHERE id=' . $home . ' AND type=' . Page::SECTION);
        if ($tdata !== false && User::checkPublicAccess($tdata['public'], $tdata['level']) && $tdata['var1'] == 1 && $tdata['var3'] != 1) {
            $continue = true;
        }
        break;

    // article
    case Post::ARTICLE_COMMENT:
        $tdata = DB::queryRow('SELECT id,time,confirmed,author,public,home1,home2,home3,comments,commentslocked FROM ' . DB::table('article') . ' WHERE id=' . $home);
        if ($tdata !== false && Article::checkAccess($tdata) && $tdata['comments'] == 1 && $tdata['commentslocked'] == 0) {
            $continue = true;
        }
        break;

    // book
    case Post::BOOK_ENTRY:
        $tdata = DB::queryRow('SELECT public,var1,var3,level FROM ' . DB::table('page') . ' WHERE id=' . $home . ' AND type=' . Page::BOOK);
        if ($tdata !== false && User::checkPublicAccess($tdata['public'], $tdata['level']) && User::checkPublicAccess($tdata['var1']) && $tdata['var3'] != 1) {
            $continue = true;
        }

        break;

    // shoutbox
    case Post::SHOUTBOX_ENTRY:
        $tdata = DB::queryRow('SELECT public,locked FROM ' . DB::table('shoutbox') . ' WHERE id=' . $home);
        if ($tdata !== false && User::checkPublicAccess($tdata['public']) && $tdata['locked'] != 1) {
            $continue = true;
        }
        break;

    // forum
    case Post::FORUM_TOPIC:
        $tdata = DB::queryRow('SELECT public,var2,var3,level FROM ' . DB::table('page') . ' WHERE id=' . $home . ' AND type=' . Page::FORUM);
        if ($tdata !== false && User::checkPublicAccess($tdata['public'], $tdata['level']) && User::checkPublicAccess($tdata['var3']) && $tdata['var2'] != 1) {
            $continue = true;
        }
        break;

    // message
    case Post::PRIVATE_MSG:
        if (Settings::get('messages') && User::isLoggedIn()) {
            $tdata = DB::queryRow('SELECT sender,receiver FROM ' . DB::table('pm') . ' WHERE id=' . $home . ' AND (sender=' . User::getId() . ' OR receiver=' . User::getId() . ') AND sender_deleted=0 AND receiver_deleted=0');
            if ($tdata !== false) {
                $continue = true;
                $xhome = $home;
            }
        }
        break;

    // plugin post
    case Post::PLUGIN:
        Extend::call('posts.' . $pluginflag . '.validate', ['home' => $home, 'valid' => &$continue]);
        break;

    // invalid
    default:
        exit;
}

// check xhome
if ($xhome != -1 && $type != Post::PRIVATE_MSG) {
    $continue2 = false;
    $tdata = DB::queryRow('SELECT xhome FROM ' . DB::table('post') . ' WHERE id=' . $xhome . ' AND home=' . $home . ' AND locked=0');
    if ($tdata !== false && $tdata['xhome'] == -1) {
        $continue2 = true;
    }
} else {
    $continue2 = true;
}

// save post
if ($continue && $continue2 && $text != '' && ($type == Post::SHOUTBOX_ENTRY || Captcha::check())) {
    if (Xsrf::check()) {
        if ($type == Post::SHOUTBOX_ENTRY || User::hasPrivilege('unlimitedpostaccess') || IpLog::check(IpLog::ANTI_SPAM)) {
            if ($guest === '' || User::isNameAvailable($guest)) {
                // extend event
                $allow = true;
                Extend::call('posts.submit', [
                    'allow' => &$allow,
                    'posttype' => $type,
                    'posttarget' => $home,
                    'xhome' => $xhome,
                    'subject' => &$subject,
                    'text' => &$text,
                    'author' => $author,
                    'guest' => $guest
                ]);

                if ($allow) {
                    // save
                    $insert_id = DB::insert('post', $post_data = [
                        'type' => $type,
                        'home' => $home,
                        'xhome' => $xhome,
                        'subject' => $subject,
                        'text' => $text,
                        'author' => $author,
                        'guest' => $guest,
                        'time' => time(),
                        'ip' => Core::getClientIp(),
                        'bumptime' => (($type == Post::FORUM_TOPIC && $xhome == -1) ? time() : '0'),
                        'flag' => $pluginflag
                    ], true);
                    if (!User::hasPrivilege('unlimitedpostaccess') && $type != Post::SHOUTBOX_ENTRY) {
                        IpLog::update(IpLog::ANTI_SPAM);
                    }
                    $return = 1;
                    Extend::call('posts.new', ['id' => $insert_id, 'posttype' => $type, 'post' => $post_data]);

                    // update topic bump time
                    if ($type == Post::FORUM_TOPIC && $xhome != -1) {
                        DB::update('post', 'id=' . $xhome, ['bumptime' => time()]);
                    }

                    // update private message timestamps
                    if ($type == Post::PRIVATE_MSG) {
                        $role = (User::equals($tdata['sender']) ? 'sender' : 'receiver');
                        DB::update('pm', 'id=' . $home, [
                            'update_time' => time(),
                            $role . '_readtime' => time()
                        ]);
                    }

                    // remove shoutbox posts beyond limit
                    if ($type == Post::SHOUTBOX_ENTRY) {
                        $pnum = DB::count('post', 'type=' . Post::SHOUTBOX_ENTRY . ' AND home=' . DB::val($home));
                        if ($pnum > Settings::get('sboxmemory')) {
                            $dnum = $pnum - Settings::get('sboxmemory');
                            $dposts = DB::queryRows('SELECT id FROM ' . DB::table('post') . ' WHERE type=' . Post::SHOUTBOX_ENTRY . ' AND home=' . $home . ' ORDER BY id LIMIT ' . $dnum, null, 'id');
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

// redirect back
$returnUrl = null;
if ($type != Post::SHOUTBOX_ENTRY) {
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
            . (($type == Post::FORUM_TOPIC) ? '&autolast' : '')
            . (($type != Post::SHOUTBOX_ENTRY && isset($insert_id)) ? '#post-' . $insert_id : (($return != 1) ? '#post-form' : ''))
    );
}

Response::redirectBack($returnUrl);
