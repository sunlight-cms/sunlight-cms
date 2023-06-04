<?php

use Sunlight\Admin\Admin;
use Sunlight\Backup\Backup;
use Sunlight\Backup\BackupRestorer;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Util\Arr;
use Sunlight\Util\Environment;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

// locate backup
$backup_file = Request::post('backup_file', '');
$backup_path = SL_ROOT . 'system/backup/' . $backup_file;

if (!is_file($backup_path)) {
    $output .= Message::error(_lang('global.badinput'));
    return;
}

// open backup
$backup = new Backup($backup_path);
$backup->open();

// validate backup
$validation_errors = [];

$backup_restorer = new BackupRestorer($backup);
$backup_restorer->validate($validation_errors);

if (!empty($validation_errors)) {
    $output .= Message::list($validation_errors, ['text' => _lang('admin.backup.restore.errors.validate')]);
    return;
}

// modify max_execution_time if possible
$max_exec_time = ini_get('max_execution_time');
$estimated_time = $backup_restorer->estimateFullRestorationTime();

if ($max_exec_time && $max_exec_time < $estimated_time && !@set_time_limit($estimated_time)) {
    $modified_time_limit = true;
} else {
    $modified_time_limit = false;
}

// restore
if (isset($_POST['restore'])) {
    $directories = Arr::filterKeys($_POST, 'directory_');
    $files = Arr::filterKeys($_POST, 'file_');
    $database = isset($_POST['database']);
    $success = $backup_restorer->restore($database, $directories, $files, $errors);

    if ($success) {
        $output .= Message::ok(_lang('admin.backup.restore.complete'));
        return;
    } else {
        $output .= Message::list($errors, ['type' => Message::ERROR]);
    }
}

// fetch info
$backup_size = filesize($backup_path);
$backup_metadata = $backup->getMetaData();
$avail_mem = Environment::getAvailableMemory();
$backup_size_warning = '';

if (
    $avail_mem !== null && $avail_mem < 5000000 // 5MB
    || $max_exec_time && $max_exec_time < $estimated_time && !$modified_time_limit
) {
    $config_info = [];

    if ($memory_limit = Environment::phpIniLimit('memory_limit')) {
        $config_info['memory_limit'] = GenericTemplates::renderFilesize($memory_limit);
        $config_info['avail_mem'] = GenericTemplates::renderFilesize($avail_mem);
    }

    if ($max_exec_time) {
        $config_info['max_execution_time'] = $max_exec_time . 's';
    }

    $config_info['estimated_time'] = $estimated_time . 's';

    $backup_size_warning = Message::warning(
        _lang('admin.backup.restore.size_warning')
        . GenericTemplates::renderMessageList($config_info, ['show_keys' => true]),
        true
    );
}

// output
$output .= '
<form method="post">
    <input type="hidden" name="backup_file" value="' . _e($backup_file) . '">
    <table class="list">
        <tr>
            <th>' . _lang('global.name') . '</th>
            <td>' . _e($backup_file) . '</td>
        </tr>
        <tr>
            <th>' . _lang('global.size') . '</th>
            <td>' . GenericTemplates::renderFileSize($backup_size) . '</td>
        </tr>
        <tr>
            <th>' . _lang('global.created_at') . '</th>
            <td>' . GenericTemplates::renderTime($backup_metadata['created_at']) . '</td>
        </tr>
        <tr class="valign-top">
            <th>' . _lang('admin.backup.restore.contents') . '</th>
            <td>
                <ul id="backup-contents" class="no-bullets">
                    <li><label><input type="checkbox"' . Form::restoreCheckedAndName('restore', 'database') . Form::disableInputUnless($backup->hasDatabaseDump()) . '> ' . _lang('admin.backup.contents.db') . '</label></li>
                    ' . _buffer(function () use ($backup_metadata) {
                        foreach ($backup_metadata['directory_list'] as $index => $directory) {
                            echo '<li><label>'
                                . '<input type="checkbox"' . Form::restoreCheckedAndName('restore', 'directory_' . $index) . ' value="' . _e($directory) . '"> '
                                . _lang('admin.backup.restore.contents.dir')
                                . ' <code>' . _e($directory) . '</code>'
                                . "</label></li>\n";
                        }
                    }) . '
                    ' . _buffer(function () use ($backup_metadata) {
                        foreach ($backup_metadata['file_list'] as $index => $file) {
                            echo '<li><label>'
                                . '<input type="checkbox"' . Form::restoreCheckedAndName('restore', 'file_' . $index) . ' value="' . _e($file) . '"> '
                                . _lang('admin.backup.restore.contents.file')
                                . ' <code>' . _e($file) . '</code>'
                                . "</label></li>\n";
                        }
                    }) . '
                </ul>

                <label class="right"><input type="checkbox" onchange="Sunlight.admin.toggleCheckboxes(document.querySelectorAll(\'#backup-contents input[type=checkbox]\'), this.checked)"> <em>' . _lang('global.all') . '</em></label>
            </td>
        </tr>
    </table>

    ' . Admin::note(_lang('admin.backup.restore.notice')) . '
    ' . $backup_size_warning . '

    <p>
        <input class="button" type="submit" name="restore" onclick="return Sunlight.confirm()" value="' . _lang('admin.backup.restore.title') . '">
    </p>
' . Xsrf::getInput() . '
</form>
';
