<?php

namespace Sunlight;

use Sunlight\Util\Url;
use Sunlight\Util\UrlHelper;

class Gallery
{
    /**
     * Sestavit kod obrazku v galerii
     *
     * @param array       $img        pole s daty obrazku
     * @param string|null $lightboxid sid lightboxu nebo null (= nepouzivat)
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
            $prevUrl = Router::file(Picture::getThumbnail($fullFile, ['x' => $width, 'y' => $height]));
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
            . (isset($lightboxid) ? ' class="lightbox" data-gallery-group="lb_' . _e($lightboxid) . '"' : '')
            . (($img['title']) ? ' title="' . _e($img['title']) . '"' : '')
            . '>'
            . '<img'
            . ' src="' . _e($prevUrl) . '"'
            . ' alt="' . _e($alt) . '"'
            . '>'
            . "</a>\n";
    }
}
