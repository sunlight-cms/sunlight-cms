<?php

namespace SunlightPlugins\Extend\Devkit;

use Sunlight\Core;
use Sunlight\Extend;

if (!defined('_root')) {
    return;
}

// register extend event logger
Extend::regGlobal(array($this->eventLogger, 'log'), 10000);

// register SQL logger in error handler
Core::$errorHandler->on('fatal', array($this->sqlLogger, 'showInDebugScreen'));
