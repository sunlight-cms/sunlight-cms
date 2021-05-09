<?php

namespace Sunlight\Util;

abstract class Html
{
    /**
     * Prevest entity zpet na HTML znaky
     *
     * @param string $input vstupni retezec
     * @return string
     */
    static function unescape(string $input): string
    {
        static $map = null;

        if ($map === null) {
            $map = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES));
        }

        return strtr($input, $map);
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
    static function cut(string $html, int $length): string
    {
        if ($length > 0 && mb_strlen($html) > $length) {
            return self::fixTrailingHtmlEntity(mb_substr($html, 0, $length));
        }

        return $html;
    }

    /**
     * Odstranit nekompletni HTML entitu z konce retezce
     *
     * @param string $string vstupni retezec
     * @return string
     */
    static function fixTrailingHtmlEntity(string $string): string
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
    static function escapeArrayItems(array $input, bool $double_encode = true): array
    {
        $output = [];

        foreach ($input as $key => $value) {
            $output[$key] = _e((string) $value, $double_encode);
        }

        return $output;
    }
}
