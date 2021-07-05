<?php

namespace Sunlight\Image;

use Sunlight\Extend;
use Sunlight\Settings;
use Sunlight\Util\Arr;
use Sunlight\Util\Filesystem;

final class ImageService
{
    /**
     * Check if a path is an image
     */
    static function isImage(string $path): bool
    {
        return ImageFormat::isValidFormat(ImageLoader::getFormat($path));
    }

    /**
     * Generate a thumbnail
     *
     * @param string $type descriptive type (for extend event)
     * @param string $source source image path
     * @param array $resizeOptions {@see ImageTransformer::resize()}
     * @param array $writeOptions {@see Image::write()}
     * @return string
     */
    static function getThumbnail(string $type, string $source, array $resizeOptions, array $writeOptions = []): string
    {
        try {
            // prepare options
            $processOptions = [
                'resize' => $resizeOptions + [
                    'mode' => ImageTransformer::RESIZE_FILL,
                    'trans' => true,
                ],
                'write' => $writeOptions,
            ];

            Extend::call('image.thumb', [
                'type' => $type,
                'source' => $source,
                'resize_options' => &$resizeOptions,
                'write_options' => &$writeOptions,
            ]);

            // check source
            $realpath = realpath($source);
            $format = ImageLoader::getFormat($source);

            if ($realpath === false) {
                throw new ImageException(ImageException::NOT_FOUND);
            }

            if (!ImageFormat::isValidFormat($format)) {
                throw new ImageException(ImageException::FORMAT_NOT_SUPPORTED);
            }

            // determine thumbnail path
            $id = Arr::hash([realpath($source), $processOptions]);
            $thumbPath = ImageStorage::getPath('images/thumb/', $id, $format, 2);

            // use existing thumbnail
            if (file_exists($thumbPath)) {
                if (time() - filemtime($thumbPath) >= Settings::get('thumb_touch_threshold')) {
                    touch($thumbPath);
                }

                return $thumbPath;
            }

            // generate a new thumbnail
            if (!self::process(
                "{$type}.thumb",
                $source,
                $thumbPath,
                $processOptions,
                $exception
            )) {
                throw $exception;
            }

            return $thumbPath;
        } catch (ImageException $e) {
            return self::getErrorImage($e->getReasonCode());
        }
    }

    /**
     * Remove unused thumbnails
     *
     * @param int $minAge min. seconds since last modification
     */
    static function cleanThumbnails(int $minAge): void
    {
        foreach (Filesystem::createRecursiveIterator(SL_ROOT . 'images/thumb', \RecursiveIteratorIterator::LEAVES_ONLY) as $thumb) {
            if (
                self::isImage($thumb)
                && time() - $thumb->getMTime() >= $minAge
            ) {
                unlink($thumb->getPathname());
            }
        }
    }

    /**
     * Load and store an image
     *
     * Supported $options:
     * --------------------------------------------
     * limits       {@see ImageLoader::load()}
     * resize       {@see ImageTransformer::resize()}
     * write        {@see Image::write()}
     * format       source image format (otherwise determined from $source)
     *
     * @param string $type descriptive type (for extend event)
     * @param string $source source path
     * @param string $target target path
     * @param array $options see above
     * @param ImageException|null $exception set to an exception object in case of failure
     * @return bool
     */
    static function process(
        string $type,
        string $source,
        string $target,
        array $options = [],
        ?ImageException &$exception = null
    ): bool
    {
        try {
            Extend::call('image.process.before', [
                'type' => $type,
                'source' => $source,
                'options' => &$options,
            ]);

            $image = ImageLoader::load($source, $options['limits'] ?? [], $options['format'] ?? null);

            if (isset($options['resize'])) {
                $image = ImageTransformer::resize($image, $options['resize']);
            }

            Extend::call('image.process.after', [
                'type' => $type,
                'image' => &$image,
                'options' => $options,
            ]);

            $image->write($target, ImageLoader::getFormat($target), $options['write'] ?? []);

            return true;
        } catch (ImageException $exception) {
            return false;
        }
    }

    /**
     * Get error image to use as a fallback
     */
    static function getErrorImage(string $reasonCode): string
    {
        return SL_ROOT . 'system/image_error.png?r=' . rawurlencode($reasonCode);
    }
}
