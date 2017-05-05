<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Plugin\PluginLoader;

/**
 * Show information about a plugin
 */
class InfoAction extends PluginAction
{
    public function getTitle()
    {
        return $GLOBALS['_lang']['admin.plugins.action.do.info'];
    }

    protected function execute()
    {
        $plugin = $this->plugin;

        return ActionResult::output(_buffer(function () use ($plugin) {
            global $_lang;

            ?>
<table class="list valign-top">
    <tr>
        <th><?php echo $_lang['global.type'] ?></th>
        <td><?php echo _e($plugin->getType()) ?></td>
    </tr>

    <tr>
        <th><?php echo $_lang['global.name'] ?></th>
        <td><?php echo _e($plugin->getName()) ?></td>
    </tr>

    <tr>
        <th><?php echo $_lang['global.dir'] ?></th>
        <td><?php echo _e($plugin->getDirectory()) ?></td>
    </tr>

    <tr>
        <th><?php echo $_lang['admin.plugins.implementation'] ?></th>
        <td><?php echo _e(get_class($plugin)) ?></td>
    </tr>

    <tr>
        <th><?php echo $_lang['admin.plugins.status'] ?></th>
        <td>
            <?php echo $_lang['admin.plugins.status.' . $plugin->getStatus()] ?>
        </td>
    </tr>

    <?php if ($plugin->hasErrors()): ?>
    <tr>
        <th><?php echo $_lang['admin.plugins.errors'] ?></th>
        <td class="text-danger">
            <?php echo _msgList($plugin->getErrors()) ?>
            <?php echo _msgList($plugin->getConfigurationErrors(), null, true) ?>
        </td>
    </tr>
    <?php endif ?>

    <tr>
        <th><?php echo $_lang['admin.plugins.data'] ?></th>
        <td>
            <pre><?php echo _e(file_get_contents($plugin->getDirectory() . '/' . PluginLoader::PLUGIN_FILE)) ?></pre>
        </td>
    </tr>
</table>
<?php
        }));
    }
}
