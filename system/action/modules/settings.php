<?php

use Sunlight\Core;
use Sunlight\Plugin\PluginManager;

if (!defined('_root')) {
    exit;
}

if (!_login) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava promennych  --- */

$message = "";
$userdata = Sunlight\Core::$userData;

// cesta k avataru
$avatar_path = _getAvatar($userdata, array('get_url' => true, 'extend' => false));

/* ---  ulozeni  --- */

if (isset($_POST['username'])) {

    $errors = array();

    // smazani vlastniho uctu
    if (_priv_selfremove && _checkboxLoad('selfremove')) {
        if (Sunlight\Util\Password::load($userdata['password'])->match(_post('selfremove-confirm'))) {
            if (_loginid != 0) {
                _deleteUser(_loginid);
                $_SESSION = array();
                session_destroy();
                $_index['redirect_to'] = _linkModule('login', 'login_form_result=4', false, true);

                return;
            } else {
                $errors[] = $_lang['mod.settings.selfremove.denied'];
            }
        } else {
            $errors[] = $_lang['mod.settings.selfremove.failed'];
        }
    }

    // username
    $username = _post('username');
    if (mb_strlen($username) > 24) {
        $username = mb_substr($username, 0, 24);
    }
    $username = _slugify($username, false);
    if ($username == "") {
        $errors[] = $_lang['user.msg.badusername'];
    } else {
        $usernamechange = false;
        if ($username != _loginname) {
            if (_priv_changeusername || mb_strtolower($username) == mb_strtolower(_loginname)) {
                if (DB::count(_users_table, '(username=' . DB::val($username) . ' OR publicname=' . DB::val($username) . ') AND id!=' . _loginid) === 0) {
                    $usernamechange = true;
                } else {
                    $errors[] = $_lang['user.msg.userexists'];
                }
            } else {
                $errors[] = $_lang['mod.settings.error.usernamechangedenied'];
            }
        }
    }

    // publicname
    $publicname = _e(_wsTrim(_post('publicname')));
    if (mb_strlen($publicname) > 24) {
        $errors[] = $_lang['user.msg.publicnametoolong'];
    } elseif ($publicname != $userdata['publicname'] && $publicname != "") {
        if (DB::count(_users_table, '(publicname=' . DB::val($publicname) . ' OR username=' . DB::val($publicname) . ') AND id!=' . _loginid) !== 0) {
            $errors[] = $_lang['user.msg.publicnameexists'];
        }
    }
    if ('' === $publicname) {
        $publicname = null;
    }

    // email
    $email = trim(_post('email'));
    if (!_validateEmail($email)) {
        $errors[] = $_lang['user.msg.bademail'];
    } else {
        if ($email != _loginemail) {
            if ('' === _post('currentpassword')) {
                $errors[] = $_lang['mod.settings.error.emailchangenopass'];
            } elseif (!Sunlight\Util\Password::load($userdata['password'])->match(_post('currentpassword'))) {
                $errors[] = $_lang['mod.settings.error.badcurrentpass'];
            }
            if (DB::count(_users_table, 'email=' . DB::val($email) . ' AND id!=' . _loginid) !== 0) {
                $errors[] = $_lang['user.msg.emailexists'];
            }
        }
    }

    // massemail, wysiwyg, icq
    $massemail = _checkboxLoad('massemail');
    if (_priv_administration) {
        $wysiwyg = _checkboxLoad('wysiwyg');
    }
    $icq = _cutHtml(_e(trim(_post('icq'))), 255);

    // skype
    $skype = _cutHtml(_e(trim(_post('skype'))), 255);

    // web
    $web = trim(_post('web'));
    if ($web != "") {
        $web = _addSchemeToURL($web);
        if (_validateURL($web)) {
            $web = _cutHtml(_e($web), 255);
        } else {
            $web = "";
        }
    }

    // avatar
    $avatar = $userdata['avatar'];
    if (_uploadavatar) {

        // smazani avataru
        if (_checkboxLoad("removeavatar") && isset($avatar)) {
            @unlink(_root . 'images/avatars/' . $avatar . '.jpg');
            $avatar = null;
        }

        // upload avataru
        if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {

            // zpracovani
            $avatarUid = _pictureProcess(array(
                'file_path' => $_FILES['avatar']['tmp_name'],
                'file_name' => $_FILES['avatar']['name'],
                'limit' => array('filesize' => 1048576, 'dimensions' => array('x' => 1400, 'y' => 1400)),
                'resize' => array('mode' => 'zoom', 'x' => 96, 'y' => 128),
                'target_path' => _root . 'images/avatars/',
                'target_format' => 'jpg',
                'jpg_quality' => 95,
            ), $avatarError);

            if (false !== $avatarUid) {

                // smazani stareho avataru
                if (null !== $avatar) {
                    @unlink(_root . 'images/avatars/' . $avatar . '.jpg');
                }

                // ok
                $avatar = $avatarUid;

            } else {
                $errors[] = $_lang['global.avatar'] . ' - ' . $avatarError;
            }

        }

    }

    // password
    $passwordchange = false;
    if (_post('newpassword') != "" || _post('newpassword-confirm') != "") {
        $newpassword = _post('newpassword');
        $newpassword_confirm = _post('newpassword-confirm');
        if (Sunlight\Util\Password::load($userdata['password'])->match(_post('currentpassword'))) {
            if ($newpassword == $newpassword_confirm) {
                if ($newpassword != "") {
                    $passwordchange = true;
                    $newpassword = Sunlight\Util\Password::create($newpassword)->build();
                } else {
                    $errors[] = $_lang['mod.settings.error.badnewpass'];
                }
            } else {
                $errors[] = $_lang['mod.settings.error.newpassnosame'];
            }
        } else {
            $errors[] = $_lang['mod.settings.error.badcurrentpass'];
        }
    }

    // note
    $note = _e(trim(mb_substr(_post('note'), 0, 1024)));

    // language
    if (_language_allowcustom) {
        $language = _post('language');

        if (!Core::$pluginManager->has(PluginManager::LANGUAGE, $language)) {
            $language = '';
        }
    }

    // changeset
    $changeset = array(
        'email' => $email,
        'avatar' => $avatar,
        'web' => $web,
        'skype' => $skype,
        'icq' => $icq,
        'massemail' => $massemail,
        'note' => $note,
        'publicname' => $publicname,
    );

    if (_priv_administration) {
        $changeset['wysiwyg'] = $wysiwyg;
    }
    if (_language_allowcustom) {
        $changeset['language'] = $language;
    }
    if ($usernamechange == true) {
        $changeset['username'] = $username;
    }
    if ($passwordchange == true) {
        $changeset['password'] = $newpassword;
    }

    // extend
    Sunlight\Extend::call('mod.settings.submit', array(
        'changeset' => &$changeset,
        'current' => $userdata,
        'errors' => &$errors,
    ));

    //  ulozeni nebo seznam chyb
    if (count($errors) == 0) {

        // uprava session pri zmene hesla
        if ($passwordchange == true) {
            $_SESSION['user_auth'] = _userAuthHash($newpassword);
        }

        // extend
        Sunlight\Extend::call('mod.settings.save', array(
            'changeset' => &$changeset,
            'current' => $userdata,
        ));

        // update
        DB::update(_users_table, 'id=' . _loginid, $changeset);
        Sunlight\Extend::call('user.edit', array('id' => _loginid, 'username' => $username, 'email' => $email));
        $_index['redirect_to'] = _linkModule('settings', 'saved', false, true);

        return;

    } else {
        $message = _msg(_msg_warn, _msgList($errors, 'errors'));
    }

}

/* ---  modul  --- */

$_index['title'] = $_lang['mod.settings'];

if (isset($_GET['saved'])) {
    $message = _msg(_msg_ok, $_lang['global.saved']);
}

// vyber jazyka
if (_language_allowcustom) {
    $language_select = '
    <tr>
    <th>' . $_lang['global.language'] . '</th>
    <td><select name="language" class="inputsmall"><option value="">' . $_lang['global.default'] . '</option>';
    $language_select .= Core::$pluginManager->select(PluginManager::LANGUAGE, $userdata['language']);
    $language_select .= '</td></tr>';
} else {
    $language_select = "";
}

// wysiwyg
if (_priv_administration) {
    $admin = "

  <tr>
  <th>" . $_lang['mod.settings.wysiwyg'] . "</th>
  <td><label><input type='checkbox' name='wysiwyg' value='1'" . _checkboxActivate($userdata['wysiwyg']) . "> " . $_lang['mod.settings.wysiwyg.label'] . "</label></td>
  </tr>

  ";
} else {
    $admin = "";
}

$output .= "
<p><a href='" . _linkModule('profile', 'id=' . _loginname) . "'>" . $_lang['mod.settings.profilelink'] . " &gt;</a></p>
<p>" . $_lang['mod.settings.p'] . "</p>" . $message . "
<form action='" . _linkModule('settings') . "' method='post' name='setform' enctype='multipart/form-data'>

" . _jsLimitLength(1024, "setform", "note") . "

  <fieldset>
  <legend>" . $_lang['mod.settings.userdata'] . "</legend>
  <table class='profiletable'>

  <tr>
  <th>" . $_lang['login.username'] . " <span class='important'>*</span></th>
  <td><input type='text'" . _restorePostValueAndName('username', _loginname) . " class='inputsmall' maxlength='24'>" . (!_priv_changeusername ? "<span class='hint'>(" . $_lang['mod.settings.namechangenote'] . ")</span>" : '') . "</td>
  </tr>

  <tr>
  <th>" . $_lang['mod.settings.publicname'] . "</th>
  <td><input type='text'" . _restorePostValueAndName('publicname', $userdata['publicname'], true) . " class='inputsmall' maxlength='24'></td>
  </tr>

  <tr class='valign-top'>
  <th>" . $_lang['global.email'] . " <span class='important'>*</span></th>
  <td><input type='email'" . _restorePostValueAndName('email', $userdata['email']) . " class='inputsmall'/> <span class='hint'>(" . $_lang['mod.settings.emailchangenote'] . ")</span></td>
  </tr>

  " . $language_select . "

  <tr>
  <th>" . $_lang['mod.settings.massemail'] . "</th>
  <td><label><input type='checkbox' name='massemail' value='1'" . _checkboxActivate($userdata['massemail']) . "> " . $_lang['mod.settings.massemail.label'] . "</label></td>
  </tr>

  " . $admin . "
  </table>
  </fieldset>

  <fieldset>
  <legend>" . $_lang['mod.settings.password'] . "</legend>
  <p>" . $_lang['mod.settings.password.hint'] . "</p>
  <table class='profiletable'>

  <tr>
  <th>" . $_lang['mod.settings.password.current'] . "</th>
  <td><input type='password' name='currentpassword' class='inputsmall' autocomplete='off'></td>
  </tr>

  <tr>
  <th>" . $_lang['mod.settings.password.new'] . "</th>
  <td><input type='password' name='newpassword' class='inputsmall' autocomplete='off'></td>
  </tr>

  <tr>
  <th>" . $_lang['mod.settings.password.new'] . " (" . $_lang['global.check'] . ")</th>
  <td><input type='password' name='newpassword-confirm' class='inputsmall' autocomplete='off'></td>
  </tr>

  </table>
  </fieldset>

  " . Sunlight\Extend::buffer('mod.settings.form') . "

  <fieldset>
  <legend>" . $_lang['mod.settings.info'] . "</legend>

  <table class='profiletable'>

  <tr>
  <th>" . $_lang['global.icq'] . "</th>
  <td><input type='text'" . _restorePostValueAndName('icq', $userdata['icq'] ? $userdata['icq'] : '') . " class='inputsmall'></td>
  </tr>

  <tr>
  <th>" . $_lang['global.skype'] . "</th>
  <td><input type='text'" . _restorePostValueAndName('skype', $userdata['skype']) . " class='inputsmall'></td>
  </tr>

  <tr>
  <th>" . $_lang['global.web'] . "</th>
  <td><input type='text' name='web' value='" . $userdata['web'] . "' class='inputsmall'></td>
  </tr>

  <tr class='valign-top'>
  <th>" . $_lang['global.note'] . "</th>
  <td><textarea class='areasmall' rows='9' cols='33' name='note'>" . _restorePostValue('note', $userdata['note'], false, false) . "</textarea></td>
  </tr>

  <tr><td></td>
  <td>" . _getPostFormControls('setform', 'note') . "</td>
  </tr>

  </table>

  </fieldset>
";

if (_uploadavatar) {
    $output .= "
  <fieldset>
  <legend>" . $_lang['mod.settings.avatar'] . "</legend>
  " . Sunlight\Extend::buffer('mod.settings.avatar', array('user' => $userdata)) . "
  <p><strong>" . $_lang['mod.settings.avatar.upload'] . ":</strong> <input type='file' name='avatar'></p>
    <table>
    <tr class='valign-top'>
    <td width='106'><img src='" . _e($avatar_path) . "' class='avatar' alt='avatar'></td>
    <td><p>" . $_lang['mod.settings.avatar.hint'] . "</p><p><label><input type='checkbox' name='removeavatar' value='1'> " . $_lang['mod.settings.avatar.remove'] . "</label></p></td>
    </tr>
    </table>
  </fieldset>
";
}

if (_priv_selfremove && _loginid != 0) {
    $output .= "

  <fieldset>
  <legend>" . $_lang['mod.settings.selfremove'] . "</legend>
  <p><label><input type='checkbox' name='selfremove' value='1' onclick='if (this.checked==true) {return Sunlight.confirm();}'> " . $_lang['mod.settings.selfremove.box'] . "</label></p>
  <div><strong>" . $_lang['mod.settings.selfremove.confirm'] . ":</strong> <input type='password' name='selfremove-confirm' class='inputsmall'></div>
  </fieldset>

";
}

$output .= "
<br>
<input type='submit' value='" . $_lang['mod.settings.submit'] . "'>
<input type='reset' value='" . $_lang['global.reset'] . "' onclick='return Sunlight.confirm();'>

" . _xsrfProtect() . "</form>
";
