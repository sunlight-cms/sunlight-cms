<?php

require '../../bootstrap.php';
Sunlight\Core::init('../../../', array(
    'content_type' => 'text/plain; charset=UTF-8',
));

/* ---  send  --- */

// nacteni promennych
$subject = trim(_post('subject'));
$sender = trim(_post('sender'));
$text = trim(_post('text'));
$fid = (int) _post('fid');

// nacteni prijemce
$skey = 'hcm_' . $fid . '_mail_receiver';
if (isset($_SESSION[$skey])) {
    $receiver = $_SESSION[$skey];
    unset($_SESSION[$skey], $skey);
} else {
    exit($_lang['global.badinput']);
}

// casove omezeni
if (_iplogCheck(_iplog_anti_spam)) {
    // zaznamenat
    _iplogUpdate(_iplog_anti_spam);
} else {
    // prekroceno
    echo str_replace('*postsendexpire*', _postsendexpire, $_lang['misc.requestlimit']);
    exit;
}

// odeslani
if (_xsrfCheck()) {
    if (_validateEmail($sender) && $text != '' && _captchaCheck()) {

        // hlavicky
        $headers = array(
            'Content-Type' => 'text/plain; charset=UTF-8',
        );
        _setMailSender($headers, $sender);

        // uprava predmetu
        if ('' === $subject) {
            $subject = $_lang['hcm.mailform.subjectprefix'];
        } else {
            $subject = sprintf('%s: %s', $_lang['hcm.mailform.subjectprefix'], $subject);
        }

        // pridani informacniho textu do tela
        $info_ip = _userip;
        if (_login) {
            $info_ip .= ' (' . _loginname . ')';
        }
        $info_from = array("*domain*", "*time*", "*ip*", "*sender*");
        $info_to = array(Sunlight\Util\Url::base()->getFullHost(), _formatTime(time()), $info_ip, $sender);
        $text .= "\n\n" . str_repeat('-', 16) . "\n" . str_replace($info_from, $info_to, $_lang['hcm.mailform.info']);

        // odeslani
        if (_mail($receiver, $subject, $text, $headers)) {
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
_returnHeader(_addGetToLink(_returnUrl(), "hcm_mr_" . $fid . "=" . $return, false) . "#hcm_mform_" . $fid);
