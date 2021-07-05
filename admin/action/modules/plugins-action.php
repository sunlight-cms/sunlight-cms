<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// parametry
$type = Request::get('type');
$name = Request::get('name');
$action = Request::get('action');

if (!Xsrf::check(true)) {
    $output .= Message::error(_lang('global.badinput'));

    return;
}

// nacist plugin a akci
if (
    !Core::$pluginManager->isValidType($type)
    || ($plugin = Core::$pluginManager->find($type, $name, false)) === null
    || ($action = $plugin->getAction($action)) === null
) {
    $output .= Message::error(_lang('global.badinput'));

    return;
}

// provest akci
$result = $action->run();

if ($result->isComplete()) {
    Core::$pluginManager->purgeCache();
}

// zobrazit vysledek
$output .= Admin::backlink('index.php?p=plugins');
$output .= '<h1>' . _e($action->getTitle()) . ': ' . _e($plugin->getOption('name')) . "</h1>\n";
$output .= $result;
