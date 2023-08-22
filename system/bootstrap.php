<?php

use Sunlight\Core;

if (($classLoader = include __DIR__ . '/../vendor/autoload.php') === false) {
    echo "Could not autoload dependencies. Did you run composer install?\n";
    exit(1);
}

Core::$classLoader = $classLoader;
unset($classLoader);
