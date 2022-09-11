<?php

use Sunlight\Admin\Admin;
use Sunlight\Backup\Backup;
use Sunlight\Backup\BackupBuilder;
use Sunlight\Backup\BackupRestorer;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\StringManipulator;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

$remove_random_suffix = function ($filename) {
    return preg_replace('{(.+)__[\w\-]{64}(\.zip)$}Di', '$1$2', $filename);
};

$add_random_suffix = function ($filename) use ($remove_random_suffix) {
    $filename = $remove_random_suffix($filename);
    $suffix = StringGenerator::generateString(64);

    return preg_replace('{(.+)(\.zip)$}Di', '${1}__' . $suffix . '${2}', $filename);
};

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

// download backup
if (isset($_GET['download'])) {
    $download = Request::get('download');

    if (isset($backup_files[$download])) {
        Response::downloadFile($backup_dir . '/' . $download, $remove_random_suffix($download));
    }
}

// prepare backup builder
$backup_builder = new BackupBuilder();

$backup_dynpath_choices = [];
foreach ($backup_builder->getDynamicPathNames() as $name) {
    $backup_dynpath_choices[$name] = [
        'label' => _lang('admin.backup.dynpath.' . $name),
    ];
}

Extend::call('admin.backup.builder', [
    'builder' => $backup_builder,
    'dynpath_choices' => &$backup_dynpath_choices,
]);

// path size computation function
$computePathSize = function ($path) {
    $size = 0;

    if (file_exists($path)) {
        if (is_dir($path)) {
            $size = Filesystem::getDirectorySize($path);
        } else {
            $size = filesize($path);
        }
    }

    return $size;
};

$static_size = 0;
foreach ($backup_builder->getStaticPaths() as $path) {
    $static_size += $computePathSize(SL_ROOT . $path);
}

foreach ($backup_dynpath_choices as $name => &$options) {
    $size = 0;
    foreach ($backup_builder->getDynamicPath($name) as $path) {
        $size += $computePathSize(SL_ROOT . $path);
    }

    $options['size'] = $size;
}
unset($options);

// process
if (!empty($_POST)) {
    try {
        if (isset($_POST['partial_backup']) || isset($_POST['full_backup'])) {
            $backup_builder->setFullBackup(isset($_POST['full_backup']));

            if ($backup_builder->isFullBackup()) {
                $store = isset($_POST['full_backup']['store']);
            } else {
                $store = isset($_POST['partial_backup']['store']);
                $backup_builder->setDatabaseDumpEnabled(isset($_POST['opt_db']));
            }

            $enabled_dynpaths = Arr::filterKeys($_POST, 'dynpath_');
            foreach ($backup_builder->getDynamicPathNames() as $name) {
                if (
                    isset($backup_dynpath_choices[$name])
                    && !in_array($name, $enabled_dynpaths, true)
                ) {
                    $backup_builder->disableDynamicPath($name);
                }
            }

            // build the backup
            $backup = $backup_builder->build();

            $backup_name = sprintf(
                '%s_%s_%s.zip',
                $backup_builder->isFullBackup() ? 'full_backup' : 'backup',
                Core::getBaseUrl()->getHost(),
                date('Y_m_d')
            );

            if ($store) {
                // save on server
                $stored_backup_name = $add_random_suffix($backup_name);
                $backup->move($backup_dir . '/' . $stored_backup_name);
                $backup_files[$stored_backup_name] = time();

                $message = Message::ok(_lang('admin.backup.store.success'));
            } else {
                // download
                Response::downloadFile($backup, $backup_name);
            }
        } elseif (isset($_POST['do_restore'])) {
            $backup_file = Request::post('backup_file');

            if (isset($backup_files[$backup_file])) {
                if (($restoring = isset($_POST['do_restore']['restore'])) || isset($_POST['do_restore']['load'])) {
                    $backup = new Backup($backup_dir . '/' . $backup_file);
                    $backup->open();
                    $backup_restorer = new BackupRestorer($backup);

                    $errors = [];
                    $backup_restorer->validate($errors);

                    if (empty($errors)) {
                        $success = false;

                        $avail_mem = Environment::getAvailableMemory();
                        $max_exec_time = ini_get('max_execution_time');
                        $estimated_time = $backup_restorer->estimateFullRestorationTime();

                        if ($max_exec_time && $max_exec_time < $estimated_time && !@set_time_limit($estimated_time)) {
                            $modified_time_limit = true;
                        } else {
                            $modified_time_limit = false;
                        }

                        if ($restoring) {
                            // restore
                            $directories = Arr::filterKeys($_POST, 'directory_');
                            $files = Arr::filterKeys($_POST, 'file_');
                            $database = isset($_POST['database']);
                            $success = $backup_restorer->restore($database, $directories, $files, $errors);

                            if ($success) {
                                $message = Message::ok(_lang('admin.backup.restore.complete'));
                            } else {
                                $message = Message::list($errors, ['type' => Message::ERROR]);
                            }
                        }

                        // show info
                        if (!$success) {
                            $backup_size = filesize($backup_dir . '/' . $backup_file);
                            $backup_size_display = GenericTemplates::renderFileSize($backup_size);
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

                                $config_info['estimated_time'] = $estimated_time;

                                $backup_size_display .= ' <img src="' . _e(Router::path('admin/images/icons/warn.png')) . '" class="icon" alt="warn">';
                                $backup_size_warning = Message::warning(_lang('admin.backup.restore.size_warning', ['%config_info%' => json_encode($config_info)]));
                            }

                            $backup_metadata = $backup->getMetaData();

                            $message .= '<div class="well">
    <h2>' . _lang('admin.backup.restore.title') . '</h2>
    <form method="post">
        <input type="hidden" name="backup_loaded" value="1">
        <input type="hidden" name="backup_file" value="' . _e($backup_file) . '">
        <table class="list">
            <tr>
                <th>' . _lang('global.name') . '</th>
                <td>' . _e($remove_random_suffix($backup_file)) . '</td>
            </tr>
            <tr>
                <th>' . _lang('global.size') . '</th>
                <td>' . $backup_size_display . '</td>
            </tr>
            <tr>
                <th>' . _lang('global.created_at') . '</th>
                <td>' . GenericTemplates::renderTime($backup_metadata['created_at']) . '</td>
            </tr>
            <tr class="valign-top">
                <th>' . _lang('admin.backup.restore.contents') . '</th>
                <td>
                    <ul id="backup-contents" class="no-bullets">
                        <li><label><input type="checkbox"' . Form::restoreCheckedAndName('backup_loaded', 'database') . Form::disableInputUnless($backup->hasDatabaseDump()) . '> ' . _lang('admin.backup.opt.db') . '</label></li>
                        ' . _buffer(function () use ($backup_metadata) {
                                foreach ($backup_metadata['directory_list'] as $index => $directory) {
                                    echo '<li><label><input type="checkbox"' . Form::restoreCheckedAndName('backup_loaded', 'directory_' . $index) . ' value="' . _e($directory) . '"> ' . _lang('admin.backup.restore.contents.dir') . ' <code>' . _e($directory) . "</code></label></li>\n";
                                }
                        }) . '
                        ' . _buffer(function () use ($backup_metadata) {
                            foreach ($backup_metadata['file_list'] as $index => $file) {
                                echo '<li><label><input type="checkbox"' . Form::restoreCheckedAndName('backup_loaded', 'file_' . $index) . ' value="' . _e($file) . '"> ' . _lang('admin.backup.restore.contents.file') . ' <code>' . _e($file) . "</code></label></li>\n";
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
            <input class="button small" type="submit" name="do_restore[restore]" onclick="return Sunlight.confirm()" value="' . _lang('admin.backup.restore.title') . '">
            <input class="button small" type="submit" value="' . _lang('global.cancel2') . '">
        </p>
    ' . Xsrf::getInput() . '
    </form>
    </div>';
                        }
                    } else {
                        $message = Message::list($errors, ['text' => _lang('admin.backup.restore.errors.validate')]);
                    }
                } elseif (isset($_POST['do_restore']['delete'])) {
                    unlink($backup_dir . '/' . $backup_file);
                    unset($backup_files[$backup_file]);

                    $message = Message::ok(_lang('global.done'));
                }
            }
        } elseif (isset($_POST['do_upload'])) {
            if (isset($_FILES['backup']) && is_uploaded_file($_FILES['backup']['tmp_name'])) {
                $backup_name = StringManipulator::slugify($_FILES['backup']['name'], false);

                if (preg_match('{\.zip$}Di', $backup_name) && Filesystem::isSafeFile($backup_name)) {
                    $stored_backup_name = $add_random_suffix($backup_name);

                    User::moveUploadedFile($_FILES['backup']['tmp_name'], $backup_dir . '/' . $stored_backup_name);
                    $backup_files[$stored_backup_name] = time();

                    $message = Message::ok(_lang('global.done'));
                } else {
                    $message = Message::warning(_lang('admin.backup.upload.error'));
                }
            } else {
                $message = Message::warning(_lang('global.noupload'));
            }
        }
    } catch (Throwable $e) {
        $message = Message::error(_lang('global.error')) . Core::renderException($e);
    }
}

// list existing backups
arsort($backup_files, SORT_NUMERIC);

$backup_list = '';
if (!empty($backup_files)) {
    foreach ($backup_files as $backup_file => $backup_ctime) {
        $displayed_backup_name = $remove_random_suffix($backup_file);

        $backup_list .= '<tr>
    <td><label><input type="radio" name="backup_file" value="' . _e($backup_file) . '"> ' . _e($displayed_backup_name) . '</label></td>
    <td>' . GenericTemplates::renderFileSize(filesize($backup_dir . '/' . $backup_file)) . '</td>
    <td>' . GenericTemplates::renderTime($backup_ctime) . '</td>
    <td><a href="' . _e(Router::admin('backup', ['query' => ['download' => $backup_file]])) . '" title="' . _lang('global.download') . '"><img src="' . _e(Router::path('admin/images/icons/floppy.png')) . '" alt="' . _lang('global.download') . '"></a></td>
</tr>
';
    }
} else {
    $backup_list = '<tr><td colspan="4">' . _lang('global.nokit') . "</td></tr>\n";
}

// forms
$output .= $message . '
<table class="two-columns">
<tr class="valign-top">
<td>

    <h2>' . _lang('admin.backup.create.title') . '</h2>
    <p>' . _lang('admin.backup.create.p') . '</p>
    <form method="post">
        <table>
            <tr class="valign-top">
                <th>' . _lang('admin.backup.opts') . '</th>
                <td>
                    <ul class="no-bullets">
                        <li><label><input type="checkbox" value="1"' . Form::restoreCheckedAndName('partial_backup', 'opt_db', true) . '> ' . _lang('admin.backup.opt.db') . '</label></li>
                        ' . _buffer(function () use ($backup_dynpath_choices) {
                            foreach ($backup_dynpath_choices as $name => $options) {
                                echo '<li><label><input type="checkbox" value="' . $name . '"' . Form::restoreCheckedAndName('partial_backup', 'dynpath_' . $name, true) . '> ' . _e($options['label']) . ' <small>(' . GenericTemplates::renderFileSize($options['size']) . ')</small></label></li>';
                            }
                        }) . '
                        ' . Extend::buffer('admin.backup.options', ['type' => 'partial']) . '
                    </ul>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input class="button small" type="submit" name="partial_backup[download]" formtarget="_blank" value="' . _lang('global.download') . '">
                    ' . _lang('global.or') . '
                    <input class="button small" type="submit" name="partial_backup[store]" value="' . _lang('admin.backup.store') . '">
                </td>
            </tr>
        </table>
    ' . Xsrf::getInput() . '
    </form>


    <h2>' . _lang('admin.backup.package.title') . '</h2>
    <p>' . _lang('admin.backup.package.p') . '</p>
    <form method="post">
        <table>
            <tr>
                <th>' . _lang('admin.backup.opts') . '</th>
                <td>
                    <ul class="no-bullets">
                        <li><label><input type="checkbox" checked disabled> ' . _lang('admin.backup.opt.db') . '</label></li>
                        <li><label><input type="checkbox" checked disabled> ' . _lang('admin.backup.opt.sys') . ' <small>(' . GenericTemplates::renderFileSize($static_size) . ')</small></label></li>
                        ' . _buffer(function () use ($backup_dynpath_choices, $backup_builder) {
                            foreach ($backup_dynpath_choices as $name => $options) {
                                $optional = $backup_builder->isDynamicPathOptional($name);

                                if ($optional) {
                                    $checked = !isset($_POST['full_backup']) || isset($_POST['full_backup'], $_POST['dynpath_' . $name]);
                                } else {
                                    $checked = true;
                                }
                                echo '<li><label><input type="checkbox" value="' . $name . '" name="dynpath_' . $name . '"' . Form::disableInputUnless($optional) . Form::activateCheckbox($checked) . '> ' . _e($options['label']) . ' <small>(' . GenericTemplates::renderFileSize($options['size']) . ')</small></label></li>';
                            }
                        }) . '
                        ' . Extend::buffer('admin.backup.options', ['type' => 'full']) . '
                    </ul>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input class="button small" type="submit" name="full_backup[download]" formtarget="_blank" value="' . _lang('global.download') . '">
                    ' . _lang('global.or') . '
                    <input class="button small" type="submit" name="full_backup[store]" value="' . _lang('admin.backup.store') . '">
                </td>
            </tr>
        </table>
    ' . Xsrf::getInput() . '
    </form>

</td>
<td>

    <h2>' . _lang('admin.backup.upload.title') . '</h2>
    <p>' . _lang('admin.backup.upload.p') . '</p>

    <form method="post" enctype="multipart/form-data">
        <table>
            <tr>
                <th>' . _lang('global.file') . '</th>
                <td>
                    <input type="file" name="backup">
                    ' . Environment::renderUploadLimit() . '
                </td>
            </tr>
            <tr>
                <td></td>
                <td><input class="button small" type="submit" name="do_upload" value="' . _lang('global.upload') . '"></td>
            </tr>
        </table>
    ' . Xsrf::getInput() . '
    </form>


    <h2>' . _lang('admin.backup.restore.title') . '</h2>
    <p>' . _lang('admin.backup.restore.p') . '</p>

    <form method="post" enctype="multipart/form-data">
        <table class="list list-hover max-width">
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

        <p>
            <input class="button small" type="submit" name="do_restore[load]" value="' . _lang('admin.backup.restore.submit.load') . '">
            ' . _lang('global.or') . '
            <input class="button small" onclick="return Sunlight.confirm()" type="submit" name="do_restore[delete]" value="' . _lang('admin.backup.restore.submit.delete') . '">
        </p>
    ' . Xsrf::getInput() . '
    </form>

</td>
</tr>
</table>
';
