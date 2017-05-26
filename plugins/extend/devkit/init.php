<?php

namespace SunlightExtend\Devkit;

use Kuria\Error\Screen\WebErrorScreen;
use Sunlight\Core;
use Sunlight\Extend;

if (!defined('_root')) {
    return;
}

// register extend event logger
Extend::regGlobal(array($this->eventLogger, 'log'), 10000);

// register SQL logger in error handler
$exceptionHandler = Core::$errorHandler->getExceptionHandler();
if ($exceptionHandler instanceof WebErrorScreen) {
    $exceptionHandler->on('render.debug', array($this->sqlLogger, 'showInDebugScreen'));
}

