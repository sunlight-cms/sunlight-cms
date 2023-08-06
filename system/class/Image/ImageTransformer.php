<?php

namespace Sunlight\Image;

use Sunlight\Extend;
use Sunlight\Util\Color;

final class ImageTransformer
{
    const RESIZE_NONE = 'none';
    const RESIZE_FILL = 'fill';
    const RESIZE_FIT = 'fit';
    const RESIZE_FIT_X = 'fit-x';
    const RESIZE_FIT_Y = 'fit-y';
    const ALIGN_LOW = -1;
    const ALIGN_CENTER = 0;
    const ALIGN_HIGH = 1;

    private const PARSE_KEYWORDS = [
        self::RESIZE_FILL => ['mode' => self::RESIZE_FILL],
        self::RESIZE_FIT => ['mode' => self::RESIZE_FIT],
        self::RESIZE_FIT_X => ['mode' => self::RESIZE_FIT_X],
        self::RESIZE_FIT_Y => ['mode' => self::RESIZE_FIT_Y],
        'keep' => ['keep_smaller' => true],
        'pad' => ['pad' => true],
        'top-left' => ['align-x' => self::ALIGN_LOW, 'align-y' => self::ALIGN_LOW],
        'top' => ['align-x' => self::ALIGN_CENTER, 'align-y' => self::ALIGN_LOW],
        'top-right' => ['align-x' => self::ALIGN_HIGH, 'align-y' => self::ALIGN_LOW],
        'left' => ['align-x' => self::ALIGN_LOW, 'align-y' => self::ALIGN_CENTER],
        'center' => ['align-x' => self::ALIGN_CENTER, 'align-y' => self::ALIGN_CENTER],
        'right' => ['align-x' => self::ALIGN_HIGH, 'align-y' => self::ALIGN_CENTER],
        'bottom-left' => ['align-x' => self::ALIGN_LOW, 'align-y' => self::ALIGN_HIGH],
        'bottom' => ['align-x' => self::ALIGN_CENTER, 'align-y' => self::ALIGN_HIGH],
        'bottom-right' => ['align-x' => self::ALIGN_HIGH, 'align-y' => self::ALIGN_HIGH],
    ];

    /**
     * Resize image using the given options
     *
     * Supported $options:
     * ------------------
     * w                    target width (optional if "h" is specified)
     * h                    target height (optional if "w" is specified)
     * mode (fill)          resize mode (see RESIZE_* class constants)
     * keep_smaller (false) don't enlarge smaller images
     * bgcolor (null)       static bg color as array{r, g, b} (ignored if trans = TRUE)
     * pad (false)          keep extra space instead of cropping (only in "fit" modes)
     * align-x (0)          horizontal alignment for cropping and padding, see ALIGN_* class constants
     * align-y (0)          vertical alignment for cropping and padding, see ALIGN_* class constants
     * trans (false)        enable transparency
     *
     * @param array{
     *     w?: int|null,
     *     h?: int|null,
     *     mode?: string|null,
     *     keep_smaller?: bool,
     *     bgcolor?: array{r: int, g: int, b: int}|null,
     *     pad?: bool,
     *     "align-x"?: int,
     *     "align-y"?: int,
     *     trans?: bool,
     * } $options see description
     * @throws ImageException
     */
    static function resize(Image $image, array $options): Image
    {
        $options += [
            'w' => null,
            'h' => null,
            'mode' => null,
            'keep_smaller' => false,
            'bgcolor' => null,
            'pad' => false,
            'align-x' => self::ALIGN_CENTER,
            'align-y' => self::ALIGN_CENTER,
            'trans' => false,
        ];

        // treat zero width or height as NULL
        if ($options['w'] === 0) {
            $options['h'] = null;
        }

        if ($options['w'] === 0) {
            $options['h'] = null;
        }

        // event
        $extendOutput = null;

        Extend::call('image.resize', [
            'image' => $image,
            'options' => &$options,
            'output' => &$extendOutput,
        ]);

        if ($extendOutput !== null) {
            return $extendOutput;
        }

        // noop?
        if ($options['mode'] === self::RESIZE_NONE || $options['w'] === null && $options['h'] === null) {
            return $image;
        }

        // verify dimensions
        if ($options['w'] !== null && $options['w'] < 0 || $options['h'] !== null && $options['h'] < 0) {
            throw new ImageException(ImageException::INVALID_DIMENSIONS);
        }

        // calculate unspecified dimension
        if ($options['w'] === null) {
            $options['w'] = max(self::intRound($image->width / $image->height * $options['h']), 1);
        } elseif ($options['h'] === null) {
            $options['h'] = max(self::intRound($options['w'] / ($image->width / $image->height)), 1);
        }

        // keep smaller or identical image
        if (
            $options['keep_smaller'] && $image->width < $options['w'] && $image->height < $options['h']
            || $image->width === $options['w'] && $image->height === $options['h']
        ) {
            return $image;
        }

        // resample
        return self::resample($image, $options);
    }

    /**
     * Parse resize options from a string
     *
     * {@see ImageTransformer::resize()}
     *
     * Input is a parameter list separated by forward slashes.
     *
     * Supported parameters:
     *
     *      NxN             width and height (one dimension may be "?" for automatic calculation)
     *      fill            set mode to fill
     *      fit             set mode to fit
     *      fit-x           set mode to fit-x
     *      fit-y           set mode to fit-y
     *      keep            keep smaller images
     *      pad             keep extra space instead of cropping (only for "fit" modes)
     *      #xxxxxx         background color (disables transparency)
     *      #xxx            background color - shorthand syntax (disables transparency)
     *      top-left        vertical align = top, horizontal align = left
     *      top             vertical align = top, horizontal align = center
     *      top-right       vertical align = top, horizontal align = right
     *      left            vertical align = center, horizontal align = left
     *      center          vertical align = center, horizontal align = center
     *      right           vertical align = center, horizontal align = right
     *      bottom-left     vertical align = bottom, horizontal align = left
     *      bottom          vertical align = bottom, horizontal align = center
     *      bottom-right    vertical align = bottom, horizontal align = right
     *
     * Examples:
     *
     *      128x96
     *      128x?
     *      640x480/fill
     *      320x?/fit/pad/top/#fff
     */
    static function parseResizeOptions(string $input, array $defaults = []): array
    {
        $opts = $defaults + [
            'w' => 96,
            'mode' => self::RESIZE_FILL,
            'trans' => true,
        ];

        foreach (explode('/', $input) as $part) {
            if (isset(self::PARSE_KEYWORDS[$part])) {
                foreach (self::PARSE_KEYWORDS[$part] as $option => $value) {
                    $opts[$option] = $value;
                }
            } elseif ($part !== '') {
                if ($part[0] === '#') {
                    // bg color
                    $bgColor = Color::fromString($part);

                    if ($bgColor !== null) {
                        $opts['bgcolor'] = $bgColor->getRgb();
                        $opts['trans'] = false;
                    }
                } elseif (preg_match('{(\d++|\?)x(\d++|\?)$}AD', $part, $match)) {
                    // size
                    $opts['w'] = $match[1] === '?' ? null : (int) $match[1];
                    $opts['h'] = $match[2] === '?' ? null : (int) $match[2];
                }
            }
        }

        return $opts;
    }

    private static function resample(Image $image, array $options): Image
    {
        // calculate scale
        $outputW = $options['w'];
        $outputH = $options['h'];

        switch ($options['mode']) {
            case self::RESIZE_FILL:
                $scale = max(1 / $image->width * $outputW, 1 / $image->height * $outputH);
                break;

            case self::RESIZE_FIT:
                $scale = min(1 / $image->width * $outputW, 1 / $image->height * $outputH);
                break;

            case self::RESIZE_FIT_X:
                $scale = 1 / $image->width * $outputW;
                break;

            case self::RESIZE_FIT_Y:
                $scale = 1 / $image->height * $outputH;
                break;

            default:
                throw new ImageException(ImageException::INVALID_RESIZE_MODE);
        }

        // calculate dimensions
        $sourceX = 0;
        $sourceY = 0;
        $sourceW = $image->width;
        $sourceH = $image->height;
        $targetX = 0;
        $targetY = 0;
        $targetW = self::intRound($image->width * $scale);
        $targetH = self::intRound($image->height * $scale);

        self::adjustDimensions($sourceX, $sourceW, $targetX, $targetW, $outputW, $scale, $options['pad'], $options['align-x']);
        self::adjustDimensions($sourceY, $sourceH, $targetY, $targetH, $outputH, $scale, $options['pad'], $options['align-y']);

        // create output image
        $output = Image::blank($outputW, $outputH);

        // enable transparency
        if ($options['trans']) {
            $output->enableAlpha();
        } elseif ($options['bgcolor'] !== null) {
            imagefilter($output->resource, IMG_FILTER_COLORIZE, $options['bgcolor'][0], $options['bgcolor'][1], $options['bgcolor'][2]);
        }

        // resample
        error_clear_last();

        if (!@imagecopyresampled($output->resource, $image->resource, $targetX, $targetY, $sourceX, $sourceY, $targetW, $targetH, $sourceW, $sourceH)) {
            throw new ImageException(ImageException::RESIZE_FAILED, null, error_get_last()['message'] ?? null);
        }

        return $output;
    }

    private static function adjustDimensions(
        int &$sourceCoord,
        int &$sourceSize,
        int &$targetCoord,
        int &$targetSize,
        int &$outputSize,
        float $scale,
        bool $pad,
        int $align
    ): void {
        if ($targetSize > $outputSize) {
            // size too big - crop
            $sourceOffset = self::intRound(($targetSize - $outputSize) / $scale);
            $sourceSize -= $sourceOffset;
            $targetSize = $outputSize;

            switch ($align) {
                case self::ALIGN_LOW: $sourceCoord = 0; break;
                case self::ALIGN_CENTER: $sourceCoord = self::intRound($sourceOffset / 2); break;
                case self::ALIGN_HIGH: $sourceCoord = $sourceOffset; break;
                default: throw new ImageException(ImageException::INVALID_ALIGN);
            }
        } elseif ($targetSize < $outputSize) {
            // size too small
            if ($pad) {
                // pad with empty space
                $offset = $outputSize - $targetSize;

                switch ($align) {
                    case self::ALIGN_LOW: $targetCoord = 0; break;
                    case self::ALIGN_CENTER: $targetCoord = self::intRound($offset / 2); break;
                    case self::ALIGN_HIGH: $targetCoord = $offset; break;
                    default: throw new ImageException(ImageException::INVALID_ALIGN);
                }
            } else {
                // shrink output
                $outputSize = $targetSize;
            }
        }
    }

    private static function intRound(float $value): int
    {
        return (int) round($value);
    }
}
