<?php

use Kuria\ClassLoader\ClassLoader;
use Kuria\ClassLoader\ComposerBridge;
use Sunlight\Core;

$vendorDir = __DIR__ . '/vendor';
$classDir = __DIR__ . '/class';

// load classes
require $vendorDir . '/kuria/class-loader/src/ClassLoader.php';
require $vendorDir . '/kuria/class-loader/src/ComposerBridge.php';
require $classDir . '/Core.php';

// init class loader
$classLoader = new ClassLoader();
$classLoader->register();

$classLoader
    ->addPrefix('Sunlight\\Admin\\', __DIR__ . '/../admin/class')
    ->addPrefix('Sunlight\\', __DIR__ . '/class')
;

ComposerBridge::configure($classLoader, $vendorDir);
Core::$classLoader = $classLoader;
