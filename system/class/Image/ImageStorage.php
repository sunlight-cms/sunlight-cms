<?php

namespace Sunlight\Image;

final class ImageStorage
{
    /**
     * Get path to an image
     *
     * @param string $directory path to the storage directory (relative to SL_ROOT), including a trailing slash
     * @param string $id image identifier
     * @param string $format image format
     * @param int $partitions number of 2-character sub-directories to create from $id
     */
    static function getPath(string $directory, string $id, string $format, int $partitions = 0): string
    {
        return SL_ROOT . self::getWebPath($directory, $id, $format, $partitions);
    }

    /**
     * Get web path to an image
     *
     * @param string $directory path to the storage directory (relative to SL_ROOT), including a trailing slash
     * @param string $id image identifier
     * @param string $format image format
     * @param int $partitions number of 2-character sub-directories to create from $id
     */
    static function getWebPath(string $directory, string $id, string $format, int $partitions = 0): string
    {
        $path = $directory;

        for ($i = 0; $i < $partitions; ++$i) {
            $path .= mb_substr($id, $i * 2, 2) . '/';
        }

        $path .= $id . '.' . $format;

        return $path;
    }
}
