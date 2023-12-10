<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Plugin\Plugin;

class EnableAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.enable');
    }

    function isAllowed(): bool
    {
        return $this->plugin->hasStatus(Plugin::STATUS_DISABLED);
    }

    protected function execute(): ActionResult
    {
        Core::$pluginManager->getConfigStore()->setFlag(
            $this->plugin->getId(),
            'disabled',
            false
        );

        return ActionResult::success(
            Message::ok(_lang('admin.plugins.action.enable.success', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
