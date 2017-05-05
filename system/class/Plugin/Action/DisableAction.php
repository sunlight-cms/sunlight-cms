<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;
use Sunlight\Plugin\PluginLoader;

/**
 * Disable a plugin
 */
class DisableAction extends PluginAction
{
    public function getTitle()
    {
        return $GLOBALS['_lang']['admin.plugins.action.do.disable'];
    }

    protected function execute()
    {
        global $_lang;

        if ($this->plugin->canBeDisabled()) {
            if (touch($this->plugin->getDirectory() . '/' . PluginLoader::PLUGIN_DEACTIVATING_FILE)) {
                return ActionResult::success(
                    Message::ok(sprintf($_lang['admin.plugins.action.disable.success'], $this->plugin->getOption('name')))
                );
            }
        }

        return ActionResult::failure(
            Message::error(sprintf($_lang['admin.plugins.action.disable.failure'], $this->plugin->getOption('name')))
        );
    }
}
