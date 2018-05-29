<?php

use Sunlight\Core;
use Sunlight\Plugin\PluginArchive;

defined('_root') or exit;

$message = '';

if (isset($_FILES['archive']) && is_uploaded_file($_FILES['archive']['tmp_name'])) {
    try {
        $merge = isset($_POST['merge']);
        $archive = new PluginArchive(Core::$pluginManager, $_FILES['archive']['tmp_name']);

        if ($archive->hasPlugins()) {
            $extractedPlugins = $archive->extract($merge, $failedPlugins);

            if (!empty($extractedPlugins)) {
                $message .= _msg(_msg_ok, _msgList(_htmlEscapeArrayItems($extractedPlugins), _lang('admin.plugins.upload.extracted')));

                Core::$pluginManager->purgeCache();
            }
            if (!empty($failedPlugins)) {
                $message .= _msg(_msg_warn, _msgList(_htmlEscapeArrayItems($failedPlugins), _lang('admin.plugins.upload.failed' . (!$merge ? '.no_merge' : ''))));
            }
        } else {
            $message = _msg(_msg_warn, _lang('admin.plugins.upload.no_plugins'));
        }
    } catch (\Exception $e) {
        $message = _msg(_msg_err, _lang('global.error')) . Core::renderException($e);
    }
}


$output .= $message . '
<p class="bborder">' . _lang('admin.plugins.upload.p') . '</p>

<form method="post" enctype="multipart/form-data">
    <table>
        <tr>
            <th>' . _lang('admin.plugins.upload.file') . '</th>
            <td><input type="file" name="archive"></td>
        </tr>
        <tr>
            <td></td>
            <td>
                <input class="button" name="do_upload" type="submit" value="' . _lang('global.upload') . '">
                <label><input type="checkbox" value="1"' . _restoreCheckedAndName('do_upload', 'merge') . '> ' . _lang('admin.plugins.upload.skip_existing') . '</label>
            </td>
        </tr>
    </table>
' . _xsrfProtect() . '</form>';
