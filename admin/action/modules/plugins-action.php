<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$id = Request::get('id', '');
$action = Request::get('action', '');

if (!Xsrf::check(true)) {
    $output .= Message::error(_lang('global.badinput'));

    return;
}

// get plugin and action
$plugin = Core::$pluginManager->getPlugins()->get($id)
    ?? Core::$pluginManager->getInactivePlugins()->get($id);

if (
    $plugin === null
    || ($action = $plugin->getAction($action)) === null
) {
    $output .= Message::error(_lang('global.badinput'));

    return;
}

// run action
$result = $action->run();

if ($result->isComplete()) {
    Core::$pluginManager->clearCache();
}

// show result
$output .= Admin::backlink(Router::admin('plugins'));
$output .= '<h1>' . _e($action->getTitle()) . ': ' . _e($plugin->getOption('name')) . "</h1>\n";
$output .= $result;
