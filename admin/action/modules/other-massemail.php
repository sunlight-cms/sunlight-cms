<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// send
if (isset($_POST['text'])) {
    $text = Request::post('text');
    $subject = Request::post('subject');
    $sender = Request::post('sender');

    if (isset($_POST['receivers'])) {
        $receivers = (array) $_POST['receivers'];
    } else {
        $receivers = [];
    }

    $ctype = Request::post('ctype');
    $maillist = Form::loadCheckbox('maillist');

    // check variables
    $errors = [];

    if ($text == '' && !$maillist) {
        $errors[] = _lang('admin.other.massemail.notext');
    }

    if (empty($receivers)) {
        $errors[] = _lang('admin.other.massemail.noreceivers');
    }

    if ($subject == '' && !$maillist) {
        $errors[] = _lang('admin.other.massemail.nosubject');
    }

    if (!Email::validate($sender) && !$maillist) {
        $errors[] = _lang('admin.other.massemail.badsender');
    }

    if (empty($errors)) {
        // headers
        $headers = [
            'Content-Type' => 'text/' . ($ctype == 2 ? 'html' : 'plain') . '; charset=UTF-8',
        ];
        Email::defineSender($headers, $sender);

        // get users
        $query = DB::query(
            'SELECT email,password'
            . ' FROM ' . DB::table('user') . ' u'
            . ' JOIN ' . DB::table('user_group') . ' g ON g.id = u.group_id'
            . ' WHERE u.massemail=1 AND u.blocked=0 AND g.blocked=0 AND u.group_id IN(' . DB::arr($receivers) . ')'
        );

        // send emails or list emails
        if (!$maillist) {
            $total = DB::size($query);
            $done = 0;

            while ($item = DB::row($query)) {
                $footer = _lang('admin.other.massemail.emailnotice.' . ($ctype == 1 ? 'text' : 'html'), [
                    '%domain%' => Core::getBaseUrl()->getFullHost(),
                    '%unsub_link%' => Router::module(
                        'massemail',
                        [
                            'query' => [
                                'email' => $item['email'],
                                'key' => User::getAuthHash(User::AUTH_MASSEMAIL, $item['email'], $item['password']),
                            ],
                            'absolute' => true,
                        ]
                    ),
                ]);

                if (Email::send($item['email'], $subject, $text . $footer, $headers)) {
                    ++$done;
                }
            }

            // message
            if ($done != 0) {
                Logger::notice('massemail', sprintf('Sent a mass email to %d recipients', $done), [
                    'subject' => $subject,
                    'text' => $text,
                    'headers' => $headers,
                    'group_ids' => $receivers,
                ]);

                $output .= Message::ok(_lang('admin.other.massemail.send', [
                    '%done%' => _num($done),
                    '%total%' => _num($total),
                ]));
            } else {
                $output .= Message::warning(_lang('admin.other.massemail.noreceiversfound'));
            }
        } else {
            // list emails
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

                $output .= Message::ok('<textarea class="areasmallwide" rows="9" cols="33" name="list">' . $emails . '</textarea>', true);
            } else {
                $output .= Message::warning(_lang('admin.other.massemail.noreceiversfound'));
            }
        }
    } else {
        $output .= Message::list($errors);
    }
}

// output
$output .= '
<br>
<form class="cform" action="' . _e(Router::admin('other-massemail')) . '" method="post">
<table class="formtable">

<tr>
<th>' . _lang('admin.other.massemail.sender') . '</th>
<td><input type="email"' . Form::restorePostValueAndName('sender', Settings::get('sysmail')) . ' class="inputbig"></td>
</tr>

<tr>
<th>' . _lang('posts.subject') . '</th>
<td><input type="text" class="inputbig"' . Form::restorePostValueAndName('subject') . '></td>
</tr>

<tr class="valign-top">
<th>' . _lang('admin.other.massemail.receivers') . '</th>
<td>'
    . Admin::userSelect('receivers', [
        'selected' => Request::post('receivers', [], true),
        'group_cond' => 'blocked=0',
        'user_cond' => 'massemail=1 AND blocked=0',
        'class' => 'selectbig',
        'select_groups' => true,
        'multiple' => 8,
    ])
. '</td>
</tr>

<tr>
<th>' . _lang('admin.other.massemail.ctype') . '</th>
<td>
  <select name="ctype" class="selectbig">
  <option value="1">' . _lang('admin.other.massemail.ctype.1') . '</option>
  <option value="2"' . Form::selectOption(Request::post('ctype') == 2) . '>' . _lang('admin.other.massemail.ctype.2') . '</option>
  </select>
</td>
</tr>

<tr class="valign-top">
<th>' . _lang('admin.other.massemail.text') . '</th>
<td>' . Admin::editor('massemail-body', 'text', Form::restorePostValue('text', null, false), ['mode' => 'code', 'rows' => 9]) . '</td>
</tr>

<tr><td></td>
<td><input type="submit" value="' . _lang('global.send') . '"> <label><input type="checkbox" name="maillist" value="1"' . Form::activateCheckbox(Form::loadCheckbox('maillist')) . '> ' . _lang('admin.other.massemail.maillist') . '</label></td>
</tr>

</table>
' . Xsrf::getInput() . '</form>
';
