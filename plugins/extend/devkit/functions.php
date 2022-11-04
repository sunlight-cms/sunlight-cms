<?php

use Kuria\Debug\Output;
use Sunlight\Core;
use Kuria\Debug\Dumper;
use Sunlight\Util\Environment;

if (!function_exists('dump')) {
    function dump($value, $maxLevel = Dumper::DEFAULT_MAX_LEVEL + 1, $maxStringLen = Dumper::DEFAULT_MAX_STRING_LENGTH * 2)
    {
        if (Environment::isCli()) {
            echo Dumper::dump($value, $maxLevel, $maxStringLen);
        } else {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);

            Core::$pluginManager->getPlugins()->getExtend('devkit')->addDump(
                $trace[0]['file'] ?? 'unknown',
                $trace[0]['line'] ?? 0,
                Dumper::dump($value, $maxLevel, $maxStringLen)
            );
        }

        return $value;
    }
}

if (!function_exists('dd')) {
    /**
     * @return never-return
     */
    function dd($value, $maxLevel = Dumper::DEFAULT_MAX_LEVEL + 1, $maxStringLen = Dumper::DEFAULT_MAX_STRING_LENGTH * 2)
    {
        if (Environment::isCli()) {
            echo Dumper::dump($value, $maxLevel, $maxStringLen);
        } else {
            Output::cleanBuffers();
            echo '<pre>', _e(Dumper::dump($value, $maxLevel, $maxStringLen)), '</pre>';
        }

        exit(1);
    }
}
