<?php

namespace Sunlight\Util;

abstract class Arr
{
    /**
     * Filter a value from an array
     */
    static function removeValue(array $array, $valueToRemove, bool $preserveKeys = false): array
    {
        $output = [];

        foreach ($array as $key => $value) {
            if ($value != $valueToRemove) {
                if (!$preserveKeys) {
                    $output[] = $value;
                } else {
                    $output[$key] = $value;
                }
            }
        }

        return $output;
    }

    /**
     * Insert key-value pairs after a specific key in the given array
     *
     * If the index is not found, the pairs will be appended to the array.
     *
     * @param array-key $existingKey
     */
    static function insertAfter(array &$array, $existingKey, array $newPairs): void
    {
        self::insertPairs($array, $existingKey, $newPairs, true);
    }

    /**
     * Insert key-value pairs before a specific key in the given array
     *
     * If the index is not found, the pairs will be prepended to the array.
     *
     * @param array-key $existingKey
     */
    static function insertBefore(array &$array, $existingKey, array $newPairs): void
    {
        self::insertPairs($array, $existingKey, $newPairs, false);
    }

    /**
     * Get a subset of keys from an array
     *
     * Missing keys will be set to NULL.
     *
     * @param array $array input array
     * @param array $keys list of keys
     * @param int|null $prefixLen exclude this many bytes from the start of the keys
     */
    static function getSubset(array $array, array $keys, ?int $prefixLen = null): array
    {
        $out = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $out[$prefixLen === null ? $key : substr($key, $prefixLen)] = $array[$key];
            } else {
                $out[$prefixLen === null ? $key : substr($key, $prefixLen)] = null;
            }
        }

        return $out;
    }

    /**
     * Filter array keys
     *
     * @param string $includedPrefix all keys must have this prefix (unless NULL)
     * @param string $excludedPrefix all keys must not have this prefix (unless NULL)
     * @param string[] $excludedKeys all keys in this list are excluded
     */
    static function filterKeys(array $array, ?string $includedPrefix = null, ?string $excludedPrefix = null, array $excludedKeys = []): array
    {
        if ($includedPrefix !== null) {
            $includeLength = strlen($includedPrefix);
        }

        if ($excludedPrefix !== null) {
            $excludeLength = strlen($excludedPrefix);
        }

        if (!empty($excludedKeys)) {
            $excludedKeys = array_flip($excludedKeys);
        }

        $output = [];

        foreach ($array as $key => $value) {
            if (
                $includedPrefix !== null && strncmp($key, $includedPrefix, $includeLength) !== 0
                || $excludedPrefix !== null && strncmp($key, $excludedPrefix, $excludeLength) === 0
                || isset($excludedKeys[$key])
            ) {
                continue;
            }

            $output[$key] = $value;
        }

        return $output;
    }

    /**
     * Get a hash of array contents
     *
     * - keys are sorted before hashing
     * - only scalar and nested array values are supported (objects will not be recursed into)
     */
    static function hash(array $array, string $algo = 'tiger128,3'): string
    {
        $context = hash_init($algo);
        $queue = [[0, '', $array]];
        $last = 0;

        while ($last >= 0) {
            // pop an item off the queue
            [$level, $path, $value] = $queue[$last];
            unset($queue[$last]);
            --$last;

            // process item
            if (is_array($value)) {
                // queue sorted array pairs
                $keys = array_keys($value);
                sort($keys);

                foreach ($keys as $key) {
                    $queue[++$last] = [$level + 1, "{$path}.{$key}", $value[$key]];
                }

                $keys = null;
            } else {
                // hash level and path
                hash_update($context, sprintf("%d\0%s\0", $level, $path));

                // hash value
                if (is_object($value)) {
                    // only class name for objects
                    hash_update($context, get_class($value));
                } else {
                    // exported value
                    hash_update($context, var_export($value, true));
                }

                hash_update($context, "\0");
            }
        }

        return hash_final($context);
    }

    /**
     * @param array-key $existingKey
     */
    private static function insertPairs(array &$array, $existingKey, array $newPairs, bool $after): void
    {
        if (array_key_exists($existingKey, $array)) {
            $newArray = [];

            foreach ($array as $key => $value) {
                if ($key === $existingKey) {
                    if (!$after) {
                        $newArray += $newPairs;
                    }

                    $newArray[$key] = $value;

                    if ($after) {
                        $newArray += $newPairs;
                    }
                } else {
                    $newArray[$key] = $value;
                }
            }

            $array = $newArray;

            return;
        }

        // insert at start or end if the key does not exist
        if ($after) {
            $array += $newPairs;
            reset($array);
        } else {
            $array = $newPairs + $array;
        }
    }
}
