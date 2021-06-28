<?php

use Sunlight\Captcha;
use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

return function ($adresa = "", $predmet = null) {
    // priprava
    $result = "";
    $_SESSION['hcm_' . Core::$hcmUid . '_mail_receiver'] = implode(",", Arr::removeValue(explode(";", trim($adresa)), ""));
    if (isset($predmet)) {
        $rsubject = " value='" . _e($predmet) . "'";
    } else {
        $rsubject = "";
    }
    $rcaptcha = Captcha::init();

    // zprava
    $msg = '';
    if (isset($_GET['hcm_mr_' . Core::$hcmUid])) {
        switch (Request::get('hcm_mr_' . Core::$hcmUid)) {
            case 1:
                $msg = Message::ok(_lang('hcm.mailform.msg.done'));
                break;
            case 2:
                $msg = Message::warning(_lang('hcm.mailform.msg.failure'));
                break;
            case 3:
                $msg = Message::error(_lang('global.emailerror'));
                break;
            case 4:
                $msg = Message::error(_lang('xsrf.msg'));
                break;
        }
    }

    // predvyplneni odesilatele
    if (_logged_in) {
        $sender = User::$data['email'];
    } else {
        $sender = "&#64;";
    }

    $result .= $msg
        . Form::render(
            [
                'id' =>  'hcm_mform_' . Core::$hcmUid,
                'name' => 'mform' . Core::$hcmUid,
                'action' => Router::generate('system/script/hcm/mform.php?_return=' . rawurlencode($GLOBALS['_index']['url'])),
                'submit_text' => _lang('hcm.mailform.send'),
            ],
            [
                ['label' => _lang('hcm.mailform.sender'), 'content' => "<input type='text' class='inputsmall' name='sender' value='" . $sender . "'><input type='hidden' name='fid' value='" . Core::$hcmUid . "'>"],
                ['label' => _lang('posts.subject'), 'content' => "<input type='text' class='inputsmall' name='subject'" . $rsubject . ">"],
                $rcaptcha,
                ['label' => _lang('hcm.mailform.text'), 'content' => "<textarea class='areasmall' name='text' rows='9' cols='33'></textarea>", 'top' => true],
            ]
        );

    return $result;
};
