<?php

use Sunlight\Article;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Post\Post;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
    $_index->unauthorized();
    return;
}

/* ---  priprava  --- */

$id = StringManipulator::slugify(Request::get('id', ''), false);
$query = DB::queryRow("SELECT * FROM " . DB::table('user') . " WHERE username=" . DB::val($id));
$public = true;
if ($query !== false) {
    $groupdata = DB::queryRow("SELECT title,descr,icon,color,blocked,level FROM " . DB::table('user_group') . " WHERE id=" . $query['group_id']);
    $public = $query['public'] || User::checkLevel($query['id'], $groupdata['level']);

    if ($public) {
        // promenne
        if ($query['note'] == "") {
            $note = "";
        } else {
            $note = "<tr class='valign-top'><th>" . _lang('global.note') . "</th><td><div class='note'>" . Post::render($query['note']) . "</div></td></tr>";
        }

        // clanky autora
        [, , $arts] = Article::createFilter('art', [], "author=" . $query['id'], true, false, false);
        if ($arts != 0) {

            // zjisteni prumerneho hodnoceni
            $avgrate = DB::result(DB::query("SELECT ROUND(SUM(ratesum)/SUM(ratenum)) FROM " . DB::table('article') . " WHERE rateon=1 AND ratenum!=0 AND confirmed=1 AND author=" . $query['id']));
            if ($avgrate === null) {
                $avgrate = _lang('article.rate.nodata');
            } else {
                $avgrate = "&Oslash; " . $avgrate . "%";
            }

            // sestaveni kodu
            $arts = "\n<tr><th>" . _lang('global.articlesnum') . "</th><td>" . $arts . ", <a href='" . _e(Router::module('profile-arts', ['query' => ['id' => $id]])) . "'>" . _lang('global.show') . " &gt;</a></td></tr>\n";
            if (Settings::get('ratemode') != 0) {
                $arts .= "\n<tr><th>" . _lang('article.rate') . "</th><td>" . $avgrate . "</td></tr>\n";
            }

        } else {
            $arts = "";
        }

        // odkaz na prispevky uzivatele
        $posts_count = DB::count('post', 'author=' . DB::val($query['id']) . ' AND type!=' . Post::PRIVATE_MSG . ' AND type!=' . Post::SHOUTBOX_ENTRY);
        if ($posts_count > 0) {
            $posts_viewlink = ", <a href='" . _e(Router::module('profile-posts', ['query' => ['id' => $id]])) . "'>" . _lang('global.show') . " &gt;</a>";
        } else {
            $posts_viewlink = "";
        }
    }
} else {
    $_index->notFound();
    return;
}

/* ---  modul  --- */

$_index->title = _lang('mod.profile') . ': ' . $query[$query['publicname'] !== null ? 'publicname' : 'username'];

// poznamka o blokovani
if ($query['blocked'] == 1 || $groupdata['blocked'] == 1) {
    $output .= Message::error(_lang('mod.profile.blockednote'));
}

if ($public) {
    if (!$query['public'] && User::getId() == $query['id']) {
        $output .= Message::ok(_lang('mod.profile.private.selfnote'));
    }

    $output .= "
<table class='profiletable'>

<tr class='valign-top'>

<td class='avatartd'>
" . User::renderAvatar($query) . "
</td>

<td>
<table class='profiletable'>

<tr>
<th>" . _lang('login.username') . "</th>
<td>" . $query['username'] . "</td>
</tr>

" . (($query['publicname'] !== null) ? "<tr><th>" . _lang('mod.settings.account.publicname') . "</th><td>" . $query['publicname'] . "</td></tr>" : '') . "

<tr>
<th>" . _lang('global.group') . "</th>
<td><span class='text-icon'>" . (($groupdata['icon'] != "") ? "<img src='" . _e(Router::path('images/groupicons/' . $groupdata['icon'])) . "' alt='icon' class='icon'>" : '') . (($groupdata['color'] !== '') ? '<span style="color:' . $groupdata['color'] . ';">' . $groupdata['title'] . '</span>' : $groupdata['title']) . "</span></td>
</tr>

" . (($groupdata['descr'] !== '') ? "<tr>
<th>" . _lang('mod.profile.groupdescr') . "</th>
<td>" . $groupdata['descr'] . "</td>
</tr>" : '') . "

" . ($query['id'] == User::getId() || User::hasPrivilege('administration') && User::hasPrivilege('adminusers') ? "<tr>
<th>" . _lang('mod.profile.lastact') . "</th>
<td>" . GenericTemplates::renderTime($query['activitytime'], 'activity') . "</td>
</tr>

<tr>
<th>" . _lang('mod.profile.logincounter') . "</th>
<td>" . $query['logincounter'] . "</td>
</tr>" : '') . "

" . Extend::buffer('mod.profile.table.main', ['user' => $query]) . "

</table>
</td>

</tr>
</table>

<div class='hr profile-hr'><hr></div>

<div class='wlimiter'>
<table class='profiletable'>

<tr><th>" . _lang('mod.profile.regtime') . "</th><td>" . GenericTemplates::renderTime($query['registertime']) . "</td></tr>
" . (Settings::get('profileemail') ? "<tr><th>" . _lang('global.email') . "</th><td>" . Email::link($query['email']) . "</td></tr>" : '') . "
<tr><th>" . _lang('global.postsnum') . "</th><td>" . $posts_count . $posts_viewlink . "</td></tr>

" . $arts . "
" . Extend::buffer('mod.profile.table.extra', ['user' => $query]) . "
" . $note . "

</table>
</div>
";
} else {
    $output .= Message::ok(_lang('mod.profile.private'));
}

// odkaz na zaslani vzkazu
if (User::isLoggedIn() && Settings::get('messages') && $query['id'] != User::getId() && $query['blocked'] == 0 && $groupdata['blocked'] == 0) {
    $output .= "<p><a class='button' href='" . _e(Router::module('messages', ['query' => ['a' => 'new', 'receiver' => $query['username']]])) . "'><img src='" . Template::image("icons/bubble.png") . "' alt='msg' class='icon'>" . _lang('mod.messages.new') . "</a></p>";
}
