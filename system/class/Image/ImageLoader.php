<?php

namespace Sunlight\Image;

use Sunlight\Core;
use Sunlight\GenericTemplates;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;

final class ImageLoader
{
    /**
     * Determine image format from a path
     */
    static function getFormat(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * Load image from a path
     *
     * Supported $limits:
     * --------------------------------------------------------------------
     * filesize     max file size in bytes
     * dimensions   array{w: int, h: int}
     * memory       max percentage of remaining memory used (default: 0.75)
     *
     * @throws ImageException
     */
    static function load(string $path, array $limits = [], ?string $format = null): Image
    {
        $limits += ['filesize' => null, 'dimensions' => null, 'memory' => 0.75];

        if ($format === null) {
            $format = self::getFormat($path);
        }

        // check format
        if (!ImageFormat::canRead($format)) {
            throw new ImageException(ImageException::FORMAT_NOT_SUPPORTED);
        }

        // check file
        self::checkFile($path, $format, $limits['filesize']);

        // get image information
        [$width, $height, $estimatedMemReq] = self::getInfo($path, $format);

        // check available memory
        $availMem = Environment::getAvailableMemory();

        if ($availMem !== null && $estimatedMemReq > $availMem * $limits['memory']) {
            throw new ImageException(ImageException::NOT_ENOUGH_MEMORY);
        }

        // check image dimensions
        self::checkDimensions($width, $height, $limits['dimensions']);

        // load the image
        $resource = self::createImageFromPath($path, $format);

        // get and re-check actual image dimensions (because of multi-frame images)
        $width = imagesx($resource);
        $height = imagesy($resource);

        self::checkDimensions($width, $height, $limits['dimensions']);

        // return loaded image
        return new Image($resource, $width, $height);
    }

    private static function checkFile(string $path, string $format, ?int $maxSize): void
    {
        if (!ImageFormat::isValidFormat($format) || !Filesystem::isSafeFile($path)) {
            throw new ImageException(ImageException::NOT_ALLOWED);
        }

        $size = @filesize($path);

        if ($size === false) {
            throw new ImageException(ImageException::NOT_FOUND);
        }

        if ($maxSize !== null && $size > $maxSize) {
            throw new ImageException(ImageException::FILE_TOO_BIG, [
                '%maxsize%' => GenericTemplates::renderFilesize($maxSize),
            ]);
        }
    }

    private static function getInfo(string $path, string $format): array
    {
        $info = getimagesize($path);

        if ($info === false || $info[0] === 0 || $info[1] === 0) {
            throw new ImageException(ImageException::COULD_NOT_GET_SIZE);
        }

        $width = $info[0];
        $height = $info[1];
        $channels = $info['channels'] ?? ($format === ImageFormat::PNG ? 4 : 3);
        $bits = $info['bits'] ?? 8;

        return [
            $width,
            $height,
            ceil(($width * $height * $bits * $channels / 8 + 65536) * 1.65),
        ];
    }

    private static function checkDimensions(int $width, int $height, ?array $limit): void
    {
        if (
            $limit !== null
            && (
                $width > $limit['w']
                || $height > $limit['h']
            )
        ) {
            throw new ImageException(ImageException::IMAGE_TOO_BIG, [
                '%maxw%' => $limit['w'],
                '%maxh%' => $limit['h'],
            ]);
        }
    }

    /**
     * @return \GdImage|resource
     */
    private static function createImageFromPath(string $path, string $format)
    {
        $resource = false;
        error_clear_last();

        switch ($format) {
            case ImageFormat::JPG:
            case ImageFormat::JPEG:
                $resource = @imagecreatefromjpeg($path);
                break;

            case ImageFormat::PNG:
                $resource = @imagecreatefrompng($path);
                break;

            case ImageFormat::GIF:
                $resource = @imagecreatefromgif($path);
                break;

            case ImageFormat::WEBP:
                $resource = @imagecreatefromwebp($path);
                break;
        }

        if ($resource === false) {
            throw new ImageException(ImageException::COULD_NOT_LOAD, null, error_get_last()['message'] ?? null);
        }

        if ($format === ImageFormat::GIF) {
            imagepalettetotruecolor($resource);
        }

        return $resource;
    }
}
