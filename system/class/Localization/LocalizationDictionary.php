<?php

namespace Sunlight\Localization;

use Sunlight\Extend;

class LocalizationDictionary
{
    /** @var array */
    private $entries = [];
    /** @var LocalizationDictionary[] prefix => dict */
    private $subDictionaries;

    /**
     * Get a localization string
     *
     * @param string      $key
     * @param array|null  $replacements replacement map
     * @param string|null $fallback
     * @return string
     */
    function get(string $key, ?array $replacements = null, ?string $fallback = null): string
    {
        // check local entries
        if (isset($this->entries[$key])) {
            return $replacements !== null
                ? strtr($this->entries[$key], $replacements)
                : $this->entries[$key];
        }

        // check sub-dictionaries
        $keyParts = explode('.', $key, 2);

        if (isset($keyParts[1], $this->subDictionaries[$keyParts[0]])) {
            return $this->subDictionaries[$keyParts[0]]->get($keyParts[1], $replacements);
        }

        // use fallback if provided
        if ($fallback !== null) {
            return $fallback;
        }

        // entry not found
        Extend::call('localization.missing', [
            'key' => $key,
            'dict' => $this,
        ]);

        return $key;
    }

    /**
     * Add entries
     *
     * Existing entries will not be overwritten.
     *
     * @param array $entries
     */
    function add(array $entries): void
    {
        $this->entries += $entries;
    }

    /**
     * Register a sub-dictionary
     *
     * - all nonexistent entries beginning with the given prefix + a dot will be fetched from the sub-dictionary
     * - the prefix is excluded when the sub-dictionary's get() method is called
     * - the prefix cannot contain a dot
     *
     * Example:
     *
     * @param string                 $prefix
     * @param LocalizationDictionary $subDictionary
     */
    function registerSubDictionary(string $prefix, LocalizationDictionary $subDictionary): void
    {
        if (_debug && strpos($prefix, '.') !== false) {
            throw new \InvalidArgumentException(sprintf('Sub-dictionary prefix "%s" must not contain a dot', $prefix));
        }

        $this->subDictionaries[$prefix] = $subDictionary;
    }
}
