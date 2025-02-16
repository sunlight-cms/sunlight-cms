<?php

use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

// output text and buttons
$output .= '<p class="bborder">' . _lang('admin.content.redir.p') . '</p>
<p>
    <a class="button" href="' . _e(Router::admin('content-redir', ['query' => ['new' => 1]])) . '"><img src="' . _e(Router::path('admin/public/images/icons/new.png')) . '" alt="new" class="icon">' . _lang('admin.content.redir.act.new') . '</a>
    <a class="button" href="' . _e(Router::admin('content-redir', ['query' => ['wipe' => 1]])) . '"><img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="wipe" class="icon">' . _lang('admin.content.redir.act.wipe') . '</a>
</p>
';

// action
if (isset($_GET['new']) || isset($_GET['edit'])) {
    do {
        $new = isset($_GET['new']);

        if (!$new) {
            $edit_id = (int) Request::get('edit');
        }

        // save
        if (isset($_POST['old'])) {
            $q = [];
            $q['old'] = StringHelper::slugify(trim(Request::post('old', '')), ['extra' => '._/']);
            $q['new'] = StringHelper::slugify(trim(Request::post('new', '')), ['extra' => '._/']);
            $q['permanent'] = Form::loadCheckbox('permanent');
            $q['active'] = Form::loadCheckbox('act');

            // check params
            if ($q['old'] === '' || $q['new'] === '') {
                $message = Message::warning(_lang('admin.content.redir.emptyidt'));
            } elseif ($new) {
                // create
                DB::insert('redirect', $q);
                $new = false;
                $message = Message::ok(_lang('global.created'));
                break;
            } else {
                // update
                DB::update('redirect', 'id=' . DB::val($edit_id), $q);
                $message = Message::ok(_lang('global.saved'));
            }
        }

        // load data
        if ($new) {
            if (!isset($q)) {
                $q = [];
            }

            $q += ['id' => null, 'old' => '', 'new' => '', 'permanent' => '0', 'active' => '1'];
        } else {
            $q = DB::queryRow('SELECT * FROM ' . DB::table('redirect') . ' WHERE id=' . $edit_id);

            if ($q === false) {
                break;
            }
        }

        // form
        $output .= $message . "\n"
. Form::start('content-redir-edit') . '
<table class="formtable">

<tr>
    <th>' . _lang('admin.content.redir.old') . '</th>
    <td>' . Form::input('text', 'old', $q['old'], ['class' => 'inputmedium', 'maxlength' => 255]) . '</td>
</tr>

<tr>
    <th>' . _lang('admin.content.redir.new') . '</th>
    <td>' . Form::input('text', 'new', $q['new'], ['class' => 'inputmedium', 'maxlength' => 255]) . '</td>
</tr>

<tr>
    <th>' . _lang('admin.content.redir.permanent') . '</th>
    <td>' . Form::input('checkbox', 'permanent', '1', ['checked' => (bool) $q['permanent']]) . '</td>
</tr>

<tr>
    <th>' . _lang('admin.content.redir.act') . '</th>
    <td>' . Form::input('checkbox', 'act', '1', ['checked' => (bool) $q['active']]) . '</td>
</tr>

<tr>
    <td></td>
    <td>' . Form::input('submit', null, _lang('global.' . ($new ? 'create' : 'save'))) . '</td>
</tr>

</table>
' . Form::end('content-redir-edit');
    } while (false);
} elseif (isset($_GET['del']) && Xsrf::check(true)) {
    // delete
    DB::delete('redirect', 'id=' . DB::val(Request::get('del')));
    $output .= Message::ok(_lang('global.done'));
} elseif (isset($_GET['wipe'])) {
    // delete all
    if (isset($_POST['wipe_confirm'])) {
        DB::query('TRUNCATE TABLE ' . DB::table('redirect'));
        $output .= Message::ok(_lang('global.done'));
    } else {
        $output .= '
' . Form::start('content-redir-delete') . '
' . Message::warning(_lang('admin.content.redir.act.wipe.confirm')) . '
' . Form::input('submit', 'wipe_confirm', _lang('global.confirmdelete')) . '
' . Form::end('content-redir-edit') . '
';
    }
}

// table
$output .= '
<div class="horizontal-scroller">
<table class="list list-hover list-max">
<thead>
<tr>
    <td>' . _lang('admin.content.redir.old') . '</td>
    <td>' . _lang('admin.content.redir.new') . '</td>
    <td>' . _lang('admin.content.redir.permanent') . '</td>
    <td>' . _lang('admin.content.redir.act') . '</td>
    <td>' . _lang('global.action') . '</td>
</tr>
</thead>
<tbody>
';

// list
$counter = 0;
$q = DB::query('SELECT * FROM ' . DB::table('redirect'));

while ($r = DB::row($q)) {
    $output .= '<tr>
        <td><code>' . $r['old'] . '</code></td>
        <td><code>' . $r['new'] . '</code></td>
        <td class="text-' . ($r['permanent'] ? 'success' : 'danger') . '">' . _lang('global.' . ($r['permanent'] ? 'yes' : 'no')) . '</td>
        <td class="text-' . ($r['active'] ? 'success' : 'danger') . '">' . _lang('global.' . ($r['active'] ? 'yes' : 'no')) . '</td>
        <td class="actions">
            <a class="button" href="' . _e(Router::admin('content-redir', ['query' => ['edit' => $r['id']]])) . '">
                <img src="' . _e(Router::path('admin/public/images/icons/edit.png')) . '" alt="edit" class="icon">' . _lang('global.edit') . '
            </a>
            <a class="button" href="' . _e(Xsrf::addToUrl(Router::admin('content-redir', ['query' => ['del' => $r['id']]]))) . '" onclick="return Sunlight.confirm();">
                <img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" alt="del" class="icon">' . _lang('global.delete') . '
            </a>
        </td>
    </tr>';
    ++$counter;
}

// no items?
if ($counter === 0) {
    $output .= '<tr><td colspan="5">' . _lang('global.nokit') . "</td></tr>\n";
}

// end table
$output .= "</tbody>
</table>
</div>\n";
