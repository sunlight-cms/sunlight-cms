<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;

/**
 * Uninstall a plugin
 */
class UninstallAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.uninstall');
    }

    protected function execute(): ActionResult
    {
        if (!$this->isConfirmed()) {
            return $this->confirm(
                _lang('admin.plugins.action.uninstall.confirm'),
                _lang('admin.plugins.action.do.uninstall')
            );
        }

        if ($this->plugin->canBeUninstalled()) {
            $installer = $this->plugin->getInstaller();

            if ($installer->uninstall()) {
                return ActionResult::success(
                    Message::ok(_lang('admin.plugins.action.uninstall.success', ['%plugin%' => $this->plugin->getOption('name')]))
                );
            }
        }

        return ActionResult::failure(
            Message::error(_lang('admin.plugins.action.uninstall.failure', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
