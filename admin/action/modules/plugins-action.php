<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Slugify\Slugify;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$id = Request::get('id', '');
$action_name = Request::get('action', '');

if (!Xsrf::check(true)) {
    $output .= Message::error(_lang('global.badinput'));

    return;
}

// get plugin and action
$plugin = Core::$pluginManager->getPlugins()->get($id)
    ?? Core::$pluginManager->getPlugins()->getInactive($id);

if (
    $plugin === null
    || ($action = $plugin->getAction($action_name)) === null
) {
    $output .= Message::error(_lang('global.badinput'));

    return;
}

// run action
$_admin->contentClasses[] = Slugify::getInstance()->slugify("plugin-action-{$action_name}");

$result = $action->run();

if ($result->isComplete()) {
    Core::$pluginManager->clearCache();
}

// show result
$output .= Admin::backlink(Router::admin('plugins'));
$output .= '<h1>' . _e($action->getTitle()) . ': ' . _e($plugin->getOption('name')) . "</h1>\n";
$output .= $result;
