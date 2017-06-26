<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
}

/* ---  priprava a akce  --- */

$message = "";
if (isset($_POST['action'])) {

    switch (_post('action')) {

            // vytvoreni
        case 1:
            // nacteni zakladnich promennych
            $title = _cutHtml(_e(_post('title')), 64);
            $public = _checkboxLoad("public");
            $locked = _checkboxLoad("lockedc");

            // vlozeni
            DB::insert(_sboxes_table, array(
                'title' => $title,
                'locked' => $locked,
                'public' => $public
            ));
            $message = _msg(_msg_ok, _lang('global.created'));
            break;

            // ulozeni
        case 2:
            $lastid = -1;
            $sql = "";
            foreach ($_POST as $var => $val) {
                if ($var == "action") {
                    continue;
                }
                $var = explode("_", $var);
                if (count($var) == 2) {
                    $id = (int) mb_substr($var[0], 1);
                    $var = $var[1];
                    if ($lastid == -1) {
                        $lastid = $id;
                    }
                    $quotes = true;
                    $skip = false;
                    switch ($var) {
                        case "title":
                            $val = _cutHtml(_e(trim($val)), 64);
                            break;
                        case "lockedtrigger":
                            $var = "locked";
                            $val = _checkboxLoad("s" . $id . "_locked");
                            $quotes = false;
                            break;
                        case "publictrigger":
                            $var = "public";
                            $val = _checkboxLoad("s" . $id . "_public");
                            $quotes = false;
                            break;
                        case "delposts":
                            $skip = true;
                            DB::delete(_posts_table, 'home=' . $id . ' AND type=' . _post_shoutbox_entry);
                            break;
                        default:
                            $skip = true;
                            break;
                    }

                    // ukladani a cachovani
                    if (!$skip) {

                        // ulozeni
                        if ($lastid != $id) {
                            DB::query("UPDATE " . _sboxes_table . " SET " . $sql . " WHERE id=" . $lastid);
                            $sql = '';
                            $lastid = $id;
                        }

                        if ($sql !== '') {
                            $sql .= ',';
                        }
                        $sql .= $var . '=';
                        if ($quotes) {
                            $sql .= DB::val($val);
                        } else {
                            $sql .= $val;
                        }
                    }

                }
            }

            // ulozeni posledniho nebo jedineho shoutboxu
            if ($sql != "") {
                $sql = trim($sql, ",");
                DB::query("UPDATE " . _sboxes_table . " SET " . $sql . " WHERE id=" . $id);
            }

            $message = _msg(_msg_ok, _lang('global.saved'));
            break;

    }

}

/* ---  odstraneni shoutboxu  --- */

if (isset($_GET['del']) && _xsrfCheck(true)) {
    $del = (int) _get('del');
    DB::delete(_sboxes_table, 'id=' . $del);
    DB::delete(_posts_table, 'home=' . $del . ' AND type=' . _post_shoutbox_entry);
    $message = _msg(_msg_ok, _lang('global.done'));
}

/* ---  vystup  --- */

$output .= "
<p class='bborder'>" . _lang('admin.content.sboxes.p') . "</p>

" . $message . "

<fieldset class='hs_fieldset'>
<legend>" . _lang('admin.content.sboxes.create') . "</legend>
<form class='cform' action='index.php?p=content-sboxes' method='post'>
<input type='hidden' name='action' value='1'>

<table>

<tr>
<th>" . _lang('admin.content.form.title') . "</th>
<td><input type='text' name='title' class='inputbig' maxlength='64'></td>
</tr>

<tr class='valign-top'>
<th>" . _lang('admin.content.form.settings') . "</th>
<td>
<label><input type='checkbox' name='public' value='1' checked> " . _lang('admin.content.form.unregpost') . "</label><br>
<label><input type='checkbox' name='locked' value='1'> " . _lang('admin.content.form.locked2') . "</label>
</td>
</tr>

<tr>
<td></td>
<td><input type='submit' value='" . _lang('global.create') . "'></td>
</tr>

</table>

" . _xsrfProtect() . "</form>
</fieldset>

<fieldset>
<legend>" . _lang('admin.content.sboxes.manage') . "</legend>
<form class='cform' action='index.php?p=content-sboxes' method='post'>
<input type='hidden' name='action' value='2'>

<input type='submit' value='" . _lang('global.savechanges') . "'>
<div class='hr'><hr></div>
";

// vypis shoutboxu
$shoutboxes = DB::query("SELECT * FROM " . _sboxes_table . " ORDER BY id DESC");
if (DB::size($shoutboxes) != 0) {
    while ($shoutbox = DB::row($shoutboxes)) {

        $output .= "
    <br>
    <table>

    <tr>
    <th>" . _lang('admin.content.form.title') . "</th>
    <td><input type='text' name='s" . $shoutbox['id'] . "_title' class='inputmedium' value='" . $shoutbox['title'] . "'></td>
    </tr>

    <tr>
    <th>" . _lang('admin.content.form.hcm') . "</th>
    <td>
        <input type='text' value='[hcm]sbox," . $shoutbox['id'] . "[/hcm]' onclick='this.select()' readonly>
        <small>" . _lang('admin.content.form.thisid') . " " . $shoutbox['id'] . "</small>
    </td>
    </tr>

    <tr class='valign-top'>
    <th>" . _lang('admin.content.form.settings') . "</th>
    <td>
    <input type='hidden' name='s" . $shoutbox['id'] . "_publictrigger' value='1'><input type='hidden' name='s" . $shoutbox['id'] . "_lockedtrigger' value='1'>
    <label><input type='checkbox' name='s" . $shoutbox['id'] . "_public' value='1'" . _checkboxActivate($shoutbox['public']) . "> " . _lang('admin.content.form.unregpost') . "</label><br>
    <label><input type='checkbox' name='s" . $shoutbox['id'] . "_locked' value='1'" . _checkboxActivate($shoutbox['locked']) . "> " . _lang('admin.content.form.locked2') . "</label><br>
    <label><input type='checkbox' name='s" . $shoutbox['id'] . "_delposts' value='1'> " . _lang('admin.content.form.delposts') . "</label><br><br>
    <a class='button' href='" . _xsrfLink("index.php?p=content-sboxes&amp;del=" . $shoutbox['id']) . "' onclick='return Sunlight.confirm();'><img src='images/icons/delete.png' alt='del' class='icon'>" . _lang('global.delete') . "</a>
    </td>
    </tr>

    </table>
    <br><div class='hr'><hr></div>
    ";
    }
} else {
    $output .= _lang('global.nokit');
}

$output .= "
" . _xsrfProtect() . "</form>
</fieldset>

";
