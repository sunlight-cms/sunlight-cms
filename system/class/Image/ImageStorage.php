<?php

namespace Sunlight\Image;

final class ImageStorage
{
    /**
     * Get path to an image
     *
     * @param string $directory path to the storage directory (relative to _root), including a trailing slash
     * @param string $id image identifier
     * @param string $format image format
     * @param int $partitions number of 2-character sub-directories to create from $id
     * @return string
     */
    static function getPath(string $directory, string $id, string $format, int $partitions = 0): string
    {
        return _root . self::getWebPath($directory, $id, $format, $partitions);
    }

    /**
     * Get web path to an image
     *
     * @param string $directory path to the storage directory (relative to _root), including a trailing slash
     * @param string $id image identifier
     * @param string $format image format
     * @param int $partitions number of 2-character sub-directories to create from $id
     * @return string
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
