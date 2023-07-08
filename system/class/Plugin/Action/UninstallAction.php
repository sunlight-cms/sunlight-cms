<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;

class UninstallAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.uninstall');
    }

    function isAllowed(): bool
    {
        return $this->plugin->isInstalled() === true && !$this->plugin->isEssential();
    }

    protected function execute(): ActionResult
    {
        if (!$this->isConfirmed()) {
            return $this->confirm(
                _lang('admin.plugins.action.uninstall.confirm'),
                [
                    'button_text' => _lang('admin.plugins.action.do.uninstall'),
                    'content_after' => $this->getDependantsWarning(),
                ]
            );
        }

        $installer = $this->plugin->getInstaller();

        if ($installer->uninstall()) {
            return ActionResult::success(
                Message::ok(_lang('admin.plugins.action.uninstall.success', ['%plugin%' => $this->plugin->getOption('name')]))
            );
        }

        return ActionResult::failure(
            Message::error(_lang('admin.plugins.action.uninstall.failure', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
