<?php

use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Util\StringHelper;

$message = '';

// load existing backups
$backup_dir = SL_ROOT . 'system/backup';
$backup_files = [];

foreach (scandir($backup_dir) as $item) {
    if (
        $item !== '.'
        && $item !== '..'
        && preg_match('{\.zip$}Di', $item)
        && is_file($backup_path = $backup_dir . '/' . $item)
    ) {
        $backup_files[$item] = filectime($backup_path);
    }
}


if (isset($_GET['download'])) {
    // download a backup
    $download = Request::get('download');

    if (isset($backup_files[$download])) {
        Response::downloadFile($backup_dir . '/' . $download);
    }
} elseif (isset($_POST['upload'])) {
    // upload a backup
    if (isset($_FILES['backup']) && is_uploaded_file($_FILES['backup']['tmp_name'])) {
        $backup_name = StringHelper::slugify($_FILES['backup']['name'], ['lower' => false]);

        if (preg_match('{\.zip$}Di', $backup_name) && Filesystem::isSafeFile($backup_name)) {
            $backup_path = $backup_dir . '/' . $backup_name;

            if (!file_exists($backup_path)) {
                User::moveUploadedFile($_FILES['backup']['tmp_name'], $backup_path);
                $backup_files[$backup_name] = time();

                $message = Message::ok(_lang('global.done'));
            } else {
                $message = Message::warning(_lang('admin.backup.upload.exists'));
            }
        } else {
            $message = Message::warning(_lang('admin.backup.upload.error'));
        }
    } else {
        $message = Message::warning(_lang('global.noupload'));
    }
} elseif (isset($_POST['delete'])) {
    // delete a backup
    $backup_file = Request::post('backup_file');

    if (isset($backup_files[$backup_file])) {
        unlink($backup_dir . '/' . $backup_file);
        unset($backup_files[$backup_file]);
        $message = Message::ok(_lang('global.done'));
    }
}

// list existing backups
arsort($backup_files, SORT_NUMERIC);

$backup_list = '';

if (!empty($backup_files)) {
    foreach ($backup_files as $backup_file => $backup_ctime) {
        $backup_list .= '<tr>
    <td><label>' . Form::input('radio', 'backup_file', $backup_file, ['required' => true]) . ' ' . _e($backup_file) . '</label></td>
    <td>' . GenericTemplates::renderFileSize(filesize($backup_dir . '/' . $backup_file)) . '</td>
    <td>' . GenericTemplates::renderTime($backup_ctime, 'backup') . '</td>
    <td>'
        . '<a href="' . _e(Router::admin('backup', ['query' => ['download' => $backup_file]])) . '" title="' . _lang('global.download') . '">'
        . '<img src="' . _e(Router::path('admin/public/images/icons/floppy.png')) . '" alt="' . _lang('global.download') . '">'
        . '</a>'
    . '</td>
</tr>
';
    }
} else {
    $backup_list = '<tr><td colspan="4">' . _lang('global.nokit') . "</td></tr>\n";
}

// forms
$output .= $message . '
<fieldset>
    <legend>' . _lang('admin.backup.create.title') . '</legend>
    <p>' . _lang('admin.backup.create.p') . '</p>

    ' . Form::start('backup_create', ['action' => Router::admin('backup-create')]) . '
        <p>
            <label>' . Form::input('radio', 'type', 'partial', ['required' => true]) . ' ' . _lang('admin.backup.create.partial') . '</label> <small>(' . _lang('admin.backup.create.partial.help') . ')</small><br>
            <label>' . Form::input('radio', 'type', 'full', ['required' => true]) . ' ' . _lang('admin.backup.create.full') . '</label> <small>(' . _lang('admin.backup.create.full.help') . ')</small>
        </p>
        
        ' . Form::input('submit', null, _lang('global.continue'), ['class' => 'button']) . '
    ' . Form::end('backup_create') . '
</fieldset>

<fieldset>
    <legend>' . _lang('admin.backup.upload.title') . '</legend>
    <p>' . _lang('admin.backup.upload.p') . '</p>

    ' . Form::start('backup_upload', ['enctype' => 'multipart/form-data']) . '
        <table>
            <tr>
                <th>' . _lang('global.file') . '</th>
                <td>
                    ' . Form::input('file', 'backup') . '
                    <br class="mobile-only">
                    ' . Environment::renderUploadLimit() . '
                </td>
            </tr>
            <tr>
                <td></td>
                <td>' . Form::input('submit', 'upload', _lang('global.upload'), ['class' => 'button']) . '</td>
            </tr>
        </table>
    ' . Form::end('backup_upload') . '
</fieldset>

<fieldset>
    <legend>' . _lang('admin.backup.restore.title') . '</legend>
    <p>' . _lang('admin.backup.restore.p') . '</p>

    ' . Form::start('backup_restore', ['action' => Router::admin('backup-restore')]) . '
        <div class="horizontal-scroller">
            <table class="list list-hover">
                <thead>
                    <tr>
                        <th>' . _lang('global.name') . '</th>
                        <th>' . _lang('global.size') . '</th>
                        <th>' . _lang('global.created_at') . '</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    ' . $backup_list . '
                </tbody>
            </table>
        </div>

        <p>
            ' . Form::input('submit', null, _lang('admin.backup.restore.submit.load'), ['class' => 'button'])
            . ' ' . _lang('global.or') . ' '
            . Form::input('submit', 'delete', _lang('admin.backup.restore.submit.delete'), ['class' => 'button', 'onclick' => 'return Sunlight.confirm()', 'formaction' => Router::admin('backup')]) . '
        </p>
    ' . Form::end('backup_restore') . '
</fieldset>
';
