<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;
use Sunlight\Util\Filesystem;

class RemoveAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.remove');
    }

    function isAllowed(): bool
    {
        return $this->plugin->isInstalled() !== true && !$this->plugin->isEssential();
    }

    protected function execute(): ActionResult
    {
        if (!$this->isConfirmed()) {
            return $this->confirm(
                _lang('admin.plugins.action.remove.confirm'),
                [
                    'button_text' => _lang('admin.plugins.action.do.remove'),
                    'content_after' => $this->getDependantsWarning(),
                ]
            );
        }

        $dir = $this->plugin->getDirectory();

        if (Filesystem::checkDirectory($dir) && Filesystem::purgeDirectory($dir)) {
            return ActionResult::success(
                Message::ok(_lang('admin.plugins.action.remove.success', ['%plugin%' => $this->plugin->getOption('name')]))
            );
        }

        return ActionResult::failure(
            Message::error(_lang('admin.plugins.action.remove.failure', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
