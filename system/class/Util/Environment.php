<?php

namespace Sunlight\Util;

use Sunlight\GenericTemplates;

abstract class Environment
{
    /**
     * Determine PHP version
     */
    static function getPhpVersion(): string
    {
        return sprintf('%d.%d.%d', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION) ;
    }

    /**
     * See if the code is running from console
     */
    static function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Determine upload limit
     *
     * @return int|null number of bytes or null if unknown
     */
    static function getUploadLimit(): ?int
    {
        static $result = null;
        if (!isset($result)) {
            $limit_lowest = null;
            $opts = ['upload_max_filesize', 'post_max_size', 'memory_limit'];
            for ($i = 0; isset($opts[$i]); ++$i) {
                $limit = self::phpIniLimit($opts[$i]);
                if (isset($limit) && (!isset($limit_lowest) || $limit < $limit_lowest)) {
                    $limit_lowest = $limit;
                }
            }
            $result = $limit_lowest ?? null;
        }

        return $result;
    }

    /**
     * Render a note about the upload limit
     *
     * @return string HTML
     */
    static function renderUploadLimit(): string
    {
        $limit = self::getUploadLimit();
        if ($limit !== null) {
            return '<small>' . _lang('global.uploadlimit') . ': <em>' . GenericTemplates::renderFileSize($limit) . '</em></small>';
        }

        return '';
    }

    /**
     * Determine a limit from PHP option
     *
     * @return int|null number of bytes or null if unknown
     */
    static function phpIniLimit(string $opt): ?int
    {
        // get ini value
        $value = ini_get($opt);

        // check value
        if (!$value || $value == -1) {
            // no limit?
            return null;
        }

        // extract type, process number
        $suffix = substr($value, -1);
        $value = (int) $value;

        // parse ini value
        switch ($suffix) {
            case 'M':
            case 'm':
                $value *= 1048576;
                break;
            case 'K':
            case 'k':
                $value *= 1024;
                break;
            case 'G':
            case 'g':
                $value *= 1073741824;
                break;
        }

        // return
        return $value;
    }

    /**
     * Determine available memory
     *
     * @return int|null number of bytes or null if not limited by PHP
     */
    static function getAvailableMemory(): ?int
    {
        $memlimit = self::phpIniLimit('memory_limit');

        if ($memlimit === null) {
            return null;
        }

        return $memlimit - memory_get_usage();
    }
}
