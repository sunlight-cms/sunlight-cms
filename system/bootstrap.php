<?php

use Kuria\ClassLoader\ClassLoader;
use Kuria\ClassLoader\ComposerBridge;
use Sunlight\Core;

$vendorDir = dirname(__DIR__) . '/vendor';

// load classes
foreach (array('/kuria/class-loader/src/ClassLoader.php', '/kuria/class-loader/src/ComposerBridge.php') as $pathToInclude) {
    if (!@include $vendorDir . $pathToInclude) {
        echo "Missing dependencies in the vendor directory. Did you run composer install?\n";
        exit(1);
    }
}

unset($pathToInclude);

// init class loader
$classLoader = new ClassLoader();
$classLoader->register();

ComposerBridge::configure($classLoader, $vendorDir);
Core::$classLoader = $classLoader;

unset($vendorDir, $classLoader);
