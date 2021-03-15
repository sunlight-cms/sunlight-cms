<?php

namespace Sunlight;

use Sunlight\Image\ImageException;
use Sunlight\Image\ImageLoader;
use Sunlight\Image\ImageService;
use Sunlight\Image\ImageStorage;
use Sunlight\Image\ImageTransformer;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\Url;
use Sunlight\Util\UrlHelper;

class Gallery
{
    /**
     * Sestavit kod obrazku v galerii
     *
     * @param array       $img        pole s daty obrazku
     * @param string|null $lightboxid skupina lightboxu nebo null (= nepouzivat)
     * @param int|null    $width      pozadovana sirka nahledu
     * @param int|null    $height     pozadovana vyska nahledu
     * @return string
     */
    static function renderImage(array $img, ?string $lightboxid, ?int $width, ?int $height): string
    {
        if (UrlHelper::isAbsolute($img['full'])) {
            $fullUrl = $img['full'];
            $fullFile = null;
        } else {
            $fullUrl = Router::generate($img['full']);
            $fullFile = _root . $img['full'];
        }

        if (!empty($img['prev'])) {
            if (UrlHelper::isAbsolute($img['prev'])) {
                $prevUrl = $img['prev'];
            } else {
                $prevUrl = Router::generate($img['prev']);
            }
        } elseif ($fullFile !== null) {
            $prevUrl = Router::file(ImageService::getThumbnail('gallery', $fullFile, ['w' => $width, 'h' => $height]));
        } else {
            $prevUrl = $fullUrl;
        }

        if ($img['title']) {
            $alt = $img['title'];
        } elseif ($fullFile) {
            $alt = basename($fullFile);
        } else {
            $alt = basename(Url::parse($fullUrl)->path);
        }

        return '<a'
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
                    'w' => _galuploadresize_w,
                    'h' => _galuploadresize_h,
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
