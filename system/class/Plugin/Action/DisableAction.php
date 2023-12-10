<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Plugin\Plugin;

class DisableAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.disable');
    }

    function isAllowed(): bool
    {
        return !$this->plugin->hasStatus(Plugin::STATUS_DISABLED) && !$this->plugin->isEssential();
    }

    protected function execute(): ActionResult
    {
        $dependantsWarning = $this->getDependantsWarning();

        if ($dependantsWarning !== null && !$this->isConfirmed()) {
            return $this->confirm(
                _lang('admin.plugins.action.disable.confirm'),
                [
                    'button_text' => _lang('admin.plugins.action.do.disable'),
                    'content_after' => $dependantsWarning,
                ]
            );
        }

        Core::$pluginManager->getConfigStore()->setFlag(
            $this->plugin->getId(),
            'disabled',
            true
        );

        return ActionResult::success(
            Message::ok(_lang('admin.plugins.action.disable.success', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
