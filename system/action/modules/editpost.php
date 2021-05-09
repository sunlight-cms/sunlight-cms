<?php

use Sunlight\Core;
use Sunlight\Comment\CommentService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\Comment\Comment;
use Sunlight\PostForm;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Util\UrlHelper;

defined('_root') or exit;

if (!_logged_in) {
    $_index['type'] = _index_unauthorized;
    return;
}

/* ---  priprava promennych  --- */

$message = '';
$form = true;
$id = (int) Request::get('id');
[$columns, $joins, $cond] = Comment::createFilter('post');
$userQuery = User::createQuery('post.author');
$columns .= ',home_page.layout page_layout,' . $userQuery['column_list'];
$joins .= ' ' . $userQuery['joins'];
$query = DB::queryRow("SELECT " . $columns . " FROM " . _comment_table . " post " . $joins . " WHERE post.id=" . $id . " AND " . $cond);

if ($query !== false) {
    if (isset($query['page_layout'])) {
        Template::change($query['page_layout']);
    }

    if (Comment::checkAccess($userQuery, $query)) {
        $bbcode = true;
        Extend::call('mod.editpost.backlink', ['backlink' => &$_index['backlink'], 'post' => $query]);

        if ($_index['backlink'] === null) {
            [$url] = Router::post($query);

            switch ($query['type']) {
                case _post_section_comment:
                    $_index['backlink'] = UrlHelper::appendParams($url, "page=" . Paginator::getItemPage(_commentsperpage, _comment_table, "id>" . $query['id'] . " AND type=" . _post_section_comment . " AND xhome=-1 AND home=" . $query['home'])) . "#post-" . $query['id'];
                    break;
                case _post_article_comment:
                    $_index['backlink'] = UrlHelper::appendParams($url, "page=" . Paginator::getItemPage(_commentsperpage, _comment_table, "id>" . $query['id'] . " AND type=" . _post_article_comment . " AND xhome=-1 AND home=" . $query['home'])) . "#post-" . $query['id'];
                    break;
                case _post_book_entry:
                    $postsperpage = DB::queryRow("SELECT var2 FROM " . _page_table . " WHERE id=" . $query['home']);
                    if ($postsperpage['var2'] === null) {
                        $postsperpage['var2'] = _commentsperpage;
                    }
                    $_index['backlink'] = UrlHelper::appendParams($url, "page=" . Paginator::getItemPage($postsperpage['var2'], _comment_table, "id>" . $query['id'] . " AND type=" . _post_book_entry . " AND xhome=-1 AND home=" . $query['home'])) . "#post-" . $query['id'];
                    break;
                case _post_shoutbox_entry:
                    $bbcode = false;
                    break;
                case _post_forum_topic:
                    if ($query['xhome'] == -1) {
                        if (!Form::loadCheckbox("delete")) {
                            $_index['backlink'] = $url;
                        } else {
                            $_index['backlink'] = Router::page($query['home'], $query['page_slug']);
                        }
                    } else {
                        $_index['backlink'] = UrlHelper::appendParams($url, "page=" . Paginator::getItemPage(_commentsperpage, _comment_table, "id<" . $query['id'] . " AND type=" . _post_forum_topic . " AND xhome=" . $query['xhome'] . " AND home=" . $query['home'])) . "#post-" . $query['id'];
                    }
                    break;

                case _post_pm:
                    $_index['backlink'] = UrlHelper::appendParams($url, 'page=' . Paginator::getItemPage(_messagesperpage, _comment_table, 'id<' . $query['id'] . ' AND type=' . _post_pm . ' AND home=' . $query['home'])) . '#post-' . $query['id'];
                    break;

                case _post_plugin:
                    if ($url === '') {
                        $output .= Message::error(_lang('plugin.error', ['%plugin%' => $query['flag']]), true);

                        return;
                    }
                    break;
                default:
                    $_index['backlink'] = Core::$url;
                    break;
            }
        }

    } else {
        $_index['type'] = _index_unauthorized;
        return;
    }
} else {
    $_index['type'] = _index_not_found;
    return;
}

/* ---  ulozeni  --- */

if (isset($_POST['text'])) {

    if (!Form::loadCheckbox("delete")) {

        /* -  uprava  - */

        // nacteni promennych

        // jmeno hosta
        if ($query['author'] == -1) {
            $guest = CommentService::normalizeGuestName(Request::post('guest', ''));
        } else {
            $guest = '';
        }

        $text = Html::cut(_e(trim(Request::post('text'))), ($query['type'] != _post_shoutbox_entry) ? 16384 : 255);
        if ($query['xhome'] == -1 && in_array($query['type'], [_post_forum_topic, _post_pm])) {
            $subject = Html::cut(_e(StringManipulator::trimExtraWhitespace(Request::post('subject'))), 48);
            if ($subject === '')  {
                $subject = '-';
            }
        } else {
            $subject = '';
        }

        // ulozeni
        if ($text != "") {
            Extend::call('posts.edit', [
                'id' => $id,
                'post' => $query,
                'message' => &$message,
            ]);
            if ($message === '') {
                $update_data = [
                    'text' => $text,
                    'subject' => $subject
                ];
                if(isset($guest)) {
                    $update_data['guest'] = $guest;
                }
                DB::update(_comment_table, 'id=' . DB::val($id), $update_data);
                $_index['type'] = _index_redir;
                $_index['redirect_to'] = Router::module('editpost', 'id=' . $id . '&saved', true);

                return;
            }
        } else {
            $message = Message::warning(_lang('mod.editpost.failed'));
        }

    } else {

        /* -  odstraneni  - */
        if ($query['type'] != _post_pm || $query['xhome'] != -1) {

            Extend::call('posts.delete', [
                'id' => $id,
                'post' => $query,
            ]);

            // debump topicu
            if ($query['type'] == _post_forum_topic && $query['xhome'] != -1) {
                // kontrola, zda se jedna o posledni odpoved
                // TODO: fixme
                $chr = DB::queryRow('SELECT id,time FROM ' . _comment_table . ' WHERE type=' . _post_forum_topic . ' AND xhome=' . $query['xhome'] . ' ORDER BY id DESC LIMIT 2');
                if ($chr !== false && $chr['id'] == $id) {
                    // ano, debump podle casu predchoziho postu nebo samotneho topicu (pokud se smazala jedina odpoved)
                    DB::update(_comment_table, 'id=' . $query['xhome'], ['bumptime' => (($chr !== false) ? $chr['time'] : DB::raw('time'))]);
                }
            }

            // smazani prispevku a odpovedi
            DB::delete(_comment_table, 'id=' . DB::val($id));
            if ($query['xhome'] == -1) {
                DB::delete(_comment_table, 'xhome=' . DB::val($id) . ' AND home=' . DB::val($query['home']) . ' AND type=' . DB::val($query['type']));
            }

            // info
            $message = Message::ok(_lang('mod.editpost.deleted'));
            $form = false;

       }

    }

}

/* ---  vystup  --- */

$_index['title'] = _lang('mod.editpost');

// zprava
if (isset($_GET['saved']) && $message == '') {
    $message = Message::ok(_lang('global.saved'));
}
$output .= $message;

// formular
if ($form) {
    $inputs = [];

    if ($query['author'] == -1) {
        $inputs[] = ['label' => _lang('posts.guestname'), 'content' => "<input type='text' name='guest' class='inputsmall' value='" . $query['guest'] . "' maxlength='24'>"];
    }
    if ($query['xhome'] == -1 && in_array($query['type'], [_post_forum_topic, _post_pm])) {
        $inputs[] = ['label' => _lang((($query['type'] != _post_forum_topic) ? 'posts.subject' : 'posts.topic')), 'content' => "<input type='text' name='subject' class='inputmedium' maxlength='48' value='" . $query['subject'] . "'>"];
    }
    $inputs[] = ['label' => _lang('posts.text'), 'content' => "<textarea name='text' class='areamedium' rows='5' cols='33'>" . $query['text'] . "</textarea>", 'top' => true];
    $inputs[] = ['label' => '', 'content' => PostForm::renderControls('postform', 'text', $bbcode)];

    $output .= Form::render(
        [
            'name' => 'postform',
            'action' => Router::module('editpost', 'id=' . $id),
            'submit_text' => _lang('global.save'),
            'submit_append' => ' ' . PostForm::renderPreviewButton('postform', 'text')
                . (($query['type'] != _post_pm || $query['xhome'] != -1) ? "<br><br><label><input type='checkbox' name='delete' value='1'> " . _lang('mod.editpost.delete') . "</label>" : ''),
        ],
        $inputs
    );

    $output .= GenericTemplates::jsLimitLength((($query['type'] != _post_shoutbox_entry) ? 16384 : 255), "postform", "text");
}
