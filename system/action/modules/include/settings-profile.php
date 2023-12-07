<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\PostForm;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;

defined('SL_ROOT') or exit;

$avatarLimits = [
    'filesize' => 3000000,
    'dimensions' => ['w' => 1000, 'h' => 1000],
];

if (isset($_POST['save'])) {
    $errors = [];
    $changeset = [];

    // avatar
    if (Settings::get('uploadavatar')) {
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
    $note = _e(StringHelper::cut(trim(Request::post('note', '')), 1024));

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

        DB::update('user', 'id=' . User::getId(), $changeset);

        $_index->redirect(Router::module('settings', ['query' => ['action' => 'profile', 'saved' => 1], 'absolute' => true]));

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
        'form_prepend' => '<fieldset><legend>' . _lang('mod.settings.profile') . '</legend>',
        'form_append' => '</fieldset>'
            . Form::input('submit', 'save', _lang('global.savechanges')) . "\n"
            . Form::input('reset', null, _lang('global.reset'), ['onclick' => 'return Sunlight.confirm();']),
        'multipart' => true,
    ],
    [
        Settings::get('uploadavatar')
            ? [
                'label' => _lang('mod.settings.profile.avatar'),
                'top' => true,
                'content' => _buffer(function () use ($avatarLimits) { ?>
                    <table>
                        <tr>
                            <td>
                                <?= Form::input('file', 'avatar') ?>

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
                                        <label><?= Form::input('checkbox', 'remove_avatar', '1') ?> <?= _lang('global.delete') ?></label>
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
            'content' => Form::textarea('note', Request::post('note', User::$data['note']), ['class' => 'areasmall', 'rows' => 9, 'cols' => 33], false)
                . GenericTemplates::jsLimitLength(1024, 'user_settings_profile', 'note'),
        ],
        [
            'label' => '',
            'content' => PostForm::renderControls('user_settings_profile', 'note'),
        ],
    ]
);
