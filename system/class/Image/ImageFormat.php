<?php

namespace Sunlight\Image;

final class ImageFormat
{
    const JPG = 'jpg';
    const JPEG = 'jpeg';
    const PNG = 'png';
    const GIF = 'gif';
    const WEBP = 'webp';

    private const OP_READ = 1;
    private const OP_WRITE = 2;

    private const FORMAT_MAP = [
        self::JPG => true,
        self::JPEG => true,
        self::PNG => true,
        self::GIF => true,
        self::WEBP => true,
    ];

    private const INFO_KEYS = [
        self::JPG => [
            'JPG Support' => self::OP_READ | self::OP_WRITE,
            'JPEG Support' => self::OP_READ | self::OP_WRITE,
        ],
        self::JPEG => [
            'JPG Support' => self::OP_READ | self::OP_WRITE,
            'JPEG Support' => self::OP_READ | self::OP_WRITE,
        ],
        self::PNG => [
            'PNG Support' => self::OP_READ | self::OP_WRITE,
        ],
        self::GIF => [
            'GIF Read Support' => self::OP_READ,
            'GIF Create Support' => self::OP_WRITE,
        ],
        self::WEBP => [
            'WebP Support' => self::OP_READ | self::OP_WRITE,
        ],
    ];

    private static $supportedOpCache = [];

    static function isValidFormat(string $format): bool
    {
        return isset(self::FORMAT_MAP[$format]);
    }

    static function canRead(string $format): bool
    {
        return self::isValidFormat($format)
            && (self::getSupportedOps($format) & self::OP_READ) !== 0;
    }

    static function canWrite(string $format): bool
    {
        return self::isValidFormat($format)
            && (self::getSupportedOps($format) & self::OP_WRITE) !== 0;
    }

    private static function getSupportedOps(string $format): int
    {
        if (isset(self::$supportedOpCache[$format])) {
            return self::$supportedOpCache[$format];
        }

        $supportedOps = 0;

        if (function_exists('gd_info')) {
            $info = gd_info();

            foreach (self::INFO_KEYS[$format] as $key => $op) {
                if ($info[$key] ?? false) {
                    $supportedOps |= $op;
                }
            }
        }

        return self::$supportedOpCache[$format] = $supportedOps;
    }
}
