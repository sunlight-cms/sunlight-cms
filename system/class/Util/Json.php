<?php

namespace Sunlight\Util;

/**
 * JSON helper
 */
abstract class Json
{
    /** Default encoder flags */
    const DEFAULT = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    /** Encoder flags for human-readable output */
    const PRETTY = JSON_PRETTY_PRINT | self::DEFAULT;

    /**
     * Encode data as JSON
     *
     * @throws \InvalidArgumentException in case of an error
     */
    static function encode($data, int $flags = self::DEFAULT, int $depth = 512): string
    {
        $json = json_encode($data, $flags, $depth);

        if ($json === false) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }

        return $json;
    }

    /**
     * Encode data as JSON intended to be used inside <script>
     *
     * @throws \InvalidArgumentException in case of an error
     */
    static function encodeForInlineJs($data, int $flags = self::DEFAULT, int $depth = 512): string
    {
        // https://html.spec.whatwg.org/multipage/scripting.html#restrictions-for-contents-of-script-elements
        return str_ireplace(
            ['<!--', '<script', '</script>'],
            ['\x3C!--', '\x3Cscript>', '\x3C/script>'],
            self::encode($data, $flags, $depth)
        );
    }

    /**
     * Decode a JSON string
     *
     * @throws \InvalidArgumentException in case of an error
     */
    static function decode(string $json, int $flags = 0, bool $assoc = true, int $depth = 512)
    {
        $data = json_decode($json, $assoc, $depth, $flags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }

        return $data;
    }
}
