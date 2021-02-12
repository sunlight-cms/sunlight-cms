<?php

namespace Sunlight\Localization;

use Sunlight\Core;

/**
 * Localization dictionary that loads entries from files in the given directory
 */
class LocalizationDirectory extends LocalizationDictionary
{
    /** @var string */
    protected $dir;
    /** @var array|null */
    protected $availableLanguages;
    /** @var bool */
    protected $isLoaded = false;

    /**
     * @param string     $dir                path to the directory containing the localization dictionaries (without a trailing slash)
     * @param array|null $availableLanguages list of available languages (saves a is_file() check)
     */
    function __construct(string $dir, array $availableLanguages = null)
    {
        $this->dir = $dir;
        $this->availableLanguages = $availableLanguages;
    }

    /**
     * @return string
     */
    function getDir(): string
    {
        return $this->dir;
    }

    /**
     * @param string      $key
     * @param array|null  $replacements
     * @param string|null $fallback
     * @return string
     */
    function get(string $key, ?array $replacements = null, ?string $fallback = null): string
    {
        $this->isLoaded or $this->load();

        return parent::get($key, $replacements, $fallback);
    }

    /**
     * @param string $language
     * @return string
     */
    function getPathForLanguage(string $language): string
    {
        return $this->dir . '/' . $language . '.php';
    }

    /**
     * @param string $language
     * @return bool
     */
    function hasDictionaryForLanguage(string $language): bool
    {
        return $this->availableLanguages !== null && in_array($language, $this->availableLanguages, true)
            || $this->availableLanguages === null && is_file($this->getPathForLanguage($language));
    }

    /**
     * @param string $language
     * @return array
     */
    protected function loadDictionaryForLanguage(string $language): array
    {
        return (array) include $this->getPathForLanguage($language);
    }

    /**
     * Load the entries
     *
     * Uses a fallback dictionary if possible.
     */
    protected function load(): void
    {
        if ($this->hasDictionaryForLanguage(_language)) {
            $this->add($this->loadDictionaryForLanguage(_language));
        } elseif (Core::$fallbackLang !== _language && $this->hasDictionaryForLanguage(Core::$fallbackLang)) {
            $this->add($this->loadDictionaryForLanguage(Core::$fallbackLang));
        }

        $this->isLoaded = true;
    }
}
