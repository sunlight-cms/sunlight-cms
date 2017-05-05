<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;
use Sunlight\Plugin\PluginLoader;

/**
 * Enable a plugin
 */
class EnableAction extends PluginAction
{
    public function getTitle()
    {
        return $GLOBALS['_lang']['admin.plugins.action.do.enable'];
    }

    protected function execute()
    {
        global $_lang;

        if ($this->plugin->isDisabled()) {
            $file = $this->plugin->getDirectory() . '/' . PluginLoader::PLUGIN_DEACTIVATING_FILE;

            if (is_file($file) && @unlink($file)) {
                return ActionResult::success(
                    Message::ok(sprintf($_lang['admin.plugins.action.enable.success'], $this->plugin->getOption('name')))
                );
            }
        }

        return ActionResult::failure(
            Message::error(sprintf($_lang['admin.plugins.action.enable.failure'], $this->plugin->getOption('name')))
        );
    }
}
