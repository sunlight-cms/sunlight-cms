<?php

use Sunlight\Backup\Backup;
use Sunlight\Backup\BackupRestorer;
use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Util\Environment;
use Sunlight\Util\Html;
use Sunlight\VersionChecker;
use Sunlight\Xsrf;

defined('_root') or exit;

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

        if (!$restorer->validate($errors)) {
            $output .= Message::error(Message::renderList(Html::escapeArrayItems($errors), _lang('admin.other.patch.incompatible')), true);
            break;
        }

        if (!$patch->getMetaData('is_patch')) {
            $output .= Message::warning(_lang('admin.other.patch.not_a_patch'));
            break;
        }

        $success = $restorer->restore(true, null, null, $errors);

        if ($success) {
            $output .= Message::ok(_lang('admin.other.patch.complete'));

            return;
        } else {
            $output .= Message::error(Message::renderList(Html::escapeArrayItems($errors), 'errors'), true);
        }

    } catch (\Throwable $e) {
        $output .= Message::error(_lang('global.error')) . Core::renderException($e);
    }
} while (false);

$output .= _buffer(function () use ($latest_version) { ?>
    <p><?php echo _lang('admin.other.patch.text', ['%link%' => 'https://sunlight-cms.cz/resource/update?from=' . rawurlencode(Core::VERSION)]) ?></p>

    <form method="post" enctype="multipart/form-data" onsubmit="return Sunlight.confirm()">
        <table class="formtable">
            <tr>
                <th><?php echo _lang('admin.other.patch.current_version') ?></th>
                <td><?php echo _e(Core::VERSION) ?></td>
            </tr>
            <tr>
                <th><?php echo _lang('admin.other.patch.latest_version') ?></th>
                <td><?php echo _e($latest_version) ?></td>
            </tr>
            <tr>
                <th><?php echo _lang('admin.other.patch.file') ?></th>
                <td>
                    <input type="file" name="patch" id="patch-input">
                    <?php echo Environment::renderUploadLimit() ?>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <?php echo Message::warning(_lang('admin.other.patch.note', ['%link%' => 'index.php?p=backup']), true) ?>
                    <input type="submit" class="button big" name="apply_patch" value="<?php echo _lang('admin.other.patch.upload') ?>">

                </td>
            </tr>
        </table>

        <?php echo Xsrf::getInput() ?>
    </form>
<?php });