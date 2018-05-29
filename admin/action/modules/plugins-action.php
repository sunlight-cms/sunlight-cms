<?php

use Sunlight\Core;

defined('_root') or exit;

// parametry
$type = \Sunlight\Util\Request::get('type');
$name = \Sunlight\Util\Request::get('name');
$action = \Sunlight\Util\Request::get('action');

if (!\Sunlight\Xsrf::check(true)) {
    $output .= \Sunlight\Message::render(_msg_err, _lang('global.badinput'));

    return;
}

// nacist plugin a akci
if (
    !Core::$pluginManager->isValidType($type)
    || ($plugin = Core::$pluginManager->find($type, $name, false)) === null
    || ($action = $plugin->getAction($action)) === null
) {
    $output .= \Sunlight\Message::render(_msg_err, _lang('global.badinput'));

    return;
}

// provest akci
$result = $action->run();

if ($result->isComplete()) {
    Core::$pluginManager->purgeCache();
}

// zobrazit vysledek
$output .= \Sunlight\Admin\Admin::backlink('index.php?p=plugins');
$output .= '<h1>' . _e($action->getTitle()) . ': ' . _e($plugin->getOption('name')) . "</h1>\n";
$output .= $result;
