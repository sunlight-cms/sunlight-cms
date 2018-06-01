<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Generic;
use Sunlight\Post;
use Sunlight\Router;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Form;

defined('_root') or exit;

return function ($id = null)
{
    // priprava
    $result = "";
    $id = (int) $id;

    // nacteni dat shoutboxu
    $sboxdata = DB::queryRow("SELECT * FROM " . _sboxes_table . " WHERE id=" . $id);
    if ($sboxdata !== false) {
        $rcontinue = true;
    } else {
        $rcontinue = false;
    }

    // sestaveni kodu
    if ($rcontinue) {

        $result = "
    <div id='hcm_sbox_" . Core::$hcmUid . "' class='sbox'>
    <div class='sbox-content'>
    " . (($sboxdata['title'] != "") ? "<div class='sbox-title'>" . $sboxdata['title'] . "</div>" : '') . "<div class='sbox-item'" . (($sboxdata['title'] == "") ? " style='border-top:none;'" : '') . ">";

        // formular na pridani
        if ($sboxdata['locked'] != 1 && User::checkPublicAccess($sboxdata['public'])) {

            // priprava bunek
            if (!_logged_in) {
                $inputs[] = array('label' => _lang('posts.guestname'), 'content' => "<input type='text' name='guest' class='sbox-input' maxlength='22'>");
            }
            $inputs[] = array('label' => _lang('posts.text'), 'content' => "<input type='text' name='text' class='sbox-input' maxlength='255'><input type='hidden' name='_posttype' value='4'><input type='hidden' name='_posttarget' value='" . $id . "'>");

            $result .= Form::render(
                array(
                    'name' => 'hcm_sboxform_' . Core::$hcmUid,
                    'action' => Router::link('system/script/post.php?_return=' . rawurlencode($GLOBALS['_index']['url']) . "#hcm_sbox_" . Core::$hcmUid),
                ),
                $inputs
            );

        } else {
            if ($sboxdata['locked'] != 1) {
                $result .= _lang('posts.loginrequired');
            } else {
                $result .= "<img src='" . Template::image("icons/lock.png") . "' alt='locked' class='icon'>" . _lang('posts.locked2');
            }
        }

        $result .= "\n</div>\n<div class='sbox-posts'>";
        // vypis prispevku
        $userQuery = User::createQuery('p.author');
        $sposts = DB::query("SELECT p.id,p.text,p.author,p.guest,p.time,p.ip," . $userQuery['column_list'] . " FROM " . _posts_table . " p " . $userQuery['joins'] . " WHERE p.home=" . $id . " AND p.type=" . _post_shoutbox_entry . " ORDER BY p.id DESC");
        if (DB::size($sposts) != 0) {
            while ($spost = DB::row($sposts)) {

                // nacteni autora
                if ($spost['author'] != -1) {
                    $author = Router::userFromQuery($userQuery, $spost, array('class' => 'post_author', 'max_len' => 16, 'title' => Generic::renderTime($spost['time'], 'post')));
                } else {
                    $author = "<span class='post-author-guest' title='" . Generic::renderTime($spost['time'], 'post') . ", ip=" . Generic::renderIp($spost['ip']) . "'>" . $spost['guest'] . "</span>";
                }

                // odkaz na spravu
                if (Post::checkAccess($userQuery, $spost)) {
                    $alink = " <a href='" . Router::module('editpost', 'id=' . $spost['id']) . "'><img src='" . Template::image("icons/edit.png") . "' alt='edit' class='icon'></a>";
                } else {
                    $alink = "";
                }

                // kod polozky
                $result .= "<div class='sbox-item'>" . $author . ':' . $alink . " " . Post::render($spost['text'], true, false, false) . "</div>\n";

            }
        } else {
            $result .= "\n<div class='sbox-item'>" . _lang('posts.noposts') . "</div>\n";
        }

        $result .= "
  </div>
  </div>
  </div>
  ";

    }

    return $result;
};
