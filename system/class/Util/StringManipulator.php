<?php

namespace Sunlight\Util;

abstract class StringManipulator
{
    /**
     * Orezat retezec na pozadovanou delku
     *
     * @param string $string
     * @param int    $length pozadovana delka
     * @return string
     */
    static function cut($string, $length)
    {
        if (mb_strlen($string) > $length) {
            return mb_substr($string, 0, $length);
        } else {
            return $string;
        }
    }

    /**
     * Orezat text na pozadovanou delku a pridat "...", pokud je delsi nez limit
     *
     * @param string $string           vstupni retezec
     * @param int    $length           pozadovana delka
     * @param bool   $convert_entities prevest html entity zpet na originalni znaky a po orezani opet zpet
     * @return string
     */
    static function ellipsis($string, $length, $convert_entities = true)
    {
        if ($length === null || $length <= 0) {
            return $string;
        }

        if ($convert_entities) {
            $string = Html::unescape($string);
        }
        if (mb_strlen($string) > $length) {
            $string = mb_substr($string, 0, max(0, $length - 3)) . "...";
        }
        if ($convert_entities) {
            $string = _e($string);
        }

        return $string;
    }

    /**
     * Odstraneni nezadoucich odradkovani a mezer z retezce
     *
     * @param string $string vstupni retezec
     * @return string
     */
    static function trimExtraWhitespace($string)
    {
        $from = array("{(\r\n){3,}}s", "{  +}s");
        $to = array("\r\n\r\n", ' ');

        return preg_replace($from, $to, trim($string));
    }

    /**
     * Formatovani retezce pro uzivatelska jmena, mod rewrite atd.
     *
     * @param string     $input vstupni retezec
     * @param bool       $lower prevest na mala pismena 1/0
     * @param array|null $extra mapa extra povolenych znaku nebo null
     * @return string
     */
    static function slugify($input, $lower = true, $extra = null)
    {
        // diakritika a mezery
        static $trans = array(' ' => '-', 'é' => 'e', 'ě' => 'e', 'É' => 'E', 'Ě' => 'E', 'ř' => 'r', 'Ř' => 'R', 'ť' => 't', 'Ť' => 'T', 'ž' => 'z', 'Ž' => 'Z', 'ú' => 'u', 'Ú' => 'U', 'ů' => 'u', 'Ů' => 'U', 'ü' => 'u', 'Ü' => 'U', 'í' => 'i', 'Í' => 'I', 'ó' => 'o', 'Ó' => 'O', 'á' => 'a', 'Á' => 'A', 'š' => 's', 'Š' => 'S', 'ď' => 'd', 'Ď' => 'D', 'ý' => 'y', 'Ý' => 'Y', 'č' => 'c', 'Č' => 'C', 'ň' => 'n', 'Ň' => 'N', 'ä' => 'a', 'Ä' => 'A', 'ĺ' => 'l', 'Ĺ' => 'L', 'ľ' => 'l', 'Ľ' => 'L', 'ŕ' => 'r', 'Ŕ' => 'R', 'ö' => 'o', 'Ö' => 'O');
        $input = strtr($input, $trans);

        // odfiltrovani nepovolenych znaku
        static
        $allow = array('A' => 0, 'a' => 1, 'B' => 2, 'b' => 3, 'C' => 4, 'c' => 5, 'D' => 6, 'd' => 7, 'E' => 8, 'e' => 9, 'F' => 10, 'f' => 11, 'G' => 12, 'g' => 13, 'H' => 14, 'h' => 15, 'I' => 16, 'i' => 17, 'J' => 18, 'j' => 19, 'K' => 20, 'k' => 21, 'L' => 22, 'l' => 23, 'M' => 24, 'm' => 25, 'N' => 26, 'n' => 27, 'O' => 28, 'o' => 29, 'P' => 30, 'p' => 31, 'Q' => 32, 'q' => 33, 'R' => 34, 'r' => 35, 'S' => 36, 's' => 37, 'T' => 38, 't' => 39, 'U' => 40, 'u' => 41, 'V' => 42, 'v' => 43, 'W' => 44, 'w' => 45, 'X' => 46, 'x' => 47, 'Y' => 48, 'y' => 49, 'Z' => 50, 'z' => 51, '0' => 52, '1' => 53, '2' => 54, '3' => 55, '4' => 56, '5' => 57, '6' => 58, '7' => 59, '8' => 60, '9' => 61, '.' => 62, '-' => 63, '_' => 64),
        $lowermap = array("A" => "a", "B" => "b", "C" => "c", "D" => "d", "E" => "e", "F" => "f", "G" => "g", "H" => "h", "I" => "i", "J" => "j", "K" => "k", "L" => "l", "M" => "m", "N" => "n", "O" => "o", "P" => "p", "Q" => "q", "R" => "r", "S" => "s", "T" => "t", "U" => "u", "V" => "v", "W" => "w", "X" => "x", "Y" => "y", "Z" => "z");
        $output = "";
        for ($i = 0; isset($input[$i]); ++$i) {
            $char = $input[$i];
            if (isset($allow[$char]) || $extra !== null && isset($extra[$char])) {
                if ($lower && isset($lowermap[$char])) {
                    $output .= $lowermap[$char];
                } else {
                    $output .= $char;
                }
            }
        }

        // dvojite symboly
        $from = array('{--+}', '{\.\.+}', '{\.-+}', '{-\.+}');
        $to = array('-', '.', '.', '-');
        if ($extra !== null) {
            foreach ($extra as $extra_char => $i) {
                $from[] = '{' . preg_quote($extra_char . $extra_char) . '+}';
                $to[] = $extra_char;
            }
        }
        $output = preg_replace($from, $to, $output);

        // orezani
        $trim_chars = '-_.';
        if ($extra !== null) {
            $trim_chars .= implode('', array_keys($extra));
        }
        $output = trim($output, $trim_chars);

        // return
        return $output;
    }

    /**
     * Formatovani retezce jako camelCase nebo CamelCase
     *
     * @param string $input
     * @param bool   $firstLetterLower
     * @return string
     */
    static function toCamelCase($input, $firstLetterLower = false)
    {
        $output = '';
        $parts = preg_split('{[^a-zA-Z0-9\x80-\xFF]+}', $input, null, PREG_SPLIT_NO_EMPTY);

        for ($i = 0; isset($parts[$i]); ++$i) {
            $part = mb_strtolower($parts[$i]);
            $firstLetter = mb_substr($part, 0, 1);

            if ($i > 0 || !$firstLetterLower) {
                $firstLetter = mb_strtoupper($firstLetter);
            } else {
                $firstLetter = mb_strtolower($firstLetter);
            }

            $output .= $firstLetter . mb_strtolower(mb_substr($part, 1));
        }

        return $output;
    }
}
