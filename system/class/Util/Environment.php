<?php

namespace Sunlight\Util;

use Sunlight\GenericTemplates;

abstract class Environment
{
    /**
     * Pokusit se detekovat, zda-li bezi tato instalace systemu pod webserverem Apache
     *
     * @return bool
     */
    static function isApache(): bool
    {
        return
            mb_stripos(php_sapi_name(), 'apache') !== false
            || isset($_SERVER['SERVER_SOFTWARE']) && mb_stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false;
    }

    /**
     * Zjistit zda-li je aktualni prostredi konzole
     *
     * @return bool
     */
    static function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Zjistit maximalni moznou celkovou velikost uploadu
     *
     * @return int|null cislo v bajtech nebo null (= neznamo)
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
     * Vykreslit upozorneni na max. velikost uploadu
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
     * Zjistit datovy limit dane konfiguracni volby PHP
     *
     * @param string $opt nazev option
     * @return int|null cislo v bajtech nebo null (= neomezeno)
     */
    static function phpIniLimit(string $opt): ?int
    {
        // get ini value
        $value = ini_get($opt);

        // check value
        if (!$value || -1 == $value) {
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
     * Zjistit dostupnou pamet
     *
     * @return int|null cislo v bajtech nebo null (= neomezeno)
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
