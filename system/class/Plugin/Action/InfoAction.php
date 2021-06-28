<?php

namespace Sunlight\Plugin\Action;

use Kuria\Debug\Dumper;
use Sunlight\Action\ActionResult;
use Sunlight\GenericTemplates;

/**
 * Show information about a plugin
 */
class InfoAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.info');
    }

    protected function execute(): ActionResult
    {
        $plugin = $this->plugin;

        return ActionResult::output(_buffer(function () use ($plugin) { ?>
<table class="list valign-top">
    <tr>
        <th><?= _lang('global.id') ?></th>
        <td><?= _e($plugin->getId()) ?></td>
    </tr>

    <tr>
        <th><?= _lang('global.type') ?></th>
        <td><?= _e($plugin->getType()) ?></td>
    </tr>

    <tr>
        <th><?= _lang('global.dir') ?></th>
        <td><?= _e($plugin->getDirectory()) ?></td>
    </tr>

    <tr>
        <th><?= _lang('admin.plugins.implementation') ?></th>
        <td><?= _e(get_class($plugin)) ?></td>
    </tr>

    <tr>
        <th><?= _lang('admin.plugins.status') ?></th>
        <td>
            <?= _lang('admin.plugins.status.' . $plugin->getStatus()) ?>
        </td>
    </tr>

    <?php if ($plugin->hasErrors()): ?>
    <tr>
        <th><?= _lang('admin.plugins.errors') ?></th>
        <td class="text-danger">
            <?= GenericTemplates::renderMessageList($plugin->getErrors()) ?>
        </td>
    </tr>
    <?php endif ?>

    <tr>
        <th><?= _lang('admin.plugins.data') ?></th>
        <td>
            <pre><?= _e(file_get_contents($plugin->getFile())) ?></pre>
        </td>
    </tr>

    <tr>
        <th><?= _lang('admin.content.form.settings') ?></th>
        <td>
            <pre><?= _e(Dumper::dump($plugin->getOptions(), 4)) ?></pre>
        </td>
    </tr>

    <tr>
        <th><?= _lang('admin.plugins.object') ?></th>
        <td>
            <pre><?= _e(Dumper::dump($plugin)) ?></pre>
        </td>
    </tr>
</table>
<?php
        }));
    }
}
