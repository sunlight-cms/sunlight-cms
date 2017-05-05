<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;

/**
 * Install a plugin
 */
class InstallAction extends PluginAction
{
    public function getTitle()
    {
        return $GLOBALS['_lang']['admin.plugins.action.do.install'];
    }

    protected function execute()
    {
        global $_lang;

        if (!$this->isConfirmed()) {
            return $this->confirm(
                $_lang['admin.plugins.action.install.confirm'],
                $_lang['admin.plugins.action.do.install']
            );
        }

        if ($this->plugin->canBeInstalled()) {
            $installer = $this->plugin->getInstaller();

            if ($installer->install()) {
                return ActionResult::success(
                    Message::ok(sprintf($_lang['admin.plugins.action.install.success'], $this->plugin->getOption('name')))
                );
            }
        }

        return ActionResult::failure(
            Message::error(sprintf($_lang['admin.plugins.action.install.failure'], $this->plugin->getOption('name')))
        );
    }
}
