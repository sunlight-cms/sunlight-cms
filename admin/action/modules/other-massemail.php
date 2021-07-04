<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Message;
use Sunlight\Settings;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  odeslani  --- */

if (isset($_POST['text'])) {

    // nacteni promennych
    $text = Request::post('text');
    $subject = Request::post('subject');
    $sender = Request::post('sender');
    if (isset($_POST['receivers'])) {
        $receivers = (array) $_POST['receivers'];
    } else {
        $receivers = [];
    }
    $ctype = Request::post('ctype');
    $maillist = Form::loadCheckbox("maillist");

    // kontrola promennych
    $errors = [];
    if ($text == "" && !$maillist) {
        $errors[] = _lang('admin.other.massemail.notext');
    }
    if (count($receivers) == 0) {
        $errors[] = _lang('admin.other.massemail.noreceivers');
    }
    if ($subject == "" && !$maillist) {
        $errors[] = _lang('admin.other.massemail.nosubject');
    }
    if (!Email::validate($sender) && !$maillist) {
        $errors[] = _lang('admin.other.massemail.badsender');
    }

    if (count($errors) == 0) {

        // sestaveni casti sql dotazu - 'where'
        $groups = 'group_id IN(' . DB::arr($receivers) . ')';

        // hlavicky
        $headers = [
            'Content-Type' => 'text/' . ($ctype == 2 ? 'html' : 'plain') . '; charset=UTF-8',
        ];
        Email::defineSender($headers, $sender);

        // nacteni prijemcu
        $query = DB::query("SELECT email FROM " . _user_table . " WHERE massemail=1 AND (" . $groups . ")");

        // odeslani nebo zobrazeni adres
        if (!$maillist) {

            // priprava
            $rec_buffer = [];
            $rec_buffer_size = 20;
            $rec_buffer_counter = 0;
            $item_counter = 0;
            $item_total = DB::size($query);

            // poznamka na konci zpravy
            $notice = _lang('admin.other.massemail.emailnotice', ['%domain%' => Core::getBaseUrl()->getFullHost()]);
            if ($ctype == 1) {
                $notice = "\n\n\n-------------------------------------\n" . $notice;
            } else {
                $notice = "<br><br><hr><p><small>" . _e($notice) . "</small></p>";
            }
            $text .= $notice;

            // postupne odesilani po skupinach
            $done = 0;
            while ($item = DB::row($query)) {
                $rec_buffer[] = $item['email'];
                ++$rec_buffer_counter;
                ++$item_counter;
                if ($rec_buffer_counter === $rec_buffer_size || $item_counter === $item_total) {
                    // odeslani emailu
                    if (Email::send('', $subject, $text, $headers + ['Bcc' => implode(",", $rec_buffer)])) {
                        $done += count($rec_buffer);
                    }
                    $rec_buffer = [];
                    $rec_buffer_counter = 0;
                }
            }

            // zprava
            if ($done != 0) {
                $output .= Message::ok(_lang('admin.other.massemail.send', [
                    '%done%' => $done,
                    '%total%' => $item_total,
                ]));
            } else {
                $output .= Message::warning(_lang('admin.other.massemail.noreceiversfound'));
            }

        } else {

            // vypis emailu
            $emails_total = DB::size($query);
            if ($emails_total != 0) {

                $emails = '';
                $email_counter = 0;
                while ($item = DB::row($query)) {
                    ++$email_counter;
                    $emails .= $item['email'];
                    if ($email_counter !== $emails_total) {
                        $emails .= ',';
                    }
                }

                $output .= Message::ok("<textarea class='areasmallwide' rows='9' cols='33' name='list'>" . $emails . "</textarea>", true);

            } else {
                $output .= Message::warning(_lang('admin.other.massemail.noreceiversfound'));
            }

        }

    } else {
        $output .= Message::list($errors);
    }

}

/* ---  vystup  --- */

$output .= "
<br>
<form class='cform' action='index.php?p=other-massemail' method='post'>
<table class='formtable'>

<tr>
<th>" . _lang('admin.other.massemail.sender') . "</th>
<td><input type='email'" . Form::restorePostValueAndName('sender', Settings::get('sysmail')) . " class='inputbig'></td>
</tr>

<tr>
<th>" . _lang('posts.subject') . "</th>
<td><input type='text' class='inputbig'" . Form::restorePostValueAndName('subject') . "></td>
</tr>

<tr class='valign-top'>
<th>" . _lang('admin.other.massemail.receivers') . "</th>
<td>" . Admin::userSelect("receivers", -1, "1", "selectbig", null, true, 4) . "</td>
</tr>

<tr>
<th>" . _lang('admin.other.massemail.ctype') . "</th>
<td>
  <select name='ctype' class='selectbig'>
  <option value='1'>" . _lang('admin.other.massemail.ctype.1') . "</option>
  <option value='2'" . (Request::post('ctype') == 2 ? " selected" : '') . ">" . _lang('admin.other.massemail.ctype.2') . "</option>
  </select>
</td>
</tr>

<tr class='valign-top'>
<th>" . _lang('admin.other.massemail.text') . "</th>
<td><textarea name='text' class='areabig editor' rows='9' cols='94' data-editor-mode='code'>" . Form::restorePostValue('text', null, false) . "</textarea></td>
</tr>

<tr><td></td>
<td><input type='submit' value='" . _lang('global.send') . "'> <label><input type='checkbox' name='maillist' value='1'" . Form::activateCheckbox(Form::loadCheckbox("maillist")) . "> " . _lang('admin.other.massemail.maillist') . "</label></td>
</tr>

</table>
" . Xsrf::getInput() . "</form>
";
