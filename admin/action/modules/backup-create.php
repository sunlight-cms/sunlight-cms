<?php

use Sunlight\Backup\BackupBuilder;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Util\Arr;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Xsrf;

// fetch type
$type = Request::post('type');

if ($type !== 'partial' && $type !== 'full') {
    $output .= Message::error(_lang('global.badinput'));
    return;
}

// create builder
$backup_builder = new BackupBuilder();
$backup_builder->setFullBackup(Request::post('type') === 'full');
Extend::call('admin.backup.create', ['builder' => $backup_builder]);

// prepare options
$get_path_size = function ($path) {
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

$dynamic_path_options = [];

foreach ($backup_builder->getDynamicPathNames() as $name) {
    $dynamic_path_options[$name] = [
        'label' => _lang('admin.backup.dynpath.' . $name),
        'optional' => $type === 'partial' || $backup_builder->isDynamicPathOptionalInFullBackup($name),
    ];
}

// create
if (isset($_POST['create'])) {
    if (!$backup_builder->isFullBackup()) {
        $backup_builder->setDatabaseDumpEnabled(Form::loadCheckbox('database'));
    }

    $enabled_dynamic_paths = Arr::filterKeys($_POST, 'dynpath_');

    foreach ($backup_builder->getDynamicPathNames() as $name) {
        if (
            isset($dynamic_path_options[$name])
            && $dynamic_path_options[$name]['optional']
            && !in_array($name, $enabled_dynamic_paths, true)
        ) {
            $backup_builder->disableDynamicPath($name);
        }
    }

    $backup = $backup_builder->build();

    $backup_name = sprintf(
        '%s_%s_%s.zip',
        $backup_builder->isFullBackup() ? 'full_backup' : 'backup',
        Core::getBaseUrl()->getHost(),
        date('Y-m-d_His')
    );

    if (Request::post('create') === 'store') {
        $backup->move(SL_ROOT . 'system/backup/' . $backup_name);
        $output .= Message::ok(_lang('admin.backup.create.store.success', ['%name%' => $backup_name]));
        return;
    } else {
        Response::downloadFile($backup->getPathname(), $backup_name);
    }
}

// calculate sizes
foreach ($dynamic_path_options as $name => &$option) {
    $size = 0;

    foreach ($backup_builder->getDynamicPath($name) as $path) {
        $size += $get_path_size(SL_ROOT . $path);
    }

    $option['size'] = $size;
}

unset($option);

$database_size = null;

$db_size_query = DB::query(
    'SELECT SUM(data_length) FROM information_schema.tables
    WHERE table_schema = ' . DB::val(DB::$database) . ' AND table_name LIKE \'' . DB::escWildcard(DB::$prefix) . '%\'
    GROUP BY table_schema',
    true
);

if ($db_size_query !== false) {
    $database_size = DB::result($db_size_query);
}

if ($type === 'full') {
    $static_size = 0;

    foreach ($backup_builder->getStaticPaths() as $path) {
        $static_size += $get_path_size(SL_ROOT . $path);
    }
}

// output
$output .= '<form method="post">
    ' . Form::input('hidden', 'type', $type) . '
    <table class="list">
        <tr>
            <th>' . _lang('global.type') . '</th>
            <td>' . _lang('admin.backup.create.' . $type) . '</td>    
        </tr>
        <tr class="valign-top">
            <th>' . _lang('admin.backup.contents') . '</th>
            <td>
                <ul id="backup-contents" class="no-bullets">                    
                    ' . ($type === 'full'
                        ? '<li><label>' . Form::input('checkbox', null, null, ['disabled' => true, 'checked' => true]) . ' '
                            . _lang('admin.backup.contents.db')
                            . ' <small>(~' . GenericTemplates::renderFileSize($database_size) . ')</small>'
                            . '</label></li>'
                            . '<li><label>' . Form::input('checkbox', null, null, ['disabled' => true, 'checked' => true]) . ' '
                            . _lang('admin.backup.contents.sys')
                            . ' <small>(' . GenericTemplates::renderFileSize($static_size) . ')</small>'
                            . '</label></li>'
                        : '<li><label>' . Form::input('checkbox', 'database', '1', ['checked' => true]) . ' '
                            . _lang('admin.backup.contents.db')
                            . ' <small>(~' . GenericTemplates::renderFileSize($database_size) . ')</small>'
                            . '</label></li>'
                    ) . '
                    ' . _buffer(function () use ($dynamic_path_options) {
                        foreach ($dynamic_path_options as $name => $option) {
                            if ($option['optional']) {
                                $input_name = 'dynpath_' . $name;
                                $input_attrs = ['checked' => true];
                            } else {
                                $input_name = null;
                                $input_attrs = ['disabled' => true, 'checked' => true];
                            }

                            echo '<li><label>'
                                . Form::input('checkbox', $input_name, $name, $input_attrs)
                                . ' ' . _e($option['label'])
                                . ' <small>(' . GenericTemplates::renderFileSize($option['size']) . ')</small>'
                                . '</label></li>';
                        }
                    }) . '
                </ul>
                
                <label class="right">' . Form::input('checkbox', null, null, ['checked' => true, 'onchange' => 'Sunlight.admin.toggleCheckboxes(document.querySelectorAll(\'#backup-contents input[type=checkbox]\'), this.checked)']) . ' <em>' . _lang('global.all') . '</em></label>
            </td>
        </tr>
        <tr>
            <td></td>
            <td>
                <button class="button" type="submit" name="create" value="download" formtarget="_blank">' . _lang('global.download') . '</button>
                ' . _lang('global.or') . '
                <button class="button" type="submit" name="create" value="store">' . _lang('admin.backup.create.store') . '</button>
            </td>
        </tr>
    </table>
' . Xsrf::getInput() . '
</form>
';
