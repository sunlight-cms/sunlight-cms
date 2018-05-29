<?php

namespace Sunlight\Util;

/**
 * Filesystem utilities
 */
abstract class Filesystem
{
    /**
     * Normalize a path
     *
     * @param string $path
     * @return string
     */
    static function normalizePath($path)
    {
        return strtr($path, '\\', '/');
    }

    /**
     * Normalize a path and add a base, if the path is not absolute
     *
     * @param string $basePath
     * @param string $path
     * @return string
     */
    static function normalizeWithBasePath($basePath, $path)
    {
        $basePath = static::normalizePath($basePath);
        $path = static::normalizePath($path);

        return
            $path === ''
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
    static function isAbsolutePath($path)
    {
        return
            isset($path[0]) && ($path[0] === '/' || $path[0] === '\\')
            || isset($path[1]) && $path[1] === ':';
    }

    /**
     * Evaluate relative parts of a path
     *
     * The returned path will have a trailing slash if $isFile = FALSE.
     *
     * The returned path may have a leading slash if $allowLeadingSlash = TRUE.
     *
     * @param string $path              the path
     * @param bool   $isFile            it is a file path 1/0
     * @param bool   $allowLeadingSlash allow slash at the beginning of the resulting path
     * @return string
     */
    static function parsePath($path, $isFile = false, $allowLeadingSlash = false)
    {
        $segments = explode('/', static::normalizePath($path));
        $parentJumps = 0;

        for ($i = sizeof($segments) - 1; $i >= 0; --$i) {
            $isCurrent = $segments[$i] === '.';
            $isParent = $segments[$i] === '..';
            $isEmpty = $segments[$i] === '';
            $isDot = $isCurrent || $isParent;

            if ($isParent) {
                ++$parentJumps;
            }
            if ($isDot || $isEmpty && (!$allowLeadingSlash || $i > 0) || $parentJumps > 0) {
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

        if ($result === '') {
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
    static function createRecursiveIterator($path, $flags = \RecursiveIteratorIterator::SELF_FIRST)
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
    static function isDirectoryEmpty($path)
    {
        $isEmpty = true;
        
        $handle = opendir($path);
        while (($item = readdir($handle)) !== false) {
            if ($item !== '.' && $item !== '..') {
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
     * @param array|null $failedPaths  an array variable to put failed paths to (null = do not track)
     * @return bool
     */
    static function checkDirectory($path, $checkWrite = true, &$failedPaths = null)
    {
        $iterator = static::createRecursiveIterator($path);

        if ($failedPaths !== null) {
            $failedPaths = array();
        }

        foreach ($iterator as $item) {
            /* @var $item \SplFileInfo */
            if (!$item->isReadable() || ($checkWrite && !$item->isWritable())) {
                if ($failedPaths !== null) {
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
    static function getDirectorySize($path)
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
     * @param string $path       path to the directory
     * @param array  $options    option array (see above)
     * @param string $failedPath variable that will contain a path that could not be removed
     * @return bool
     */
    static function purgeDirectory($path, array $options = array(), &$failedPath = null)
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
                    || $options['file_callback'] === null
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

    /**
     * Deny access to a directory using a .htaccess file
     *
     * @param string $path
     */
    static function denyAccessToDirectory($path)
    {
        file_put_contents($path . '/.htaccess', <<<HTACCESS
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>

HTACCESS
);
    }
}
