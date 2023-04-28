<?php

use Sunlight\Hcm;
use Sunlight\Post\PostService;
use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Post\Post;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Form;

return function ($id = 0) {
    Hcm::normalizeArgument($id, 'int');

    $result = '';

    // fetch shoutbox data
    $sboxdata = DB::queryRow('SELECT * FROM ' . DB::table('shoutbox') . ' WHERE id=' . $id);

    if ($sboxdata !== false) {
        $rcontinue = true;
    } else {
        $rcontinue = false;
    }

    // render
    if ($rcontinue) {
        $result = '
    <div id="hcm_sbox_' . Hcm::$uid . '" class="sbox">
    <div class="sbox-content">
    ' . (($sboxdata['title'] != '') ? '<div class="sbox-title">' . $sboxdata['title'] . '</div>' : '')
    . '<div class="sbox-item"' . (($sboxdata['title'] == '') ? ' style="border-top:none;"' : '') . '>';

        // post form
        if ($sboxdata['locked'] != 1 && User::checkPublicAccess($sboxdata['public'])) {
            // prepare inputs
            if (!User::isLoggedIn()) {
                $inputs[] = ['label' => _lang('posts.guestname'), 'content' => '<input type="text" name="guest" class="sbox-input" maxlength="24">'];
            }

            $inputs[] = [
                'label' => _lang('posts.text'),
                'content' => '<input type="text" name="text" class="sbox-input" maxlength="255">'
                    . '<input type="hidden" name="_posttype" value="4"><input type="hidden" name="_posttarget" value="' . $id . '">',
            ];
            $inputs[] = Form::getSubmitRow();

            $result .= Form::render(
                [
                    'name' => 'hcm_sboxform_' . Hcm::$uid,
                    'action' => Router::path('system/script/post.php', ['query' => ['_return' => $GLOBALS['_index']->url], 'fragment' => 'hcm_sbox_' . Hcm::$uid]),
                ],
                $inputs
            );
        } elseif ($sboxdata['locked'] != 1) {
            $result .= _lang('posts.loginrequired');
        } else {
            $result .= '<img src="' . Template::image('icons/lock.png') . '" alt="locked" class="icon">' . _lang('posts.locked2');
        }

        $result .= "\n</div>\n<div class=\"sbox-posts\">";

        // list posts
        $userQuery = User::createQuery('p.author');
        $sposts = DB::query(
            'SELECT p.id,p.text,p.author,p.guest,p.time,p.ip,' . $userQuery['column_list']
            . ' FROM ' . DB::table('post') . ' p '
            . $userQuery['joins']
            . ' WHERE p.home=' . $id . ' AND p.type=' . Post::SHOUTBOX_ENTRY
            . ' ORDER BY p.id DESC'
        );

        if (DB::size($sposts) != 0) {
            while ($spost = DB::row($sposts)) {
                // author
                if ($spost['author'] != -1) {
                    $author = Router::userFromQuery($userQuery, $spost, [
                        'class' => 'post_author',
                        'max_len' => 16,
                        'title' => GenericTemplates::renderTime($spost['time'], 'post')
                    ]);
                } else {
                    $author = '<span class="post-author-guest" title="' . GenericTemplates::renderTime($spost['time'], 'post') . ', ip=' . GenericTemplates::renderIp($spost['ip']) . '">'
                        . PostService::renderGuestName($spost['guest'])
                        . '</span>';
                }

                // edit link
                if (Post::checkAccess($userQuery, $spost)) {
                    $alink = ' <a href="' . _e(Router::module('editpost', ['query' => ['id' => $spost['id']]])) . '">'
                        . '<img src="' . Template::image('icons/edit.png') . '" alt="edit" class="icon">'
                        . '</a>';
                } else {
                    $alink = '';
                }

                // item
                $result .= '<div class="sbox-item">' . $author . ':' . $alink . ' ' . Post::render($spost['text'], true, false) . "</div>\n";
            }
        } else {
            $result .= "\n<div class=\"sbox-item\">" . _lang('posts.noposts') . "</div>\n";
        }

        $result .= '
  </div>
  </div>
  </div>
  ';
    }

    return $result;
};
