<?php

namespace Sunlight\Localization;

use Sunlight\Core;

/**
 * Localization dictionary that loads entries from files in the given directory
 */
class LocalizationDirectory extends LocalizationDictionary
{
    /** @var string */
    private $dir;
    /** @var array|null */
    private $availableLanguages;
    /** @var bool */
    private $isLoaded = false;

    /**
     * @param string $dir path to the directory containing the localization dictionaries (without a trailing slash)
     * @param array|null $availableLanguages list of available languages (saves an is_file() check)
     */
    function __construct(string $dir, ?array $availableLanguages = null)
    {
        $this->dir = $dir;
        $this->availableLanguages = $availableLanguages;
    }

    function getDir(): string
    {
        return $this->dir;
    }

    function get(string $key, ?array $replacements = null, ?string $fallback = null): string
    {
        $this->isLoaded or $this->load();

        return parent::get($key, $replacements, $fallback);
    }

    function getPathForLanguage(string $language): string
    {
        return $this->dir . '/' . $language . '.php';
    }

    function hasDictionaryForLanguage(string $language): bool
    {
        return $this->availableLanguages !== null && in_array($language, $this->availableLanguages, true)
            || $this->availableLanguages === null && is_file($this->getPathForLanguage($language));
    }

    private function loadDictionaryForLanguage(string $language): array
    {
        return (array) include $this->getPathForLanguage($language);
    }

    /**
     * Load the entries
     *
     * Uses a fallback dictionary if possible.
     */
    private function load(): void
    {
        if ($this->hasDictionaryForLanguage(Core::$lang)) {
            $this->add($this->loadDictionaryForLanguage(Core::$lang));
        } elseif (Core::$fallbackLang !== Core::$lang && $this->hasDictionaryForLanguage(Core::$fallbackLang)) {
            $this->add($this->loadDictionaryForLanguage(Core::$fallbackLang));
        }

        $this->isLoaded = true;
    }
}
