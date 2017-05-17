<?php

if (!defined('_root')) {
    exit;
}

if (!_lostpass) {
    $_index['is_found'] = false;
    return;
}

if (_login) {
    $_index['is_guest_only'] = true;
    return;
}

/* ---  vystup  --- */

$_index['title'] = $_lang['mod.lostpass'];

if (isset($_GET['user'], $_GET['hash'])) {
    // kontrola hashe a zmena hesla
    do {

        // kontrola limitu
        if (!_iplogCheck(_iplog_failed_login_attempt)) {
            $output .= _msg(_msg_err, str_replace(array('*1*', '*2*'), array(_maxloginattempts, _maxloginexpire / 60), $_lang['login.attemptlimit']));
            break;
        }

        // data uzivatele
        $user = _get('user');
        $hash = _get('hash');
        $userdata = DB::queryRow("SELECT id,email,username,security_hash,security_hash_expires FROM " . _users_table . " WHERE username=" . DB::val($user));
        if (
            false === $userdata
            || $hash !== $userdata['security_hash']
            || time() >= $userdata['security_hash_expires']
        ) {
            _iplogUpdate(_iplog_failed_login_attempt);
            $output .= _msg(_msg_warn, $_lang['mod.lostpass.badlink']);
            $output .= '<p><a href="' . _linkModule('lostpass') . '">' . $_lang['global.tryagain'] . ' &gt;</a></p>';
            break;
        }

        // vygenerovat heslo a odeslat na email
        $newpass = Sunlight\Util\StringGenerator::generateHash(12);
        $text_tags = array('*domain*', '*username*', '*newpass*', '*date*', '*ip*');
        $text_contents = array(Sunlight\Util\Url::base()->getFullHost(), $userdata['username'], $newpass, _formatTime(time()), _userip);

        if (!_mail(
            $userdata['email'],
            str_replace('*domain*', Sunlight\Util\Url::base()->getFullHost(), $_lang['mod.lostpass.mail.subject']),
            str_replace($text_tags, $text_contents, $_lang['mod.lostpass.mail.text2'])
        )) {
            $output .= _msg(_msg_err, $_lang['global.emailerror']);
            break;
        }

        // zmenit heslo
        DB::update(_users_table, 'id=' . DB::val($userdata['id']), array(
            'password' => Sunlight\Util\Password::create($newpass)->build(),
            'security_hash' => null,
            'security_hash_expires' => 0,
        ));

        // vse ok! email s heslem byl odeslan
        $output .= _msg(_msg_ok, $_lang['mod.lostpass.generated']);

    } while (false);
} else {
    // zobrazeni formulare
    $output .= "<p class='bborder'>" . $_lang['mod.lostpass.p'] . "</p>";

    // odeslani emailu
    $sent = false;
    if (isset($_POST['username'])) do {

        // kontrola limitu
        if (!_iplogCheck(_iplog_password_reset_requested)) {
            $output .= _msg(_msg_err, str_replace('*limit*', _lostpassexpire / 60, $_lang['mod.lostpass.limit']));
            break;
        }

        // kontrolni obrazek
        if (!_captchaCheck()) {
            $output .= _msg(_msg_warn, $_lang['captcha.failure2']);
            break;
        }

        // data uzivatele
        $username = _post('username');
        $email = _post('email');
        $userdata = DB::queryRow("SELECT id,email,username FROM " . _users_table . " WHERE username=" . DB::val($username) . " AND email=" . DB::val($email));
        if (false === $userdata) {
            $output .= _msg(_msg_warn, $_lang['mod.lostpass.notfound']);
            break;
        }

        // vygenerovani hashe
        $hash = hash_hmac('sha256', uniqid('', true), Sunlight\Core::$secret);
        DB::update(_users_table, 'id=' . DB::val($userdata['id']), array(
            'security_hash' => $hash,
            'security_hash_expires' => time() + 3600,
        ));

        // odeslani emailu
        $link = _linkModule('lostpass', 'user=' . $username . '&hash=' . $hash, false, true);
        $text_tags = array('*domain*', '*username*', '*link*', '*date*', '*ip*');
        $text_contents = array(Sunlight\Util\Url::base()->getFullHost(), $userdata['username'], $link, _formatTime(time()), _userip);

        if (!_mail(
            $userdata['email'],
            str_replace('*domain*', Sunlight\Util\Url::base()->getFullHost(), $_lang['mod.lostpass.mail.subject']),
            str_replace($text_tags, $text_contents, $_lang['mod.lostpass.mail.text'])
        )) {
            $output .= _msg(_msg_err, $_lang['global.emailerror']);
            break;
        }

        // vse ok! email byl odeslan
        _iplogUpdate(_iplog_password_reset_requested);
        $output .= _msg(_msg_ok, $_lang['mod.lostpass.mailsent']);
        $sent = true;

    } while (false);

    // formular
    if (!$sent) {
        $captcha = _captchaInit();

        $output .= _formOutput(
            array(
                'name' => 'lostpassform',
                'action' => _linkModule('lostpass'),
                'submit_text' => $_lang['global.send'],
                'autocomplete' => 'off',
            ),
            array(
                array('label' => $_lang['login.username'], 'content' => "<input type='text' class='inputsmall' maxlength='24'" . _restorePostValueAndName('username') . ">"),
                array('label' => $_lang['global.email'], 'content' => "<input type='email' class='inputsmall' " . _restorePostValueAndName('email', '@') . ">"),
                $captcha
            )
        );
    }
}
