<?php

use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Post\Post;
use Sunlight\PostForm;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn()) {
    $_index->unauthorized();
    return;
}

$message = '';
$form = true;
$id = (int) Request::get('id');
[$columns, $joins, $cond] = Post::createFilter('p');
$userQuery = User::createQuery('p.author');
$columns .= ',home_page.layout page_layout,' . $userQuery['column_list'];
$joins .= ' ' . $userQuery['joins'];
$post = DB::queryRow('SELECT ' . $columns . Extend::buffer('posts.columns') . ' FROM ' . DB::table('post') . ' p ' . $joins . ' WHERE p.id=' . $id . ' AND ' . $cond);

if ($post !== false) {
    if (isset($post['page_layout'])) {
        $_index->changeTemplate($post['page_layout']);
    }

    if (!Post::checkAccess($userQuery, $post)) {
        $_index->unauthorized();
        return;
    }
} else {
    $_index->notFound();
    return;
}

$_index->backlink = Router::postPermalink($id);

// save
if (isset($_POST['text'])) {
    if (!Form::loadCheckbox('delete')) {
        if ($post['author'] == -1) {
            $guest = PostService::normalizeGuestName(Request::post('guest', ''));
        } else {
            $guest = '';
        }

        $text = Html::cut(_e(trim(Request::post('text', ''))), ($post['type'] != Post::SHOUTBOX_ENTRY) ? 16384 : 255);

        if ($post['xhome'] == -1 && in_array($post['type'], [Post::FORUM_TOPIC, Post::PRIVATE_MSG])) {
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
            Extend::call('mod.editpost.edit', [
                'id' => $id,
                'post' => $post,
                'subject' => &$subject,
                'text' => &$text,
                'continue' => &$continue,
            ]);

            if ($continue) {
                $update_data = [
                    'text' => $text,
                    'subject' => $subject,
                    'guest' => $guest,
                ];

                DB::update('post', 'id=' . DB::val($id), $update_data);
                $_index->redirect(Router::module('editpost', ['query' => ['id' => $id , 'saved' => 1], 'absolute' => true]));

                return;
            }
        } else {
            $message = Message::warning(_lang('mod.editpost.failed'));
        }
    } else {
        // delete
        if ($post['type'] != Post::PRIVATE_MSG || $post['xhome'] != -1) {
            $continue = true;
            Extend::call('mod.editpost.delete', [
                'id' => $id,
                'post' => $post,
                'continue' => &$continue,
            ]);

            if ($continue) {
                // update topic bump time
                if ($post['type'] == Post::FORUM_TOPIC && $post['xhome'] != -1) {
                    // check if this is the last reply
                    $chq = DB::query('SELECT id,time FROM ' . DB::table('post') . ' WHERE type=' . Post::FORUM_TOPIC . ' AND xhome=' . $post['xhome'] . ' ORDER BY id DESC LIMIT 2');
                    $chr = DB::row($chq);

                    if ($chr !== false && $chr['id'] == $id) {
                        // update bump time to last reply or topic creation time (if last reply is deleted)
                        $chr = DB::row($chq);
                        DB::update('post', 'id=' . $post['xhome'], ['bumptime' => $chr !== false ? $chr['time'] : DB::raw('time')]);
                    }
                }

                // remove replies
                DB::delete('post', 'id=' . DB::val($id));

                if ($post['xhome'] == -1) {
                    DB::delete('post', 'xhome=' . DB::val($id) . ' AND home=' . DB::val($post['home']) . ' AND type=' . DB::val($post['type']));
                }

                // info
                $_index->backlink = PostService::getPostHomeUrl($post);
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

    if ($post['author'] == -1) {
        $inputs[] = ['label' => _lang('posts.guestname'), 'content' => '<input type="text" name="guest" class="inputsmall" value="' . $post['guest'] . '" maxlength="24">'];
    }

    if ($post['xhome'] == -1 && in_array($post['type'], [Post::FORUM_TOPIC, Post::PRIVATE_MSG])) {
        $inputs[] = ['label' => _lang((($post['type'] != Post::FORUM_TOPIC) ? 'posts.subject' : 'posts.topic')), 'content' => '<input type="text" name="subject" class="inputmedium" maxlength="48" value="' . $post['subject'] . '">' ];
    }

    $inputs[] = ['label' => _lang('posts.text'), 'content' => '<textarea name="text" class="areamedium" rows="5" cols="33">' . $post['text'] . '</textarea>', 'top' => true];
    $inputs[] = ['label' => '', 'content' => PostForm::renderControls('postform', 'text', $post['type'] != Post::SHOUTBOX_ENTRY)];
    $inputs[] = Form::getSubmitRow([
        'text' => _lang('global.save'),
        'append' => ' ' . PostForm::renderPreviewButton('postform', 'text')
            . (($post['type'] != Post::PRIVATE_MSG || $post['xhome'] != -1) ? '<br><br><label><input type="checkbox" name="delete" value="1"> ' . _lang('mod.editpost.delete') . '</label>' : ''),
    ]);

    $output .= Form::render(
        [
            'name' => 'postform',
            'action' => Router::module('editpost', ['query' => ['id' => $id]]),
        ],
        $inputs
    );

    $output .= GenericTemplates::jsLimitLength((($post['type'] != Post::SHOUTBOX_ENTRY) ? 16384 : 255), 'postform', 'text');
}
