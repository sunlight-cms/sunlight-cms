<?php

use Sunlight\Captcha;
use Sunlight\Core;
use Sunlight\Email;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Response;
use Sunlight\Util\Request;
use Sunlight\Util\UrlHelper;
use Sunlight\Xsrf;

require __DIR__ . '/../../bootstrap.php';
Core::init(['content_type' => 'text/plain; charset=UTF-8']);

// load variables
$subject = trim(Request::post('subject', ''));
$sender = trim(Request::post('sender', ''));
$text = trim(Request::post('text', ''));
$fid = (int) Request::post('fid');

// load receiver
$skey = 'hcm_' . $fid . '_mail_receiver';

if (isset($_SESSION[$skey])) {
    $receiver = $_SESSION[$skey];
    unset($_SESSION[$skey], $skey);
} else {
    exit(_lang('global.badinput'));
}

// check anti spam
if (IpLog::check(IpLog::ANTI_SPAM)) {
    IpLog::update(IpLog::ANTI_SPAM);
} else {
    echo _lang('error.antispam', ['%antispamtimeout%' => Settings::get('antispamtimeout')]);
    exit;
}

// send
if (Xsrf::check()) {
    if (Email::validate($sender) && $text != '' && Captcha::check()) {
        // headers
        $headers = [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ];
        Email::defineSender($headers, $sender);

        // update subject
        if ($subject === '') {
            $subject = _lang('hcm.mailform.subjectprefix');
        } else {
            $subject = sprintf('%s: %s', _lang('hcm.mailform.subjectprefix'), $subject);
        }

        // add information to the message body
        $info_ip = Core::getClientIp();

        if (User::isLoggedIn()) {
            $info_ip .= ' (' . User::getUsername() . ')';
        }

        $text .= "\n\n" . str_repeat('-', 16) . "\n" . _lang('hcm.mailform.info', [
            '%domain%' => Core::getBaseUrl()->getFullHost(),
            '%time%' => GenericTemplates::renderTime(time(), 'email'),
            '%ip%' => $info_ip,
            '%sender%' => $sender,
        ]);

        // send
        if (Email::send($receiver, $subject, $text, $headers)) {
            $return = 1;
        } else {
            $return = 3;
        }
    } else {
        $return = 2;
    }
} else {
    $return = 4;
}

// redirect back
Response::redirectBack(UrlHelper::appendParams(Response::getReturnUrl(), 'hcm_mr_' . $fid . '=' . $return) . '#hcm_mform_' . $fid);
