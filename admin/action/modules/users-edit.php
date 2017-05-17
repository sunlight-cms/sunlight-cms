<?php

if (!defined('_root')) {
    exit;
}

/* ---  priprava promennych  --- */

$message = "";
$errno = 0;

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = _get('id');
    $query = DB::query("SELECT u.*,g.level group_level FROM " . _users_table . " u JOIN " . _groups_table . " g ON(u.group_id=g.id) WHERE u.username=" . DB::val($id));
    if (DB::size($query) != 0) {
        $query = DB::row($query);

        // test pristupu
        if ($query['id'] != _loginid) {
            if (_levelCheck($query['id'], $query['group_level'])) {
                if ($query['id'] != 0) {
                    $continue = true;
                } else {
                    $errno = 2;
                }
            }
        } else {
            $admin_redirect_to = _linkModule('settings');

            return;
        }

    } else {
        $errno = 1;
    }
} else {
    $continue = true;
    $id = null;
    $query = array(
        'id' => '-1',
        'group_id' => _defaultgroup,
        'levelshift' => 0,
        'username' => '',
        'publicname' => null,
        'blocked' => 0,
        'email' => '@',
        'avatar' => null,
        'web' => '',
        'skype' => '',
        'icq' => '',
        'note' => '',
        'wysiwyg' => '1',
        'massemail' => '1',
    );
}

if ($continue) {
    
    // vyber skupiny
    $group_select = _adminUserSelect('group_id', (isset($_POST['group_id']) ? (int) _post('group_id') : $query['group_id']), "id!=2 AND level<" . _priv_level, null, null, true);

    /* ---  ulozeni  --- */
    if (isset($_POST['username'])) {

        $errors = array();

        // nacteni a kontrola promennych

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
            if ($username != $query['username']) {
                if (DB::result(DB::query("SELECT COUNT(*) FROM " . _users_table . " WHERE (username=" . DB::val($username) . " OR publicname=" . DB::val($username) . ") AND id!=" . $query['id']), 0) == 0) {
                    $usernamechange = true;
                } else {
                    $errors[] = $_lang['user.msg.userexists'];
                }
            }
        }

        // publicname
        $publicname = _e(_wsTrim(_post('publicname')));
        if (mb_strlen($publicname) > 24) {
            $errors[] = $_lang['user.msg.publicnametoolong'];
        } elseif ($publicname != $query['publicname'] && $publicname != "") {
            if (DB::result(DB::query("SELECT COUNT(*) FROM " . _users_table . " WHERE (publicname=" . DB::val($publicname) . " OR username=" . DB::val($publicname) . ") AND id!=" . $query['id']), 0) != 0) {
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
            if ($email != $query['email']) {
                if (DB::result(DB::query("SELECT COUNT(*) FROM " . _users_table . " WHERE email=" . DB::val($email) . " AND id!=" . $query['id']), 0) != 0) {
                    $errors[] = $_lang['user.msg.emailexists'];
                }
            }
        }

        // wysiwyg
        $wysiwyg = _checkboxLoad('wysiwyg');

        // hromadny email
        $massemail = _checkboxLoad('massemail');

        // icq
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
        if (isset($query['avatar']) && _checkboxLoad("removeavatar")) {
            @unlink(_root . 'images/avatars/' . $query['avatar'] . '.jpg');
            $avatar = null;
        } else {
            $avatar = $query['avatar'];
        }

        // password
        $passwordchange = false;
        $password = _post('password');
        if ($id == null && $password == "") {
            $errors[] = $_lang['admin.users.edit.passwordneeded'];
        }
        if ($password != "") {
            $passwordchange = true;
            $password = Sunlight\Util\Password::create($password)->build();
        }

        // note
        $note = _e(trim(_cutString(_post('note'), 1024)));

        // blocked
        $blocked = _checkboxLoad("blocked");

        // group
        if (isset($_POST['group_id'])) {
            $group = (int) _post('group_id');
            $group_test = DB::query("SELECT level FROM " . _groups_table . " WHERE id=" . $group . " AND id!=2 AND level<" . _priv_level);
            if (DB::size($group_test) != 0) {
                $group_test = DB::row($group_test);
                if ($group_test['level'] > _priv_level) {
                    $errors[] = $_lang['global.badinput'];
                }
            } else {
                $errors[] = $_lang['global.badinput'];
            }
        } else {
            $group = $query['group_id'];
        }

        // levelshift
        if (_loginid == _super_admin_id) {
            $levelshift = _checkboxLoad('levelshift');
        } else {
            $levelshift = $query['levelshift'];
        }

        // ulozeni / vytvoreni anebo seznam chyb
        if (count($errors) == 0) {

            // changeset
            $changeset = array(
                'email' => $email,
                'avatar' => $avatar,
                'web' => $web,
                'skype' => $skype,
                'icq' => $icq,
                'note' => $note,
                'publicname' => $publicname,
                'group_id' => $group,
                'blocked' => $blocked,
                'levelshift' => $levelshift,
                'massemail' => $massemail,
                'wysiwyg' => $wysiwyg,
            );
            if ($id === null || $passwordchange) {
                $changeset['password'] = $password;
            }
            if ($id === null || $usernamechange) {
                $changeset['username'] = $username;
            }

            $action = (null === $id ? 'new' : 'edit');
            Sunlight\Extend::call('admin.user.' . $action . '.pre', array(
                'id' => $id,
                'user' => null === $id ? null : $query,
                'changeset' => &$changeset,
            ));

            if (null !== $id) {
                // uprava
                DB::update(_users_table, 'id=' . DB::val($query['id']), $changeset);
                Sunlight\Extend::call('user.edit', array('id' => $query['id'], 'username' => $username, 'email' => $email));
                $admin_redirect_to = 'index.php?p=users-edit&r=1&id=' . $username;

                return;
            } else {
                // vytvoreni
                $changeset += array(
                    'registertime' => time(),
                    'activitytime' => time(),
                );
                $id = DB::insert(_users_table, $changeset, true);
                Sunlight\Extend::call('user.new', array('id' => $id, 'username' => $username, 'email' => $email));
                $admin_redirect_to = 'index.php?p=users-edit&r=2&id=' . $username;

                return;
            }

        } else {
            $message = _msgList($errors, 'errors');
        }

    }

    /* ---  vystup  --- */

    // zpravy
    $messages_code = "";

    if (isset($_GET['r'])) {
        switch (_get('r')) {
            case 1:
                $messages_code .= _msg(_msg_ok, $_lang['global.saved']);
                break;
            case 2:
                $messages_code .= _msg(_msg_ok, $_lang['global.created']);
                break;
        }
    }

    if ($message != "") {
        $messages_code .= _msg(_msg_warn, $message);
    }

    $output .= "
<p class='bborder'>" . $_lang['admin.users.edit.p'] . "</p>
" . $messages_code . "
<form autocomplete='off' action='index.php?p=users-edit" . (($id != null) ? "&amp;id=" . $id : '') . "' method='post' name='userform'>
<table class='formtable'>

<tr>
<th>" . $_lang['login.username'] . "</th>
<td><input type='text' class='inputsmall'" . _restorePostValueAndName('username', $query['username']) . " maxlength='24'></td>
</tr>

<tr>
<th>" . $_lang['mod.settings.publicname'] . "</th>
<td><input type='text' class='inputsmall'" . _restorePostValueAndName('publicname', $query['publicname'], true) . " maxlength='24'></td>
</tr>

<tr>
<th>" . $_lang[(($id == null) ? 'login.password' : 'mod.settings.password.new')] . "</th>
<td><input type='password' name='password' class='inputsmall'></td>
</tr>

<tr>
<th>" . $_lang['global.group'] . "</th>
<td>" . $group_select . "</td>
</tr>

<tr>
<th>" . $_lang['login.blocked'] . "</th>
<td><input type='checkbox' name='blocked' value='1'" . _checkboxActivate($query['blocked'] || isset($_POST['blocked'])) . "></td>
</tr>

<tr>
<th>" . $_lang['global.levelshift'] . "</th>
<td><input type='checkbox' name='levelshift' value='1'" . _checkboxActivate($query['levelshift'] || isset($_POST['levelshift'])) . _inputDisableUnless(_loginid == _super_admin_id) . "></td>
</tr>

<tr>
<th>" . $_lang['mod.settings.wysiwyg'] . "</th>
<td><input type='checkbox' name='wysiwyg' value='1'" . _checkboxActivate($query['wysiwyg'] || isset($_POST['wysiwyg'])) . "></td>
</tr>

<tr>
<th>" . $_lang['mod.settings.massemail'] . "</th>
<td><input type='checkbox' name='massemail' value='1'" . _checkboxActivate($query['massemail'] || isset($_POST['massemail'])) . "></td>
</tr>

<tr>
<th>" . $_lang['global.email'] . "</th>
<td><input type='email' class='inputsmall'" . _restorePostValueAndName('email', $query['email']) . "></td>
</tr>

<tr>
<th>" . $_lang['global.icq'] . "</th>
<td><input type='text' class='inputsmall'" . _restorePostValueAndName('icq', $query['icq'], true) . "></td>
</tr>

<tr>
<th>" . $_lang['global.skype'] . "</th>
<td><input type='text' class='inputsmall'" . _restorePostValueAndName('skype', $query['skype'], true) . "></td>
</tr>

<tr>
<th>" . $_lang['global.web'] . "</th>
<td><input type='text' class='inputsmall'" . _restorePostValueAndName('web', $query['web'], true) . "></td>
</tr>

<tr>
<th>" . $_lang['global.avatar'] . "</th>
<td><label><input type='checkbox' name='removeavatar' value='1'> " . $_lang['mod.settings.avatar.remove'] . "</label></td>
</tr>

<tr class='valign-top'>
<th>" . $_lang['global.note'] . "</th>
<td><textarea class='areasmall' rows='9' cols='33' name='note'>" . _restorePostValue('note', $query['note'], false, false) . "</textarea></td>
</tr>

" . Sunlight\Extend::buffer('admin.user.form', array('user' => $query)) . "

<tr><td></td>
<td><input type='submit' value='" . $_lang[(isset($_GET['id']) ? 'global.save' : 'global.create')] . "'>" . (($id != null) ? " <small>" . $_lang['admin.content.form.thisid'] . " " . $query['id'] . "</small>" : '') . "</td>
</tr>

</table>
" . _xsrfProtect() . "</form>
";

    // odkaz na profil a zjisteni ip
    if ($id != null) {
        $output .= "
  <p>
    <a href='" . _linkModule('profile', 'id=' . $query['username']) . "' target='_blank'>" . $_lang['mod.settings.profilelink'] . " &gt;</a>
  </p>
  ";
    }

} else {
    switch ($errno) {
        case 1:
            $output .= _msg(_msg_warn, $_lang['global.baduser']);
            break;
        case 2:
            $output .= _msg(_msg_warn, $_lang['global.rootnote']);
            break;
        default:
            $output .= _msg(_msg_err, $_lang['global.disallowed']);
            break;
    }
}
