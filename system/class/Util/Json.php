<?php

namespace Sunlight\Util;

/**
 * JSON helper
 */
abstract class Json
{
    const CONTENT_TYPE_JSON = 'application/json; charset=UTF-8';
    const CONTENT_TYPE_JSONP = 'application/javascript; charset=UTF-8';

    /**
     * Encode data as JSON
     *
     * @param bool $pretty produce formatted JSON 1/0
     * @param bool $escapedUnicode escape unicode 1/0
     * @param bool $escapedSlashes escape slashes 1/0
     * @throws \RuntimeException in case of an error
     */
    static function encode($data, bool $pretty = true, bool $escapedUnicode = true, bool $escapedSlashes = false): string
    {
        $options = 0;

        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }
        if (!$escapedSlashes) {
            $options |= JSON_UNESCAPED_SLASHES;
        }
        if (!$escapedUnicode) {
            $options |= JSON_UNESCAPED_UNICODE;
        }

        $json = json_encode($data, $options);

        if ($json === false) {
            throw new \RuntimeException(json_last_error_msg());
        }

        return $json;
    }

    /**
     * Encode data as JSONP
     *
     * @param bool $pretty produce formatted JSON 1/0 (true works in PHP 5.4.0+ only)
     * @param bool $escapedUnicode escape unicode 1/0 (false works in PHP 5.4.0+ only)
     * @param bool $escapedSlashes escape slashes 1/0 (false works in PHP 5.4.0+ only)
     * @throws \RuntimeException in case of an error
     */
    static function encodeJsonp(string $callback, $data, bool $pretty = true, bool $escapedUnicode = true, bool $escapedSlashes = true): string
    {
        return sprintf('%s(%s);', $callback, self::encode($data, $pretty, $escapedUnicode, $escapedSlashes));
    }

    /**
     * Determine JSON / JSONP format using a GET parameter and return the content type and encoded data
     *
     * @param bool $pretty produce formatted JSON 1/0 (true works in PHP 5.4.0+ only)
     * @param bool $escapedUnicode escape unicode 1/0 (false works in PHP 5.4.0+ only)
     * @param bool $escapedSlashes escape slashes 1/0 (false works in PHP 5.4.0+ only)
     * @param string $jsonpCallbackParam JSONP callback parameter name
     * @throws \RuntimeException in case of an error
     * @return string[] content type, encoded data
     */
    static function smartEncode($data, bool $pretty = true, bool $escapedUnicode = true, bool $escapedSlashes = false, string $jsonpCallbackParam = 'callback'): array
    {
        if (
            $jsonpCallbackParam !== null
            && isset($_GET[$jsonpCallbackParam])
            && preg_match('{[a-z_$]\w+$}ADi', $callback = Request::get($jsonpCallbackParam))
        ) {
            $contentType = self::CONTENT_TYPE_JSONP;
            $encodedData = self::encodeJsonp($callback, $data, $pretty, $escapedUnicode, $escapedSlashes);
        } else {
            $contentType = self::CONTENT_TYPE_JSON;
            $encodedData = self::encode($data, $pretty, $escapedUnicode, $escapedSlashes);
        }

        return [$contentType, $encodedData];
    }

    /**
     * Decode a JSON string
     *
     * @param string $json the JSON string to decode
     * @param bool $assoc decode objects as associative arrays 1/0
     * @param bool $bigIntAsString represent big integers as strings (instead of floats) 1/0
     * @throws \RuntimeException in case of an error
     */
    static function decode(string $json, bool $assoc = true, bool $bigIntAsString = false)
    {
        $flags = 0;

        if ($bigIntAsString) {
            $flags |= JSON_BIGINT_AS_STRING;
        }

        $data = json_decode($json, $assoc, 512, $flags);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(json_last_error_msg());
        }

        return $data;
    }
}
