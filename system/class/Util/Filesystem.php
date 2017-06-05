<?php

namespace Sunlight\Util;

/**
 * Filesystem utilities
 */
class Filesystem
{
    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Normalize a path
     *
     * @param string $basePath
     * @param string $path
     * @return string
     */
    public static function normalizePath($basePath, $path)
    {
        $basePath = str_replace('\\', '/', $basePath);
        $path = str_replace('\\', '/', $path);

        return
            '' === $path
                ? $basePath
                : (static::isAbsolutePath($path)
                    ? $path
                    : $basePath . '/' . $path
                );
    }

    /**
     * See if a path is absolute
     *
     * @param string $path
     * @return bool
     */
    public static function isAbsolutePath($path)
    {
        return
            isset($path[0]) && ('/' === $path[0] || '\\' === $path[0])
            || isset($path[1]) && ':' === $path[1];
    }

    /**
     * Evaluate relative parts of a path
     *
     * @param string $path   the path
     * @param bool   $isFile it is a file path 1/0
     * @return string a path always without a leading slash and with trailing slash (unless $isFile = true)
     */
    public static function parsePath($path, $isFile = false)
    {
        $segments = explode('/', trim(str_replace('\\', '/', $path), '/'));
        $parentJumps = 0;

        for ($i = sizeof($segments) - 1; $i >= 0; --$i) {
            $isCurrent = '.' === $segments[$i];
            $isParent = '..' === $segments[$i];
            $isEmpty = '' === $segments[$i];
            $isDot = $isCurrent || $isParent;

            if ($isParent) {
                ++$parentJumps;
            }
            if ($isDot || $isEmpty || $parentJumps > 0) {
                unset($segments[$i]);

                if ($parentJumps > 0 && !$isDot && !$isEmpty) {
                    --$parentJumps;
                }
            }
        }

        $result = '';

        if ($parentJumps > 0) {
            $result .= str_repeat('../', $parentJumps);
        }

        $result .= implode('/', $segments);

        if (!$isFile && !empty($segments)) {
            $result .= '/';
        }

        if ('' === $result) {
            $result = './';
        }

        return $result;
    }
    
    /**
     * Create recursive directory iterator
     *
     * @param string $path
     * @param int    $flags
     * @return \RecursiveIteratorIterator
     */
    public static function createRecursiveIterator($path, $flags = \RecursiveIteratorIterator::SELF_FIRST)
    {
        $directoryIterator = new \RecursiveDirectoryIterator(
            $path,
            \FilesystemIterator::CURRENT_AS_FILEINFO
            | \FilesystemIterator::SKIP_DOTS
            | \FilesystemIterator::UNIX_PATHS
        );

        return new \RecursiveIteratorIterator(
            $directoryIterator,
            $flags
        );
    }

    /**
     * Check whether a directory is empty
     *
     * @param string $path path to the directory
     * @return bool
     */
    public static function isDirectoryEmpty($path)
    {
        $isEmpty = true;
        
        $handle = opendir($path);
        while (false !== ($item = readdir($handle))) {
            if ('.' !== $item && '..' !== $item) {
                $isEmpty = false;
                break;
            }
        }
        closedir($handle);

        return $isEmpty;
    }

    /**
     * Recursively verify privileges for the given directory and all its contents
     *
     * @param string     $path         path to the directory
     * @param bool       $checkWrite   test write access as well 1/0 (false = test only read access)
     * @param array|null &$failedPaths an array variable to put failed paths to (null = do not track)
     * @return bool
     */
    public static function checkDirectory($path, $checkWrite = true, &$failedPaths = null)
    {
        $iterator = static::createRecursiveIterator($path);

        if (null !== $failedPaths) {
            $failedPaths = array();
        }

        foreach ($iterator as $item) {
            /* @var $item \SplFileInfo */
            if (!$item->isReadable() || ($checkWrite && !$item->isWritable())) {
                if (null !== $failedPaths) {
                    $failedPaths[] = $item->getPathname();
                } else {
                    return false;
                }
            }
        }

        return empty($failedPaths);
    }

    /**
     * Recursively calculate size of a directory
     *
     * @param string $path path to the directory
     * @return int total size in bytes
     */
    public static function getDirectorySize($path)
    {
        $totalSize = 0;

        foreach (static::createRecursiveIterator($path) as $item) {
            /* @var $item \SplFileInfo */
            if ($item->isFile()) {
                $totalSize += $item->getSize();
            }
        }

        return $totalSize;
    }

    /**
     * Recursively purge a directory
     *
     * Available options:
     * ------------------
     * keep_dir (0)         keep the empty directory (remove children only) 1/0
     * files_only (0)       remove files only (keep directory structure) 1/0
     * file_callback (-)    callback(\SplFileInfo file): bool - decide, whether to remove a file or not
     *                      (this option is active only if files_only = 1)
     *
     * @param string $path        path to the directory
     * @param array  $options     option array (see above)
     * @param string &$failedPath variable that will contain a path that could not be removed
     * @return bool
     */
    public static function purgeDirectory($path, array $options = array(), &$failedPath = null)
    {
        $options += array(
            'keep_dir' => false,
            'files_only' => false,
            'file_callback' => null,
        );

        // create iterator
        $iterator = static::createRecursiveIterator($path, \RecursiveIteratorIterator::CHILD_FIRST);

        // remove children
        $success = true;
        foreach ($iterator as $item) {
            /* @var $item \SplFileInfo */
            if ($item->isDir()) {
                if (!$options['files_only'] && !@rmdir($item)) {
                    $failedPath = $item->getPathname();
                    $success = false;
                    break;
                }
            } elseif (
                (
                    !$options['files_only']
                    || null === $options['file_callback']
                    || call_user_func($options['file_callback'], $item)
                )
                && !@unlink($item)
            ) {
                $failedPath = $item->getPathname();
                $success = false;
                break;
            }
        }

        // remove directory
        if ($success && !$options['keep_dir']) {
            if (!@rmdir($path)) {
                $success = false;
                $failedPath = $path;
            }
        }

        return $success;
    }
}
