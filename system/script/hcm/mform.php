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

require '../../bootstrap.php';
Core::init('../../../', [
    'content_type' => 'text/plain; charset=UTF-8',
]);

/* ---  send  --- */

// nacteni promennych
$subject = trim(Request::post('subject', ''));
$sender = trim(Request::post('sender', ''));
$text = trim(Request::post('text', ''));
$fid = (int) Request::post('fid');

// nacteni prijemce
$skey = 'hcm_' . $fid . '_mail_receiver';
if (isset($_SESSION[$skey])) {
    $receiver = $_SESSION[$skey];
    unset($_SESSION[$skey], $skey);
} else {
    exit(_lang('global.badinput'));
}

// casove omezeni
if (IpLog::check(IpLog::ANTI_SPAM)) {
    // zaznamenat
    IpLog::update(IpLog::ANTI_SPAM);
} else {
    // prekroceno
    echo _lang('misc.antispam_error', ['%antispamtimeout%' => Settings::get('antispamtimeout')]);
    exit;
}

// odeslani
if (Xsrf::check()) {
    if (Email::validate($sender) && $text != '' && Captcha::check()) {

        // hlavicky
        $headers = [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ];
        Email::defineSender($headers, $sender);

        // uprava predmetu
        if ($subject === '') {
            $subject = _lang('hcm.mailform.subjectprefix');
        } else {
            $subject = sprintf('%s: %s', _lang('hcm.mailform.subjectprefix'), $subject);
        }

        // pridani informacniho textu do tela
        $info_ip = Core::getClientIp();
        if (User::isLoggedIn()) {
            $info_ip .= ' (' . User::getUsername() . ')';
        }
        $text .= "\n\n" . str_repeat('-', 16) . "\n" . _lang('hcm.mailform.info', [
            '%domain%' => Core::getBaseUrl()->getFullHost(),
            '%time%' => GenericTemplates::renderTime(time()),
            '%ip%' => $info_ip,
            '%sender%' => $sender,
        ]);

        // odeslani
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

// presmerovani zpet
Response::redirectBack(UrlHelper::appendParams(Response::getReturnUrl(), "hcm_mr_" . $fid . "=" . $return) . "#hcm_mform_" . $fid);
