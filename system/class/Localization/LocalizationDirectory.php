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
    public function __construct($dir, array $availableLanguages = null)
    {
        $this->dir = $dir;
        $this->availableLanguages = $availableLanguages;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    public function get($key, array $replacements = null)
    {
        $this->isLoaded or $this->load();

        return parent::get($key, $replacements);
    }

    /**
     * @param string $language
     * @return string
     */
    public function getPathForLanguage($language)
    {
        return $this->dir . '/' . $language . '.php';
    }

    /**
     * @param string $language
     * @return bool
     */
    public function hasDictionaryForLanguage($language)
    {
        return $this->availableLanguages !== null && in_array($language, $this->availableLanguages, true)
            || $this->availableLanguages === null && is_file($this->getPathForLanguage($language));
    }

    /**
     * @param string $language
     * @return array
     */
    protected function loadDictionaryForLanguage($language)
    {
        return (array) include $this->getPathForLanguage($language);
    }

    /**
     * Load the entries
     *
     * Uses a fallback dictionary if possible.
     */
    protected function load()
    {
        if ($this->hasDictionaryForLanguage(_language)) {
            $this->add($this->loadDictionaryForLanguage(_language));
        } elseif (Core::$fallbackLang !== _language && $this->hasDictionaryForLanguage(Core::$fallbackLang)) {
            $this->add($this->loadDictionaryForLanguage(Core::$fallbackLang));
        }

        $this->isLoaded = true;
    }
}
