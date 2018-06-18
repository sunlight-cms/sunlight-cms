<?php

namespace Sunlight\Util;

/**
 * Zip archive helper
 */
abstract class Zip
{
    /** Path mode - full paths */
    const PATH_FULL = 0;
    /** Path mode - subpaths */
    const PATH_SUB = 1;
    /** Path mode - none (files only, no directories) */
    const PATH_NONE = 2;
    /** Default big file treshold */
    const DEFAULT_BIG_FILE_THRESHOLD = 500000;

    /**
     * Extract a single file
     *
     * @param \ZipArchive $zip
     * @param string      $archivePath
     * @param string      $targetPath
     * @param int|null    $bigFileThreshold
     * @throws \InvalidArgumentException if archive path is not valid
     * @return bool
     */
    static function extractFile(\ZipArchive $zip, $archivePath, $targetPath, $bigFileThreshold = null)
    {
        $stat = $zip->statName($archivePath);

        if ($stat === false) {
            throw new \InvalidArgumentException(sprintf('Entry "%s" was not found in the archive', $archivePath));
        }

        return static::extractFileEntry($zip, $stat, $targetPath, $bigFileThreshold);
    }

    /**
     * Extract a single entry
     *
     * @param \ZipArchive $zip
     * @param array       $stat
     * @param string      $targetPath
     * @param int|null    $bigFileThreshold
     * @return bool
     */
    static function extractFileEntry(\ZipArchive $zip, array $stat, $targetPath, $bigFileThreshold = null)
    {
        if (substr($stat['name'], -1) === '/') {
            // empty dir
            return false;
        }

        if ($stat['size'] > ($bigFileThreshold ?: static::DEFAULT_BIG_FILE_THRESHOLD)) {
            // extract big files using streams
            // (this is slower but also less memory intensive)
            $source = $zip->getStream($stat['name']);
            if ($source === false) {
                throw new \InvalidArgumentException(sprintf('Could not get stream for "%s"', $stat['name']));
            }

            $targetPath = fopen($targetPath, 'w');

            $bytesWritten = stream_copy_to_stream($source, $targetPath);

            fclose($source);
            fclose($targetPath);

            return $bytesWritten = $stat['size'];
        } else {
            // extract small files by getting all the data at once
            $data = $zip->getFromIndex($stat['index']);
            if ($data === false) {
                throw new \InvalidArgumentException(sprintf('Could not get data for "%s"', $stat['name']));
            }

            return file_put_contents($targetPath, $data) === $stat['size'];
        }
    }

    /**
     * Extract one or more paths from an archive
     *
     * Supported $options:
     * ==========================================================
     * path_mode (PATH_FULL)    (see Zip::PATH_* constants)
     * dir_mode (0777)          mode of newly created directories
     * recursive (1)            extract subdirectories 1/0
     * exclude_prefix (-)       a common prefix to exclude from subpaths (e.g. "foo/")
     *                          (the trailing slash is important)
     * big_file_threshold (-)
     *
     * @param \ZipArchive     $zip
     * @param string[]|string $directories archive directory paths (e.g. "foo", "foo/bar" or "" for root)
     * @param string          $targetPath  path where to extract the files to
     * @param array           $options
     */
    static function extractDirectories(\ZipArchive $zip, $directories, $targetPath, array $options = array())
    {
        $options += array(
            'path_mode' => static::PATH_FULL,
            'dir_mode' => 0777,
            'recursive' => true,
            'exclude_prefix' => null,
            'big_file_threshold' => null,
        );

        if ($options['big_file_threshold'] === null && ($availMem = Environment::getAvailableMemory()) !== null) {
            $options['big_file_threshold'] = (int) ($availMem * 0.75);
        }

        $targetPath = realpath($targetPath);

        if ($targetPath === false) {
            throw new \InvalidArgumentException('Target path does not exist or is inaccessible');
        }

        $excludePrefixLen = $options['exclude_prefix'] !== null
            ? strlen($options['exclude_prefix'])
            : 0;

        // build archive path prefix map
        $archivePathPrefixMap = array();
        foreach ((array) $directories as $archivePath) {
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
                            $targetDir .= $subpath;
                        }

                        // create target directory
                        if (!is_dir($targetDir)) {
                            if (is_file($targetDir)) {
                                unlink($targetDir);
                            }

                            mkdir($targetDir, $options['dir_mode'], true);
                        }

                        // extract the file
                        static::extractFileEntry($zip, $stat, $targetDir . $fileName, $options['big_file_threshold']);
                    }
                }
            }
        }
    }

    /**
     * Determine a subpath
     *
     * If a string is returned it will always begin with a slash.
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
        // determine subpath
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

        // exclude prefix
        if (
            $subpath !== null
            && $excludePrefix !== null
            && strncmp($excludePrefix, $subpath, $excludePrefixLen) === 0
        ) {
            $subpath = substr($subpath, $excludePrefixLen);
        }

        // normalize the value
        if ($subpath === '') {
            $subpath = null;
        }

        if ($subpath !== null && $subpath[0] !== '/') {
            $subpath = "/{$subpath}";
        }

        return $subpath;
    }
}
