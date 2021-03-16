<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;
use Sunlight\Util\Filesystem;

/**
 * Remove a plugin
 */
class RemoveAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.remove');
    }

    protected function execute(): ActionResult
    {
        if (!$this->isConfirmed()) {
            return $this->confirm(
                _lang('admin.plugins.action.remove.confirm'),
                _lang('admin.plugins.action.do.remove')
            );
        }

        if ($this->plugin->canBeRemoved()) {
            $dir = $this->plugin->getDirectory();

            if (Filesystem::checkDirectory($dir) && Filesystem::purgeDirectory($dir)) {
                return ActionResult::success(
                    Message::ok(_lang('admin.plugins.action.remove.success', ['%plugin%' => $this->plugin->getOption('name')]))
                );
            }
        }

        return ActionResult::failure(
            Message::error(_lang('admin.plugins.action.remove.failure', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
