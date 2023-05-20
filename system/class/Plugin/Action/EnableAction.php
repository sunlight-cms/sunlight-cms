<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;
use Sunlight\Plugin\Plugin;

/**
 * Enable a plugin
 */
class EnableAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.enable');
    }

    protected function execute(): ActionResult
    {
        if ($this->plugin->hasStatus(Plugin::STATUS_DISABLED)) {
            $file = $this->plugin->getDirectory() . '/' . Plugin::DEACTIVATING_FILE;

            if (is_file($file) && @unlink($file)) {
                return ActionResult::success(
                    Message::ok(_lang('admin.plugins.action.enable.success', ['%plugin%' => $this->plugin->getOption('name')]))
                );
            }
        }

        return ActionResult::failure(
            Message::error(_lang('admin.plugins.action.enable.failure', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
