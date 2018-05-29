<?php

use Sunlight\Core;
use Sunlight\Util\Url;

require '../../bootstrap.php';
Core::init('../../../', array(
    'content_type' => 'text/plain; charset=UTF-8',
));

/* ---  send  --- */

// nacteni promennych
$subject = trim(\Sunlight\Util\Request::post('subject'));
$sender = trim(\Sunlight\Util\Request::post('sender'));
$text = trim(\Sunlight\Util\Request::post('text'));
$fid = (int) \Sunlight\Util\Request::post('fid');

// nacteni prijemce
$skey = 'hcm_' . $fid . '_mail_receiver';
if (isset($_SESSION[$skey])) {
    $receiver = $_SESSION[$skey];
    unset($_SESSION[$skey], $skey);
} else {
    exit(_lang('global.badinput'));
}

// casove omezeni
if (\Sunlight\IpLog::check(_iplog_anti_spam)) {
    // zaznamenat
    \Sunlight\IpLog::update(_iplog_anti_spam);
} else {
    // prekroceno
    echo _lang('misc.requestlimit', array('*postsendexpire*' => _postsendexpire));
    exit;
}

// odeslani
if (\Sunlight\Xsrf::check()) {
    if (\Sunlight\Email::validate($sender) && $text != '' && \Sunlight\Captcha::check()) {

        // hlavicky
        $headers = array(
            'Content-Type' => 'text/plain; charset=UTF-8',
        );
        \Sunlight\Email::defineSender($headers, $sender);

        // uprava predmetu
        if ($subject === '') {
            $subject = _lang('hcm.mailform.subjectprefix');
        } else {
            $subject = sprintf('%s: %s', _lang('hcm.mailform.subjectprefix'), $subject);
        }

        // pridani informacniho textu do tela
        $info_ip = _user_ip;
        if (_logged_in) {
            $info_ip .= ' (' . _user_name . ')';
        }
        $text .= "\n\n" . str_repeat('-', 16) . "\n" . _lang('hcm.mailform.info', array(
            '*domain*' => Url::base()->getFullHost(),
            '*time*' => \Sunlight\Generic::renderTime(time()),
            '*ip*' => $info_ip,
            '*sender*' => $sender,
        ));

        // odeslani
        if (\Sunlight\Email::send($receiver, $subject, $text, $headers)) {
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
\Sunlight\Response::redirectBack(\Sunlight\Util\UrlHelper::appendParams(\Sunlight\Response::getReturnUrl(), "hcm_mr_" . $fid . "=" . $return, false) . "#hcm_mform_" . $fid);
