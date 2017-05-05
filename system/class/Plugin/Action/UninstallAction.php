<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;

/**
 * Uninstall a plugin
 */
class UninstallAction extends PluginAction
{
    public function getTitle()
    {
        return $GLOBALS['_lang']['admin.plugins.action.do.uninstall'];
    }

    protected function execute()
    {
        global $_lang;

        if (!$this->isConfirmed()) {
            return $this->confirm(
                $_lang['admin.plugins.action.uninstall.confirm'],
                $_lang['admin.plugins.action.do.uninstall']
            );
        }

        if ($this->plugin->canBeUninstalled()) {
            $installer = $this->plugin->getInstaller();

            if ($installer->uninstall()) {
                return ActionResult::success(
                    Message::ok(sprintf($_lang['admin.plugins.action.uninstall.success'], $this->plugin->getOption('name')))
                );
            }
        }

        return ActionResult::failure(
            Message::error(sprintf($_lang['admin.plugins.action.uninstall.failure'], $this->plugin->getOption('name')))
        );
    }
}
