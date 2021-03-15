<?php

namespace Sunlight\Util;

abstract class Arr
{
    /**
     * Odfiltrovani dane hodnoty z pole
     *
     * @param array $array         vstupni pole
     * @param mixed $value_remove  hodnota ktera ma byt odstranena
     * @param bool  $preserve_keys zachovat ciselnou radu klicu 1/0
     * @return array
     */
    static function removeValue(array $array, $value_remove, bool $preserve_keys = false): array
    {
        $output = [];
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                if ($value != $value_remove) {
                    if (!$preserve_keys) {
                        $output[] = $value;
                    } else {
                        $output[$key] = $value;
                    }
                }
            }
        }

        return $output;
    }

    /**
     * Ziskani danych klicu z pole
     *
     * @param array    $array              vstupni pole
     * @param array    $keys               seznam pozadovanych klicu
     * @param int|null $prefixLen          delka prefixu v nazvu vsech klicu, ktery ma byt odebran
     * @param bool     $exceptionOnMissing vyvolat vyjimku pri chybejicim klici
     * @return array
     */
    static function getSubset(array $array, array $keys, ?int $prefixLen = null, bool $exceptionOnMissing = true): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $out[$prefixLen === null ? $key : substr($key, $prefixLen)] = $array[$key];
            } elseif ($exceptionOnMissing) {
                throw new \OutOfBoundsException(sprintf('Missing key "%s"', $key));
            } else {
                $out[$prefixLen === null ? $key : substr($key, $prefixLen)] = null;
            }
        }

        return $out;
    }

    /**
     * Filtrovat klice v poli
     *
     * @param array       $array       vstupni pole
     * @param string|null $include     prefix - klice zacinajici timto prefixem budou ZAHRNUTY
     * @param string|null $exclude     prefix - klice zacinajici timto prefixem budou VYRAZENY
     * @param array       $excludeList pole s klici, ktere maji byt VYRAZENY
     * @return array
     */
    static function filterKeys(array $array, ?string $include = null, ?string $exclude = null, array $excludeList = []): array
    {
        if ($include !== null) {
            $includeLength = strlen($include);
        }
        if ($exclude !== null) {
            $excludeLength = strlen($exclude);
        }
        if (!empty($excludeList)) {
            $excludeList = array_flip($excludeList);
        }

        $output = [];
        foreach ($array as $key => $value) {
            if (
                $include !== null && strncmp($key, $include, $includeLength) !== 0
                || $exclude !== null && strncmp($key, $exclude, $excludeLength) === 0
                || isset($excludeList[$key])
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
    static function hash(array $array, string $algo = 'sha256'): string
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
}
