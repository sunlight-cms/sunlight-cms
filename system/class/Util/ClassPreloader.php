<?php

namespace Sunlight\Util;

use Sunlight\Core;

/**
 * Preloads classes using the system autoloader
 */
class ClassPreloader
{
    /** @var string[] */
    private $psr4PrefixPatterns = [];
    /** @var string[] */
    private $excludedClassPatterns = [];

    function addPsr4Prefix(string $pattern): void
    {
        $this->psr4PrefixPatterns[] = $pattern;
    }

    function addExcludedClassPattern(string $pattern): void
    {
        $this->excludedClassPatterns[] = $pattern;
    }

    function preload(): void
    {
        $psr4Prefixes = array_filter(
            Core::$classLoader->getPrefixesPsr4(),
            function (string $prefix) {
                foreach ($this->psr4PrefixPatterns as $pattern) {
                    if (fnmatch($pattern, $prefix, FNM_NOESCAPE)) {
                        return true;
                    }
                }

                return false;
            },
            ARRAY_FILTER_USE_KEY
        );
        
        foreach ($psr4Prefixes as $prefix => $paths) {
            foreach ($paths as $path) {
                $pathLen = strlen($path);
        
                foreach (Filesystem::createRecursiveIterator($path) as $item) {
                    /** @var \SplFileInfo $item */
                    if ($item->isFile() && $item->getExtension() === 'php') {
                        // determine class name
                        $subPath = substr($item->getPathname(), $pathLen + 1, -4);
                        $className = $prefix . strtr($subPath, '/', '\\');

                        // skip excluded classes
                        foreach ($this->excludedClassPatterns as $pattern) {
                            if (fnmatch($pattern, $className, FNM_NOESCAPE)) {
                                continue 2;
                            }
                        }

                        // load class unless it is already loaded
                        if (
                            !class_exists($className, false) 
                            && !interface_exists($className, false)
                            && !trait_exists($className, false)
                            && (PHP_VERSION_ID < 80100 || !enum_exists($className, false))
                        ) {
                            Core::$classLoader->loadClass($className);
                        }
                    }
                }
            }
        }
    }
}
