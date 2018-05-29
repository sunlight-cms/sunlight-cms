<?php

namespace Sunlight\Util;

abstract class Html
{
    /**
     * Prevest HTML znaky na entity
     *
     * @param string $input        vstupni retezec
     * @param bool   $doubleEncode prevadet i jiz existujici entity 1/0
     * @return string
     */
    static function escape($input, $doubleEncode = true)
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8', $doubleEncode);
    }

    /**
     * Prevest entity zpet na HTML znaky
     *
     * @param string $input vstupni retezec
     * @return string
     */
    static function unescape($input)
    {
        static $map = null;

        if ($map === null) {
            $map = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES));
        }
        $output = strtr($input, $map);

        return $output;
    }

    /**
     * Orezat HTML na pozadovanou delku
     *
     * Je-li kod uriznut uprostred zapisu HTML entity, je tato entita odstranena.
     *
     * @param string $html   vstupni HTML kod
     * @param int    $length pozadovana delka
     * @return string
     */
    static function cut($html, $length)
    {
        if ($length > 0 && mb_strlen($html) > $length) {
            return static::fixTrailingHtmlEntity(mb_substr($html, 0, $length));
        } else {
            return $html;
        }
    }

    /**
     * Odstranit nekompletni HTML entitu z konce retezce
     *
     * @param string $string vstupni retezec
     * @return string
     */
    static function fixTrailingHtmlEntity($string)
    {
        return preg_replace('{\\s*&[^;]*$}D', '', $string);
    }

    /**
     * Prevet HTML znaky vsech polozek v poli na entity
     *
     * Klice jsou zachovany.
     *
     * @param array $input         vstupni pole
     * @param bool  $double_encode prevadet i jiz existujici entity 1/0
     * @return array
     */
    static function escapeArrayItems(array $input, $double_encode = true)
    {
        $output = array();

        foreach ($input as $key => $value) {
            $output[$key] = static::escape((string) $value, $double_encode);
        }

        return $output;
    }
}
