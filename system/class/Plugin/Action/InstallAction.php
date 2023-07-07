<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;

/**
 * Install a plugin
 */
class InstallAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.install');
    }

    function isAllowed(): bool
    {
        return $this->plugin->isInstalled() === false;
    }

    protected function execute(): ActionResult
    {
        if (!$this->isConfirmed()) {
            return $this->confirm(
                _lang('admin.plugins.action.install.confirm'),
                ['button_text' => _lang('admin.plugins.action.do.install')]
            );
        }

        $installer = $this->plugin->getInstaller();

        if ($installer->install()) {
            return ActionResult::success(
                Message::ok(_lang('admin.plugins.action.install.success', ['%plugin%' => $this->plugin->getOption('name')]))
            );
        }

        return ActionResult::failure(
            Message::error(_lang('admin.plugins.action.install.failure', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
