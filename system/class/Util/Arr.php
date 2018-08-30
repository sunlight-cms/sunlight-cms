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
    static function removeValue($array, $value_remove, $preserve_keys = false)
    {
        $output = array();
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
    static function getSubset(array $array, array $keys, $prefixLen = null, $exceptionOnMissing = true)
    {
        $out = array();
        foreach ($keys as $key) {
            if (key_exists($key, $array)) {
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
    static function filterKeys(array $array, $include = null, $exclude = null, array $excludeList = array())
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

        $output = array();
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
}
