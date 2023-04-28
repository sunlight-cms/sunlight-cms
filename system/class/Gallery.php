<?php

namespace Sunlight;

use Kuria\Url\Url;
use Sunlight\Image\ImageException;
use Sunlight\Image\ImageLoader;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageStorage;
use Sunlight\Image\ImageTransformer;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\UrlHelper;

class Gallery
{
    /**
     * Render a gallery image
     *
     * @param array $img image data
     * @param string|null $lightboxid lightbox group ID, if any
     * @param array $resizeOptions {@see ImageTransformer::resize()}
     */
    static function renderImage(array $img, ?string $lightboxid, array $resizeOptions): string
    {
        if (UrlHelper::isAbsolute($img['full'])) {
            $fullUrl = $img['full'];
            $fullFile = null;
        } else {
            $fullUrl = Router::path($img['full']);
            $fullFile = SL_ROOT . $img['full'];
        }

        if (!empty($img['prev'])) {
            if (UrlHelper::isAbsolute($img['prev'])) {
                $prevUrl = $img['prev'];
            } else {
                $prevUrl = Router::path($img['prev']);
            }
        } elseif ($fullFile !== null) {
            $prevUrl = Router::file(ImageService::getThumbnail('gallery', $fullFile, $resizeOptions));
        } else {
            $prevUrl = $fullUrl;
        }

        if ($img['title']) {
            $alt = $img['title'];
        } elseif ($fullFile) {
            $alt = basename($fullFile);
        } else {
            $alt = basename(Url::parse($fullUrl)->getPath());
        }

        $image = Extend::buffer('gallery.image.render', [
            'image' => $img,
            'full_url' => $fullUrl,
            'full_file' => $fullFile,
            'prev_url' => $prevUrl,
            'alt' => $alt,
            'lightbox_id' => $lightboxid
        ]);

        if ($image === '') {
            $image = '<a'
                . ' href="' . _e($fullUrl) . '" target="_blank"'
                . (isset($lightboxid) ? Extend::buffer('image.lightbox', ['group' => 'gal_' . $lightboxid]) : '')
                . (($img['title']) ? ' title="' . _e($img['title']) . '"' : '')
                . '>'
                . '<img'
                . ' src="' . _e($prevUrl) . '"'
                . ' alt="' . _e($alt) . '"'
                . '>'
                . "</a>\n";
        }

        return $image;
    }

    /**
     * Upload a gallery image
     *
     * Returns image web path or NULL on failure.
     */
    static function uploadImage(
        string $source,
        string $originalFilename,
        string $storageDir,
        ?ImageException &$exception
    ): ?string {
        $uid = StringGenerator::generateUniqueHash();
        $format = ImageLoader::getFormat($originalFilename);

        return ImageService::process(
            'gallery',
            $source,
            ImageStorage::getPath($storageDir, $uid, $format),
            [
                'resize' => [
                    'mode' => ImageTransformer::RESIZE_FIT,
                    'keep_smaller' => true,
                    'w' => Settings::get('galuploadresize_w'),
                    'h' => Settings::get('galuploadresize_h'),
                ],
                'write' => ['jpg_quality' => 95],
                'format' => $format,
            ],
            $exception
        )
            ? ImageStorage::getWebPath($storageDir, $uid, $format)
            : null;
    }
}
