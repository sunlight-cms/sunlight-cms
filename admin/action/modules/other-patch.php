<?php

use Sunlight\Backup\Backup;
use Sunlight\Backup\BackupRestorer;
use Sunlight\Core;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Environment;
use Sunlight\Util\Form;
use Sunlight\VersionChecker;

defined('SL_ROOT') or exit;

$version_data = VersionChecker::check();

if ($version_data !== null) {
    $latest_version = $version_data['latestVersion'];
} else {
    $latest_version = '---';
}

if (isset($_POST['apply_patch'])) do {
    // check upload
    if (!isset($_FILES['patch']) || !is_string($_FILES['patch']['name']) || $_FILES['patch']['error'] !== UPLOAD_ERR_OK) {
        $output .= Message::warning(_lang('global.noupload'));

        break;
    }

    // load patch
    try {
        $patch = new Backup($_FILES['patch']['tmp_name']);
        $patch->open();

        $restorer = new BackupRestorer($patch);

        if (!$restorer->validate(true, $errors)) {
            $output .= Message::list($errors, ['type' => Message::ERROR, 'text' => _lang('admin.other.patch.errors.validate')]);
            break;
        }

        $success = $restorer->restore(true, null, null, $errors);

        if ($success) {
            $output .= Message::ok(_lang('admin.other.patch.complete', ['%new_version%' => $patch->getMetaData('patch')['new_system_version'] ?? '???']));

            return;
        }

        $output .= Message::list($errors, ['type' => Message::ERROR]);
    } catch (Throwable $e) {
        $output .= Message::error(_lang('global.error')) . GenericTemplates::renderException($e);
    }
} while (false);

$output .= _buffer(function () use ($latest_version) { ?>
    <p><?= _lang('admin.other.patch.text', ['%link%' => 'https://sunlight-cms.cz/resource/8.x/update?from=' . urlencode(Core::VERSION)]) ?></p>

    <?= Form::start('patch-upload', ['enctype' => 'multipart/form-data', 'onsubmit' => 'return Sunlight.confirm()']) ?>
        <table class="formtable">
            <tr>
                <th><?= _lang('admin.other.patch.current_version') ?></th>
                <td><?= _e(Core::VERSION) ?></td>
            </tr>
            <tr>
                <th><?= _lang('admin.other.patch.latest_version') ?></th>
                <td><?= _e($latest_version) ?></td>
            </tr>
            <tr>
                <th><?= _lang('admin.other.patch.file') ?></th>
                <td>
                    <?= Form::input('file', 'patch', null, ['id' => 'patch-input']) ?>
                    <?= Environment::renderUploadLimit() ?>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <?= Message::warning(_lang('admin.other.patch.note', ['%link%' => Router::admin('backup')]), true) ?>
                    <?= Form::input('submit', 'apply_patch', _lang('admin.other.patch.upload'), ['class' => 'button big']) ?>

                </td>
            </tr>
        </table>
    <?= Form::end('patch-upload') ?>
<?php });
