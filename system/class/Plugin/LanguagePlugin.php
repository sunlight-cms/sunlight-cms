<?php

namespace Sunlight\Plugin;

use Sunlight\Core;

class LanguagePlugin extends Plugin
{
    function isEssential(): bool
    {
        return $this->id === Core::$fallbackLang;
    }

    /**
     * Get localization entries
     */
    function getLocalizationEntries(bool $loadAdminDict): ?array
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
}
