<?php

use Sunlight\Core;
use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Paginator;
use Sunlight\Post\Post;
use Sunlight\PostForm;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Util\UrlHelper;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn()) {
    $_index->unauthorized();
    return;
}

$message = '';
$form = true;
$id = (int) Request::get('id');
[$columns, $joins, $cond] = Post::createFilter('post');
$userQuery = User::createQuery('post.author');
$columns .= ',home_page.layout page_layout,' . $userQuery['column_list'];
$joins .= ' ' . $userQuery['joins'];
$query = DB::queryRow('SELECT ' . $columns . ' FROM ' . DB::table('post') . ' post ' . $joins . ' WHERE post.id=' . $id . ' AND ' . $cond);

if ($query !== false) {
    if (isset($query['page_layout'])) {
        $_index->changeTemplate($query['page_layout']);
    }

    if (Post::checkAccess($userQuery, $query)) {
        $bbcode = true;
        Extend::call('mod.editpost.backlink', ['backlink' => &$_index->backlink, 'post' => $query]);

        if ($_index->backlink === null) {
            [$url] = Router::post($query);

            switch ($query['type']) {
                case Post::SECTION_COMMENT:
                    $_index->backlink = UrlHelper::appendParams($url, 'page=' . Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id>' . $query['id'] . ' AND type=' . Post::SECTION_COMMENT . ' AND xhome=-1 AND home=' . $query['home'])) . '#post-' . $query['id'];
                    break;
                case Post::ARTICLE_COMMENT:
                    $_index->backlink = UrlHelper::appendParams($url, 'page=' . Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id>' . $query['id'] . ' AND type=' . Post::ARTICLE_COMMENT . ' AND xhome=-1 AND home=' . $query['home'])) . '#post-' . $query['id'];
                    break;
                case Post::BOOK_ENTRY:
                    $postsperpage = DB::queryRow('SELECT var2 FROM ' . DB::table('page') . ' WHERE id=' . $query['home']);
                    if ($postsperpage['var2'] === null) {
                        $postsperpage['var2'] = Settings::get('commentsperpage');
                    }
                    $_index->backlink = UrlHelper::appendParams($url, 'page=' . Paginator::getItemPage($postsperpage['var2'], DB::table('post'), 'id>' . $query['id'] . ' AND type=' . Post::BOOK_ENTRY . ' AND xhome=-1 AND home=' . $query['home'])) . '#post-' . $query['id'];
                    break;
                case Post::SHOUTBOX_ENTRY:
                    $bbcode = false;
                    break;
                case Post::FORUM_TOPIC:
                    if ($query['xhome'] == -1) {
                        if (!Form::loadCheckbox('delete')) {
                            $_index->backlink = $url;
                        } else {
                            $_index->backlink = Router::page($query['home'], $query['page_slug']);
                        }
                    } else {
                        $_index->backlink = UrlHelper::appendParams($url, 'page=' . Paginator::getItemPage(Settings::get('commentsperpage'), DB::table('post'), 'id<' . $query['id'] . ' AND type=' . Post::FORUM_TOPIC . ' AND xhome=' . $query['xhome'] . ' AND home=' . $query['home'])) . '#post-' . $query['id'];
                    }
                    break;

                case Post::PRIVATE_MSG:
                    $_index->backlink = UrlHelper::appendParams($url, 'page=' . Paginator::getItemPage(Settings::get('messagesperpage'), DB::table('post'), 'id<' . $query['id'] . ' AND type=' . Post::PRIVATE_MSG . ' AND home=' . $query['home'])) . '#post-' . $query['id'];
                    break;

                case Post::PLUGIN:
                    if ($url === '') {
                        $output .= Message::error(_lang('plugin.error', ['%plugin%' => $query['flag']]), true);

                        return;
                    }
                    break;
                default:
                    $_index->backlink = Core::getBaseUrl()->getPath() . '/';
                    break;
            }
        }
    } else {
        $_index->unauthorized();
        return;
    }
} else {
    $_index->notFound();
    return;
}

// save
if (isset($_POST['text'])) {
    if (!Form::loadCheckbox('delete')) {
        if ($query['author'] == -1) {
            $guest = PostService::normalizeGuestName(Request::post('guest', ''));
        } else {
            $guest = '';
        }

        $text = Html::cut(_e(trim(Request::post('text', ''))), ($query['type'] != Post::SHOUTBOX_ENTRY) ? 16384 : 255);
        if ($query['xhome'] == -1 && in_array($query['type'], [Post::FORUM_TOPIC, Post::PRIVATE_MSG])) {
            $subject = Html::cut(_e(StringManipulator::trimExtraWhitespace(Request::post('subject'))), 48);
            if ($subject === '')  {
                $subject = '-';
            }
        } else {
            $subject = '';
        }

        // save
        if ($text != '') {
            $continue = true;
            Extend::call('posts.edit', [
                'id' => $id,
                'post' => $query,
                'continue' => &$continue,
            ]);

            if ($continue) {
                $update_data = [
                    'text' => $text,
                    'subject' => $subject
                ];
                if (isset($guest)) {
                    $update_data['guest'] = $guest;
                }
                DB::update('post', 'id=' . DB::val($id), $update_data);
                $_index->redirect(Router::module('editpost', ['query' => ['id' => $id , 'saved' => 1], 'absolute' => true]));

                return;
            }
        } else {
            $message = Message::warning(_lang('mod.editpost.failed'));
        }
    } else {
        // delete
        if ($query['type'] != Post::PRIVATE_MSG || $query['xhome'] != -1) {
            $continue = true;
            Extend::call('posts.delete', [
                'id' => $id,
                'post' => $query,
                'continue' => &$continue,
            ]);

            if ($continue) {
                // update topic bump time
                if ($query['type'] == Post::FORUM_TOPIC && $query['xhome'] != -1) {
                    // check if this is the last reply
                    $chq = DB::query('SELECT id,time FROM ' . DB::table('post') . ' WHERE type=' . Post::FORUM_TOPIC . ' AND xhome=' . $query['xhome'] . ' ORDER BY id DESC LIMIT 2');
                    $chr = DB::row($chq);

                    if ($chr !== false && $chr['id'] == $id) {
                        // update bump time to last reply or topic creation time (if last reply is deleted)
                        $chr = DB::row($chq);
                        DB::update('post', 'id=' . $query['xhome'], ['bumptime' => $chr !== false ? $chr['time'] : DB::raw('time')]);
                    }
                }

                // remove replies
                DB::delete('post', 'id=' . DB::val($id));
                if ($query['xhome'] == -1) {
                    DB::delete('post', 'xhome=' . DB::val($id) . ' AND home=' . DB::val($query['home']) . ' AND type=' . DB::val($query['type']));
                }

                // info
                $message = Message::ok(_lang('mod.editpost.deleted'));
                $form = false;
            }
        }
    }
}

// output
$_index->title = _lang('mod.editpost');

// message
if (isset($_GET['saved']) && $message == '') {
    $message = Message::ok(_lang('global.saved'));
}
$output .= $message;

// form
if ($form) {
    $inputs = [];

    if ($query['author'] == -1) {
        $inputs[] = ['label' => _lang('posts.guestname'), 'content' => '<input type="text" name="guest" class="inputsmall" value="' . $query['guest'] . '" maxlength="24">'];
    }
    if ($query['xhome'] == -1 && in_array($query['type'], [Post::FORUM_TOPIC, Post::PRIVATE_MSG])) {
        $inputs[] = ['label' => _lang((($query['type'] != Post::FORUM_TOPIC) ? 'posts.subject' : 'posts.topic')), 'content' => '<input type="text" name="subject" class="inputmedium" maxlength="48" value="' . $query['subject'] . '">' ];
    }
    $inputs[] = ['label' => _lang('posts.text'), 'content' => '<textarea name="text" class="areamedium" rows="5" cols="33">' . $query['text'] . '</textarea>', 'top' => true];
    $inputs[] = ['label' => '', 'content' => PostForm::renderControls('postform', 'text', $bbcode)];
    $inputs[] = Form::getSubmitRow([
        'text' => _lang('global.save'),
        'append' => ' ' . PostForm::renderPreviewButton('postform', 'text')
            . (($query['type'] != Post::PRIVATE_MSG || $query['xhome'] != -1) ? '<br><br><label><input type="checkbox" name="delete" value="1"> ' . _lang('mod.editpost.delete') . '</label>' : ''),
    ]);

    $output .= Form::render(
        [
            'name' => 'postform',
            'action' => Router::module('editpost', ['query' => ['id' => $id]]),
        ],
        $inputs
    );

    $output .= GenericTemplates::jsLimitLength((($query['type'] != Post::SHOUTBOX_ENTRY) ? 16384 : 255), 'postform', 'text');
}
