<?php

use Sunlight\Captcha;
use Sunlight\Hcm;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

return function ($receiver = '', $subject = null) {
    $result = '';
    $_SESSION['hcm_' . Hcm::$uid . '_mail_receiver'] = implode(',', Arr::removeValue(explode(';', trim($receiver)), ''));

    if (isset($subject)) {
        $subject_value = ' value="' . _e($subject) . '"';
    } else {
        $subject_value = '';
    }

    $rcaptcha = Captcha::init();

    // message
    $msg = '';

    if (isset($_GET['hcm_mr_' . Hcm::$uid])) {
        switch (Request::get('hcm_mr_' . Hcm::$uid)) {
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

    // pre-fill the sender
    if (User::isLoggedIn()) {
        $sender = User::$data['email'];
    } else {
        $sender = '@';
    }

    $result .= $msg
        . Form::render(
            [
                'id' =>  'hcm_mform_' . Hcm::$uid,
                'name' => 'mform' . Hcm::$uid,
                'action' => Router::path('system/script/hcm/mform.php', ['query' => ['_return' => $GLOBALS['_index']->url]]),
            ],
            [
                ['label' => _lang('hcm.mailform.sender'), 'content' => '<input type="email" class="inputsmall" name="sender" value="' . _e($sender) . '"><input type="hidden" name="fid" value="' . Hcm::$uid . '">' ],
                ['label' => _lang('posts.subject'), 'content' => '<input type="text" class="inputsmall" name="subject"' . $subject_value . '>'],
                $rcaptcha,
                ['label' => _lang('hcm.mailform.text'), 'content' => '<textarea class="areasmall" name="text" rows="9" cols="33"></textarea>', 'top' => true],
                Form::getSubmitRow(['text' => _lang('hcm.mailform.send')])
            ]
        );

    return $result;
};
