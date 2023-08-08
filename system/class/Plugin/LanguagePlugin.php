<?php

namespace Sunlight\Plugin;

use Sunlight\Core;

class LanguagePlugin extends Plugin
{
    function isEssential(): bool
    {
        return $this->id === Core::$fallbackLang;
    }

    function getIsoCode(): string
    {
        return $this->options['iso_code'];
    }

    function getDecimalPoint(): string
    {
        return $this->options['decimal_point'];
    }

    function getThousandsSeparator(): string
    {
        return $this->options['thousands_separator'];
    }

    function formatInteger(int $integer): string
    {
        return number_format($integer, 0, '', $this->options['thousands_separator']);
    }

    function formatFloat(float $float, int $decimals): string
    {
        return number_format($float, $decimals, $this->options['decimal_point'], $this->options['thousands_separator']);
    }

    function getEntries(bool $loadAdminDict): ?array
    {
        // base dictionary
        $fileName = sprintf('%s/dictionary.php', $this->dir);

        if (is_file($fileName)) {
            $data = (array) include $fileName;

            // admin dictionary
            if ($loadAdminDict) {
                $adminFileName = sprintf('%s/admin_dictionary.php', $this->dir);

                if (is_file($adminFileName)) {
                    $data += (array) include $adminFileName;
                } elseif ($this->manager->getPlugins()->hasLanguage(Core::$fallbackLang)) {
                    $adminFileName = sprintf(
                        '%s/admin_dictionary.php',
                        $this->manager->getPlugins()->getLanguage(Core::$fallbackLang)->getDirectory()
                    );

                    if (is_file($adminFileName)) {
                        $data += (array) include $adminFileName;
                    }
                }
            }

            return $data;
        }

        return null;
    }

    function load(): void
    {
        $entries = $this->getEntries(Core::$env === Core::ENV_ADMIN);

        if ($entries !== null) {
            Core::$dictionary->add($entries);
        }
    }
}
