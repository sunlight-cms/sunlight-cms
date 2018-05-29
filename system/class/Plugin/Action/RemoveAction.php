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
    function getTitle()
    {
        return _lang('admin.plugins.action.do.remove');
    }

    protected function execute()
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
                    Message::ok(sprintf(_lang('admin.plugins.action.remove.success'), $this->plugin->getOption('name')))
                );
            }
        }

        return ActionResult::failure(
            Message::error(sprintf(_lang('admin.plugins.action.remove.failure'), $this->plugin->getOption('name')))
        );
    }
}
