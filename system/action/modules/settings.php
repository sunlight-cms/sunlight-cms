<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Picture;
use Sunlight\Plugin\PluginManager;
use Sunlight\PostForm;
use Sunlight\Util\Response;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Util\Url;
use Sunlight\Xsrf;

defined('_root') or exit;

if (!_logged_in) {
    $_index['is_accessible'] = false;
    return;
}

/* ---  priprava promennych  --- */

$message = "";
$userdata = Core::$userData;

// cesta k avataru
$avatar_path = User::renderAvatar($userdata, array('get_url' => true, 'extend' => false));

/* ---  ulozeni  --- */

if (isset($_POST['save'])) {

    $errors = array();

    // smazani vlastniho uctu
    if (_priv_selfremove && Form::loadCheckbox('selfremove')) {
        if (Password::load($userdata['password'])->match(Request::post('selfremove-confirm'))) {
            if (_user_id != 0) {
                User::delete(_user_id);
                $_SESSION = array();
                session_destroy();
                $_index['redirect_to'] = Router::module('login', 'login_form_result=4', false, true);

                return;
            } else {
                $errors[] = _lang('mod.settings.selfremove.denied');
            }
        } else {
            $errors[] = _lang('mod.settings.selfremove.failed');
        }
    }

    // username
    $username = Request::post('username');
    if (mb_strlen($username) > 24) {
        $username = mb_substr($username, 0, 24);
    }
    $username = StringManipulator::slugify($username, false);
    if ($username == "") {
        $errors[] = _lang('user.msg.badusername');
    } else {
        $usernamechange = false;
        if ($username != _user_name) {
            if (_priv_changeusername || mb_strtolower($username) == mb_strtolower(_user_name)) {
                if (DB::count(_user_table, '(username=' . DB::val($username) . ' OR publicname=' . DB::val($username) . ') AND id!=' . _user_id) === 0) {
                    $usernamechange = true;
                } else {
                    $errors[] = _lang('user.msg.userexists');
                }
            } else {
                $errors[] = _lang('mod.settings.error.usernamechangedenied');
            }
        }
    }

    // publicname
    $publicname = _e(StringManipulator::trimExtraWhitespace(Request::post('publicname')));
    if (mb_strlen($publicname) > 24) {
        $errors[] = _lang('user.msg.publicnametoolong');
    } elseif ($publicname != $userdata['publicname'] && $publicname != "") {
        if (DB::count(_user_table, '(publicname=' . DB::val($publicname) . ' OR username=' . DB::val($publicname) . ') AND id!=' . _user_id) !== 0) {
            $errors[] = _lang('user.msg.publicnameexists');
        }
    }
    if ($publicname === '') {
        $publicname = null;
    }

    // email
    $email = trim(Request::post('email'));
    if (!Email::validate($email)) {
        $errors[] = _lang('user.msg.bademail');
    } else {
        if ($email != _user_email) {
            if (Request::post('currentpassword') === '') {
                $errors[] = _lang('mod.settings.error.emailchangenopass');
            } elseif (!Password::load($userdata['password'])->match(Request::post('currentpassword'))) {
                $errors[] = _lang('mod.settings.error.badcurrentpass');
            }
            if (DB::count(_user_table, 'email=' . DB::val($email) . ' AND id!=' . _user_id) !== 0) {
                $errors[] = _lang('user.msg.emailexists');
            }
        }
    }

    // massemail, wysiwyg, public
    $massemail = Form::loadCheckbox('massemail');
    if (_priv_administration) {
        $wysiwyg = Form::loadCheckbox('wysiwyg');
    }
    $public = Form::loadCheckbox('public');

    // avatar
    $avatar = $userdata['avatar'];
    if (_uploadavatar) {

        // smazani avataru
        if (Form::loadCheckbox("removeavatar") && isset($avatar)) {
            @unlink(_root . 'images/avatars/' . $avatar . '.jpg');
            $avatar = null;
        }

        // upload avataru
        if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {

            // zpracovani
            $avatarUid = Picture::process(array(
                'file_path' => $_FILES['avatar']['tmp_name'],
                'file_name' => $_FILES['avatar']['name'],
                'limit' => array('filesize' => 1048576, 'dimensions' => array('x' => 1400, 'y' => 1400)),
                'resize' => array('mode' => 'zoom', 'x' => 96, 'y' => 128),
                'target_path' => _root . 'images/avatars/',
                'target_format' => 'jpg',
                'jpg_quality' => 95,
            ), $avatarError);

            if ($avatarUid !== false) {

                // smazani stareho avataru
                if ($avatar !== null) {
                    @unlink(_root . 'images/avatars/' . $avatar . '.jpg');
                }

                // ok
                $avatar = $avatarUid;

            } else {
                $errors[] = _lang('global.avatar') . ' - ' . $avatarError;
            }

        }

    }

    // password
    $passwordchange = false;
    if (Request::post('newpassword') != "" || Request::post('newpassword-confirm') != "") {
        $newpassword = Request::post('newpassword');
        $newpassword_confirm = Request::post('newpassword-confirm');
        if (Password::load($userdata['password'])->match(Request::post('currentpassword'))) {
            if ($newpassword == $newpassword_confirm) {
                if ($newpassword != "") {
                    $passwordchange = true;
                    $newpassword = Password::create($newpassword)->build();
                } else {
                    $errors[] = _lang('mod.settings.error.badnewpass');
                }
            } else {
                $errors[] = _lang('mod.settings.error.newpassnosame');
            }
        } else {
            $errors[] = _lang('mod.settings.error.badcurrentpass');
        }
    }

    // note
    $note = _e(trim(mb_substr(Request::post('note'), 0, 1024)));

    // language
    if (_language_allowcustom) {
        $language = Request::post('language');

        if (!Core::$pluginManager->has(PluginManager::LANGUAGE, $language)) {
            $language = '';
        }
    }

    // changeset
    $changeset = array(
        'email' => $email,
        'avatar' => $avatar,
        'massemail' => $massemail,
        'public' => $public,
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
    Extend::call('mod.settings.submit', array(
        'changeset' => &$changeset,
        'current' => $userdata,
        'errors' => &$errors,
    ));

    //  ulozeni nebo seznam chyb
    if (count($errors) == 0) {

        // uprava session pri zmene hesla
        if ($passwordchange == true) {
            $_SESSION['user_auth'] = User::getAuthHash($newpassword);
        }

        // extend
        Extend::call('mod.settings.save', array(
            'changeset' => &$changeset,
            'current' => $userdata,
        ));

        // update
        DB::update(_user_table, 'id=' . _user_id, $changeset);
        Extend::call('user.edit', array('id' => _user_id, 'username' => $username, 'email' => $email));
        $_index['redirect_to'] = Router::module('settings', 'saved', false, true);

        return;

    } else {
        $message .= Message::warning(Message::renderList($errors, 'errors'), true);
    }

} elseif (isset($_POST['download_personal_data'])) {
    if (Password::load($userdata['password'])->match(Request::post('currentpassword'))) {
        $ips = DB::queryRows('SELECT DISTINCT ip FROM ' . _comment_table . ' WHERE author = ' . $userdata['id'], null, 'ip');
        $ips[] = $userdata['ip'];

        $personal_data = array(
            _lang('login.username') => $userdata['username'],
            _lang('mod.settings.publicname') => (string) $userdata['publicname'],
            _lang('global.email') => $userdata['email'],
            _lang('mod.profile.regtime') => date(DATE_ISO8601, $userdata['registertime']),
            _lang('mod.profile.logincounter') => $userdata['logincounter'],
            _lang('global.ip') => $ips,
        );

        Extend::call('mod.settings.download_personal_data', array('data' => &$personal_data));

        Response::download(sprintf('%s_%s.csv', Url::current()->host, $userdata['username']));

        $outputHandle = fopen('php://output', 'a');

        foreach ($personal_data as $label => $values) {
            $first = true;

            foreach ((array) $values as $value) {
                if ($first) {
                    $fields = array($label, (string) $value);
                    $first = false;
                } else {
                    $fields = array('', (string) $value);
                }

                fputcsv($outputHandle, $fields);
            }
        }

        Extend::call('mod.settings.download_personal_data.output');

        exit;
    } else {
        $message .= Message::warning(_lang('mod.settings.download_personal_data') . ' - ' . _lang('mod.settings.error.badcurrentpass'));
    }
}

/* ---  modul  --- */

$_index['title'] = _lang('mod.settings');

if (isset($_GET['saved'])) {
    $message .= Message::ok(_lang('global.saved'));
}

// vyber jazyka
if (_language_allowcustom) {
    $language_select = '
    <tr>
    <th>' . _lang('global.language') . '</th>
    <td><select name="language" class="inputsmall"><option value="">' . _lang('global.default') . '</option>';
    $language_select .= Core::$pluginManager->select(PluginManager::LANGUAGE, $userdata['language']);
    $language_select .= '</td></tr>';
} else {
    $language_select = "";
}

// wysiwyg
if (_priv_administration) {
    $admin = "

  <tr>
  <th>" . _lang('mod.settings.wysiwyg') . "</th>
  <td><label><input type='checkbox' name='wysiwyg' value='1'" . Form::activateCheckbox($userdata['wysiwyg']) . "> " . _lang('mod.settings.wysiwyg.label') . "</label></td>
  </tr>

  ";
} else {
    $admin = "";
}

$output .= "
<p><a href='" . Router::module('profile', 'id=' . _user_name) . "'>" . _lang('mod.settings.profilelink') . " &gt;</a></p>
<p>" . _lang('mod.settings.p') . "</p>" . $message . "
<form action='" . Router::module('settings') . "' method='post' name='setform' enctype='multipart/form-data'>

" . GenericTemplates::jsLimitLength(1024, "setform", "note") . "

  <fieldset>
  <legend>" . _lang('mod.settings.userdata') . "</legend>
  <table class='profiletable'>

  <tr>
  <th>" . _lang('login.username') . " <span class='important'>*</span></th>
  <td><input type='text'" . Form::restorePostValueAndName('username', _user_name) . " class='inputsmall' maxlength='24'>" . (!_priv_changeusername ? "<span class='hint'>(" . _lang('mod.settings.namechangenote') . ")</span>" : '') . "</td>
  </tr>

  <tr>
  <th>" . _lang('mod.settings.publicname') . "</th>
  <td><input type='text'" . Form::restorePostValueAndName('publicname', $userdata['publicname'], true) . " class='inputsmall' maxlength='24'></td>
  </tr>

  <tr class='valign-top'>
  <th>" . _lang('global.email') . " <span class='important'>*</span></th>
  <td><input type='email'" . Form::restorePostValueAndName('email', $userdata['email']) . " class='inputsmall'/> <span class='hint'>(" . _lang('mod.settings.emailchangenote') . ")</span></td>
  </tr>

  " . $language_select . "

  <tr>
  <th>" . _lang('mod.settings.massemail') . "</th>
  <td><label><input type='checkbox' name='massemail' value='1'" . Form::activateCheckbox($userdata['massemail']) . "> " . _lang('mod.settings.massemail.label') . "</label></td>
  </tr>
  
  <tr>
  <th>" . _lang('mod.settings.public') . "</th>
  <td><label><input type='checkbox' name='public' value='1'" . Form::activateCheckbox($userdata['public']) . "> " . _lang('mod.settings.public.label') . "</label></td>
  </tr>

  " . $admin . "
  </table>
  </fieldset>

  <fieldset>
  <legend>" . _lang('mod.settings.password') . "</legend>
  <p>" . _lang('mod.settings.password.hint') . "</p>
  <table class='profiletable'>

  <tr>
  <th>" . _lang('mod.settings.password.current') . "</th>
  <td><input type='password' name='currentpassword' class='inputsmall' autocomplete='off'></td>
  </tr>

  <tr>
  <th>" . _lang('mod.settings.password.new') . "</th>
  <td><input type='password' name='newpassword' class='inputsmall' autocomplete='off'></td>
  </tr>

  <tr>
  <th>" . _lang('mod.settings.password.new') . " (" . _lang('global.check') . ")</th>
  <td><input type='password' name='newpassword-confirm' class='inputsmall' autocomplete='off'></td>
  </tr>

  </table>
  </fieldset>
  
  <fieldset>
  <legend>" . _lang('mod.settings.download_personal_data') . "</legend>
  <p>" . _lang('mod.settings.download_personal_data.hint') . "</p>
  <input type='submit' name='download_personal_data' value='" . _lang('mod.settings.download_personal_data.action') . "'>
  </fieldset>

  " . Extend::buffer('mod.settings.form') . "

  <fieldset>
  <legend>" . _lang('mod.settings.info') . "</legend>

  <table class='profiletable'>
  
   " . Extend::buffer('mod.settings.form.info') . "

  <tr class='valign-top'>
  <th>" . _lang('global.note') . "</th>
  <td><textarea class='areasmall' rows='9' cols='33' name='note'>" . Form::restorePostValue('note', $userdata['note'], false, false) . "</textarea></td>
  </tr>

  <tr><td></td>
  <td>" . PostForm::renderControls('setform', 'note') . "</td>
  </tr>

  </table>

  </fieldset>
";

if (_uploadavatar) {
    $output .= "
  <fieldset>
  <legend>" . _lang('mod.settings.avatar') . "</legend>
  " . Extend::buffer('mod.settings.avatar', array('user' => $userdata)) . "
  <p><strong>" . _lang('mod.settings.avatar.upload') . ":</strong> <input type='file' name='avatar'></p>
    <table>
    <tr class='valign-top'>
    <td width='106'><img src='" . _e($avatar_path) . "' class='avatar' alt='avatar'></td>
    <td><p>" . _lang('mod.settings.avatar.hint') . "</p><p><label><input type='checkbox' name='removeavatar' value='1'> " . _lang('mod.settings.avatar.remove') . "</label></p></td>
    </tr>
    </table>
  </fieldset>
";
}

if (_priv_selfremove && _user_id != 0) {
    $output .= "

  <fieldset>
  <legend>" . _lang('mod.settings.selfremove') . "</legend>
  <p><label><input type='checkbox' name='selfremove' value='1' onclick='if (this.checked==true) {return Sunlight.confirm();}'> " . _lang('mod.settings.selfremove.box') . "</label></p>
  <div><strong>" . _lang('mod.settings.selfremove.confirm') . ":</strong> <input type='password' name='selfremove-confirm' class='inputsmall'></div>
  </fieldset>

";
}

$output .= "
<br>
<input type='submit' name='save' value='" . _lang('mod.settings.submit') . "'>
<input type='reset' value='" . _lang('global.reset') . "' onclick='return Sunlight.confirm();'>

" . Xsrf::getInput() . "</form>
";
