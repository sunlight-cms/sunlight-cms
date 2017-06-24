<?php

namespace Sunlight\Util;

use ZipArchive;

/**
 * Zip archive helper
 */
class Zip
{
    /** Path mode - full paths */
    const PATH_FULL = 0;
    /** Path mode - subpaths */
    const PATH_SUB = 1;
    /** Path mode - none (files only, no directories) */
    const PATH_NONE = 2;

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Extract a single file
     *
     * @param ZipArchive $zip
     * @param string     $archivePath
     * @param string     $targetPath
     * @throws \InvalidArgumentException if archive path is not valid
     * @return bool
     */
    public static function extract(ZipArchive $zip, $archivePath, $targetPath)
    {
        if (substr($archivePath, -1) !== '/') {
            $source = $zip->getStream($archivePath);
            if ($source === false) {
                throw new \InvalidArgumentException(sprintf('Could not get stream for "%s"', $archivePath));
            }

            $targetPath = fopen($targetPath, 'w');

            stream_copy_to_stream($source, $targetPath);

            fclose($source);
            fclose($targetPath);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Extract one or more paths from an archive
     *
     * Supported $options:
     * ==========================================================
     * path_mode (PATH_FULL)    see ZipHelper::PATH_* constants
     * dir_mode (0777)          mode of newly created directories
     * recursive (1)            extract subdirectories 1/0
     * exclude_prefix (-)       a common prefix to exclude from subpaths (e.g. "foo/")
     *                          (the trailing slash is important)
     *
     * @param ZipArchive      $zip
     * @param string[]|string $archivePaths archive directory paths (e.g. "foo", "foo/bar" or "" for root)
     * @param string          $targetPath   path where to extract the files to
     * @param array           $options
     */
    public static function extractPaths(ZipArchive $zip, $archivePaths, $targetPath, array $options = array())
    {
        $options += array(
            'path_mode' => static::PATH_FULL,
            'dir_mode' => 0777,
            'recursive' => true,
            'exclude_prefix' => null,
        );

        $excludePrefixLen = $options['exclude_prefix'] !== null
            ? strlen($options['exclude_prefix'])
            : 0;

        // build archive path prefix map
        $archivePathPrefixMap = array();
        foreach ((array) $archivePaths as $archivePath) {
            if ($archivePath !== '') {
                $archivePathPrefix = "{$archivePath}/";
                $archivePathPrefixMap[$archivePathPrefix] = strlen($archivePathPrefix);
            } else {
                $archivePathPrefixMap[''] = 0;
            }
        }

        // iterate archive files
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);

            foreach ($archivePathPrefixMap as $archivePathPrefix => $archivePathPrefixLen) {
                if (
                    $archivePathPrefixLen === 0
                    || strncmp($archivePathPrefix, $stat['name'], $archivePathPrefixLen) === 0
                ) {
                    $lastSlashPos = strrpos($stat['name'], '/');
                    if ($lastSlashPos === false || $options['recursive'] || $lastSlashPos === $archivePathPrefixLen - 1) {
                        // parse current item
                        $fileName = $lastSlashPos !== false ? substr($stat['name'], $lastSlashPos) : $stat['name'];
                        $subpath = static::getSubpath($options['path_mode'], $stat['name'], $lastSlashPos, $archivePathPrefixLen, $options['exclude_prefix'], $excludePrefixLen);

                        // determine target directory
                        $targetDir = $targetPath;
                        if ($subpath !== null) {
                            $targetDir .= "/{$subpath}";
                        }

                        // create target directory
                        if (!is_dir($targetDir)) {
                            if (is_file($targetDir)) {
                                unlink($targetDir);
                            }

                            mkdir($targetDir, $options['dir_mode'], true);
                        }

                        // extract the file
                        static::extract($zip, $stat['name'], "{$targetDir}/{$fileName}");
                    }
                }
            }
        }
    }

    /**
     * Determine a subpath
     *
     * @param int         $mode
     * @param string      $path
     * @param int|bool    $lastSlashPos
     * @param int         $prefixLen
     * @param string|null $excludePrefix
     * @param int         $excludePrefixLen
     * @throws \InvalidArgumentException if the mode is invalid
     * @return string|null
     */
    protected static function getSubpath($mode, $path, $lastSlashPos, $prefixLen, $excludePrefix, $excludePrefixLen)
    {
        switch ($mode) {
            case static::PATH_FULL:
                $subpath = $lastSlashPos !== false
                    ? substr($path, 0, $lastSlashPos)
                    : null;
                break;
            case static::PATH_SUB:
                $subpath = $lastSlashPos !== false && $lastSlashPos > $prefixLen
                    ? substr($path, $prefixLen, $lastSlashPos - $prefixLen)
                    : null;
                break;
            case static::PATH_NONE:
                $subpath = null;
                break;
            default:
                throw new \InvalidArgumentException('Invalid mode');
        }

        if (
            $subpath !== null
            && $excludePrefix !== null
            && strncmp($excludePrefix, $subpath, $excludePrefixLen) === 0
        ) {
            $subpath = substr($subpath, $excludePrefixLen);

            if ($subpath === '') {
                $subpath = null;
            }
        }

        return $subpath;
    }
}
