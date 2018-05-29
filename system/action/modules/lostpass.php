<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Util\Password;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\Url;

defined('_root') or exit;

if (!_lostpass) {
    $_index['is_found'] = false;
    return;
}

if (_logged_in) {
    $_index['is_guest_only'] = true;
    return;
}

/* ---  vystup  --- */

$_index['title'] = _lang('mod.lostpass');

if (isset($_GET['user'], $_GET['hash'])) {
    // kontrola hashe a zmena hesla
    do {

        // kontrola limitu
        if (!\Sunlight\IpLog::check(_iplog_failed_login_attempt)) {
            $output .= \Sunlight\Message::render(_msg_err, _lang('login.attemptlimit', array('*1*' => _maxloginattempts, '*2*' => _maxloginexpire / 60)));
            break;
        }

        // data uzivatele
        $user = \Sunlight\Util\Request::get('user');
        $hash = \Sunlight\Util\Request::get('hash');
        $userdata = DB::queryRow("SELECT id,email,username,security_hash,security_hash_expires FROM " . _users_table . " WHERE username=" . DB::val($user));
        if (
            $userdata === false
            || $hash !== $userdata['security_hash']
            || time() >= $userdata['security_hash_expires']
        ) {
            \Sunlight\IpLog::update(_iplog_failed_login_attempt);
            $output .= \Sunlight\Message::render(_msg_warn, _lang('mod.lostpass.badlink'));
            $output .= '<p><a href="' . \Sunlight\Router::module('lostpass') . '">' . _lang('global.tryagain') . ' &gt;</a></p>';
            break;
        }

        // vygenerovat heslo a odeslat na email
        $newpass = StringGenerator::generateHash(12);

        if (!\Sunlight\Email::send(
            $userdata['email'],
            _lang('mod.lostpass.mail.subject', array('*domain*' => Url::base()->getFullHost())),
            _lang('mod.lostpass.mail.text2', array(
                '*domain*' => Url::base()->getFullHost(),
                '*username*' =>  $userdata['username'],
                '*newpass*' => $newpass,
                '*date*' => \Sunlight\Generic::renderTime(time()),
                '*ip*' => _user_ip,
            ))
        )) {
            $output .= \Sunlight\Message::render(_msg_err, _lang('global.emailerror'));
            break;
        }

        // zmenit heslo
        DB::update(_users_table, 'id=' . DB::val($userdata['id']), array(
            'password' => Password::create($newpass)->build(),
            'security_hash' => null,
            'security_hash_expires' => 0,
        ));

        // vse ok! email s heslem byl odeslan
        $output .= \Sunlight\Message::render(_msg_ok, _lang('mod.lostpass.generated'));

    } while (false);
} else {
    // zobrazeni formulare
    $output .= "<p class='bborder'>" . _lang('mod.lostpass.p') . "</p>";

    // odeslani emailu
    $sent = false;
    if (isset($_POST['username'])) do {

        // kontrola limitu
        if (!\Sunlight\IpLog::check(_iplog_password_reset_requested)) {
            $output .= \Sunlight\Message::render(_msg_err, _lang('mod.lostpass.limit', array('*limit*' => _lostpassexpire / 60)));
            break;
        }

        // kontrolni obrazek
        if (!\Sunlight\Captcha::check()) {
            $output .= \Sunlight\Message::render(_msg_warn, _lang('captcha.failure2'));
            break;
        }

        // data uzivatele
        $username = \Sunlight\Util\Request::post('username');
        $email = \Sunlight\Util\Request::post('email');
        $userdata = DB::queryRow("SELECT id,email,username FROM " . _users_table . " WHERE username=" . DB::val($username) . " AND email=" . DB::val($email));
        if ($userdata === false) {
            $output .= \Sunlight\Message::render(_msg_warn, _lang('mod.lostpass.notfound'));
            break;
        }

        // vygenerovani hashe
        $hash = hash_hmac('sha256', uniqid('', true), Core::$secret);
        DB::update(_users_table, 'id=' . DB::val($userdata['id']), array(
            'security_hash' => $hash,
            'security_hash_expires' => time() + 3600,
        ));

        // odeslani emailu
        $link = \Sunlight\Router::module('lostpass', 'user=' . $username . '&hash=' . $hash, false, true);

        if (!\Sunlight\Email::send(
            $userdata['email'],
            _lang('mod.lostpass.mail.subject', array('*domain*' => Url::base()->getFullHost())),
            _lang('mod.lostpass.mail.text', array(
                '*domain*' => Url::base()->getFullHost(),
                '*username*' => $userdata['username'],
                '*link*' => $link,
                '*date*' => \Sunlight\Generic::renderTime(time()),
                '*ip*' => _user_ip,
            ))
        )) {
            $output .= \Sunlight\Message::render(_msg_err, _lang('global.emailerror'));
            break;
        }

        // vse ok! email byl odeslan
        \Sunlight\IpLog::update(_iplog_password_reset_requested);
        $output .= \Sunlight\Message::render(_msg_ok, _lang('mod.lostpass.mailsent'));
        $sent = true;

    } while (false);

    // formular
    if (!$sent) {
        $captcha = \Sunlight\Captcha::init();

        $output .= \Sunlight\Util\Form::render(
            array(
                'name' => 'lostpassform',
                'action' => \Sunlight\Router::module('lostpass'),
                'submit_text' => _lang('global.send'),
                'autocomplete' => 'off',
            ),
            array(
                array('label' => _lang('login.username'), 'content' => "<input type='text' class='inputsmall' maxlength='24'" . \Sunlight\Util\Form::restorePostValueAndName('username') . ">"),
                array('label' => _lang('global.email'), 'content' => "<input type='email' class='inputsmall' " . \Sunlight\Util\Form::restorePostValueAndName('email', '@') . ">"),
                $captcha
            )
        );
    }
}
