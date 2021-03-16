<?php

namespace Sunlight\Util;

/**
 * Filesystem utilities
 */
abstract class Filesystem
{
    static $unsafeExtRegex = '{(php\d*?|[ps]html|asp|py|cgi|htaccess)}Ai';

    /**
     * Vytvorit docasny soubor v system/tmp
     *
     * @return TemporaryFile
     */
    static function createTmpFile(): TemporaryFile
    {
        return new TemporaryFile(null, _root . 'system/tmp');
    }

    /**
     * Zjistit, zda je nazev souboru bezpecny
     *
     * @param string $filepath nazev souboru
     * @return bool
     */
    static function isSafeFile(string $filepath): bool
    {
        $parts = explode('.', basename(trim($filepath)));

        // check all extensions since some webservers will evaluate files such as "example.php.html"
        for ($i = 1; isset($parts[$i]); ++$i) {
            if (preg_match(self::$unsafeExtRegex, $parts[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ujistit se, ze existuje dany soubor
     *
     * @param string $filepath
     * @throws \RuntimeException pokud soubor neexistuje
     */
    static function ensureFileExists(string $filepath): void
    {
        if (!is_file($filepath)) {
            throw new \RuntimeException(sprintf('File "%s" does not exist', $filepath));
        }
    }

    /**
     * Normalize a path
     *
     * @param string $path
     * @return string
     */
    static function normalizePath(string $path): string
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
    static function normalizeWithBasePath(string $basePath, string $path): string
    {
        $basePath = self::normalizePath($basePath);
        $path = self::normalizePath($path);

        return
            $path === ''
                ? $basePath
                : (self::isAbsolutePath($path)
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
    static function isAbsolutePath(string $path): bool
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
    static function parsePath(string $path, bool $isFile = false, bool $allowLeadingSlash = false): string
    {
        $segments = explode('/', self::normalizePath($path));
        $parentJumps = 0;

        for ($i = count($segments) - 1; $i >= 0; --$i) {
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
     * Create directory iterator
     *
     * @param string $path
     * @return \FilesystemIterator
     */
    static function createIterator(string $path): \FilesystemIterator
    {
        return new \FilesystemIterator(
            $path,
            \FilesystemIterator::CURRENT_AS_FILEINFO
            | \FilesystemIterator::SKIP_DOTS
            | \FilesystemIterator::UNIX_PATHS
        );
    }
    
    /**
     * Create recursive directory iterator
     *
     * @param string $path
     * @param int    $flags
     * @return \RecursiveIteratorIterator
     */
    static function createRecursiveIterator(string $path, int $flags = \RecursiveIteratorIterator::SELF_FIRST): \RecursiveIteratorIterator
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
    static function isDirectoryEmpty(string $path): bool
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
    static function checkDirectory(string $path, bool $checkWrite = true, ?array &$failedPaths = null): bool
    {
        $iterator = self::createRecursiveIterator($path);

        if ($failedPaths !== null) {
            $failedPaths = [];
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
    static function getDirectorySize(string $path): int
    {
        $totalSize = 0;

        foreach (self::createRecursiveIterator($path) as $item) {
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
     * @param string      $path       path to the directory
     * @param array       $options    option array (see above)
     * @param string|null $failedPath variable that will contain a path that could not be removed
     * @return bool
     */
    static function purgeDirectory(string $path, array $options = [], ?string &$failedPath = null): bool
    {
        $options += [
            'keep_dir' => false,
            'files_only' => false,
            'file_callback' => null,
        ];

        // create iterator
        $iterator = self::createRecursiveIterator($path, \RecursiveIteratorIterator::CHILD_FIRST);

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
    static function denyAccessToDirectory(string $path): void
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
