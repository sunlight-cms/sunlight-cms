<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\PostForm;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;

defined('_root') or exit;

$avatarLimits = [
    'filesize' => 3000000,
    'dimensions' => ['w' => 1000, 'h' => 1000],
];

if (isset($_POST['save'])) {
    // avatar
    if (_uploadavatar) {
        if (isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $avatar = User::uploadAvatar($_FILES['avatar']['tmp_name'], $_FILES['avatar']['name'], $avatarLimits, $avatarError);

            if ($avatar !== null) {
                $changeset['avatar'] = $avatar;
            } else {
                $errors[] = Message::prefix(_lang('global.avatar'), $avatarError->getUserFriendlyMessage());
            }
        } elseif (isset($_POST['remove_avatar'])) {
            $changeset['avatar'] = null;
        }
    }

    // note
    $note = _e(StringManipulator::cut(trim(Request::post('note', '')), 1024));

    if ($note !== User::$data['note']) {
        $changeset['note'] = $note;
    }

    // process
    Extend::call('mod.settings.profile.submit', [
        'changeset' => &$changeset,
        'errors' => &$errors,
    ]);

    if (empty($errors)) {
        Extend::call('mod.settings.profile.save', ['changeset' => &$changeset]);

        if (isset($changeset['avatar'], User::$data['avatar'])) {
            User::removeAvatar(User::$data['avatar']);
        }

        DB::update(_user_table, 'id=' . _user_id, $changeset);
        Extend::call('user.edit', ['id' => _user_id]);

        $_index['type'] = _index_redir;
        $_index['redirect_to'] = Router::module('settings', 'action=profile&saved', true);

        return;
    } else {
        $output .= Message::list($errors);
    }
} elseif (isset($_GET['saved'])) {
    $output .= Message::ok(_lang('global.saved'));
}

$output .= Form::render(
    [
        'name' => 'user_settings_profile',
        'table_attrs' => ' class="profiletable"',
        'submit_row' => [],
        'form_prepend' => '<fieldset><legend>' . _lang('mod.settings.profile') . '</legend>',
        'form_append' => '</fieldset>'
            . '<input type="submit" name="save" value="' . _lang('global.savechanges') . '">' . "\n"
            . '<input type="reset" value="' . _lang('global.reset') . '" onclick="return Sunlight.confirm();">',
        'multipart' => true,
    ],
    [
        _uploadavatar
            ? [
                'label' => _lang('mod.settings.profile.avatar'),
                'top' => true,
                'content' => _buffer(function () use ($avatarLimits) { ?>
                    <table>
                        <tr>
                            <td>
                                <input type="file" name="avatar">

                                <p>
                                    <?= _lang('mod.settings.profile.avatar.hint', [
                                        '%maxsize%' => GenericTemplates::renderFilesize($avatarLimits['filesize']),
                                        '%maxw%' => $avatarLimits['dimensions']['w'],
                                        '%maxh%' => $avatarLimits['dimensions']['h'],
                                    ]) ?>
                                </p>
                            </td>
                            <td class="center">
                                <?= User::renderAvatar(User::$data, ['link' => false]) ?>
                                <?php if (User::$data['avatar'] !== null): ?>
                                    <p>
                                        <label><input type="checkbox" name="remove_avatar" value="1"> <?= _lang('global.delete') ?></label>
                                    </p>
                                <?php endif ?>
                            </td>
                        </tr>
                    </table>
                <?php }),
            ]
            : [],
        [
            'label' => _lang('global.note'),
            'top' => true,
            'content' => _buffer(function () { ?>
                <textarea class="areasmall" rows="9" cols="33" name="note"><?= Form::restorePostValue('note', User::$data['note'], false, false) ?></textarea>
                <?= GenericTemplates::jsLimitLength(1024, 'user_settings_profile', 'note') ?>
            <?php }),
        ],
        [
            'label' => '',
            'content' => PostForm::renderControls('user_settings_profile', 'note'),
        ],
    ]
);
