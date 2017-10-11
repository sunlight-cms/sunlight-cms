<?php

use Sunlight\Backup\Backup;
use Sunlight\Backup\BackupBuilder;
use Sunlight\Backup\BackupRestorer;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Url;

if (!defined('_root')) {
    exit;
}

$message = '';

$remove_hash_suffix = function ($filename) {
    return preg_replace('/(.+)__[0-9a-f]{64}(\.zip)$/i', '$1$2', $filename);
};

$add_hash_suffix = function ($filename) use ($remove_hash_suffix) {
    $filename = $remove_hash_suffix($filename);
    $hash = hash_hmac('sha256', uniqid($filename, true), Core::$secret);

    return preg_replace('/(.+)(\.zip)$/i', '${1}__' . $hash . '${2}', $filename);
};

// nacteni existujicich zaloh
$backup_dir = _root . 'system/backup';
$backup_files = array();
foreach (scandir($backup_dir) as $item) {
    if (
        $item !== '.'
        && $item !== '..'
        && preg_match('/\.zip$/i', $item)
        && is_file($backup_path = $backup_dir . '/' . $item)
    ) {
        $backup_files[$item] = filectime($backup_path);
    }
}

// stazeni zalohy
if (isset($_GET['download'])) {
    $download = _get('download');

    if (isset($backup_files[$download])) {
        _downloadFile($backup_dir . '/' . $download, $download);
        exit;
    }
}

// pripravit backup builder
$backup_builder = new BackupBuilder();

// pripravit volby dynamickych cest
$backup_dynpath_choices = array();
foreach ($backup_builder->getDynamicPathNames() as $name) {
    $backup_dynpath_choices[$name] = array(
        'label' => _lang('admin.backup.dynpath.' . $name),
    );
}

Extend::call('admin.backup.builder', array(
    'builder' => $backup_builder,
    'dynpath_choices' => &$backup_dynpath_choices,
));

// spocitat velikosti
$computePathSize = function ($path) {
    $size = 0;
    $fullPath = _root . $path;

    if (file_exists($fullPath)) {
        if (is_dir($fullPath)) {
            $size = Filesystem::getDirectorySize($fullPath);
        } else {
            $size = filesize($fullPath);
        }
    }

    return $size;
};

$static_size = 0;
foreach ($backup_builder->getStaticPaths() as $path) {
    $static_size += $computePathSize($path);
}

foreach ($backup_dynpath_choices as $name => &$options) {
    $size = 0;
    foreach ($backup_builder->getDynamicPath($name) as $path) {
        $size += $computePathSize($path);
    }

    $options['size'] = $size;
}

// zpracovani
if (!empty($_POST)) {
    try {
        if (isset($_POST['do_create']) || isset($_POST['do_package'])) {

            if (isset($_POST['do_create'])) {
                $type = BackupBuilder::TYPE_PARTIAL;
                $store = isset($_POST['do_create']['store']);
            } else {
                $type = BackupBuilder::TYPE_FULL;
                $store = isset($_POST['do_package']['store']);
            }

            // nastaveni zalohy
            $backup_builder->setDatabaseDumpEnabled(isset($_POST['opt_db']));

            $enabled_dynpaths = _arrayFilter($_POST, 'dynpath_');
            foreach ($backup_builder->getDynamicPathNames() as $name) {
                if (
                    isset($backup_dynpath_choices[$name])
                    && !in_array($name, $enabled_dynpaths, true)
                ) {
                    $backup_builder->disableDynamicPath($name);
                }
            }

            // sestavit zalohu
            $backup = $backup_builder->build($type);

            $backup_name = sprintf(
                '%s_%s_%s.zip',
                BackupBuilder::TYPE_PARTIAL === $type
                    ? 'backup'
                    : 'full_backup',
                Url::base()->host,
                date('Y_m_d')
            );

            if ($store) {
                // ulozit na serveru
                $stored_backup_name = $add_hash_suffix($backup_name);
                $backup->move($backup_dir . '/' . $stored_backup_name);
                $backup_files[$stored_backup_name] = time();

                $message = _msg(_msg_ok, _lang('admin.backup.store.success'));
            } else {
                // stahnout
                _downloadFile($backup, $backup_name);
                $backup->discard();
                exit;
            }

        } elseif (isset($_POST['do_restore'])) {

            $backup_file = _post('backup_file');

            if (isset($backup_files[$backup_file])) {
                if (($restoring = isset($_POST['do_restore']['restore'])) || isset($_POST['do_restore']['load'])) {

                    $backup = new Backup($backup_dir . '/' . $backup_file);
                    $backup->open();
                    $backup_restorer = new BackupRestorer($backup);

                    $errors = array();
                    $backup_restorer->validate($errors);

                    if (empty($errors)) {
                        $success = false;

                        if ($restoring) {
                            // obnovit
                            $directories = _arrayFilter($_POST, 'directory_');
                            $files = _arrayFilter($_POST, 'file_');
                            $database = isset($_POST['database']);
                            $success = $backup_restorer->restore($database, $directories, $files, $errors);

                            if ($success) {
                                $message = _msg(_msg_ok, _lang('admin.backup.restore.complete'));
                            } else {
                                $message = _msg(_msg_err, _msgList(_htmlEscapeArrayItems($errors), 'errors'));
                            }
                        }

                        // zobrazit info
                        if (!$success) {
                            $backup_metadata = $backup->getMetaData();

                            $message .= '<div class="well">
    <h2>' . _lang('admin.backup.restore.title') . '</h2>
    <form method="post">
        <input type="hidden" name="backup_loaded" value="1">
        <input type="hidden" name="backup_file" value="' . _e($backup_file) . '">
        <table class="list">
            <tr>
                <th>' . _lang('global.name') . '</th>
                <td>' . _e($remove_hash_suffix($backup_file)) . '</td>
            </tr>
            <tr>
                <th>' . _lang('global.size') . '</th>
                <td>' . _formatFilesize(filesize($backup_dir . '/' . $backup_file)) . '</td>
            </tr>
            <tr>
                <th>' . _lang('global.created_at') . '</th>
                <td>' . _formatTime($backup_metadata['created_at']) . '</td>
            </tr>
            <tr class="valign-top">
                <th>' . _lang('admin.backup.restore.contents') . '</th>
                <td>
                    <ul id="backup-contents" class="no-bullets">
                        <li><label><input type="checkbox"' . _restoreCheckedAndName('backup_loaded', 'database') . _inputDisableUnless($backup->hasDatabaseDump()) . '> ' . _lang('admin.backup.opt.db') . '</label></li>
                        ' . _buffer(function () use ($backup_metadata) {
                                foreach ($backup_metadata['directory_list'] as $index => $directory) {
                                    echo '<li><label><input type="checkbox"' . _restoreCheckedAndName('backup_loaded', 'directory_' . $index) . ' value="' . _e($directory) . '"> ' . _lang('admin.backup.restore.contents.dir') . ' <code>' . _e($directory) . "</code></label></li>\n";
                                }
                        }) . '
                        ' . _buffer(function () use ($backup_metadata) {
                            foreach ($backup_metadata['file_list'] as $index => $file) {
                                echo '<li><label><input type="checkbox"' . _restoreCheckedAndName('backup_loaded', 'file_' . $index) . ' value="' . _e($file) . '"> ' . _lang('admin.backup.restore.contents.file') . ' <code>' . _e($file) . "</code></label></li>\n";
                            }
                        }) . '
                    </ul>
                    
                    <label class="right"><input type="checkbox" onchange="Sunlight.admin.toggleCheckboxes(document.querySelectorAll(\'#backup-contents input[type=checkbox]\'), this.checked)"> <em>' . _lang('global.all') . '</em></label>
                </td>
            </tr>
        </table>

        ' . _msg(_msg_warn, _lang('admin.backup.restore.warning')) . '

        <p>
            <input class="button small" type="submit" name="do_restore[restore]" onclick="return Sunlight.confirm()" value="' . _lang('admin.backup.restore.title') . '">
            <input class="button small" type="submit" value="' . _lang('global.cancel2') . '">
        </p>
    ' . _xsrfProtect() . '
    </form>
    </div>';
                        }
                    } else {
                        $message = _msg(_msg_err, _msgList(_htmlEscapeArrayItems($errors), _lang('admin.backup.restore.errors.validate')));
                    }

                } elseif (isset($_POST['do_restore']['delete'])) {
                    unlink($backup_dir . '/' . $backup_file);
                    unset($backup_files[$backup_file]);

                    $message = _msg(_msg_ok, _lang('global.done'));
                }
            }

        } elseif (isset($_POST['do_upload'])) {

            if (isset($_FILES['backup']) && is_uploaded_file($_FILES['backup']['tmp_name'])) {

                $backup_name = _slugify($_FILES['backup']['name'], false);

                if (preg_match('/\.zip$/i', $backup_name) && _isSafeFile($backup_name)) {
                    $stored_backup_name = $add_hash_suffix($backup_name);

                    _userMoveUploadedFile($_FILES['backup']['tmp_name'], $backup_dir . '/' . $stored_backup_name);
                    $backup_files[$stored_backup_name] = time();

                    $message = _msg(_msg_ok, _lang('global.done'));
                } else {
                    $message = _msg(_msg_warn, _lang('admin.backup.upload.error'));
                }

            } else {
                $message = _msg(_msg_warn, _lang('global.noupload'));
            }

        }
    } catch (\Exception $e) {
        $message = _msg(_msg_err, _lang('global.error')) . Core::renderException($e);
    }
}

// vypis existujicich zaloh
arsort($backup_files, SORT_NUMERIC);

$backup_list = '';
if (!empty($backup_files)) {
    foreach ($backup_files as $backup_file => $backup_ctime) {
        $displayed_backup_name = $remove_hash_suffix($backup_file);

        $backup_list .= '<tr>
    <td><label><input type="radio" name="backup_file" value="' . _e($backup_file) . '"> ' . _e($displayed_backup_name) . '</label></td>
    <td>' . _formatFilesize(filesize($backup_dir . '/' . $backup_file)) . '</td>
    <td>' . _formatTime($backup_ctime) . '</td>
    <td><a href="index.php?p=backup&download=' . _e($backup_file) . '" title="' . _lang('global.download') . '"><img src="images/icons/floppy.png" alt="' . _lang('global.download') . '"></a></td>
</tr>
';
    }
} else {
    $backup_list = '<tr><td colspan="4">' . _lang('global.nokit') . "</td></tr>\n";
}

// formulare
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
                        <li><label><input type="checkbox" value="1"' . _restoreCheckedAndName('do_create', 'opt_db', true) . '> ' . _lang('admin.backup.opt.db') . '</label></li>
                        ' . _buffer(function () use ($backup_dynpath_choices) {
                            foreach ($backup_dynpath_choices as $name => $options) {
                                echo '<li><label><input type="checkbox" value="' . $name . '"' . _restoreCheckedAndName('do_create', 'dynpath_' . $name, true) . '> ' . _e($options['label']) . ' <small>(' . _formatFilesize($options['size']) . ')</small></label></li>';
                            }
                        }) . '
                        ' . Extend::buffer('admin.backup.options', array('type' => 'partial')) . '
                    </ul>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input class="button small" type="submit" name="do_create[download]" formtarget="_blank" value="' . _lang('global.download') . '">
                    ' . _lang('global.or') . '
                    <input class="button small" type="submit" name="do_create[store]" value="' . _lang('admin.backup.store') . '">
                </td>
            </tr>
        </table>
    ' . _xsrfProtect() . '
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
                        <li><label><input type="checkbox" checked disabled> ' . _lang('admin.backup.opt.sys') . ' <small>(' . _formatFilesize($static_size) . ')</small></label></li>
                        ' . _buffer(function () use ($backup_dynpath_choices, $backup_builder) {
                            foreach ($backup_dynpath_choices as $name => $options) {
                                $optional = $backup_builder->isDynamicPathOptional($name);

                                if ($optional) {
                                    $checked = !isset($_POST['do_package']) || isset($_POST['do_package'], $_POST['dynpath_' . $name]);
                                } else {
                                    $checked = true;
                                }
                                echo '<li><label><input type="checkbox" value="' . $name . '" name="dynpath_' . $name . '"' . _inputDisableUnless($optional) . _checkboxActivate($checked) . '> ' . _e($options['label']) . ' <small>(' . _formatFilesize($options['size']) . ')</small></label></li>';
                            }
                        }) . '
                        ' . Extend::buffer('admin.backup.options', array('type' => 'full')) . '
                    </ul>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input class="button small" type="submit" name="do_package[download]" formtarget="_blank" value="' . _lang('global.download') . '">
                    ' . _lang('global.or') . '
                    <input class="button small" type="submit" name="do_package[store]" value="' . _lang('admin.backup.store') . '">
                </td>
            </tr>
        </table>
    ' . _xsrfProtect() . '
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
                    ' . _renderUploadLimit() . '
                </td>
            </tr>
            <tr>
                <td></td>
                <td><input class="button small" type="submit" name="do_upload" value="' . _lang('global.upload') . '"></td>
            </tr>
        </table>
    ' . _xsrfProtect() . '
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
    ' . _xsrfProtect() . '
    </form>

</td>
</tr>
</table>
';
