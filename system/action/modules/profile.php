<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;

defined('_root') or exit;

if (!_logged_in && _notpublicsite) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava  --- */

$id = _slugify(_get('id'));
$query = DB::queryRow("SELECT * FROM " . _users_table . " WHERE username=" . DB::val($id));
$public = true;
if ($query !== false) {
    $groupdata = DB::queryRow("SELECT title,descr,icon,color,blocked,level FROM " . _groups_table . " WHERE id=" . $query['group_id']);
    $public = $query['public'] || _levelCheck($query['id'], $groupdata['level']);

    if ($public) {
        // promenne
        if ($query['note'] == "") {
            $note = "";
        } else {
            $note = "<tr class='valign-top'><th>" . _lang('global.note') . "</th><td><div class='note'>" . _parsePost($query['note']) . "</div></td></tr>";
        }

        // clanky autora
        list(, , $arts) = _articleFilter('art', array(), "author=" . $query['id'], true, false, false);
        if ($arts != 0) {

            // zjisteni prumerneho hodnoceni
            $avgrate = DB::result(DB::query("SELECT ROUND(SUM(ratesum)/SUM(ratenum)) FROM " . _articles_table . " WHERE rateon=1 AND ratenum!=0 AND confirmed=1 AND author=" . $query['id']), 0);
            if ($avgrate === null) {
                $avgrate = _lang('article.rate.nodata');
            } else {
                $avgrate = "&Oslash; " . $avgrate . "%";
            }

            // sestaveni kodu
            $arts = "\n<tr><th>" . _lang('global.articlesnum') . "</th><td>" . $arts . ", <a href='" . _linkModule('profile-arts', 'id=' . $id) . "'>" . _lang('global.show') . " &gt;</a></td></tr>\n";
            if (_ratemode != 0) {
                $arts .= "\n<tr><th>" . _lang('article.rate') . "</th><td>" . $avgrate . "</td></tr>\n";
            }

        } else {
            $arts = "";
        }

        // odkaz na prispevky uzivatele
        $posts_count = DB::count(_posts_table, 'author=' . DB::val($query['id']) . ' AND type!=' . _post_pm . ' AND type!=' . _post_shoutbox_entry);
        if ($posts_count > 0) {
            $posts_viewlink = ", <a href='" . _linkModule('profile-posts', 'id=' . $id) . "'>" . _lang('global.show') . " &gt;</a>";
        } else {
            $posts_viewlink = "";
        }
    }
} else {
    $_index['is_found'] = false;
    return;
}

/* ---  modul  --- */

$_index['title'] = _lang('mod.profile') . ': ' . $query[$query['publicname'] !== null ? 'publicname' : 'username'];

// poznamka o blokovani
if ($query['blocked'] == 1 || $groupdata['blocked'] == 1) {
    $output .= _msg(_msg_err, _lang('mod.profile.blockednote'));
}

if ($public) {
    if (!$query['public'] && _user_id == $query['id']) {
        $output .= _msg(_msg_ok, _lang('mod.profile.private.selfnote'));
    }

    $output .= "
<table>

<tr class='valign-top'>

<td class='avatartd'>
" . _getAvatar($query) . "
</td>

<td>
<table class='profiletable'>

<tr>
<th>" . _lang('login.username') . "</th>
<td>" . $query['username'] . "</td>
</tr>

" . (($query['publicname'] !== null) ? "<tr><th>" . _lang('mod.settings.publicname') . "</th><td>" . $query['publicname'] . "</td></tr>" : '') . "

<tr>
<th>" . _lang('global.group') . "</th>
<td><span class='text-icon'>" . (($groupdata['icon'] != "") ? "<img src='" . _link('images/groupicons/' . $groupdata['icon']) . "' alt='icon' class='icon'>" : '') . (($groupdata['color'] !== '') ? '<span style="color:' . $groupdata['color'] . ';">' . $groupdata['title'] . '</span>' : $groupdata['title']) . "</span></td>
</tr>

" . (($groupdata['descr'] !== '') ? "<tr>
<th>" . _lang('mod.profile.groupdescr') . "</th>
<td>" . $groupdata['descr'] . "</td>
</tr>" : '') . "

" . ($query['id'] == _user_id || _priv_administration && _priv_adminusers ? "<tr>
<th>" . _lang('mod.profile.lastact') . "</th>
<td>" . _formatTime($query['activitytime'], 'activity') . "</td>
</tr>

<tr>
<th>" . _lang('mod.profile.logincounter') . "</th>
<td>" . $query['logincounter'] . "</td>
</tr>" : '') . "

" . Extend::buffer('mod.profile.table.main', array('user' => $query)) . "

</table>
</td>

</tr>
</table>

<div class='hr profile-hr'><hr></div>

<div class='wlimiter'>
<table class='profiletable'>

<tr><th>" . _lang('mod.profile.regtime') . "</th><td>" . _formatTime($query['registertime']) . "</td></tr>
" . (_profileemail ? "<tr><th>" . _lang('global.email') . "</th><td>" . _mailto($query['email']) . "</td></tr>" : '') . "
<tr><th>" . _lang('global.postsnum') . "</th><td>" . $posts_count . $posts_viewlink . "</td></tr>

" . $arts . "
" . Extend::buffer('mod.profile.table.extra', array('user' => $query)) . "
" . $note . "

</table>
</div>
";
} else {
    $output .= _msg(_msg_ok, _lang('mod.profile.private'));
}

// odkaz na zaslani vzkazu
if (_logged_in && _messages && $query['id'] != _user_id && $query['blocked'] == 0 && $groupdata['blocked'] == 0) {
    $output .= "<p><a class='button' href='" . _linkModule('messages', 'a=new&receiver=' . $query['username']) . "'><img src='" . Sunlight\Template::image("icons/bubble.png") . "' alt='msg' class='icon'>" . _lang('mod.messages.new') . "</a></p>";
}
