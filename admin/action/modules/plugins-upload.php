<?php

use Sunlight\Core;
use Sunlight\GenericTemplates;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Plugin\PluginArchive;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

$modes = [
    PluginArchive::MODE_ALL_OR_NOTHING => _lang('admin.plugins.upload.mode.all_or_nothing'),
    PluginArchive::MODE_SKIP_EXISTING => _lang('admin.plugins.upload.mode.skip'),
    PluginArchive::MODE_OVERWRITE_EXISTING => _lang('admin.plugins.upload.mode.overwrite'),
];

if (isset($_FILES['archive']) && is_uploaded_file($_FILES['archive']['tmp_name'])) {
    try {
        $mode = (int) Request::post('mode');
        $archive = new PluginArchive(Core::$pluginManager, $_FILES['archive']['tmp_name']);

        if (!isset($modes[$mode])) {
            throw new \UnexpectedValueException('Invalid mode');
        }

        if ($archive->hasPlugins()) {
            $extractedPlugins = $archive->extract($mode, $failedPlugins);

            if (!empty($extractedPlugins)) {
                $message .= Message::list($extractedPlugins, ['type' => Message::OK, 'text' => _lang('admin.plugins.upload.extracted')]);

                Core::$pluginManager->clearCache();
            }

            if (!empty($failedPlugins)) {
                $message .= Message::list($failedPlugins, ['text' => _lang('admin.plugins.upload.skipped')]);
            }

            Logger::notice(
                'system',
                sprintf(
                    'Uploaded plugin archive "%s" with %d plugins',
                    $_FILES['archive']['name'],
                    count($extractedPlugins)
                ),
                ['extracted_plugins' => $extractedPlugins, 'failed_plugins' => $failedPlugins]
            );
        } else {
            $message = Message::warning(_lang('admin.plugins.upload.no_plugins'));
        }
    } catch (Throwable $e) {
        $message = Message::error(_lang('global.error')) . GenericTemplates::renderException($e);
    }
}

$output .= $message . '
<p class="bborder">' . _lang('admin.plugins.upload.p') . '</p>

<form method="post" enctype="multipart/form-data">
    <table class="formtable">
        <tr>
            <th>' . _lang('admin.plugins.upload.file') . '</th>
            <td>' . Form::input('file', 'archive') . '</td>
        </tr>
        <tr>
            <th>' . _lang('admin.plugins.upload.mode') . '</th>
            <td>' . Form::select('mode', $modes, Request::post('mode')) . '</td>
        </tr>
        <tr>
            <td></td>
            <td>
                ' . Form::input('submit', 'do_upload', _lang('global.upload'), ['class' => 'button']) . '
            </td>
        </tr>
    </table>
' . Xsrf::getInput() . '</form>';
