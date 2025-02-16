<?php

use Sunlight\Hcm;
use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

// action
if (isset($_POST['action'])) {
    switch (Request::post('action')) {
        // create
        case 1:
            $title = Html::cut(_e(Request::post('title', '')), 64);
            $public = Form::loadCheckbox('public');
            $locked = Form::loadCheckbox('lockedc');

            DB::insert('shoutbox', [
                'title' => $title,
                'locked' => $locked,
                'public' => $public
            ]);
            $message = Message::ok(_lang('global.created'));
            break;

        // save
        case 2:
            $lastid = -1;
            $sql = '';

            foreach ($_POST as $var => $val) {
                if ($var == 'action') {
                    continue;
                }

                $var = explode('_', $var);

                if (count($var) == 2) {
                    $id = (int) mb_substr($var[0], 1);
                    $var = $var[1];

                    if ($lastid == -1) {
                        $lastid = $id;
                    }

                    $quotes = true;
                    $skip = false;

                    switch ($var) {
                        case 'title':
                            $val = Html::cut(_e(trim($val)), 64);
                            break;
                        case 'lockedtrigger':
                            $var = 'locked';
                            $val = Form::loadCheckbox('s' . $id . '_locked');
                            $quotes = false;
                            break;
                        case 'publictrigger':
                            $var = 'public';
                            $val = Form::loadCheckbox('s' . $id . '_public');
                            $quotes = false;
                            break;
                        case 'delposts':
                            $skip = true;
                            DB::delete('post', 'home=' . $id . ' AND type=' . Post::SHOUTBOX_ENTRY);
                            break;
                        default:
                            $skip = true;
                            break;
                    }

                    // save each shoutbox
                    if (!$skip) {
                        if ($lastid != $id) {
                            DB::query('UPDATE ' . DB::table('shoutbox') . ' SET ' . $sql . ' WHERE id=' . $lastid);
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

            // save last (or only) shoutbox
            if ($sql != '') {
                $sql = trim($sql, ',');
                DB::query('UPDATE ' . DB::table('shoutbox') . ' SET ' . $sql . ' WHERE id=' . $id);
            }

            $message = Message::ok(_lang('global.saved'));
            break;
    }
}

// delete shoutbox
if (isset($_GET['del']) && Xsrf::check(true)) {
    $del = (int) Request::get('del');
    DB::delete('shoutbox', 'id=' . $del);
    DB::delete('post', 'home=' . $del . ' AND type=' . Post::SHOUTBOX_ENTRY);
    $message = Message::ok(_lang('global.done'));
}

// output
$output .= '
<p class="bborder">' . _lang('admin.content.sboxes.p') . '</p>

' . $message . '

<fieldset class="hs_fieldset">
<legend>' . _lang('admin.content.sboxes.create') . '</legend>
' . Form::start('sbox-create', ['class' => 'cform', 'action' => Router::admin('content-sboxes')]) . '
' . Form::input('hidden', 'action', '1') . '

<table>

<tr>
<th>' . _lang('admin.content.form.title') . '</th>
<td>' . Form::input('text', 'title', null, ['class' => 'inputbig', 'maxlength' => 64]) . '</td>
</tr>

<tr class="valign-top">
<th>' . _lang('admin.content.form.settings') . '</th>
<td>
<label>' . Form::input('checkbox', 'public', '1', ['checked' => true]) . ' ' . _lang('admin.content.form.unregpost') . '</label><br>
<label>' . Form::input('checkbox', 'locked', '1') . ' ' . _lang('admin.content.form.locked2') . '</label>
</td>
</tr>

<tr>
<td></td>
<td>' . Form::input('submit', null, _lang('global.create')) . '</td>
</tr>

</table>

' . Form::end('sbox-create') . '
</fieldset>

<fieldset>
<legend>' . _lang('admin.content.sboxes.manage') . '</legend>
' . Form::start('sboxes', ['class' => 'cform', 'action' => Router::admin('content-sboxes')]) . '
' . Form::input('hidden', 'action', '2') . '

' . Form::input('submit', null, _lang('global.savechanges'), ['accesskey' => 's']) . '
<div class="hr"><hr></div>
';

// list shoutboxes
$shoutboxes = DB::query('SELECT * FROM ' . DB::table('shoutbox') . ' ORDER BY id DESC');

if (DB::size($shoutboxes) != 0) {
    while ($shoutbox = DB::row($shoutboxes)) {
        $output .= '
    <br>
    <table>

    <tr>
    <th>' . _lang('admin.content.form.title') . '</th>
    <td>' . Form::input('text', 's' . $shoutbox['id'] . '_title', $shoutbox['title'], ['class' => 'inputmedium']) . '</td>
    </tr>

    <tr>
    <th>' . _lang('admin.content.form.hcm') . '</th>
    <td>
        ' . Form::input('text', null, Hcm::compose("sbox,{$shoutbox['id']}"), ['onclick' => 'this.select()', 'readonly' => true]) . '
        <br class="mobile-only">
        <small>' . _lang('admin.content.form.thisid') . ' ' . $shoutbox['id'] . '</small>
    </td>
    </tr>

    <tr class="valign-top">
    <th>' . _lang('admin.content.form.settings') . '</th>
    <td>
    ' . Form::input('hidden', 's' . $shoutbox['id'] . '_publictrigger', '1') . Form::input('hidden', 's' . $shoutbox['id'] . '_lockedtrigger', '1') . '
    <label>' . Form::input('checkbox', 's' . $shoutbox['id'] . '_public', '1', ['checked' => (bool) $shoutbox['public']]) . ' ' . _lang('admin.content.form.unregpost') . '</label><br>
    <label>' . Form::input('checkbox', 's' . $shoutbox['id'] . '_locked', '1', ['checked' => (bool) $shoutbox['locked']]) . ' ' . _lang('admin.content.form.locked2') . '</label><br>
    <label>' . Form::input('checkbox', 's' . $shoutbox['id'] . '_delposts', '1') . ' ' . _lang('admin.content.form.delposts') . '</label><br><br>
    <a class="button" href="' . _e(Xsrf::addToUrl(Router::admin('content-sboxes', ['query' => ['del' => $shoutbox['id']]]))) . '" onclick="return Sunlight.confirm();">
        <img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">' . _lang('global.delete') . '
    </a>
    </td>
    </tr>

    </table>
    <br><div class="hr"><hr></div>
    ';
    }
} else {
    $output .= _lang('global.nokit');
}

$output .= '
' . Form::end('sboxes') . '
</fieldset>

';
