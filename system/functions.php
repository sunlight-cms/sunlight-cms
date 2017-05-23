<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\Url;
use Sunlight\Message;
use Sunlight\Exception\ContentPrivilegeException;
use Kuria\Cache\Util\TemporaryFile;
use Kuria\Debug\Output;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Password;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateHelper;

/**
 * Vlozeni GET promenne do odkazu
 *
 * @param string $link   adresa
 * @param string $params cisty query retezec
 * @param bool   $entity pouzit &amp; pro oddeleni 1/0
 * @return string
 */
function _addGetToLink($link, $params, $entity = true)
{
    // oddelovaci znak
    if ('' !== $params) {
        if (false === strpos($link, '?')) {
            $link .= '?';
        } else {
            if ($entity) {
                $link .= '&amp;';
            } else {
                $link .= '&';
            }
        }
    }

    return $link . ($entity ? _e($params) : $params);
}

/**
 * Pridat schema do URL, pokud jej neobsahuje nebo neni relativni
 *
 * @param string $url
 * @return string
 */
function _addSchemeToURL($url)
{
    if (mb_substr($url, 0, 7) !== 'http://' && mb_substr($url, 0, 8) !== 'https://' && $url[0] !== '/' && mb_substr($url, 0, 2) !== './') {
        $url = 'http://' . $url;
    }

    return $url;
}

/**
 * Formatovat cas jako HTTP-date
 *
 * @param int  $time     timestamp
 * @param bool $relative relativne k aktualnimu casu 1/0
 * @return string
 */
function _httpDate($time, $relative = false)
{
    if ($relative) {
        $time += time();
    }

    return gmdate('D, d M Y H:i:s', $time) . ' GMT';
}

/**
 * Formatovani retezce pro uzivatelska jmena, mod rewrite atd.
 *
 * @param string     $input vstupni retezec
 * @param bool       $lower prevest na mala pismena 1/0
 * @param array|null $extra mapa extra povolenych znaku nebo null
 * @return string
 */
function _slugify($input, $lower = true, $extra = null)
{
    // diakritika a mezery
    static $trans = array(' ' => '-', 'é' => 'e', 'ě' => 'e', 'É' => 'E', 'Ě' => 'E', 'ř' => 'r', 'Ř' => 'R', 'ť' => 't', 'Ť' => 'T', 'ž' => 'z', 'Ž' => 'Z', 'ú' => 'u', 'Ú' => 'U', 'ů' => 'u', 'Ů' => 'U', 'ü' => 'u', 'Ü' => 'U', 'í' => 'i', 'Í' => 'I', 'ó' => 'o', 'Ó' => 'O', 'á' => 'a', 'Á' => 'A', 'š' => 's', 'Š' => 'S', 'ď' => 'd', 'Ď' => 'D', 'ý' => 'y', 'Ý' => 'Y', 'č' => 'c', 'Č' => 'C', 'ň' => 'n', 'Ň' => 'N', 'ä' => 'a', 'Ä' => 'A', 'ĺ' => 'l', 'Ĺ' => 'L', 'ľ' => 'l', 'Ľ' => 'L', 'ŕ' => 'r', 'Ŕ' => 'R', 'ö' => 'o', 'Ö' => 'O');
    $input = strtr($input, $trans);

    // odfiltrovani nepovolenych znaku
    static
        $allow = array('A' => 0, 'a' => 1, 'B' => 2, 'b' => 3, 'C' => 4, 'c' => 5, 'D' => 6, 'd' => 7, 'E' => 8, 'e' => 9, 'F' => 10, 'f' => 11, 'G' => 12, 'g' => 13, 'H' => 14, 'h' => 15, 'I' => 16, 'i' => 17, 'J' => 18, 'j' => 19, 'K' => 20, 'k' => 21, 'L' => 22, 'l' => 23, 'M' => 24, 'm' => 25, 'N' => 26, 'n' => 27, 'O' => 28, 'o' => 29, 'P' => 30, 'p' => 31, 'Q' => 32, 'q' => 33, 'R' => 34, 'r' => 35, 'S' => 36, 's' => 37, 'T' => 38, 't' => 39, 'U' => 40, 'u' => 41, 'V' => 42, 'v' => 43, 'W' => 44, 'w' => 45, 'X' => 46, 'x' => 47, 'Y' => 48, 'y' => 49, 'Z' => 50, 'z' => 51, '0' => 52, '1' => 53, '2' => 54, '3' => 55, '4' => 56, '5' => 57, '6' => 58, '7' => 59, '8' => 60, '9' => 61, '.' => 62, '-' => 63, '_' => 64),
        $lowermap = array("A" => "a", "B" => "b", "C" => "c", "D" => "d", "E" => "e", "F" => "f", "G" => "g", "H" => "h", "I" => "i", "J" => "j", "K" => "k", "L" => "l", "M" => "m", "N" => "n", "O" => "o", "P" => "p", "Q" => "q", "R" => "r", "S" => "s", "T" => "t", "U" => "u", "V" => "v", "W" => "w", "X" => "x", "Y" => "y", "Z" => "z")
    ;
    $output = "";
    for ($i = 0; isset($input[$i]); ++$i) {
        $char = $input[$i];
        if (isset($allow[$char]) || null !== $extra && isset($extra[$char])) {
            if ($lower && isset($lowermap[$char])) {
                $output .= $lowermap[$char];
            } else {
                $output .= $char;
            }
        }
    }

    // dvojite symboly
    $from = array('|--+|', '|\.\.+|', '|\.-+|', '|-\.+|');
    $to = array('-', '.', '.', '-');
    if (null !== $extra) {
        foreach ($extra as $extra_char => $i) {
            $from[] = '|' . preg_quote($extra_char . $extra_char) . '+|';
            $to[] = $extra_char;
        }
    }
    $output = preg_replace($from, $to, $output);

    // orezani
    $trim_chars = '-_.';
    if (null !== $extra) {
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
function _camelCase($input, $firstLetterLower = false)
{
    $output = '';
    $parts = preg_split('/[^a-zA-Z0-9\x80-\xFF]+/', $input, null, PREG_SPLIT_NO_EMPTY);

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

/**
 * Odfiltrovani dane hodnoty z pole
 *
 * @param array $array         vstupni pole
 * @param mixed $value_remove  hodnota ktera ma byt odstranena
 * @param bool  $preserve_keys zachovat ciselnou radu klicu 1/0
 * @return array
 */
function _arrayRemoveValue($array, $value_remove, $preserve_keys = false)
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
function _arrayGetSubset(array $array, array $keys, $prefixLen = null, $exceptionOnMissing = true)
{
    $out = array();
    foreach ($keys as $key) {
        if (array_key_exists($key, $array)) {
            $out[null === $prefixLen ? $key : substr($key, $prefixLen)] = $array[$key];
        } elseif ($exceptionOnMissing) {
            throw new OutOfBoundsException(sprintf('Missing key "%s"', $key));
        } else {
            $out[null === $prefixLen ? $key : substr($key, $prefixLen)] = null;
        }
    }

    return $out;
}

/**
 * Filtrovat klice v poli
 *
 * Pro ziskani konkretnich klicu (dle seznamu) slouzi {@see _arrayGetSubset()}
 *
 * @param array       $array       vstupni pole
 * @param string|null $include     prefix - klice zacinajici timto prefixem budou ZAHRNUTY
 * @param string|null $exclude     prefix - klice zacinajici timto prefixem budou VYRAZENY
 * @param array       $excludeList pole s klici, ktere maji byt VYRAZENY
 * @return array
 */
function _arrayFilter(array $array, $include = null, $exclude = null, array $excludeList = array())
{
    if (null !== $include) {
        $includeLength = strlen($include);
    }
    if (null !== $exclude) {
        $excludeLength = strlen($exclude);
    }
    if (!empty($excludeList)) {
        $excludeList = array_flip($excludeList);
    }

    $output = array();
    foreach ($array as $key => $value) {
        if (
            null !== $include && 0 !== strncmp($key, $include, $includeLength)
            || null !== $exclude && 0 === strncmp($key, $exclude, $excludeLength)
            || isset($excludeList[$key])
        ) {
            continue;
        }

        $output[$key] = $value;
    }

    return $output;
}

/**
 * Vratit textovou reprezentaci boolean hodnoty cisla
 *
 * @param mixed $input vstupni hodnota
 * @return string 'true' nebo 'false'
 */
function _booleanStr($input)
{
    return $input ? 'true' : 'false';
}

/**
 * Normalizovat promennou
 *
 * V pripade chyby bude promenna nastavena na null.
 *
 * @param &mixed $variable    promenna
 * @param string $type        pozadovany typ, viz PHP funkce settype()
 * @param bool   $emptyToNull je-li hodnota prazdna ("" nebo null), nastavit na null 1/0
 */
function _normalize(&$variable, $type, $emptyToNull = true)
{
    if (
        $emptyToNull && (null === $variable || '' === $variable)
        || !settype($variable, $type)
    ) {
        $variable = null;
    }
}

/**
 * Vygenerovat nahodne cislo v danem rozmezi (inkluzivni)
 *
 * @param int $min
 * @param int $max
 * @return int
 */
function _randomInteger($min, $max)
{
    static $fc = null;

    if (null === $fc) {
        if (function_exists('random_int')) {
            $fc = 'random_int';
        } else {
            $fc = 'mt_rand';
        }
    }

    return $fc($min, $max);
}

/**
 * Zaskrtnout checkbox na zaklade podminky
 *
 * @param bool $input
 * @return string
 */
function _checkboxActivate($input)
{
    return $input ? ' checked' : '';
}

/**
 * Aktivovat volbu na zaklade podminky
 *
 * @param bool $input
 * @return string
 */
function _optionActivate($input)
{
    return $input ? ' selected' : '';
}

/**
 * Nacteni odeslaneho checkboxu formularem (POST)
 *
 * @param string $name jmeno checkboxu (post)
 * @return int 1/0
 */
function _checkboxLoad($name)
{
    return isset($_POST[$name]) ? 1 : 0;
}

/**
 * Orezat retezec na pozadovanou delku
 *
 * @param string $string
 * @param int    $length pozadovana delka
 * @return string
 */
function _cutString($string, $length)
{
    if (mb_strlen($string) > $length) {
        return mb_substr($string, 0, $length);
    } else {
        return $string;
    }
}

/**
 * Orezat text na pozadovanou delku
 *
 * @param string $string           vstupni retezec
 * @param int    $length           pozadovana delka
 * @param bool   $convert_entities prevest html entity zpet na originalni znaky a po orezani opet zpet
 * @return string
 */
function _cutText($string, $length, $convert_entities = true)
{
    if ($length === null || $length <= 0) {
        return $string;
    }

    if ($convert_entities) {
        $string = _unescapeHtml($string);
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
 * Orezat HTML na pozadovanou delku
 * Je-li kod uriznut uprostred zapisu HTML entity, je tato entita odstranena.
 *
 * @param string $html   vstupni HTML kod
 * @param int    $length pozadovana delka
 * @return string
 */
function _cutHtml($html, $length)
{
    if ($length > 0 && mb_strlen($html) > $length) {
        return _fixTrailingHtmlEntity(mb_substr($html, 0, $length));
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
function _fixTrailingHtmlEntity($string)
{
    return preg_replace('/\\s*&[^;]*$/', '', $string);
}

/**
 * Prevest HTML znaky na entity
 *
 * @param string $input         vstupni retezec
 * @param bool   $double_encode prevadet i jiz existujici entity 1/0
 * @return string
 */
function _e($input, $double_encode = true)
{
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8', $double_encode);
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
function _htmlEscapeArrayItems(array $input, $double_encode = true)
{
    $output = array();

    foreach ($input as $key => $value) {
        $output[$key] = _e((string) $value, $double_encode);
    }

    return $output;
}

/**
 * Prevest entity zpet na HTML znaky
 *
 * @param string $input vstupni retezec
 * @return string
 */
function _unescapeHtml($input)
{
    static $map = null;
    
    if (null === $map) {
        $map = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES));
    }
    $output = strtr($input, $map);

    return $output;
}

/**
 * Zakazat pole formulare, pokud NEPLATI podminka
 *
 * @param bool $cond pole je povoleno 1/0
 * @return string
 */
function _inputDisableUnless($cond)
{
    if ($cond != true) {
        return ' disabled';
    }

    return '';
}

/**
 * Rozpoznat, zda se jedna o URL v absolutnim tvaru
 *
 * @param string $path adresa
 * @return bool
 */
function _isAbsoluteUrl($path)
{
    $path = @parse_url($path);

    return isset($path['scheme']);
}

/**
 * Rozebrat retezec na parametry a vratit jako pole
 *
 * @param string $input vstupni retezec
 * @return array
 */
function _parseStr($input)
{
    // nastaveni funkce
    static
        $sep = ',',
        $quote = '"',
        $quote2 = '\'',
        $esc = '\\',
        $ws = array("\n" => 0, "\r" => 1, "\t" => 2, " " => 3),
        $keywords = array('null' => 0, 'true' => true, 'false' => false)
    ;

    // priprava
    $output = array();
    $input = trim($input);
    $last = strlen($input) - 1;
    $val = '';
    $ws_buffer = '';
    $val_quote = null;
    $mode = 0;
    $val_fc = null;
    $escaped = null;

    // vyhodnoceni
    for ($i = 0; isset($input[$i]); ++$i) {

        $char = $input[$i];
        switch ($mode) {

                /* ----  najit zacatek argumentu  ---- */
            case 0:
                if (!isset($ws[$char])) {
                    if ($char === $sep) {
                        // prazdny argument
                        $output[] = null;
                    } else {
                        --$i;
                        $mode = 1;
                        $val = '';
                        $val_fc = true;
                        $escaped = false;
                    }
                }
                break;

                /* ----  nacist hodnotu  ---- */
            case 1:

                // prvni znak - rozpoznat uvozovky
                if ($val_fc) {
                    $val_fc = false;
                    if ($char === $quote || $char === $quote2) {
                        $val_quote = $char;
                        break;
                    } else {
                        $val_quote = null;
                    }
                }

                // zpracovat znak
                if (isset($val_quote)) {

                    // v retezci s uvozovkami
                    if ($char === $esc) {

                        // escape znak
                        if ($escaped) {
                            // escaped + escaped
                            $val .= $char;
                            $escaped = false;
                        } else {
                            // aktivovat
                            $escaped = true;
                        }

                    } elseif ($char === $val_quote) {

                        // uvozovka
                        if ($escaped) {
                            // escaped uvozovka
                            $val .= $char;
                            $escaped = false;
                        } else {
                            // konec hodnoty
                            $output[] = $val;
                            $mode = 2; // najit konec
                        }

                    } else {
                        // normalni znak
                        if ($escaped) {
                            // escapovany normalni znak
                            $val .= $esc;
                            $escaped = false;
                        }
                        $val .= $char;
                    }

                } else {

                    // mimo uvozovky
                    if ($char === $sep || $i === $last) {
                        // konec hodnoty
                        $ws_buffer = '';
                        if ($i === $last) {
                            $val .= $char;
                        }

                        // detekovat klicova slova
                        if (isset($keywords[$val])) {
                            if (0 === $keywords[$val]) {
                                $val = null;
                            } else {
                                $val = $keywords[$val];
                            }
                        }

                        $output[] = $val;
                        $mode = 0;
                    } elseif (isset($ws[$char])) {
                        // bile znaky
                        $ws_buffer .= $char;
                    } else {
                        // normal znak
                        if ($ws_buffer !== '') {
                            // vyprazdnit buffer bilych znaku
                            $val .= $ws_buffer;
                            $ws_buffer = '';
                        }
                        $val .= $char;
                    }

                }

                break;

                /* ----  najit konec argumentu  ---- */
            case 2:
                if ($char === $sep) {
                    $mode = 0;
                }
                break;

        }

    }

    // vystup
    return $output;
}

/**
 * Overit, zda adresa neobsahuje skodlivy kod
 *
 * @param string $url adresa
 * @return bool
 */
function _isSafeUrl($url)
{
    return 0 === preg_match('/^[\s\0-\32a-z0-9_\-]+:/i', $url);
}

/**
 * Odstraneni vsech lomitek z konce retezce
 *
 * @param string $string vstupni retezec
 * @return string
 */
function _removeSlashesFromEnd($string)
{
    return rtrim($string, '/');
}

/**
 * Obnovit stav zaskrtnuti na zaklade POST/GET dat
 *
 * @param string $key_var nazev klice, ktery indikuje odeslani daneho formulare
 * @param string $name    nazev checkboxu
 * @param bool   $default vychozi stav
 * @param string $method  POST/GET
 * @return string
 */
function _restoreChecked($key_var, $name, $default = false, $method = 'POST')
{
    if (isset($GLOBALS['_' . $method][$key_var]) && $method === $_SERVER['REQUEST_METHOD']) {
        $active = isset($GLOBALS['_' . $method][$name]);
    } else {
        $active = $default;
    }

    return $active ? ' checked' : '';
}

/**
 * Nastavit nazev prvku a obnovit stav zaskrtnuti na zaklade POST/GET dat
 *
 * @param string $key_var nazev klice, ktery indikuje odeslani daneho formulare
 * @param string $name    nazev checkboxu
 * @param bool   $default vychozi stav
 * @param string $method  POST/GET
 * @return string
 */
function _restoreCheckedAndName($key_var, $name, $default = false, $method = 'POST')
{
    return ' name="' . $name . '"' . _restoreChecked($key_var, $name, $default, $method);
}

/**
 * Obnoveni hodnoty prvku podle stavu $_POST
 *
 * @param string      $name          nazev klice v post
 * @param string|null $else          vychozi hodnota
 * @param bool        $param         vykreslit jako atribut ' value=".."' 1/0
 * @param bool        $else_entities escapovat hodnotu $else 1/0
 * @return string
 */
function _restorePostValue($name, $else = null, $param = true, $else_entities = true)
{
    return _restoreValue('_POST', $name, $else, $param, $else_entities);
}

/**
 * Nastaveni nazvu prvku a obnoveni hodnoty z $_POST
 *
 * @param string      $name          nazev klice
 * @param string|null $else          vychozi hodnota
 * @param bool        $else_entities escapovat hodnotu $else 1/0
 * @return string
 */
function _restorePostValueAndName($name, $else = null, $else_entities = true)
{
    return ' name="' . $name . '"' . _restorePostValue($name, $else, true, $else_entities);
}

/**
 * Obnoveni hodnoty prvku podle stavu $_GET
 *
 * @param string      $name          nazev klice
 * @param string|null $else          vychozi hodnota
 * @param bool        $param         vykreslit jako atribut ' value=".."' 1/0
 * @param bool        $else_entities escapovat hodnotu $else 1/0
 * @return string
 */
function _restoreGetValue($name, $else = null, $param = true, $else_entities = true)
{
    return _restoreValue('_GET', $name, $else, $param, $else_entities);
}

/**
 * Nastaveni nazvu prvku a obnoveni hodnoty z $_GET
 *
 * @param string      $name          nazev klice
 * @param string|null $else          vychozi hodnota
 * @param bool        $else_entities escapovat hodnotu $else 1/0
 * @return string
 */
function _restoreGetValueAndName($name, $else = null, $else_entities = true)
{
    return ' name="' . $name . '"' . _restoreGetValue($name, $else, true, $else_entities);
}

/**
 * Obnoveni hodnoty prvku na zaklade globalni promenne
 *
 * @param string      $var           nazev globalni promenne (_GET, _POST, ..)
 * @param string      $name          nazev klice
 * @param string|null $else          vychozi hodnota
 * @param bool        $param         vykreslit jako atribut ' value=".."' 1/0
 * @param bool        $else_entities escapovat hodnotu $else 1/0
 * @return string
 */
function _restoreValue($var, $name, $else = null, $param = true, $else_entities = true)
{
    if (isset($GLOBALS[$var][$name]) && is_scalar($GLOBALS[$var][$name])) {
        $value = _e((string) $GLOBALS[$var][$name]);
    } else {
        $value = ($else_entities ? _e($else) : $else);
    }

    if ($param) {
        if (null !== $value && '' !== $value) {
            return ' value="' . $value . '"';
        } else {
            return '';
        }
    } else {
        return $value;
    }
}

/**
 * Ziskat hodnotu z $_GET
 *
 * @param string $key         klic
 * @param mixed  $default     vychozi hodnota
 * @param bool   $allow_array povolit pole 1/0
 * @return mixed
 */
function _get($key, $default = null, $allow_array = false)
{
    if (isset($_GET[$key]) && ($allow_array || !is_array($_GET[$key]))) {
        return $_GET[$key];
    }

    return $default;
}

/**
 * Ziskat hodnotu z $_POST
 *
 * @param string $key         klic
 * @param mixed  $default     vychozi hodnota
 * @param bool   $allow_array povolit pole 1/0
 * @return mixed
 */
function _post($key, $default = null, $allow_array = false)
{
    if (isset($_POST[$key]) && ($allow_array || !is_array($_POST[$key]))) {
        return $_POST[$key];
    }

    return $default;
}

/**
 * Vykreslit aktualni POST data jako serii skrytych formularovych prvku
 *
 * XSRF token je automaticky vynechan.
 *
 * Pro vysvetleni parametru viz {@see _arrayFilter()}
 *
 * @param string|null $include
 * @param string|null $exclude
 * @param array       $excludeList
 * @return string
 */
function _renderHiddenPostInputs($include = null, $exclude = null, array $excludeList = array())
{
    $excludeList[] = '_security_token';

    return _renderHiddenInputs(_arrayFilter($_POST, $include, $exclude, $excludeList));
}

/**
 * Vykreslit dana data jako serii skrytych formularovych prvku
 *
 * @param array $data data
 * @return string
 */
function _renderHiddenInputs(array $data)
{
    $output = '';
    $counter = 0;

    foreach ($data as $key => $value) {
        if ($counter > 0) {
            $output .= "\n";
        }
        $output .= _renderHiddenInput($key, $value);
        ++$counter;
    }

    return $output;
}

/**
 * Vykreslit 1 nebo vice skrytych prvku formulare pro danou hodnotu
 *
 * @param string $key   aktualni klic
 * @param mixed  $value hodnota
 * @param array  $pkeys nadrazene klice
 * @return string
 */
function _renderHiddenInput($key, $value, array $pkeys = array())
{
    if (is_array($value)) {
        // pole
        $output = '';
        $counter = 0;

        foreach($value as $vkey => $vvalue) {
            if ($counter > 0) {
                $output .= "\n";
            }
            $output .= _renderHiddenInput($key, $vvalue, array_merge($pkeys, array($vkey)));
            ++$counter;
        }

        return $output;
    } else {
        // hodnota
        $name = _e($key);
        if (!empty($pkeys)) {
            $name .= _e('[' . implode('][', $pkeys) . ']');
        }

        return "<input type='hidden' name='" . $name . "' value='" . _e($value) . "'>";
    }
}

/**
 * Validace e-mailove adresy
 *
 * @param string $email e-mailova adresa
 * @return bool
 */
function _validateEmail($email)
{
    $isValid = true;
    $atIndex = mb_strrpos($email, '@');
    if (mb_strlen($email) > 255) {
        $isValid = false;
    } elseif (is_bool($atIndex) && !$atIndex) {
        $isValid = false;
    } else {
        $domain = mb_substr($email, $atIndex + 1);
        $local = mb_substr($email, 0, $atIndex);
        $localLen = mb_strlen($local);
        $domainLen = mb_strlen($domain);
        if ($localLen < 1 || $localLen > 64) {
            // local part length exceeded
            $isValid = false;
        } else
            if ($domainLen < 1 || $domainLen > 255) {
                // domain part length exceeded
                $isValid = false;
            } else
                if ($local[0] == '.' || $local[$localLen - 1] == '.') {
                    // local part starts or ends with '.'
                    $isValid = false;
                } else
                    if (preg_match('/\\.\\./', $local)) {
                        // local part has two consecutive dots
                        $isValid = false;
                    } else
                        if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                            // character not valid in domain part
                            $isValid = false;
                        } else
                            if (preg_match('/\\.\\./', $domain)) {
                                // domain part has two consecutive dots
                                $isValid = false;
                            } else
                                if (!preg_match('/^[A-Za-z0-9\\-\\._]+$/', $local)) {
                                    // character not valid in local part
                                    $isValid = false;
                                }
        if (!_dev && function_exists('checkdnsrr')) {
            if ($isValid && !(checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A'))) {
                // domain not found in DNS
                $isValid = false;
            }
        }
    }

    return $isValid;
}

/**
 * Kontrola, zda je zadana URL (v absolutnim tvaru zacinajici http:// nebo https://) platna
 *
 * @param string $url adresa
 * @return bool
 */
function _validateURL($url)
{
    return (preg_match('|^https?:\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6}((:[0-9]{1,5})?\/.*)?$|i', $url) === 1);
}

/**
 * Odstraneni nezadoucich odradkovani a mezer z retezce
 *
 * @param string $string vstupni retezec
 * @return string
 */
function _wsTrim($string)
{
    $from = array("|(\r\n){3,}|s", "|  +|s");
    $to = array("\r\n\r\n", " ");

    return preg_replace($from, $to, trim($string));
}

/**
 * Zjistit maximalni moznou celkovou velikost uploadu
 *
 * @return int|null cislo v bajtech nebo null (= neznamo)
 */
function _getUploadLimit()
{
    static $result = null;
    if (!isset($result)) {
        $limit_lowest = null;
        $opts = array('upload_max_filesize', 'post_max_size', 'memory_limit');
        for ($i = 0; isset($opts[$i]); ++$i) {
            $limit = _phpIniLimit($opts[$i]);
            if (isset($limit) && (!isset($limit_lowest) || $limit < $limit_lowest)) {
                $limit_lowest = $limit;
            }
        }
        if (isset($limit_lowest)) {
            $result = $limit_lowest;
        } else {
            $result = null;
        }
    }

    return $result;
}

/**
 * Vykreslit upozorneni na max. velikost uploadu
 *
 * @return string HTML
 */
function _renderUploadLimit()
{
    $limit = _getUploadLimit();
    if (null !== $limit) {
        return '<small>' . $GLOBALS['_lang']['global.uploadlimit'] . ': <em>' . _formatFilesize($limit) . '</em></small>';
    } else {
        return '';
    }
}

/**
 * Zjistit datovy limit dane konfiguracni volby PHP
 *
 * @param string $opt nazev option
 * @return number|null cislo v bajtech nebo null (= neomezeno)
 */
function _phpIniLimit($opt)
{
    // get ini value
    $value = ini_get($opt);

    // check value
    if (!$value || -1 == $value) {
        // no limit?
        return null;
    }

    // extract type, process number
    $suffix = substr($value, -1);
    $value = (int) $value;

    // parse ini value
    switch ($suffix) {
        case 'M':
        case 'm':
            $value *= 1048576;
            break;
        case 'K':
        case 'k':
            $value *= 1024;
            break;
        case 'G':
        case 'g':
            $value *= 1073741824;
            break;
    }

    // return
    return $value;
}

/**
 * Pokusit se detekovat, zda-li bezi tato instalace systemu pod webserverem Apache
 *
 * @return bool
 */
function _isApache()
{
    return
        false !== mb_stripos(php_sapi_name(), 'apache')
        || isset($_SERVER['SERVER_SOFTWARE']) && false !== mb_stripos($_SERVER['SERVER_SOFTWARE'], 'apache')
    ;
}

/**
 * Vytvorit docasny soubor v system/tmp
 *
 * @return TemporaryFile
 */
function _tmpFile()
{
    return new TemporaryFile(null, _root . 'system/tmp');
}

/**
 * Zjistit zda je den podle casu vychozu a zapadu slunce
 *
 * @param int|null $time      timestamp nebo null (= aktualni)
 * @param bool     $get_times navratit casy misto vyhodnoceni, ve formatu array(time, sunrise, sunset)
 * @return bool|array
 */
function _isDayTime($time = null, $get_times = false)
{
    // priprava casu
    if (!isset($time)) {
        $time = time();
    }
    $sunrise = date_sunrise($time, SUNFUNCS_RET_TIMESTAMP, 50.5, 14.26, 90.583333, date('Z') / 3600);
    $sunset = date_sunset($time, SUNFUNCS_RET_TIMESTAMP, 50.5, 14.26, 90.583333, date('Z') / 3600);

    // navrat vysledku
    if ($get_times) {
        return array($time, $sunrise, $sunset);
    }
    if ($time >= $sunrise && $time < $sunset) {
        return true;
    }
    return false;
}

/**
 * Zjistit, zda je nazev souboru bezpecny
 *
 * @param string $fname nazev souboru
 * @return bool
 */
function _isSafeFile($fname)
{
    if (preg_match('/\.([^.]+)(?:\..+)?$/s', trim($fname), $match)) {
        return !in_array(mb_strtolower($match[1]), Core::$dangerousServerSideExt, true);
    }

    return true;
}

/**
 * Sestavit kod inputu pro vyber casu
 *
 * @param string        $name             identifikator casove hodnoty
 * @param int|null|bool $timestamp        cas, -1 (= aktualni) nebo null (= nevyplneno)
 * @param bool          $updatebox        zobrazit checkbox pro nastaveni na aktualni cas pri ulozeni
 * @param bool          $updateboxchecked zaskrtnuti checkboxu 1/0
 * @return string
 */
function _editTime($name, $timestamp = null, $updatebox = false, $updateboxchecked = false)
{
    global $_lang;

    $output = Extend::buffer('fc.edit_time', array(
        'timestamp' => $timestamp,
        'updatebox' => $updatebox,
        'updatebox_checked' => $updateboxchecked,
    ));

    if ('' === $output) {
        if (-1 === $timestamp) {
            $timestamp = time();
        }
        if (null !== $timestamp) {
            $timestamp = getdate($timestamp);
        } else {
            $timestamp = array('seconds' => '', 'minutes' => '', 'hours' => '', 'mday' => '', 'mon' => '', 'year' => '');
        }
        $output .= "<input type='text' size='1' maxlength='2' name='{$name}[tday]' value='" . $timestamp['mday'] . "'>.<input type='text' size='1' maxlength='2' name='{$name}[tmonth]' value='" . $timestamp['mon'] . "'> <input type='text' size='3' maxlength='4' name='{$name}[tyear]' value='" . $timestamp['year'] . "'> <input type='text' size='1' maxlength='2' name='{$name}[thour]' value='" . $timestamp['hours'] . "'>:<input type='text' size='1' maxlength='2' name='{$name}[tminute]' value='" . $timestamp['minutes'] . "'>:<input type='text' size='1' maxlength='2' name='{$name}[tsecond]' value='" . $timestamp['seconds'] . "'> <small>" . $_lang['time.help'] . "</small>";
        if ($updatebox) {
            $output .= " <label><input type='checkbox' name='{$name}[tupdate]' value='1'" . _checkboxActivate($updateboxchecked) . "> " . $_lang['time.update'] . "</label>";
        }
    }

    return $output;
}

/**
 * Nacist casovou hodnotu vytvorenou a odeslanou pomoci {@see _editTime()}
 *
 * @param string $name    identifikator casove hodnoty
 * @param int    $default vychozi casova hodnota pro pripad chyby
 * @return int|null
 */
function _loadTime($name, $default = null)
{
    $result = Extend::fetch('fc.load_time', array(
        'name' => $name,
        'default' => $default,
    ));

    if (null === $result) {
        if (!isset($_POST[$name]) || !is_array($_POST[$name])) {
            $result = $default;
        } elseif (!isset($_POST[$name]['tupdate'])) {
            $day = (int) $_POST[$name]['tday'];
            $month = (int) $_POST[$name]['tmonth'];
            $year = (int) $_POST[$name]['tyear'];
            $hour = (int) $_POST[$name]['thour'];
            $minute = (int) $_POST[$name]['tminute'];
            $second = (int) $_POST[$name]['tsecond'];
            if (checkdate($month, $day, $year) && $hour >= 0 && $hour < 24 && $minute >= 0 && $minute < 60 && $second >= 0 && $second < 60) {
                $result = mktime($hour, $minute, $second, $month, $day, $year);
            } else {
                $result =  $default;
            }
        } else {
            $result = time();
        }
    }

    return $result;
}

/**
 * Sestavit HTML pro vlozeni CSS a JS do <head>
 *
 * Parametry v $assets:
 * ------------------------------------------------------
 * css          pole s cestami k css souborum
 * js           pole s cestami k js souborum
 * css_before   html vlozene pred css
 * css_after    html vlozene za css
 * js_before    html vlozene pred js
 * js_after     html vlozene za js
 * extend_event nazev extend udalosti pro toto sestaveni
 *
 * @param array $assets pole s konfiguraci assetu
 * @return string
 */
function _headAssets(array $assets)
{
    $html = '';
    $cacheParam = '_' . _cacheid;

    // vychozi hodnoty
    $assets += array(
        'meta' => '',
        'css' => array(),
        'js' => array(),
        'css_before' => '',
        'css_after' => '',
        'js_before' => '',
        'js_after' => '',
    );

    // extend udalost
    if (isset($assets['extend_event'])) {
        Extend::call($assets['extend_event'], array(
            'meta' => &$assets['meta'],
            'css' => &$assets['css'],
            'js' => &$assets['js'],
            'css_before' => &$assets['css_before'],
            'css_after' => &$assets['css_after'],
            'js_before' => &$assets['js_before'],
            'js_after' => &$assets['js_after'],
        ));
    }

    // meta
    $html .= $assets['meta'];

    // css
    $html .= $assets['css_before'];
    foreach ($assets['css'] as $item) {
        $html .= "\n<link rel=\"stylesheet\" href=\"" . _addGetToLink($item, $cacheParam) . "\" type=\"text/css\">";
    }
    $html .= $assets['css_after'];

    // javascript
    $html .= $assets['js_before'];
    foreach ($assets['js'] as $item) {
        $html .= "\n<script type=\"text/javascript\" src=\"" . _addGetToLink($item, $cacheParam) . "\"></script>";
    }
    $html .= $assets['js_after'];

    return $html;
}

/**
 * Vykreslit seznam informaci
 *
 * Kazda polozka musi byt pole o 1 nebo 2 prvcich:
 *
 *      array(obsah) nebo array(popisek, obsah)
 *
 * @param array[] $infos
 * @param string  $class
 * @return string
 */
function _renderInfos(array $infos, $class = 'list-info')
{
    if (!empty($infos)) {
        $output = '<ul class="' . _e($class) . "\"\n>";
        foreach ($infos as $info) {
            if (isset($info[1])) {
                $output .= "<li><strong>{$info[0]}:</strong> {$info[1]}</li>\n";
            } else {
                $output .= "<li>{$info[0]}</li>\n";
            }
        }
        $output .= "</ul>\n";

        return $output;
    }

    return '';
}

/**
 * Vytvoreni nahledu clanku pro vypis
 *
 * @param array    $art       pole s daty clanku vcetne cat_slug a data uzivatele z {@see _userQuery()}
 * @param array    $userQuery vystup funkce {@see _userQuery()}
 * @param bool     $info      vypisovat radek s informacemi 1/0
 * @param bool     $perex     vypisovat perex 1/0
 * @param int|null pocet      komentaru (null = nezobrazi se)
 * @return string
 */
function _articlePreview(array $art, array $userQuery, $info = true, $perex = true, $comment_count = null)
{
    // extend
    $extendOutput = Extend::buffer('article.preview', array(
        'art' => $art,
        'user_query' => $userQuery,
        'info' => $info,
        'perex' => $perex,
        'comment_count' => $comment_count,
    ));
    if ('' !== $extendOutput) {
        return $extendOutput;
    }

    global $_lang;

    $output = "<div class='list-item article-preview'>\n";

    // titulek
    $link = _linkArticle($art['id'], $art['slug'], $art['cat_slug']);
    $output .= "<h2 class='list-title'><a href='" . $link . "'>" . $art['title'] . "</a></h2>\n";

    // perex a obrazek
    if ($perex == true) {
        if (isset($art['picture_uid'])) {
            $thumbnail = _pictureThumb(
                _pictureStorageGet(_root . 'images/articles/', null, $art['picture_uid'], 'jpg'),
                array(
                    'mode' => 'fit',
                    'x' => _article_pic_thumb_w,
                    'y' => _article_pic_thumb_h,
                )
            );
        } else {
            $thumbnail = null;
        }

        $output .= "<div class='list-perex'>" . (null !== $thumbnail ? "<a href='" . _e($link) . "'><img class='list-perex-image' src='" . _e(_linkFile($thumbnail)) . "' alt='" . $art['title'] . "'></a>" : '') . $art['perex'] . "</div>\n";
    }

    // info
    if ($info == true) {

        $infos = array(
            array($_lang['article.author'], _linkUserFromQuery($userQuery, $art)),
            array($_lang['article.posted'], _formatTime($art['time'], 'article')),
            array($_lang['article.readnum'], $art['readnum'] . 'x'),
        );

        if ($art['comments'] == 1 && _comments && $comment_count !== null) {
            $infos[] = array($_lang['article.comments'], $comment_count);
        }

        Extend::call('article.preview.infos', array(
            'art' => $art,
            'user_query' => $userQuery,
            'perex' => $perex,
            'comment_count' => $comment_count,
            'infos' => &$infos,
        ));

        $output .= _renderInfos($infos);
    } elseif ($perex && isset($art['picture_uid'])) {
        $output .= "<div class='cleaner'></div>\n";
    }

    $output .= "</div>\n";

    return $output;
}

/**
 * Sestavit kod obrazku v galerii
 *
 * @param array       $img        pole s daty obrazku
 * @param string|null $lightboxid sid lightboxu nebo null (= nepouzivat)
 * @param int|null    $width      pozadovana sirka nahledu
 * @param int|null    $height     pozadovana vyska nahledu
 * @return string
 */
function _galleryImage($img, $lightboxid, $width, $height)
{
    if (_isAbsoluteUrl($img['full'])) {
        $fullUrl = $img['full'];
        $fullFile = null;
    } else {
        $fullUrl = _link($img['full']);
        $fullFile = _root . $img['full'];
    }

    if (!empty($img['prev'])) {
        if (_isAbsoluteUrl($img['prev'])) {
            $prevUrl = $img['prev'];
        } else {
            $prevUrl = _link($img['prev']);
        }
    } elseif (null !== $fullFile) {
        $prevUrl = _linkFile(_pictureThumb($fullFile, array('x' => $width, 'y' => $height)));
    } else {
        $prevUrl = $fullUrl;
    }

    if ($img['title']) {
        $alt = $img['title'];
    } elseif ($fullFile) {
        $alt = basename($fullFile);
    } else {
        $alt = basename(Url::parse($fullUrl)->path);
    }

    return '<a'
            . ' href="' . _e($fullUrl) . '" target="_blank"'
            . (isset($lightboxid) ? ' class="lightbox" data-gallery-group="lb_' . _e($lightboxid) . '"' : '')
            . (($img['title']) ? ' title="' . _e($img['title']) . '"' : '')
        . '>'
        . '<img'
            . ' src="' . _e($prevUrl) . '"'
            . ' alt="' . _e($alt) . '"'
        . '>'
        . "</a>\n"
    ;
}

/**
 * Inicializace captchy
 *
 * @return array radek pro funkci {@see _formOutput()}
 */
function _captchaInit()
{
    static $captchaCounter = 0;

    $output = Extend::fetch('captcha.init');
    if (null !== $output) {
        return $output;
    }

    if (_captcha && !_login) {
        global $_lang;
        ++$captchaCounter;
        if (!isset($_SESSION['captcha_code']) || !is_array($_SESSION['captcha_code'])) {
            $_SESSION['captcha_code'] = array();
        }
        $_SESSION['captcha_code'][$captchaCounter] = array(StringGenerator::generateCaptchaCode(8), false);

        return array(
            'label' => $_lang['captcha.input'],
            'content' => "<input type='text' name='_cp' class='inputc'><img src='" . _link('system/script/captcha/image.php?n=' . $captchaCounter) . "' alt='captcha' title='" . $_lang['captcha.help'] . "' class='cimage'><input type='hidden' name='_cn' value='" . $captchaCounter . "'>",
            'top' => true,
            'class' => 'captcha-row',
        );
    } else {
        return array();
    }
}

/**
 * Zkontrolovat vyplneni captcha obrazku
 *
 * @return bool
 */
function _captchaCheck()
{
    $result = Extend::fetch('captcha.check');

    if (null === $result) {
        // pole pro nahradu matoucich znaku
        $disambiguation = array(
            '0' => 'O',
            'Q' => 'O',
            'D' => 'O',
            '1' => 'I',
            '6' => 'G',
        );

        // kontrola
        if (_captcha and !_login) {
            $enteredCode = _post('_cp');
            $captchaId = _post('_cn');

            if (null !== $enteredCode && isset($_SESSION['captcha_code'][$captchaId])) {
                if (strtr($_SESSION['captcha_code'][$captchaId][0], $disambiguation) === strtr(mb_strtoupper($enteredCode), $disambiguation)) {
                    $result = true;
                }
                unset($_SESSION['captcha_code'][$captchaId]);
            }
        } else {
            $result = true;
        }
    }

    Extend::call('captcha.check.post', array('output' => &$result));

    return $result;
}

/**
 * Odstraneni uzivatele
 *
 * @param int $id id uzivatele
 * @return bool
 */
function _deleteUser($id)
{
    // nacist jmeno
    if ($id == _super_admin_id) {
        return false;
    }
    $udata = DB::queryRow("SELECT username,avatar FROM " . _users_table . " WHERE id=" . $id);
    if ($udata === false) {
        return false;
    }

    // udalost
    $allow = true;
    Extend::call('user.delete', array('id' => $id, 'username' => $udata['username'], 'allow' => &$allow));
    if (!$allow) {
        return false;
    }

    // vyresit vazby
    DB::delete(_users_table, 'id=' . $id);
    DB::query("DELETE " . _pm_table . ",post FROM " . _pm_table . " LEFT JOIN " . _posts_table . " AS post ON (post.type=" . _post_pm . " AND post.home=" . _pm_table . ".id) WHERE receiver=" . $id . " OR sender=" . $id);
    DB::update(_posts_table, 'author=' . $id, array(
        'guest' => $udata['username'],
        'author' => -1
    ));
    DB::update(_articles_table, 'author=' . $id, array('author' => 0));
    DB::update(_polls_table, 'author=' . $id, array('author' => 0));

    // odstraneni uploadovaneho avataru
    if (isset($udata['avatar'])) {
        @unlink(_root . 'images/avatars/' . $udata['avatar'] . '.jpg');
    }

    return true;
}

/**
 * Zformatovat ciselnout hodnotu na zaklade aktualni lokalizace
 *
 * @param number $number
 * @param int    $decimals
 * @return string
 */
function _formatNumber($number, $decimals = 2)
{
    global $_lang;

    if (is_int($number) || $decimals <= 0 || abs(fmod($number, 1)) < pow(0.1, $decimals)) {
        // an integer value
        return number_format($number, 0, '', $_lang['numbers.thousands_sep']);
    } else {
        // a float value
        return number_format($number, $decimals, $_lang['numbers.dec_point'], $_lang['numbers.thousands_sep']);
    }
}

/**
 * Zformatovat timestamp na zaklade nastaveni systemu
 *
 * @param number      $timestamp UNIX timestamp
 * @param string|null $category  kategorie casu (null, article, post, activity)
 * @return string
 */
function _formatTime($timestamp, $category = null)
{
    $extend = Extend::buffer('fc.format_time', array(
        'timestamp' => $timestamp,
        'category' => $category
    ));

    if ('' !== $extend) {
        return $extend;
    } else {
        return date(_time_format, $timestamp);
    }
}

/**
 * Zformatovat velikost souboru
 *
 * @param int $bytes
 * @return string
 */
function _formatFilesize($bytes)
{
    $units = array('B', 'kB', 'MB');

    for ($i = 2; $i >= 0; --$i) {
        $bytesPerUnit = pow(1000, $i);
        $value = $bytes / $bytesPerUnit;
        if ($value >= 1 || 0 === $i) {
            break;
        }
    }

    return _formatNumber(2 === $i ? $value : ceil($value)) . ' ' . $units[$i];
}

/**
 * Systemovy box se zpravou
 *
 * @param string $type   typ zpravy, hodnota konstanty _msg_ok, _msg_warn nebo _msg_err
 * @param string $string text zpravy
 * @param bool   $isHtml text zpravy je HTML 1/0
 * @return string
 */
function _msg($type, $string, $isHtml = true)
{
    return (string) new Message($type, $string, $isHtml);
}

/**
 * Sestavit formatovany seznam zprav
 *
 * @param array       $messages    zpravy
 * @param string|null $description popis ("errors" = $_lang['misc.errorlog.intro'], null = zadny, cokoliv jineho = vlastni)
 * @param bool        $showKeys    vykreslit take klice z pole $messages
 * @return string
 */
function _msgList($messages, $description = null, $showKeys = false)
{
    $output = '';

    if (!empty($messages)) {
        // popis
        if ($description != null) {
            if ($description !== 'errors') {
                $output .= $description;
            } else {
                $output .= $GLOBALS['_lang']['misc.errorlog.intro'];
            }
            $output .= "\n";
        }

        // zpravy
        $output .= "<ul>\n";
        foreach($messages as $key => $item) {
            $output .= '<li>' . ($showKeys ? '<strong>' . _e($key) . '</strong>: ' : '') . $item . "</li>\n";
        }
        $output .= "</ul>\n";
    }

    return $output;
}

/**
 * Sestaveni formulare
 *
 *
 * Mozne klice v $options:
 * -----------------------
 * name (-)             name atribut formulare
 * method (post)        method atribut formulare
 * action (-)           action atribut formulare
 * autocomplete (1)     autocomplete atribut formulare (on/off)
 * enctype (-)          enctype atribut formulare
 * multipart (0)        nastavit enctype na "multipart/form-data"
 * id (-)               id atribut formulare
 * class (-)            class atribut formulare
 * embedded (0)         nevykreslovat <form> tag ani submit radek 1/0
 *
 * submit_text (*)      popisek odesilaciho tlacitka (vychozi je $_lang['global.send'])
 * submit_append (-)    vlastni HTML vlozene za odesilaci tlacitko
 * submit_span (0)      roztahnout bunku s odesilacim tlacitkem na cely radek (tzn. zadna mezera vlevo)
 * submit_name (-)      name atribut odesilaciho tlacitka
 * submit_row (-)       1 vlastni pole s atributy radku s odesilacim tlacitkem (viz format zaznamu v $rows)
 *                      uvedeni teto volby potlaci submit_text, submit_span a submit_name
 *
 * table_append         vlastni HTML vlozene pred </table>
 * form_append          vlastni HTML vlozene pred </form>
 *
 * Format $cells:
 * --------------
 *  array(
 *      array(
 *          label       => popisek
 *          content     => obsah
 *          top         => zarovnani nahoru 1/0
 *          class       => class atribut pro <tr>
 *      ),
 *      ...
 *  )
 *
 * - radek je preskocen, pokud je obsah i popisek prazdny
 * - pokud je label null, zabere bunka s obsahem cely radek
 *
 * @param array $options parametry formulare (viz popis funkce)
 * @param array $rows    pole s radky (viz popis funkce)
 * @return string
 */
function _formOutput(array $options, array $rows)
{
    // vychozi parametry
    $options += array(
        'name' => null,
        'method' => 'post',
        'action' => null,
        'autocomplete' => null,
        'enctype' => null,
        'multipart' => false,
        'id' => null,
        'class' => null,
        'embedded' => false,
        'submit_text' => $GLOBALS['_lang']['global.send'],
        'submit_append' => '',
        'submit_span' => false,
        'submit_name' => null,
        'submit_row' => null,
        'table_append' => '',
        'form_append' => '',
    );
    if ($options['multipart']) {
        $options['enctype'] = 'multipart/form-data';
    }

    // extend
    $extend_buffer = Extend::buffer('fc.form_output', array(
        'options' => &$options,
        'rows' => &$rows,
    ));

    if ('' !== $extend_buffer) {
        // vykresleni pretizeno
        return $extend_buffer;
    }

    // vykresleni
    $output = '';

    // form tag
    if (!$options['embedded']) {
        $output .= '<form';

        foreach (array('name', 'method', 'action', 'enctype', 'id', 'class', 'autocomplete') as $attr) {
            if (null !== $options[$attr]) {
                $output .= ' ' . $attr . '="' . _e($options[$attr]) . '"';
            }
        }

        $output .= ">\n";
    }

    // zacatek tabulky
    $output .= "<table>\n";

    // radky
    foreach ($rows as $row) {
        $output .= _formOutputRow($row);
    }

    // radek s odesilacim tlacitkem
    if (!$options['embedded']) {
        if (null !== $options['submit_row']) {
            $submit_row = $options['submit_row'];
        } else {
            $submit_row = array(
                'label' => $options['submit_span'] ? null : '',
                'content' => '<input type="submit"' . (!empty($options['submit_name']) ? ' name="' . _e($options['submit_name']) . '"' : '') . ' value="' . _e($options['submit_text']) . '">',
            );
        }
        if (isset($submit_row['content'])) {
            $submit_row['content'] .= $options['submit_append'];
        }
        $output .= _formOutputRow($submit_row);
    } elseif (!empty($options['submit_append'])) {
        $output .= _formOutputRow(array(
            'label' => $options['submit_span'] ? null : '',
            'content' => $options['submit_append'],
        ));
    }

    // konec tabulky
    $output .= $options['table_append'];
    $output .= "</table>\n";

    // konec formulare
    $output .= $options['form_append'];
    if (!$options['embedded']) {
        $output .= _xsrfProtect();
        $output .= "\n</form>\n";
    }

    return $output;
}

/**
 * Vykreslit radek tabulky formulare
 *
 * @param array $row viz $rows v popisu funkce {@see _formOutput()}
 * @return string
 */
function _formOutputRow(array $row)
{
    $row += array(
        'label' => null,
        'content' => null,
        'top' => false,
        'class' => '',
    );
    if ($row['top']) {
        $row['class'] .= ('' !== $row['class'] ? ' ' : '') . 'valign-top';
    }

    // prazdny radek?
    if (empty($row['label']) && empty($row['content'])) {
        return '';
    }

    // zacatek radku
    $output = '<tr' . ('' !== $row['class'] ? " class=\"{$row['class']}\"" : '') . ">\n";

    // popisek
    if (null !== $row['label']) {
        $output .= "<th>{$row['label']}</th>\n";
    }

    // obsah
    $output .= '<td';
    if (null === $row['label']) {
        $output .= ' colspan="2"';
    }
    $output .= ">{$row['content']}</td>\n";

    // konec radku
    $output .= "</tr>\n";

    return $output;
}

/**
 * Vratit pole se jmeny vsech existujicich opravneni
 *
 * @return array
 */
function _getPrivileges()
{
    static $extended = false;
    static $privileges = array(
        'level',
        'administration',
        'adminsettings',
        'adminplugins',
        'adminusers',
        'admingroups',
        'admincontent',
        'adminother',
        'adminroot',
        'adminsection',
        'admincategory',
        'adminbook',
        'adminseparator',
        'admingallery',
        'adminlink',
        'admingroup',
        'adminforum',
        'adminpluginpage',
        'adminart',
        'adminallart',
        'adminchangeartauthor',
        'adminpoll',
        'adminpollall',
        'adminsbox',
        'adminbox',
        'adminconfirm',
        'adminautoconfirm',
        'fileaccess',
        'fileglobalaccess',
        'fileadminaccess',
        'adminhcm',
        'adminhcmphp',
        'adminbackup',
        'adminmassemail',
        'adminposts',
        'changeusername',
        'unlimitedpostaccess',
        'locktopics',
        'stickytopics',
        'movetopics',
        'postcomments',
        'artrate',
        'pollvote',
        'selfremove',
    );

    if (!$extended) {
        Extend::call('user.privileges', array('privileges' => &$privileges));
        $extended = true;
    }

    return $privileges;
}

/**
 * Zjistit, zda uzivatel ma dane pravo
 *
 * @param string $name nazev prava
 * @return bool
 */
function _userHasRight($name)
{
    $constant = '_priv_' . $name;

    return defined($constant) && constant($constant);
}

/**
 * Ziskat domovsky adresar uzivatele
 *
 * Adresar NEMUSI existovat!
 *
 * @param bool $getTopmost ziskat cestu na nejvyssi mozne urovni 1/0
 * @throws RuntimeException nejsou-li prava
 * @return string
 */
function _userHomeDir($getTopmost = false)
{
    if (!_priv_fileaccess) {
        throw new RuntimeException('User has no filesystem access');
    }

    if (_priv_fileglobalaccess) {
        if ($getTopmost && _priv_fileadminaccess) {
            $homeDir = _root;
        } else {
            $homeDir = _upload_dir;
        }
    } else {
        $subPath = 'home/' . _loginname . '/';
        Extend::call('user.home_dir', array('subpath' => &$subPath));
        $homeDir = _upload_dir . $subPath;
    }

    return $homeDir;
}

/**
 * Normalizovat cestu k adresari dle prav uzivatele
 *
 * @param string $dirPath
 * @return string cesta s lomitkem na konci
 */
function _userNormalizeDir($dirPath)
{
    if (
        null !== $dirPath
        && '' !== $dirPath
        && false !== ($dirPath = _userCheckPath($dirPath, false, true))
        && is_dir($dirPath)
    ) {
        return $dirPath;
    } else {
        return _userHomeDir();
    }
}

/**
 * Zjistit, zda ma uzivatel pravo pracovat s danou cestou
 *
 * @param string $path    cesta
 * @param bool   $isFile  jedna se o soubor 1/0
 * @param bool   $getPath vratit zpracovanou cestu v pripade uspechu 1/0
 * @return bool|string true / false nebo cesta, je-li kontrola uspesna a $getPath je true
 */
function _userCheckPath($path, $isFile, $getPath = false)
{
    if (_priv_fileaccess) {
        $path = Filesystem::parsePath($path, $isFile);
        $homeDirPath = Filesystem::parsePath(_userHomeDir(true));

        if (
            /* nepovolit vystup z rootu */                  substr_count($path, '..') <= substr_count(_root, '..')
            /* nepovolit vystup z domovskeho adresare */    && 0 === strncmp($homeDirPath, $path, strlen($homeDirPath))
            /* nepovolit praci s nebezpecnymi soubory */    && (!$isFile || _userCheckFilename(basename($path)))
        ) {
            return $getPath ? $path : true;
        }
    }

    return false;
}

/**
 * Presunout soubor, ktery byl nahran uzivatelem
 *
 * @param string $path
 * @param string $newPath
 * @return bool
 */
function _userMoveUploadedFile($path, $newPath)
{
    $handled = false;

    Extend::call('user.move_uploaded_file', array(
        'path' => $path,
        'new_path' => $newPath,
        'handled' => &$handled,
    ));

    return $handled || move_uploaded_file($path, $newPath);
}

/**
 * Zjistit, zda ma uzivatel pravo pracovat s danym nazvem souboru
 *
 * Tato funkce kontroluje NAZEV souboru, nikoliv cestu!
 * Pro cesty je funkce {@see _userCheckPath()}.
 *
 * @param string $filename
 * @return bool
 */
function _userCheckFilename($filename)
{
    return
        _priv_fileaccess
        && (
            _priv_fileadminaccess
            || _isSafeFile($filename)
        )
    ;
}

/**
 * Sestavit casti dotazu pro nacteni dat uzivatele
 *
 * Struktura navratove hodnoty:
 *
 * array(
 *      columns => array(a,b,c,...),
 *      column_list => "a,b,c,...",
 *      joins => "JOIN ...",
 *      alias => "...",
 *      prefix = "...",
 * )
 *
 * @param string|null $joinUserIdColumn nazev sloupce obsahujici ID uzivatele nebo NULL (= nejoinovat)
 * @param string      $prefix           predpona pro nazvy nacitanych sloupcu
 * @param string      $alias            alias joinovane user tabulky
 * @param mixed       $emptyValue       hodnota, ktera reprezentuje neurceneho uzivatele
 * @return array viz popis funkce
 */
function _userQuery($joinUserIdColumn = null, $prefix = 'user_', $alias = 'u', $emptyValue = -1)
{
    $groupAlias = "{$alias}g";

    // pole sloupcu
    $columns = array(
        "{$alias}.id" => "{$prefix}id",
        "{$alias}.username" => "{$prefix}username",
        "{$alias}.publicname" => "{$prefix}publicname",
        "{$alias}.group_id" => "{$prefix}group_id",
        "{$alias}.avatar" => "{$prefix}avatar",
        "{$groupAlias}.title" => "{$prefix}group_title",
        "{$groupAlias}.icon" => "{$prefix}group_icon",
        "{$groupAlias}.level" => "{$prefix}group_level",
        "{$groupAlias}.color" => "{$prefix}group_color",
    );

    // joiny
    $joins = array();
    if (null !== $joinUserIdColumn) {
        $joins[] = 'LEFT JOIN ' . _users_table . " {$alias} ON({$joinUserIdColumn}" . DB::notEqual($emptyValue) . " AND {$joinUserIdColumn}={$alias}.id)";
    }
    $joins[] = 'LEFT JOIN ' . _groups_table . " {$groupAlias} ON({$groupAlias}.id={$alias}.group_id)";

    // extend
    Extend::call('user.query', array(
        'columns' => &$columns,
        'joins' => &$joins,
        'empty_value' => $emptyValue,
        'prefix' => $prefix,
        'alias' => $alias,
    ));

    // sestavit seznam sloupcu
    $columnList = '';
    $isFirstColumn = true;
    foreach ($columns as $columnName => $columnAlias) {
        if (!$isFirstColumn) {
            $columnList .= ',';
        } else {
            $isFirstColumn = false;
        }

        $columnList .= "{$columnName} {$columnAlias}";
    }

    return array(
        'columns' => array_values($columns),
        'column_list' => $columnList,
        'joins' => implode(' ', $joins),
        'alias' => $alias,
        'prefix' => $prefix,
    );
}

/**
 * Sestavit kod ovladaciho panelu na smajliky a BBCode tagy
 *
 * @param string $form    nazev formulare
 * @param string $area    nazev textarey
 * @param bool   $bbcode  zobrazit BBCode 1/0
 * @param bool   $smileys zobrazit smajliky 1/0
 * @return string
 */
function _getPostFormControls($form, $area, $bbcode = true, $smileys = true)
{
    $template = _getCurrentTemplate();

    $output = '';

    // bbcode
    if ($bbcode && _bbcode && $template->getOption('bbcode.buttons')) {

        // nacteni tagu
        $bbtags = _parseBBCode(null, true);

        // pridani kodu
        $output .= '<span class="post-form-bbcode">';
        foreach ($bbtags as $tag => $vars) {
            if (!isset($vars[4])) {
                // tag bez tlacitka
                continue;
            }
            $icon = (($vars[4] === 1) ? _templateImage("bbcode/" . $tag . ".png") : $vars[4]);
            $output .= "<a class=\"bbcode-button post-form-bbcode-{$tag}\" href=\"#\" onclick=\"return Sunlight.addBBCode('" . $form . "','" . $area . "','" . $tag . "', " . ($vars[0] ? 'true' : 'false') . ");\" class='bbcode-button'><img src=\"" . $icon . "\" alt=\"" . $tag . "\"></a>\n";
        }
        $output .= '</span>';
    }

    // smajlici
    if ($smileys && _smileys) {
        $smiley_count = $template->getOption('smiley.count');
        $output .= '<span class="post-form-smileys">';
        for($x = 1; $x <= $smiley_count; ++$x) {
            $output .= "<a href=\"#\" onclick=\"return Sunlight.addSmiley('" . $form . "','" . $area . "'," . $x . ");\" class='smiley-button'><img src=\"" . $template->getWebPath() . '/images/smileys/' . $x . '.' . $template->getOption('smiley.format') . "\" alt=\"" . $x . "\" title=\"" . $x . "\"></a> ";
        }
        $output .= '</span>';
    }

    return "<span class='posts-form-buttons'>" . trim($output) . "</span>";
}

/**
 * Sestavit kod tlacitka pro nahled prispevku
 *
 * @param string $form nazev formulare
 * @param string $area nazev textarey
 * @return string
 */
function _getPostFormPreviewButton($form, $area)
{
    return '<button class="post-form-preview" onclick="Sunlight.postPreview(this, \'' . $form . '\', \'' . $area . '\'); return false;">' . $GLOBALS['_lang']['global.preview'] . '</button>';
}

/**
 * Zkontrolovat log IP adres
 *
 * Typ  Popis                   Var
 *
 * 1    prihlasení              -
 * 2    prectení clanku         id clanku
 * 3    hodnoceni clanku        id clanku
 * 4    hlasování v ankete      id ankety
 * 5    zaslani pozadavku       -
 * 6    pokus o aktivaci uctu   -
 * 7    zadost o obnovu hesla   -
 * 8+   vlastni typ             hodnota, ktera ma byt nalezena
 *
 * @param int      $type    typ zaznamu
 * @param mixed    $var     promenny argument dle typu
 * @param int|null $expires doba expirace zaznamu v sekundach pro typ 8+
 * @return bool
 */
function _iplogCheck($type, $var = null, $expires = null)
{
    $type = (int) $type;
    if (null !== $var) {
        $var = (int) $var;
    }

    // vycisteni iplogu
    static $cleaned = array(
        'system' => false,
        'custom' => array(),
    );
    if ($type < _iplog_password_reset_requested) {
        if (!$cleaned['system']) {
            DB::query("DELETE FROM " . _iplog_table . " WHERE (type=1 AND " . time() . "-time>" . _maxloginexpire . ") OR (type=2 AND " . time() . "-time>" . _artreadexpire . ") OR (type=3 AND " . time() . "-time>" . _artrateexpire . ") OR (type=4 AND " . time() . "-time>" . _pollvoteexpire . ") OR (type=5 AND " . time() . "-time>" . _postsendexpire . ") OR (type=6 AND " . time() . "-time>" . _accactexpire . ") OR (type=7 AND " . time() . "-time>" . _lostpassexpire . ")");
            $cleaned['system'] = true;
        }
    } elseif (!isset($cleaned['custom'][$type])) {
        if (null === $expires) {
            throw new InvalidArgumentException('The "expires" argument must be specified for custom types');
        }
        DB::delete(_iplog_table, 'type=' . $type .((null !== $var) ? ' AND var=' . $var : '') . ' AND ' . time() . '-time>' . ((int) $expires));
        $cleaned['custom'][$type] = true;
    }

    // priprava
    $result = true;
    $querybasic = "SELECT * FROM " . _iplog_table . " WHERE ip=" . DB::val(_userip) . " AND type=" . $type;

    switch ($type) {

        case _iplog_failed_login_attempt:
            $query = DB::queryRow($querybasic);
            if ($query !== false) {
                if ($query['var'] >= _maxloginattempts) {
                    $result = false;
                }
            }
            break;

        case _iplog_article_read:
        case _iplog_article_rated:
        case _iplog_poll_vote:
            $query = DB::query($querybasic . " AND var=" . $var);
            if (DB::size($query) != 0) {
                $result = false;
            }
            break;

        case _iplog_anti_spam:
        case _iplog_password_reset_requested:
            $query = DB::query($querybasic);
            if (DB::size($query) != 0) {
                $result = false;
            }
            break;

        case _iplog_failed_account_activation:
            $query = DB::queryRow($querybasic);
            if ($query !== false) {
                if ($query['var'] >= 5) {
                    $result = false;
                }
            }
            break;

        default:
            $query = DB::query($querybasic . ((null !== $var) ? " AND var=" . $var : ''));
            if (DB::size($query) != 0) {
                $result = false;
            }
            break;
    }

    Extend::call('iplog.check', array(
        'type' => $type,
        'var' => $var,
        'result' => &$result,
    ));

    return $result;
}

/**
 * Aktualizace logu IP adres
 * Pro info o argumentech viz {@see _ipLogCheck()}
 *
 * @param int   $type typ zaznamu
 * @param mixed $var  promenny argument dle typu
 */
function _iplogUpdate($type, $var = null)
{
    $type = (int) $type;
    if (null !== $var) {
        $var = (int) $var;
    }

    $querybasic = "SELECT * FROM " . _iplog_table . " WHERE ip=" . DB::val(_userip) . " AND type=" . $type;

    switch ($type) {

        case _iplog_failed_login_attempt:
            $query = DB::queryRow($querybasic);
            if ($query !== false) {
                DB::update(_iplog_table, 'id=' . $query['id'], array('var' => ($query['var'] + 1)));
            } else {
                DB::insert(_iplog_table, array(
                    'ip' => _userip,
                    'type' => _iplog_failed_login_attempt,
                    'time' => time(),
                    'var' => 1
                ));
            }
            break;

        case _iplog_article_read:
            DB::insert(_iplog_table, array(
                'ip' => _userip,
                'type' => _iplog_article_read,
                'time' => time(),
                'var' => $var
            ));
            break;

        case _iplog_article_rated:
            DB::insert(_iplog_table, array(
                'ip' => _userip,
                'type' => _iplog_article_rated,
                'time' => time(),
                'var' => $var
            ));
            break;

        case _iplog_poll_vote:
            DB::insert(_iplog_table, array(
                'ip' => _userip,
                'type' => _iplog_poll_vote,
                'time' => time(),
                'var' => $var
            ));
            break;

        case _iplog_anti_spam:
        case _iplog_password_reset_requested:
            DB::insert(_iplog_table, array(
                'ip' => _userip,
                'type' => $type,
                'time' => time(),
                'var' => 0
            ));
            break;

        case _iplog_failed_account_activation:
            $query = DB::queryRow($querybasic);
            if ($query !== false) {
                DB::update(_iplog_table, 'id=' . $query['id'], array('var' => ($query['var'] + 1)));
            } else {
                DB::insert(_iplog_table, array(
                    'ip' => _userip,
                    'type' => _iplog_failed_account_activation,
                    'time' => time(),
                    'var' => 1
                ));
            }
            break;

        default:
            $query = DB::queryRow($querybasic . ((null !== $var) ? " AND var=" . $var : ''));
            if ($query !== false) {
                DB::update(_iplog_table, 'id=' . $query['id'], array('time' => time()));
            } else {
                DB::insert(_iplog_table, array(
                    'ip' => _userip,
                    'type' => $type,
                    'time' => time(),
                    'var' => $var
                ));
            }
            break;
    }
}

/**
 * Sestavit kod pro limitovani delky textarey javascriptem
 *
 * @param int    $maxlength maximalni povolena delka textu
 * @param string $form      nazev formulare
 * @param string $name      nazev textarey
 * @return string
 */
function _jsLimitLength($maxlength, $form, $name)
{
    return "
<script type='text/javascript'>
//<![CDATA[
$(document).ready(function(){
    var events = ['keyup', 'mouseup', 'mousedown'];
    for (var i = 0; i < events.length; ++i) $(document)[events[i]](function() {
        Sunlight.limitTextarea(document.{$form}.{$name}, {$maxlength});
    });
});

//]]>
</script>
";
}

/**
 * Vyhodnotit pravo pristupu k cilovemu uzivateli
 *
 * @param int $targetUserId    ID ciloveho uzivatele
 * @param int $targetUserLevel uroven skupiny ciloveho uzivatele
 * @return bool
 */
function _levelCheck($targetUserId, $targetUserLevel)
{
    if (_login && (_priv_level > $targetUserLevel || $targetUserId == _loginid)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Nalezt stranku a nacist jeji data
 *
 * Oddelovace jsou ignorovany.
 *
 * @param array  $segments      segmenty
 * @param string $extra_columns sloupce navic (automaticky oddeleno carkou)
 * @param string $extra_joins   joiny navic (automaticky oddeleno mezerou)
 * @param string $extra_conds   podminky navic (automaticky oddeleno pomoci " AND (*conds*)")
 * @return array|bool false pri nenalezeni
 */
function _findPage(array $segments, $extra_columns = null, $extra_joins = null, $extra_conds = null)
{
    // zaklad dotazu
    $sql = 'SELECT page.*';
    if (null !== $extra_columns) {
         $sql .= ',' . $extra_columns;
    }
    $sql .= ' FROM ' . _root_table . ' AS page';
    if (null !== $extra_joins) {
        $sql .= ' ' . $extra_joins;
    }

    // podminky
    $conds = array();

    // ignorovat oddelovace
    $conds[] = 'page.type!=' . _page_separator;

    // predane podminky
    if (null !== $extra_conds) {
        $extra_conds[] = '(' . $conds . ')';
    }

    // identifikator
    if (!empty($segments)) {
        $slugs = array();
        for ($i = sizeof($segments); $i > 0; --$i) {
            $slugs[] = implode('/', array_slice($segments, 0, $i));
        }
        $conds[] = 'page.slug IN(' . DB::arr($slugs) . ')';
    } else {
        $indexPageId = Extend::fetch('page.find.index', array(), _index_page_id);
        $conds[] = 'page.id=' . DB::val($indexPageId);
    }

    // dokoncit dotaz
    $sql .= ' WHERE ' . implode(' AND ', $conds);
    if (!empty($segments)) {
        $sql .= ' ORDER BY LENGTH(page.slug) DESC';
    }
    $sql .= ' LIMIT 1';

    // nacist data
    return DB::queryRow($sql);
}

/**
 * Nalezt clanek a nacist jeho data
 * Jsou nactena vsechna data clanku + cat[1|2|3]_[id|title|slug|public|level] a author_query
 *
 * @param string   $slug   identifikator clanku
 * @param int|null $cat_id ID hlavni kategorie clanku (home1)
 * @return array|bool false pri nenalezeni
 */
function _findArticle($slug, $cat_id = null)
{
    $author_user_query = _userQuery('a.author');

    $sql = 'SELECT a.*';
    for ($i = 1; $i <= 3; ++$i) {
        $sql .= ",cat{$i}.id cat{$i}_id,cat{$i}.title cat{$i}_title,cat{$i}.slug cat{$i}_slug,cat{$i}.public cat{$i}_public,cat{$i}.level cat{$i}_level";
    }
    $sql .= ',' . $author_user_query['column_list'];
    $sql .= ' FROM ' . _articles_table . ' a';
    for ($i = 1; $i <= 3; ++$i) {
        $sql .= ' LEFT JOIN ' . _root_table . " cat{$i} ON(a.home{$i}=cat{$i}.id)";
    }
    $sql .= ' ' . $author_user_query['joins'];
    $sql .= ' WHERE a.slug=' . DB::val($slug);
    if (null !== $cat_id) {
        $sql .= ' AND a.home1=' . DB::val($cat_id);
    }
    $sql .= ' LIMIT 1';

    $query = DB::queryRow($sql);
    if (false !== $query) {
        $query['author_query'] = $author_user_query;
    }

    return $query;
}

/**
 * Sestavit adresu k libovolne ceste
 *
 * Cesta bude relativni k zakladni adrese systemu.
 *
 * @param string $path cesta v URL, muze obsahovat query string a fragment
 * @param bool   $absolute
 * @return string
 */
function _link($path, $absolute = false)
{
    $url = ($absolute ? Core::$url : Url::base()->path) . '/' . $path;

    Extend::call('link', array(
        'path' => $path,
        'absolute' => $absolute,
        'output' => &$url,
    ));

    return $url;
}

/**
 * Sestavit webovou cestu k existujicimu souboru
 *
 * Soubor musi byt umisten v korenovem adresari systemu nebo v jeho podadresarich.
 *
 * @param string $filePath
 * @param bool   $absolute
 * @return string
 */
function _linkFile($filePath, $absolute = false)
{
    static $realRootPath = null, $realRootPathLength = null;

    if (null === $realRootPath) {
        $realRootPath = realpath(_root) . DIRECTORY_SEPARATOR;
        $realRootPathLength = strlen($realRootPath);
    }

    $realFilePath = realpath($filePath);

    if (false !== $realFilePath && substr($realFilePath, 0, $realRootPathLength) === $realRootPath) {
        $path = str_replace('\\', '/', substr($realFilePath, $realRootPathLength));
    } else {
        if (_dev) {
            if (false === $realFilePath) {
                throw new \InvalidArgumentException(sprintf('File "%s" does not exist or is not accessible', $filePath));
            } else {
                throw new \InvalidArgumentException(sprintf('File "%s" is outside of the root ("%s")', $realFilePath, $realRootPath));
            }
        }

        $path = '';
    }

    return _link($path, $absolute);
}

/**
 * Sestavit adresu clanku
 *
 * @param int|null    $id            ID clanku
 * @param string|null $slug          jiz nacteny identifikator clanku nebo null
 * @param string|null $category_slug jiz nacteny identifikator kategorie nebo null
 * @param bool        $absolute      sestavit absolutni adresu 1/0
 * @return string
 */
function _linkArticle($id, $slug = null, $category_slug = null, $absolute = false)
{
    if (null !== $id) {
        if (null === $slug || null === $category_slug) {
            $slug = DB::queryRow("SELECT art.slug AS art_ts, cat.slug AS cat_ts FROM " . _articles_table . " AS art JOIN " . _root_table . " AS cat ON(cat.id=art.home1) WHERE art.id=" . $id);
            if ($slug === false) {
                $slug = array('---', '---');
            } else {
                $slug = array($slug['art_ts'], $slug['cat_ts']);
            }
        } else {
            $slug = array($slug, $category_slug);
        }
    } else {
        $slug = array($slug, $category_slug);
    }

    return _linkRoot(null, $slug[1], $slug[0], $absolute);
}

/**
 * Sestavit adresu stranky
 *
 * @param string $slug     cely identifikator stranky (prazdny pro hlavni stranu)
 * @param bool   $absolute sestavit absolutni adresu 1/0
 * @return string
 */
function _linkPage($slug, $absolute = false)
{
    if (_pretty_urls) {
        $path = $slug;
    } else {
        if ('' !== $slug) {
            $path = 'index.php?p=' . $slug;
        } else {
            $path = '';
        }
    }

    return _link($path, $absolute);
}

/**
 * Sestavit adresu stranky existujici v databazi
 *
 * @param int|null    $id       ID stranky
 * @param string|null $slug     jiz nacteny identifikator nebo null
 * @param string|null $segment  segment nebo null
 * @param bool        $absolute sestavit absolutni adresu 1/0
 * @return string
 */
function _linkRoot($id, $slug = null, $segment = null, $absolute = false)
{
    if (null !== $id && null === $slug) {
        $slug = DB::queryRow("SELECT slug FROM " . _root_table . " WHERE id=" . DB::val($id));
        $slug = (false !== $slug ? $slug['slug'] : '---');
    }

    if (null !== $segment) {
        $slug .= '/' . $segment;
    } elseif ($id == _index_page_id) {
        $slug = '';
    }

    return _linkPage($slug, $absolute);
}

/**
 * Sestavit adresu a titulek komentare
 *
 * @param array $post     data komentare (potreba sloupce z {@see _postFilter()}
 * @param bool  $entity   formatovat vystup pro HTML 1/0
 * @param bool  $absolute sestavit absolutni adresu 1/0
 * @return array adresa, titulek
 */
function _linkPost(array $post, $entity = true, $absolute = false)
{
    switch ($post['type']) {
        case _post_section_comment:
        case _post_book_entry:
            return array(
                _linkRoot($post['home'], $post['root_slug'], null, $absolute),
                $post['root_title'],
            );
        case _post_article_comment:
            return array(
                _linkArticle(null, $post['art_slug'], $post['cat_slug'], $absolute),
                $post['art_title'],
            );
        case _post_forum_topic:
        case _post_pm:
            if (-1 == $post['xhome']) {
                $topicId = $post[_post_pm == $post['type'] ? 'home' : 'id'];
            } else {
                $topicId = $post['xhome'];
            }
            if (_post_forum_topic == $post['type']) {
                $url = _linkTopic($topicId, $post['root_slug'], $absolute);
            } else {
                $url = _linkModule('messages', "a=list&read={$topicId}", $entity, $absolute);
            }

            return array(
                $url,
                (-1 == $post['xhome'])
                    ? $post['subject']
                    : $post['xhome_subject']
                ,
            );
        case _post_plugin:
            $url = '';
            $title = '';

            Extend::call("posts.{$post['flag']}.link", array(
                'post' => $post,
                'url' => &$url,
                'title' => &$title,
                'entity' => $entity,
                'absolute' => $absolute,
            ));

            return array($url, $title);
        default:
            return array('', '');
    }
}

/**
 * Sestavit adresu tematu
 *
 * @param int         $topic_id   ID tematu
 * @param string|null $forum_slug jiz nacteny identifikator domovskeho fora nebo null
 * @param bool        $absolute   sestavit absolutni adresu 1/0
 * @return string
 */
function _linkTopic($topic_id, $forum_slug = null, $absolute = false)
{
    if (null === $forum_slug) {
        $forum_slug = DB::queryRow('SELECT r.slug FROM ' . _root_table . ' r WHERE type=' . _page_forum . ' AND id=(SELECT p.home FROM ' . _posts_table . ' p WHERE p.id=' . DB::val($topic_id) . ')');
        if (false !== $forum_slug) {
            $forum_slug = $forum_slug['slug'];
        } else {
            $forum_slug = '---';
        }
    }

    return _linkRoot(null, $forum_slug, $topic_id, $absolute);
}

/**
 * Sestavit adresu modulu
 *
 * @param string      $module   jmeno modulu
 * @param string|null $params   standartni querystring
 * @param bool        $entity   formatovat vystup pro HTML 1/0
 * @param bool        $absolute sestavit absolutni adresu 1/0
 * @return string
 */
function _linkModule($module, $params = null, $entity = true, $absolute = false)
{
    if (_pretty_urls) {
        $path = 'm/' . $module;
    } else {
        $path = 'index.php?m=' . $module;
    }

    if (!empty($params)) {
        if (_pretty_urls) {
            $path .= '?';
        } else {
            $path .= ($entity ? '&amp;' : '&');
        }
        $path .= ($entity ? _e($params) : $params);
    }

    return _link($path, $absolute);
}

/**
 * Sestavit adresu RSS zdroje
 *
 * $type    Popis               $id
 *
 * 1        komentare sekce     ID sekce
 * 2        komentare článku    ID článku
 * 3        prispevky knihy     ID knihy
 * 4        nejnovejsi články   ID kategorie / -1
 * 5        nejnovějsi témata   ID fóra
 * 6        nejnovějsi odpovědi ID příspevku (tématu) ve foru
 * 7        nejnovejsi koment.  -1
 *
 * @param int  $id     id polozky
 * @param int  $type   typ
 * @param bool $entity formatovat vystup pro HTML 1/0
 * @return string
 */
function _linkRSS($id, $type, $entity = true)
{
    if (_rss) {
        return _addGetToLink(_root . 'system/script/rss.php', 'tp=' . $type . '&id=' . $id, $entity);
    } else {
        return '';
    }
}

/**
 * Sestaveni kodu odkazu na uzivatele
 *
 * Mozne klice v $options
 * ----------------------
 * plain (0)        vratit pouze jmeno uzivatele 1/0
 * link (1)         odkazovat na profil uzivatele 1/0
 * color (1)        obarvit podle skupiny 1/0
 * icon (1)         zobrazit ikonu skupiny 1/0
 * publicname (1)   vykreslit publicname, ma-li jej uzivatel vyplneno 1/0
 * new_window (0)   odkazovat do noveho okna 1/0 (v prostredi administrace je vychozi 1)
 * max_len (-)      maximalni delka vykresleneho jmena
 * class (-)        vlastni CSS trida
 * title (-)        titulek
 *
 * @param array $data    samostatna data uzivatele viz {@see _userQuery()}
 * @param array $options moznosti vykresleni, viz popis funkce
 * @return string HTML kod
 */
function _linkUser(array $data, array $options = array())
{
    // vychozi nastaveni
    $options += array(
        'plain' => false,
        'link' => true,
        'color' => true,
        'icon' => true,
        'publicname' => true,
        'new_window' => _env_admin,
        'max_len' => null,
        'class' => null,
        'title' => null,
    );

    $tag = ($options['link'] ? 'a' : 'span');
    $name = $data[$options['publicname'] && null !== $data['publicname'] ? 'publicname' : 'username'];
    $nameIsTooLong = (null !== $options['max_len'] && mb_strlen($name) > $options['max_len']);

    // pouze jmeno?
    if ($options['plain']) {
        if ($nameIsTooLong) {
            return _cutHtml($name, $options['max_len']);
        } else {
            return $name;
        }
    }

    // titulek
    $title = $options['title'];
    if ($nameIsTooLong) {
        if (null === $title) {
            $title = $name;
        } else {
            $title = "{$name}, {$title}";
        }
    }

    // oteviraci tag
    $out = "<{$tag}"
        . ($options['link'] ? ' href="' . _linkModule('profile', 'id=' .  $data['username']) . '"' : '')
        . ($options['link'] && $options['new_window'] ? ' target="_blank"' : '')
        . " class=\"user-link user-link-{$data['id']} user-link-group-{$data['group_id']}" . (null !== $options['class'] ? " {$options['class']}" : '') . "\""
        . ($options['color'] && '' !== $data['group_color'] ? " style=\"color:{$data['group_color']}\"" : '')
        . (null !== $title ? " title=\"{$title}\"" : '')
        . '>'
    ;

    // ikona skupiny
    if ($options['icon'] && '' !== $data['group_icon']) {
        $out .= "<img src=\"" . _link('images/groupicons/' . $data['group_icon']) . "\" title=\"{$data['group_title']}\" alt=\"{$data['group_title']}\" class=\"icon\">";
    }

    // jmeno uzivatele
    if ($nameIsTooLong) {
        $out .= _cutHtml($name, $options['max_len']) . '...';
    } else {
        $out .= $name;
    }

    // uzaviraci tag
    $out .= "</{$tag}>";

    return $out;
}

/**
 * Sestaveni kodu odkazu na uzivatele na zaklade dat z funkce {@see _userQuery()}
 *
 * @param array $userQuery vystup z {@see _userQuery()}
 * @param array $row       radek z vysledku dotazu
 * @param array $options   nastaveni vykresleni, viz {@see _linkUser()}
 * @return string
 */
function _linkUserFromQuery(array $userQuery, array $row, array $options = array())
{
    $userData = _arrayGetSubset($row, $userQuery['columns'], strlen($userQuery['prefix']));

    if (null === $userData['id']) {
        return '?';
    }

    return _linkUser($userData, $options);
}

/**
 * Sestavit kod odkazu na e-mail s ochranou
 *
 * @param string $email emailova adresa
 * @return string
 */
function _mailto($email)
{
    if ('' !== _atreplace) {
        $email = str_replace("@", _atreplace, $email);
    }

    return "<a href='#' onclick='return Sunlight.mai_lto(this);'>" . _e($email) . "</a>";
}

/**
 * Filtrovat uzivatelsky obsah na zaklade opravneni
 *
 * @param string $content obsah
 * @param bool   $isHtml  jedna se o HTML kod
 * @param bool   $hasHcm  obsah muze obsahovat HCM moduly
 * @throws \LogicException
 * @throws ContentPrivilegeException
 * @return string
 */
function _filterUserContent($content, $isHtml = true, $hasHcm = true)
{
    if ($hasHcm) {
        if (!$isHtml) {
            throw new LogicException('Content that supports HCM modules is always HTML');
        }

        $content = _filterHCM($content, true);
    }

    Extend::call('fc.filter_user_content', array(
        'content' => &$content,
        'is_html' => $isHtml,
        'has_hcm' => $hasHcm,
    ));

    return $content;
}

/**
 * Vyhodnotit HCM moduly v retezci
 *
 * @param string $input   vstupni retezec
 * @param string $handler callback vyhodnocovace modulu
 * @return string
 */
function _parseHCM($input, $handler = '_parseHCM_module')
{
    return preg_replace_callback('|\[hcm\](.*?)\[/hcm\]|s', $handler, $input);
}

/**
 * Handler pro vykonani modulu
 *
 * @param array $match
 * @return string
 */
function _parseHCM_module($match)
{
    $params = _parseStr($match[1]);
    if (isset($params[0])) {
        return _runHCM($params[0], array_splice($params, 1));
    } else {
        return '';
    }
}

/**
 * Zavolat konkretni HCM modul
 *
 * @param string $name nazev hcm modulu
 * @param array  $args pole s argumenty
 * @return mixed vystup HCM modulu
 */
function _runHCM($name, array $args)
{
    if (!_env_web) {
        // HCM moduly zavisi na prostredi webu
        return '';
    }

    $module = explode('/', $name, 2);

    if (!isset($module[1])) {
        // systemovy modul
        $functionName = '_HCM_' . str_replace('/', '_', $name);
        $functionExists = function_exists($functionName);

        if (!$functionExists) {
            $file = _root . 'system/hcm/' . basename($module[0]) . '.php';
            if (is_file($file)) {
                require $file;

                $functionExists = function_exists($functionName);
            }
        }

        if ($functionExists) {
            ++Core::$hcmUid;
            
            return call_user_func_array($functionName, $args);
        }
    } else {
        // extend modul
        ++Core::$hcmUid;

        return Extend::buffer("hcm.{$module[0]}.{$module[1]}", array(
            'arg_list' => $args,
        ));
    }

    return '';
}

/**
 * Filtrovat HCM moduly v obsahu na zakladne opravneni
 *
 * @param string $content   obsah, ktery ma byt filtrovan
 * @param bool   $exception emitovat vyjimku v pripade nalezeni nepovoleneho HCM modulu 1/0
 * @throws ContentPrivilegeException
 * @return string
 */
function _filterHCM($content, $exception = false)
{
    // pripravit seznamy
    $blacklist = array();
    if (!_priv_adminhcmphp) {
        $blacklist[] = 'php';
    }

    $whitelist = preg_split('/\s*,\s*/', _priv_adminhcm);
    if (1 === sizeof($whitelist) && '*' === $whitelist[0]) {
        $whitelist = null; // vsechny HCM moduly povoleny
    }

    Extend::call('fc.filter_hcm', array(
        'blacklist' => &$blacklist,
        'whitelist' => &$blacklist,
    ));

    // pripravit mapy
    $blacklistMap = null !== $blacklist ? array_flip($blacklist) : null;
    $whitelistMap = null !== $whitelist ? array_flip($whitelist) : null;

    // filtrovat
    return _parseHCM($content, function ($match) use ($blacklistMap, $whitelistMap, $exception) {
        $params = _parseStr($match[1]);
        $module = isset($params[0]) ? mb_strtolower($params[0]) : '';

        if (
            null !== $whitelistMap && !isset($whitelistMap[$module])
            || null === $blacklistMap
            || isset($blacklistMap[$module])
        ) {
            if ($exception) {
                throw new ContentPrivilegeException(sprintf('HCM module "%s"', $params[0]));
            }

            return '';
        }

        return $match[0];
    });
}

/**
 * Odstranit vsechny HCM moduly z obsahu
 *
 * @param string $content
 * @return string
 */
function _removeHCM($content)
{
    return _parseHCM($content, function () {
        return '';
    });
}

/**
 * Vyhodnotit BBCode tagy
 *
 * @param string $s        vstupni retezec (HTML)
 * @param bool   $get_tags navratit seznam tagu namisto parsovani 1/0
 * @return string|array
 */
function _parseBBCode($s, $get_tags = false)
{
    // tag => array(0 => pair 1/0, 1 => arg 1/0, 2 => nestable (can have parent) 1/0, 3 => can-contain-children 1/0, 4 => button-icon(null = none | 1 = template | string = path))
    static $tags = array(
            'b' => array(true, false, true, true, 1), // bold
            'i' => array(true, false, true, true, 1), // italic
            'u' => array(true, false, true, true, 1), // underline
            'q' => array(true, false, true, true, null), // quote
            's' => array(true, false, true, true, 1), // strike
            'img' => array(true, false, false, false, 1), // image
            'code' => array(true, true, false, true, 1), // code
            'c' => array(true, false, true, true, null), // inline code
            'url' => array(true, true, true, false, 1), // link
            'hr' => array(false, false, false, false, 1), // horizontal rule
            'color' => array(true, true, true, true, null), // color
            'size' => array(true, true, true, true, null), // size
            'noformat' => array(true, false, true, false, null), //no format
        ),
        $syntax = array('[', ']', '/', '=', '"'), // syntax
        $extended = false
    ;

    // merge tags with _extend
    if (!$extended) {
        Extend::call('bbcode.init.tags', array('tags' => &$tags));
        $extended = true;
    }

    // get tags only?
    if ($get_tags) {
        return $tags;
    }

    // prepare
    $mode = 0;
    $submode = 0;
    $closing = false;
    $parents = array(); // 0 = tag, 1 = arg, 2 = buffer
    $parents_n = -1;
    $tag = '';
    $output = '';
    $buffer = '';
    $arg = '';
    $reset = 0;

    // scan
    for ($i = 0; isset($s[$i]); ++$i) {

        // get char
        $char = $s[$i];

        // mode step
        switch ($mode) {

                ########## look for tag ##########
            case 0:
                if ($char === $syntax[0]) {
                    $mode = 1;
                    if ($parents_n === -1) {
                        $output .= $buffer;
                    }
                    else {
                        $parents[$parents_n][2] .= $buffer;
                    }
                    $buffer = '';
                }
                break;

                ########## scan tag ##########
            case 1:
                if (($ord = ord($char)) > 47 && $ord < 59 || $ord > 64 && $ord < 91 || $ord > 96 && $ord < 123) {
                    // tag charaxter
                    $tag .= $char;
                } elseif ($tag === '' && $char === $syntax[2]) {
                    // closing tag
                    $closing = true;
                    break;
                } elseif ($char === $syntax[1]) {
                    // tag end
                    $tag = mb_strtolower($tag);
                    if (isset($tags[$tag])) {
                        if ($parents_n === -1 || $tags[$tag][2] || $tags[$tag][0] && $closing) {
                            if ($tags[$tag][0]) {
                                // paired tag
                                if ($closing) {
                                    if ($parents_n === -1 || $parents[$parents_n][0] !== $tag) {
                                        // reset - invalid closing tag
                                        $reset = 2;
                                    } else {
                                        --$parents_n;
                                        $pop = array_pop($parents);
                                        $buffer = _parseBBCode_processTag($pop[0], $pop[1], $pop[2]);
                                        if ($parents_n === -1) {
                                            $output .= $buffer;
                                        } else {
                                            $parents[$parents_n][2] .= $buffer;
                                        }
                                        $reset = 1;
                                        $char = '';
                                    }
                                } elseif ($parents_n === -1 || $tags[$parents[$parents_n][0]][3]) {
                                    // opening tag
                                    $parents[] = array($tag, $arg, '');
                                    ++$parents_n;
                                    $buffer = '';
                                    $char = '';
                                    $reset = 1;
                                } else {
                                    // reset - disallowed children
                                    $reset = 7;
                                }
                            } else {
                                // standalone tag
                                $buffer = _parseBBCode_processTag($tag, $arg);
                                if ($parents_n === -1) {
                                    $output .= $buffer;
                                } else {
                                    $parents[$parents_n][2] .= $buffer;
                                }
                                $reset = 1;
                            }
                        } else {
                            // reset - disallowed nesting
                            $reset = 3;
                        }
                    } else {
                        // reset - bad tag
                        $reset = 4;
                    }
                } elseif ($char === $syntax[3]) {
                    if (isset($tags[$tag]) && $tags[$tag][1] === true && $arg === '' && !$closing) {
                        $mode = 2; // scan tag argument
                    } else {
                        // reset - bad / no argument
                        $reset = 5;
                    }
                } else {
                    // reset - invalid character
                    $reset = 8;
                }
                break;

                ########## scan tag argument ##########
            case 2:

                // detect submode
                if ($submode === 0) {
                    if ($char === $syntax[4]) {
                        // quoted mode
                        $submode = 1;
                        break;
                    } else {
                        // unquoted mode
                        $submode = 2;
                    }
                }

                // gather argument
                if ($submode === 1) {
                    if ($char !== $syntax[4]) {
                        // char ok
                        $arg .= $char;
                        break;
                    }
                } elseif ($char !== $syntax[1]) {
                    // char ok
                    $arg .= $char;
                    break;
                }

                // end
                if ($submode === 2) {
                    // end of unquoted
                    $mode = 1;
                    $char = '';
                    --$i;
                } else {
                    // end of quoted
                    if (isset($s[$i + 1]) && $s[$i + 1] === $syntax[1]) {
                        $mode = 1;
                    } else {
                        // reset - bad syntax
                        $reset = 6;
                    }
                }

                break;

        }

        // buffer char
        $buffer .= $char;

        // reset
        if ($reset !== 0) {
            if ($reset > 1) {
                if ($parents_n === -1) {
                    $output .= $buffer;
                } else {
                    $parents[$parents_n][2] .= $buffer;
                }
            }
            $buffer = '';
            $reset = 0;
            $mode = 0;
            $submode = 0;
            $closing = false;
            $tag = '';
            $arg = '';
        }

    }

    // flush remaining parents and buffer
    if ($parents_n !== -1) {
        for($i = 0; isset($parents[$i]); ++$i) {
            $output .= $parents[$i][2];
        }
    }
    $output .= $buffer;

    // return output
    return $output;
}

/**
 * @internal
 */
function _parseBBCode_processTag($tag, $arg = '', $buffer = null)
{
    // load extend tag processors
    static $ext = null;
    if (!isset($ext)) {
        $ext = array();
        Extend::call('bbcode.init.proc', array('tags' => &$ext));
    }

    // make sure the buffer is always safe
    $buffer = _e($buffer, false);

    // process
    if (isset($ext[$tag])) {
        return call_user_func($ext[$tag], $arg, $buffer);
    }
    switch ($tag) {
        case 'b':
            if ($buffer !== '') {
                return '<strong>' . $buffer . '</strong>';
            }
            break;

        case 'i':
            if ($buffer !== '') {
                return '<em>' . $buffer . '</em>';
            }
            break;

        case 'u':
            if ($buffer !== '') {
                return '<u>' . $buffer . '</u>';
            }
            break;

        case 'q':
            if ($buffer !== '') {
                return '<q>' . $buffer . '</q>';
            }
            break;

        case 's':
            if ($buffer !== '') {
                return '<del>' . $buffer . '</del>';
            }
            break;

        case 'code':
            if ($buffer !== '') {
                return '<span class="pre">' . str_replace(' ', '&nbsp;', $buffer) . '</span>';
            }
            break;

        case 'c':
            if ($buffer !== '') {
                return '<code>' . $buffer . '</code>';
            }
            break;

        case 'url':
            if ($buffer !== '') {
                $url = trim($arg !== '' ? $arg : $buffer);
                $url = _isSafeUrl($url) ? _addSchemeToURL($url) : '#';

                return '<a href="' . $url . '" rel="nofollow" target="_blank">' . $buffer . '</a>';
            }
            break;

        case 'hr':
            return '<span class="hr"></span>';

        case 'color':
            static $colors = array('aqua' => 0, 'black' => 1, 'blue' => 2, 'fuchsia' => 3, 'gray' => 4, 'green' => 5, 'lime' => 6, 'maroon' => 7, 'navy' => 8, 'olive' => 9, 'orange' => 10, 'purple' => 11, 'red' => 12, 'silver' => 13, 'teal' => 14, 'white' => 15, 'yellow' => 16);
            if ($buffer !== '') {
                if (preg_match('/^#[0-9A-Fa-f]{3,6}$/', $arg) !== 1) {
                    $arg = mb_strtolower($arg);
                    if (!isset($colors[$arg])) {
                        return $buffer;
                    }
                }

                return '<span style="color:' . $arg . ';">' . $buffer . '</span>';
            }
            break;

        case 'size':
            if ($buffer !== '') {
                $arg = (int) $arg;
                if ($arg < 1 || $arg > 8) {
                    return $buffer;
                }
                return '<span style="font-size:' . round((0.5 + ($arg / 6)) * 100) . '%;">' . $buffer . '</span>';
            }
            break;

        case 'img':
            $buffer = trim($buffer);
            if ($buffer !== '' && _isSafeUrl($buffer)) {
                return '<img src="' . _addSchemeToURL($buffer) . '" alt="img" class="bbcode-img">';
            }
            break;

        case 'noformat':
            return $buffer;
    }

    return '';
}

/**
 * Vyhodnotit text prispevku
 *
 * @param string $input   vstupni text
 * @param bool   $smileys vyhodnotit smajliky 1/0
 * @param bool   $bbcode  vyhodnotit bbcode 1/0
 * @param bool   $nl2br   prevest odrakovani na <br>
 * @returns string
 */
function _parsePost($input, $smileys = true, $bbcode = true, $nl2br = true)
{
    // vyhodnoceni smajlu
    if (_smileys && $smileys) {
        $template = _getCurrentTemplate();

        $input = preg_replace('/\*(\d{1,3})\*/s', '<img src=\'' . $template->getWebPath() . '/images/smileys/$1.' . $template->getOption('smiley.format') . '\' alt=\'$1\' class=\'post-smiley\'>', $input, 32);
    }

    // vyhodnoceni BBCode
    if (_bbcode && $bbcode) {
        $input = _parseBBCode($input);
    }

    // prevedeni novych radku
    if ($nl2br) {
        $input = nl2br($input, false);
    }

    // navrat vystupu
    return $input;
}

/**
 * Vyhodnotit pravo aktualniho uzivatele k pristupu ke clanku
 *
 * @param array $article          pole s daty clanku (potreba id,time,confirmed,author,public,home1,home2,home3)
 * @param bool  $check_categories kontrolovat kategorie 1/0
 * @return bool
 */
function _articleAccess($article, $check_categories = true)
{
    // nevydany / neschvaleny clanek
    if (!$article['confirmed'] || $article['time'] > time()) {
        return _priv_adminconfirm || $article['author'] == _loginid;
    }

    // pristup k clanku
    if (!_publicAccess($article['public'])) {
        return false;
    }

    // pristup ke kategoriim
    if ($check_categories) {
        // nacist
        $homes = array($article['home1']);
        if ($article['home2'] != -1) {
            $homes[] = $article['home2'];
        }
        if ($article['home3'] != -1) {
            $homes[] = $article['home3'];
        }
        $result = DB::query('SELECT public,level FROM ' . _root_table . ' WHERE id IN(' . implode(',', $homes) . ')');
        while ($r = DB::row($result)) {
            if (_publicAccess($r['public'], $r['level'])) {
                // do kategorie je pristup (staci alespon 1)
                return true;
            }
        }

        // neni pristup k zadne kategorii
        return false;
    } else {
        // nekontrolovat
        return true;
    }
}

/**
 * Vyhodnotit pravo uzivatele na pristup k prispevku
 *
 * @param array $userQuery vystup z {@see _userQuery()}
 * @param array $post      data prispevku (potreba data uzivatele a post.time)
 * @return bool
 */
function _postAccess(array $userQuery, array $post)
{
    // uzivatel je prihlasen
    if (_login) {
        // extend
        $access = Extend::fetch('posts.access', array(
            'post' => $post,
            'user_query' => $userQuery,
        ));
        if (null !== $access) {
            return $access;
        }

        // je uzivatel autorem prispevku?
        if (_loginid == $post[$userQuery['prefix'] . 'id'] && ($post['time'] + _postadmintime > time() || _priv_unlimitedpostaccess)) {
            return true;
        } elseif (_priv_adminposts && _priv_level > $post[$userQuery['prefix'] . 'group_level']) {
            // uzivatel ma pravo spravovat cizi prispevky
            return true;
        }
    }

    return false;
}

/**
 * Vyhodnocenu prava aktualniho uzivatele pro pristup na zaklade verejnosti, urovne a stavu prihlaseni
 *
 * @param bool     $public polozka je verejna 1/0
 * @param int|null $level  minimalni pozadovana uroven
 * @return bool
 */
function _publicAccess($public, $level = 0)
{
    return (_login || $public) && _priv_level >= $level;
}

/**
 * Strankovani vysledku
 *
 * Format vystupu:
 * array(
 *      paging      => html kod seznamu stran
 *      sql_limit   => cast sql dotazu - limit
 *      current     => aktualni strana (1+)
 *      total       => celkovy pocet stran
 *      count       => pocet polozek
 *      first       => cislo prvni zobrazene polozky
 *      last        => cislo posledni zobrazene polozky
 *      per_page    => pocet polozek na jednu stranu
 * )
 *
 * @param string      $url        vychozi adresa (cista - bez HTML entit!)
 * @param int         $limit      limit polozek na 1 stranu
 * @param string|int  $table      nazev tabulky (tabulka[:alias]) nebo celkovy pocet polozek jako integer
 * @param string      $conditions kod SQL dotazu za WHERE v SQL dotazu pro zjistovani poctu polozek; pokud je $table cislo, nema tato promenna zadny vyznam
 * @param string      $linksuffix retezec pridavany za kazdy odkaz generovany strankovanim
 * @param string|null $param      nazev parametru pro cislo strany (null = 'page')
 * @param bool        $autolast   posledni strana je vychozi strana 1/0
 * @return array
 */
function _resultPaging($url, $limit, $table, $conditions = '1', $linksuffix = '', $param = null, $autolast = false)
{
    global $_lang;

    // alias tabulky
    if (is_string($table)) {
        $table = explode(':', $table);
        $alias = (isset($table[1]) ? $table[1] : null);
        $table = $table[0];
    } else {
        $alias = null;
    }

    // priprava promennych
    if (!isset($param)) {
        $param = 'page';
    }
    if (is_string($table)) {
        $count = DB::result(DB::query("SELECT COUNT(*) FROM " . DB::escIdt($table) . (isset($alias) ? " AS {$alias}" : '') . " WHERE " . $conditions), 0);
    } else {
        $count = $table;
    }

    $pages = max(1, ceil($count / $limit));
    if (isset($_GET[$param])) {
        $s = abs((int) _get($param) - 1);
    } elseif ($autolast) {
        $s = $pages - 1;
    } else {
        $s = 0;
    }

    if ($s + 1 > $pages) {
        $s = $pages - 1;
    }
    $start = $s * $limit;
    $beginpage = $s + 1 - _showpages;
    if ($beginpage < 1) {
        $endbonus = abs($beginpage) + 1;
        $beginpage = 1;
    } else {
        $endbonus = 0;
    }
    $endpage = $s + 1 + _showpages + $endbonus;
    if ($endpage > $pages) {
        $beginpage -= $endpage - $pages;
        if ($beginpage < 1) {
            $beginpage = 1;
        }
        $endpage = $pages;
    }

    // vypis stran
    $paging = null;
    Extend::call('fc.paging', array(
        'url' => $url,
        'param' => $param,
        'autolast' => $autolast,
        'table' => $table,
        'count' => $count,
        'offset' => $start,
        'limit' => $limit,
        'current' => $s + 1,
        'total' => $pages,
        'begin' => $beginpage,
        'end' => $endpage,
        'paging' => &$paging,
    ));

    if (null === $paging) {
        if ($pages > 1) {

            if (false === strpos($url, "?")) {
                $url .= '?';
            } else {
                $url .= '&';
            }

            $url = _e($url);

            $paging = "\n<div class='paging'>\n<span class='paging-label'>" . $_lang['global.paging'] . ":</span>\n";

            // prvni
            if ($beginpage > 1) {
                $paging .= "<a href='" . $url . $param . "=1" . $linksuffix . "' title='" . $_lang['global.first'] . "'>1</a><span class='paging-first-addon'> ...</span>\n";
            }

            // predchozi
            if ($s + 1 != 1) {
                $paging .= "<a class='paging-prev' href='" . $url . $param . "=" . ($s) . $linksuffix . "'>&laquo; " . $_lang['global.previous'] . "</a>\n";
            }

            // strany
            $paging .= "<span class='paging-pages'>\n";
            for ($x = $beginpage; $x <= $endpage; ++$x) {
                if ($x == $s + 1) {
                    $class = " class='act'";
                } else {
                    $class = "";
                }
                $paging .= "<a href='" . $url . $param . "=" . $x . $linksuffix . "'" . $class . ">" . $x . "</a>\n";
                if ($x != $endpage) {
                    $paging .= " ";
                }
            }
            $paging .= "</span>\n";

            // dalsi
            if ($s + 1 != $pages) {
                $paging .= "<a class='paging-next' href='" . $url . $param . "=" . ($s + 2) . $linksuffix . "'>" . $_lang['global.next'] . " &raquo;</a>\n";
            }
            if ($endpage < $pages) {
                $paging .= "<span class='paging-last-addon'> ... </span><a class='paging-last' href='" . $url . $param . "=" . $pages . $linksuffix . "' title='" . $_lang['global.last'] . "'>" . $pages . "</a>\n";
            }

            $paging .= "\n</div>\n\n";
        } else {
            $paging = '';
        }
    }

    // return
    $end_item = ($start + $limit - 1);

    return array(
        'paging' => $paging,
        'sql_limit' => 'LIMIT ' . $start . ', ' . $limit,
        'current' => ($s + 1),
        'total' => $pages,
        'count' => $count,
        'first' => $start,
        'last' => (($end_item > $count - 1) ? $count - 1 : $end_item),
        'per_page' => $limit,
    );
}

/**
 * Zjistit stranku, na ktere se polozka nachazi pri danem strankovani a podmince razeni
 *
 * @param int    $limit      pocet polozek na jednu stranu
 * @param string $table      nazev tabulky v databazi
 * @param string $conditions kod SQL dotazu za WHERE v SQL dotazu pro zjistovani poctu polozek
 * @return int
 */
function _resultPagingGetItemPage($limit, $table, $conditions = "1")
{
    $count = DB::result(DB::query("SELECT COUNT(*) FROM " .  DB::escIdt($table) . " WHERE " . $conditions), 0);

    return floor($count / $limit + 1);
}

/**
 * Zjisteni, zda je polozka s urcitym cislem v rozsahu aktualni strany strankovani
 *
 * @param array $pagingdata pole, ktere vraci funkce {@see _resultPaging()}
 * @param int   $itemnumber poradove cislo polozky (poradi zacina nulou)
 * @return bool
 */

function _resultPagingIsItemInRange($pagingdata, $itemnumber)
{
    return $itemnumber >= $pagingdata['first'] && $itemnumber <= $pagingdata['last'];
}

/**
 * Zjisteni, zda-li ma byt strankovani zobrazeno nahore
 *
 * @return bool
 */
function _showPagingAtTop()
{
    return _pagingmode == 1 || _pagingmode == 2;
}

/**
 * Zjisteni, zda-li ma byt strankovani zobrazeno dole
 *
 * @return bool
 */
function _showPagingAtBottom()
{
    return _pagingmode == 2 || _pagingmode == 3;
}

/**
 * Odeslat 404 hlavicku
 */
function _notFoundHeader()
{
    header('HTTP/1.1 404 Not Found');
}

/**
 * Odeslat 401 hlavicku
 */
function _unauthorizedHeader()
{
    header('HTTP/1.1 401 Unauthorized');
}

/**
 * Odeslat hlavicky pro presmerovani
 *
 * @param string $url       absolutni URL
 * @param bool   $permanent vytvorit permanentni presmerovani 1/0
 */
function _redirectHeader($url, $permanent = false)
{
    header('HTTP/1.1 ' . ($permanent ? '301 Moved Permanently' : '302 Found'));
    header('Location: ' . $url);
}

/**
 * Navrat na predchozi stranku
 * Po provedeni presmerovani je skript ukoncen.
 *
 * @param string|null $url adresa pro navrat, null = {@see _returnUrl()}
 */
function _returnHeader($url = null)
{
    if (null === $url) {
        $url = _returnUrl();
    }

    if (!headers_sent()) {
        _redirectHeader($url);
    } else {
        ?>
        <meta http-equiv="refresh" content="1;url=<?php echo _e($url) ?>">
        <p><a href="<?php echo _e($url) ?>"><?php echo $GLOBALS['_lang']['global.continue'] ?></a></p>
        <?php
    }

    exit;
}

/**
 * Ziskat navratovou adresu
 *
 * Jsou pouzity nasledujici adresy (v poradi priority):
 *
 * 1) parametr $url
 * 2) _get('_return')
 * 3) $_SERVER['HTTP_REFERER']
 *
 * @return string
 */
function _returnUrl()
{
    $specifiedUrl = _get('_return', '');
    $baseUrl = Url::base();

    if ('' !== $specifiedUrl) {
        $returnUrl = clone $baseUrl;

        if ('/' === $specifiedUrl[0]) {
            $returnUrl->path = $specifiedUrl;
        }  elseif ('./' !== $specifiedUrl) {
            $returnUrl->path = $returnUrl->path . '/' . $specifiedUrl;
        }
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $returnUrl = Url::parse($_SERVER['HTTP_REFERER']);
    }

    // pouzit vychozi URL, pokud ma zjistena navratova URL jiny hostname (prevence open redirection vulnerability)
    if ($baseUrl->host !== $returnUrl->host) {
        $returnUrl = $baseUrl;
    }

    return $returnUrl->generateAbsolute();
}

/**
 * Poslat hlavicky pro stazeni souboru
 *
 * @param string   $filename nazev souboru
 * @param int|null $filesize velikost souboru v bajtech, je-li znama
 */
function _downloadHeaders($filename, $filesize = null)
{
    header('Content-Type: application/octet-stream');
    header(sprintf('Content-Disposition: attachment; filename="%s"', $filename));

    if (null !== $filesize) {
        header(sprintf('Content-Length: %d', $filesize));
    }
}

/**
 * Stahnout lokalni soubor
 *
 * Skript NENI ukoncen po zavolani teto funkce.
 *
 * @param string      $filepath cesta k souboru
 * @param string|null $filename vlastni nazev souboru nebo null (= zjistit z $filepath)
 */
function _downloadFile($filepath, $filename = null)
{
    _ensureHeadersNotSent();
    _ensureFileExists($filepath);

    if (null === $filename) {
        $filename = basename($filepath);
    }

    Output::cleanBuffers();
    _downloadHeaders($filename, filesize($filepath));

    $handle = fopen($filepath, 'rb');
    while (!feof($handle)) {
        echo fread($handle, 131072);
        flush();
    }
    fclose($handle);
}

/**
 * Ujistit se, ze jeste nebyly odeslany hlavicky
 *
 * @throws RuntimeException pokud jiz byly hlavicky odeslany
 */
function _ensureHeadersNotSent()
{
    if (headers_sent($file, $line)) {
        throw new RuntimeException(sprintf('Headers already sent (output started in "%s" on line %d)', $file, $line));
    }
}

/**
 * Ujistit se, ze existuje dany soubor
 *
 * @param string $filepath
 * @throws RuntimeException pokud soubor neexistuje
 */
function _ensureFileExists($filepath)
{
    if (!is_file($filepath)) {
        throw new RuntimeException(sprintf('File "%s" does not exist', $filepath));
    }
}

/**
 * Sestavit casti SQL dotazu pro vypis komentaru
 *
 * Join aliasy: home_root, home_art, home_cat1..3, home_post
 * Sloupce: data postu + (root|cat|art)_(title|slug), xhome_subject
 *
 * @param string $alias         alias tabulky komentaru pouzity v dotazu
 * @param array  $types         pole s typy prispevku, ktere maji byt nacteny
 * @param array  $homes         pole s ID domovskych polozek
 * @param string $sqlConditions SQL s vlastnimi WHERE podminkami
 * @param bool   $doCount       vracet take pocet odpovidajicich prispevku 1/0
 * @return array sloupce, joiny, where podminka, [pocet]
 */
function _postFilter($alias, array $types = array(), array $homes = array(), $sqlConditions = null, $doCount = false)
{
    // sloupce
    $columns = "{$alias}.id,{$alias}.type,{$alias}.home,{$alias}.xhome,{$alias}.subject,
{$alias}.author,{$alias}.guest,{$alias}.time,{$alias}.text,{$alias}.flag,
home_root.title root_title,home_root.slug root_slug,
home_cat1.title cat_title,home_cat1.slug cat_slug,
home_art.title art_title,home_art.slug art_slug,
home_post.subject xhome_subject";

    // podminky
    $conditions = array();

    if (!empty($types)) {
        $conditions[] = "{$alias}.type IN(" . DB::arr($types) . ")";
    }
    if (!empty($homes)) {
        $conditions[] = "{$alias}.home IN(" . DB::arr($homes) . ")";
    }

    $conditions[] = "(home_root.id IS NULL OR " . (_login ? '' : 'home_root.public=1 AND ') . "home_root.level<=" . _priv_level . ")
AND (home_art.id IS NULL OR " . (_login ? '' : 'home_art.public=1 AND ') . "home_art.time<=" . time() . " AND home_art.confirmed=1)
AND ({$alias}.type!=" . _post_article_comment . " OR (
    " . (_login ? '' : '(home_cat1.public=1 OR home_cat2.public=1 OR home_cat3.public=1) AND') . "
    (home_cat1.level<=" . _priv_level . " OR home_cat2.level<=" . _priv_level . " OR home_cat3.level<=" . _priv_level . ")
))";

    // vlastni podminky
    if (!empty($sqlConditions)) {
        $conditions[] = $sqlConditions;
    }

    // joiny
    $joins = "LEFT JOIN " . _root_table . " home_root ON({$alias}.type IN(1,3,5) AND {$alias}.home=home_root.id)
LEFT JOIN " . _articles_table . " home_art ON({$alias}.type=" . _post_article_comment . " AND {$alias}.home=home_art.id)
LEFT JOIN " . _root_table . " home_cat1 ON({$alias}.type=" . _post_article_comment . " AND home_art.home1=home_cat1.id)
LEFT JOIN " . _root_table . " home_cat2 ON({$alias}.type=" . _post_article_comment . " AND home_art.home2!=-1 AND home_art.home2=home_cat2.id)
LEFT JOIN " . _root_table . " home_cat3 ON({$alias}.type=" . _post_article_comment . " AND home_art.home3!=-1 AND home_art.home3=home_cat3.id)
LEFT JOIN " . _posts_table . " home_post ON({$alias}.type=" . _post_forum_topic . " AND {$alias}.xhome!=-1 AND {$alias}.xhome=home_post.id)";

    // extend
    Extend::call('posts.filter', array(
        'columns' => &$columns,
        'joins' => &$joins,
        'conditions' => &$conditions,
    ));

    // sestaveni vysledku
    $result = array(
        $columns,
        $joins,
        implode(' AND ', $conditions),
    );

    // pridat pocet
    if ($doCount) {
        $result[] = (int) DB::result(DB::query("SELECT COUNT({$alias}.id) FROM " . _posts_table . " {$alias} {$joins} WHERE {$result[2]}"), 0);
    }

    return $result;
}

/**
 * Sestaveni casti SQL dotazu po WHERE pro vyhledani clanku v urcitych kategoriich.
 *
 * @param array       $categories pole s ID kategorii
 * @param string|null $alias      alias tabulky clanku pouzity v dotazu
 * @return string
 */
function _articleFilterCategories(array $categories, $alias = null)
{
    if (empty($categories)) {
        return '1';
    }

    if (null !== $alias) {
        $alias .= '.';
    }

    $cond = '(';
    $idList = DB::arr($categories);
    for ($i = 1; $i <= 3; ++$i) {
        if ($i > 1) {
            $cond .= ' OR ';
        }
        $cond .= "{$alias}home{$i} IN({$idList})";
    }
    $cond .= ')';

    return $cond;
}

/**
 * Sestavit casti SQL dotazu pro vypis clanku
 *
 * Join aliasy: cat1, cat2, cat3
 *
 * @param string $alias         alias tabulky clanku pouzity v dotazu
 * @param array  $categories    pole s ID kategorii, muze byt prazdne
 * @param string $sqlConditions SQL s vlastnimi WHERE podminkami
 * @param bool   $doCount       vracet take pocet odpovidajicich clanku 1/0
 * @param bool   $checkPublic   nevypisovat neverejne clanky, neni-li uzivatel prihlasen
 * @param bool   $hideInvisible nevypisovat neviditelne clanky
 * @return array joiny, where podminka, [pocet clanku]
 */
function _articleFilter($alias, array $categories = array(), $sqlConditions = null, $doCount = false, $checkPublic = true, $hideInvisible = true)
{
    //kategorie
    if (!empty($categories)) {
        $conditions[] = _articleFilterCategories($categories);
    }

    // cas vydani
    $conditions[] = "{$alias}.time<=" . time();
    $conditions[] = "{$alias}.confirmed=1";

    // neviditelnost
    if ($hideInvisible) {
        $conditions[] = "{$alias}.visible=1";
    }

    // neverejnost
    if ($checkPublic && !_login) {
        $conditions[] = "{$alias}.public=1";
        $conditions[] = "(cat1.public=1 OR cat2.public=1 OR cat3.public=1)";
    }
    $conditions[] = "(cat1.level<=" . _priv_level . " OR cat2.level<=" . _priv_level . " OR cat3.level<=" . _priv_level . ")";

    // vlastni podminky
    if (!empty($sqlConditions)) {
        $conditions[] = $sqlConditions;
    }

    // joiny
    $joins = '';
    for ($i = 1; $i <= 3; ++$i) {
        if ($i > 1) {
            $joins .= ' ';
        }
        $joins .= 'LEFT JOIN ' . _root_table . " cat{$i} ON({$alias}.home{$i}!=-1 AND cat{$i}.id={$alias}.home{$i})";
    }

    // spojit podminky
    $conditions = implode(' AND ', $conditions);

    // sestaveni vysledku
    $result = array($joins, $conditions);

    // pridat pocet
    if ($doCount) {
        $result[] = (int) DB::result(DB::query("SELECT COUNT({$alias}.id) FROM " . _articles_table . " {$alias} {$joins} WHERE {$conditions}"), 0);
    }

    return $result;
}

/**
 * Sestaveni casti SQL dotazu po WHERE pro filtrovani zaznamu podle moznych hodnot daneho sloupce
 *
 * @param string       $column nazev sloupce v tabulce
 * @param string|array $values mozne hodnoty sloupce v poli, oddelene pomlckami nebo "all" pro vypnuti limitu
 * @return string
 */
function _sqlWhereColumn($column, $values)
{
    if ($values !== 'all') {
        if (!is_array($values)) {
            $values = explode('-', $values);
        }
        return $column . ' IN(' . DB::val($values, true) . ')';
    } else {
        return '1';
    }
}

/**
 * Sestavit autentifikacni hash uzivatele
 *
 * @param string $storedPassword heslo ulozene v databazi
 * @return string
 */
function _userAuthHash($storedPassword)
{
    return hash('sha512', $storedPassword);
}

/**
 * Prihlaseni uzivatele
 *
 * @param int    $id
 * @param string $storedPassword
 * @param string $email
 * @param bool   $ipBound
 * @param bool   $persistent
 */
function _userLogin($id, $storedPassword, $email, $persistent = false)
{
    $authHash = _userAuthHash($storedPassword);

    $_SESSION['user_id'] = $id;
    $_SESSION['user_auth'] = $authHash;
    $_SESSION['user_ip'] = _userip;

    if ($persistent && !headers_sent()) {
        $cookie_data = array();
        $cookie_data[] = $id;
        $cookie_data[] = _userPersistentLoginHash($id, $authHash, $email);

        setcookie(
            Core::$appId . '_persistent_key',
            implode('$', $cookie_data),
            (time() + 31536000),
            '/'
        );
    }
}

/**
 * Sestavit kod prihlasovaciho formulare
 *
 * @param bool        $title    vykreslit titulek 1/0
 * @param bool        $required jedna se o povinne prihlaseni z duvodu nedostatku prav 1/0
 * @param string|null $return   navratova URL
 * @param bool        $embedded nevykreslovat <form> tag 1/0
 * @return string
 */
function _userLoginForm($title = false, $required = false, $return = null, $embedded = false)
{
    global $_lang;

    $output = '';

    // titulek
    if ($title) {
        $title_text = $_lang[$required ? 'login.required.title' : 'login.title'];
        if (_env_admin) {
            $output .= '<h1>' . $title_text . "</h1>\n";
        } else {
            $GLOBALS['_index']['title'] = $title_text;
        }
    }

    // text
    if ($required) {
        $output .= '<p>' . $_lang['login.required.p'] . "</p>\n";
    }

    // zpravy
    if (isset($_GET['login_form_result'])) {
        $login_result = _userLoginMessage(_get('login_form_result'));
        if (null !== $login_result) {
            $output .= $login_result;
        }
    }

    // obsah
    if (!_login) {

        $form_append = '';

        // adresa pro navrat
        if (null === $return && !$embedded) {
            if (isset($_GET['login_form_return'])) {
                $return = _get('login_form_return');
            } else  {
                $return = $_SERVER['REQUEST_URI'];
            }
        }

        // akce formulare
        if (!$embedded) {
            // systemovy skript
            $action = _link('system/script/login.php');
        } else {
            // vlozeny formular
            $action = null;
        }
        if (!empty($return)) {
            $action = _addGetToLink($action, '_return=' . rawurlencode($return), false);
        }

        // adresa formulare
        $form_url = Url::current();
        if ($form_url->has('login_form_result')) {
            $form_url->remove('login_form_result');
        }
        $form_append .= "<input type='hidden' name='login_form_url' value='" . _e($form_url) . "'>\n";

        // kod formulare
        $output .= _formOutput(
            array(
                'name' => 'login_form',
                'action' => $action,
                'embedded' => $embedded,
                'submit_text' => $_lang['global.login'],
                'submit_append' => " <label><input type='checkbox' name='login_persistent' value='1'> " . $_lang['login.persistent'] . "</label>",
                'form_append' => $form_append,
            ),
            array(
                array('label' => $_lang['login.username'], 'content' => "<input type='text' name='login_username' class='inputmedium'" . _restoreGetValue('login_form_username') . " maxlength='24'>"),
                array('label' => $_lang['login.password'], 'content' => "<input type='password' name='login_password' class='inputmedium'>")
            )
        );

        // odkazy
        if (!$embedded) {
            $links = array();
            if (_registration && _env_web) {
                $links['reg'] = array('url' => _linkModule('reg'), 'text' => $_lang['mod.reg']);
            }
            if (_lostpass) {
                $links['lostpass'] = array('url' => _linkModule('lostpass'), 'text' => $_lang['mod.lostpass']);
            }
            Extend::call('user.login.links', array('links' => &$links));

            if (!empty($links)) {
                $output .= "<ul class=\"login-form-links\">\n";
                foreach ($links as $link_id => $link) {
                    $output .= "<li class=\"login-form-link-{$link_id}\"><a href=\"{$link['url']}\">{$link['text']}</a></li>\n";
                }
                $output .= "</ul>\n";
            }
        }

    } else {
        $output .= "<p>" . $_lang['login.ininfo'] . " <em>" . _loginname . "</em> - <a href='" . _xsrfLink(_link('system/script/logout.php')) . "'>" . $_lang['usermenu.logout'] . "</a>.</p>";
    }

    return $output;
}

/**
 * Ziskat hlasku pro dany kod
 *
 * Existujici kody:
 * ------------------------------------------------------
 * 0    prihlaseni se nezdarilo (spatne jmeno nebo heslo / jiz prihlasen)
 * 1    prihlaseni uspesne
 * 2    uzivatel je blokovan
 * 3    automaticke odhlaseni z bezp. duvodu
 * 4    smazani vlastniho uctu
 * 5    vycerpan limit neuspesnych prihlaseni
 * 6    neplatny XSRF token
 *
 * @param int $code
 * @return Message|null
 */
function _userLoginMessage($code)
{
    global $_lang;

    switch ($code) {
        case 0:
            return Message::warning($_lang['login.failure']);
        case 1:
            return Message::ok($_lang['login.success']);
        case 2:
            return Message::warning($_lang['login.blocked.message']);
        case 3:
            return Message::error($_lang['login.securitylogout']);
        case 4:
            return Message::ok($_lang['login.selfremove']);
        case 5:
            return Message::warning(str_replace(array("*1*", "*2*"), array(_maxloginattempts, _maxloginexpire / 60), $_lang['login.attemptlimit']));
        case 6:
            return Message::error($_lang['xsrf.msg']);
        default:
            return Extend::fetch('user.login.message', array('code' => $code));
    }
}

/**
 * Zpracovat POST prihlaseni
 *
 * @param string $username
 * @param string $plainPassword
 * @param bool   $persistent
 * @return int kod {@see _userLoginMessage())
 */
function _userLoginSubmit($username, $plainPassword, $persistent = false)
{
    // jiz prihlasen?
    if (_login) {
        return 0;
    }

    // XSRF kontrola
    if (!_xsrfCheck()) {
        return 6;
    }

    // kontrola limitu
    if (!_iplogCheck(_iplog_failed_login_attempt)) {
        return 5;
    }

    // kontrola uziv. jmena
    if ('' === $username) {
        // prazdne uziv. jmeno
        return 0;
    }

    // udalost
    $extend_result = null;
    Extend::call('user.login.pre', array(
        'username' => $username,
        'password' => $plainPassword,
        'persistent' => $persistent,
        'result' => &$extend_result,
    ));
    if (null !== $extend_result) {
        return $extend_result;
    }

    // nalezeni uzivatele
    if (false !== strpos($username, '@')) {
        $cond = 'u.email=' . DB::val($username);
    } else {
        $cond = 'u.username=' . DB::val($username) . ' OR u.publicname=' . DB::val($username);
    }

    $query = DB::queryRow("SELECT u.id,u.username,u.email,u.logincounter,u.password,u.blocked,g.blocked group_blocked FROM " . _users_table . " u JOIN " . _groups_table . " g ON(u.group_id=g.id) WHERE " . $cond);
    if (false === $query) {
        // uzivatel nenalezen
        return 0;
    }

    // kontrola hesla
    $password = Password::load($query['password']);

    if (!$password->match($plainPassword)) {
        // nespravne heslo
        _iplogUpdate(_iplog_failed_login_attempt);

        return 0;
    }

    // kontrola blokace
    if ($query['blocked'] || $query['group_blocked']) {
        // uzivatel nebo skupina je blokovana
        return 2;
    }

    // aktualizace dat uzivatele
    $changeset = array(
        'ip' => _userip,
        'activitytime' => time(),
        'logincounter' => $query['logincounter'] + 1,
        'security_hash' => null,
        'security_hash_expires' => 0,
    );

    if ($password->shouldUpdate()) {
        // aktualizace formatu hesla
        $password->update($plainPassword);

        $changeset['password'] = $query['password'] = $password->build();
    }

    DB::update(_users_table, 'id=' . DB::val($query['id']), $changeset);

    // extend udalost
    Extend::call('user.login', array('user' => $query));

    // prihlaseni
    _userLogin($query['id'], $query['password'], $query['email'], $persistent);

    // vse ok, uzivatel byl prihlasen
    return 1;
}

/**
 * Sestavit HASH pro trvale prihlaseni uzivatele
 *
 * @param int    $id
 * @param string $authHash
 * @param string $email
 * @return string
 */
function _userPersistentLoginHash($id, $authHash, $email)
{
    return hash_hmac(
        'sha512',
        $id . '$' . $authHash . '$' . $email,
        Core::$secret
    );
}

/**
 * Odhlaseni aktualniho uzivatele
 *
 * @param bool $destroy uplne znicit session
 * @return bool
 */
function _userLogout($destroy = true)
{
    if (!defined('_login') || _login == 1) {
        Extend::call('user.logout');

        $_SESSION = array();

        if ($destroy) {
            session_destroy();

            if (!headers_sent()) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
        }

        if (!headers_sent() && isset($_COOKIE[Core::$appId . '_persistent_key'])) {
            setcookie(Core::$appId . '_persistent_key', '', (time() - 3600), '/');
        }

        return true;
    }

    return false;
}

/**
 * Ziskat pocet neprectenych PM (soukromych zprav) aktualniho uzivatele
 *
 * Vystup teto funkce je cachovan.
 *
 * @return int
 */
function _userGetUnreadPmCount()
{
    static $result = null;

    if (null === $result) {
        $result = DB::count(_pm_table, "(receiver=" . _loginid . " AND receiver_deleted=0 AND receiver_readtime<update_time) OR (sender=" . _loginid . " AND sender_deleted=0 AND sender_readtime<update_time)");
    }

    return $result;
}

/**
 * Kontrola podpory formatu GD knihovnou
 *
 * @param string|null $check_format nazev formatu (jpg, jpeg, png , gif) jehoz podpora se ma zkontrolovat nebo null
 * @return bool
 */
function _checkGD($check_format = null)
{
    if (function_exists('gd_info')) {
        if (isset($check_format)) {
            $info = gd_info();
            $support = false;
            switch (strtolower($check_format)) {
                case 'png':
                    if (isset($info['PNG Support']) && $info['PNG Support'] == true) {
                        $support = true;
                    }
                    break;
                case 'jpg':
                case 'jpeg':
                    if ((isset($info['JPG Support']) && $info['JPG Support'] == true) || (isset($info['JPEG Support']) && $info['JPEG Support'] == true)) {
                        $support = true;
                    }
                    break;
                case 'gif':
                    if (isset($info['GIF Read Support']) && $info['GIF Read Support'] == true) {
                        $support = true;
                    }
                    break;
            }

            return $support;
        } else {
            return true;
        }
    }

    return false;
}

/**
 * Ziskat kod avataru daneho uzivatele
 *
 * Mozne klice v $options
 * ----------------------
 * get_path (0)     ziskat pouze cestu namisto html kodu obrazku 1/0
 * default (1)      vratit vychozi avatar, pokud jej uzivatel nema nastaven 1/0 (jinak null)
 * link (1)         odkazat na profil uzivatele 1/0
 * extend (1)       vyvolat extend udalost 1/0
 * class (-)        vlastni CSS trida
 *
 * @param array $data    samostatna data uzivatele (avatar, username, publicname)
 * @param array $options moznosti vykresleni, viz popis funkce
 * @return string|null HTML kod s obrazkem nebo URL
 */
function _getAvatar(array $data, array $options = array())
{
    // vychozi nastaveni
    $options += array(
        'get_url' => false,
        'default' => true,
        'link' => true,
        'extend' => true,
        'class' => null,
    );

    $hasAvatar = (null !== $data['avatar']);
    $url = _link('images/avatars/' . ($hasAvatar ? $data['avatar'] : 'no-avatar' . (_getCurrentTemplate()->getOption('dark') ? '-dark' : '')) . '.jpg');

    // zpracovani rozsirenim
    if ($options['extend']) {
        $extendOutput = Extend::buffer('user.avatar', array(
            'data' => $data,
            'url' => &$url,
            'options' => $options,
        ));
        if ('' !== $extendOutput) {
            return $extendOutput;
        }
    }

    // vratit null neni-li avatar a je deaktivovan vychozi
    if (!$options['default'] && !$hasAvatar) {
        return null;
    }

    // vratit pouze URL?
    if ($options['get_url']) {
        return $url;
    }

    // vykreslit obrazek
    $out = '';
    if ($options['link']) {
        $out .= '<a href="' . _linkModule('profile', 'id=' .  $data['username']) . '">';
    }
    $out .= "<img class=\"avatar" . (null !== $options['class'] ? " {$options['class']}" : '') . "\" src=\"{$url}\" alt=\"" . $data[null !== $data['publicname'] ? 'publicname' : 'username'] . "\">";
    if ($options['link']) {
        $out .= '</a>';
    }

    return $out;
}

/**
 * Ziskat kod avataru daneho uzivatele na zaklade dat z funkce {@see _userQuery()}
 *
 * @param array $userQuery vystup z {@see _userQuery()}
 * @param array $row       radek z vysledku dotazu
 * @param array $options   nastaveni vykresleni, viz {@see _getAvatar()}
 * @return string
 */
function _getAvatarFromQuery(array $userQuery, array $row, array $options = array())
{
    $userData = _arrayGetSubset($row, $userQuery['columns'], strlen($userQuery['prefix']));

    return _getAvatar($userData, $options);
}

/**
 * Nacteni obrazku ze souboru
 *
 * Mozne klice v $limit:
 *
 * filesize     maximalni velikost souboru v bajtech
 * dimensions   max. rozmery ve formatu array(x => max_sirka, y => max_vyska)
 * memory       maximalni procento zbyvajici dostupne pameti, ktere muze byt vyuzito (vychozi je 0.75) a je treba pocitat s +- odchylkou
 *
 * @param string      $filepath realna cesta k souboru
 * @param array       $limit    volby omezeni
 * @param string|null $filename pouzity nazev souboru (pokud se lisi od $filepath)
 * @return array pole s klici (bool)status, (int)code, (string)msg, (resource)resource, (string)ext
 */
function _pictureLoad($filepath, $limit = array(), $filename = null)
{
    // vychozi nastaveni
    static $limit_default = array(
        'filesize' => null,
        'dimensions' => null,
        'memory' => 0.75,
    );

    // vlozeni vychoziho nastaveni
    $limit += $limit_default;

    // proces
    $code = 0;
    do {

        /* --------  kontroly a nacteni  -------- */

        // zjisteni nazvu souboru
        if (null === $filename) {
            $filename = basename($filepath);
        }

        // zjisteni pripony
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // kontrola pripony
        if (!in_array($ext, Core::$imageExt) || !_isSafeFile($filepath) || !_isSafeFile($filename)) {
            // nepovolena pripona
            $code = 1;
            break;
        }

        // kontrola velikosti souboru
        $size = @filesize($filepath);
        if ($size === false) {
            // soubor nenalezen
            $code = 2;
            break;
        }
        if (isset($limit['filesize']) && $size > $limit['filesize']) {
            // prekrocena datova velikost
            $code = 3;
            break;
        }

        // kontrola podpory formatu
        if (!_checkGD($ext)) {
            // nepodporovany format
            $code = 4;
            break;
        }

        // zjisteni informaci o obrazku
        $imageInfo = getimagesize($filepath);
        if (isset($imageInfo['channels'])) {
            $channels = $imageInfo['channels'];
        } else {
            switch ($ext) {
                case 'png': $channels = 4; break;
                default: $channels = 3; break;
            }
        }
        if (!isset($imageInfo['bits'])) {
            $imageInfo['bits'] = 8;
        }
        if (false === $imageInfo || 0 == $imageInfo[0] || 0 == $imageInfo[1]) {
            $code = 5;
            break;
        }

        // kontrola dostupne pameti
        if ($memlimit = _phpIniLimit('memory_limit')) {
            $availMem = floor($limit['memory'] * ($memlimit - memory_get_usage()));
            $requiredMem = ceil(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $channels / 8 + 65536) * 1.65);

            if ($requiredMem > $availMem) {
                // nedostatek pameti
                $code = 5;
                break;
            }
        }

        // nacteni rozmeru
        $x = $imageInfo[0];
        $y = $imageInfo[1];

        // kontrola rozmeru
        if (isset($limit['dimensions']) && ($x > $limit['dimensions']['x'] || $y > $limit['dimensions']['y'])) {
            $code = 6;
            break;
        }

        // pokus o nacteni obrazku
        switch ($ext) {

            case 'jpg':
            case 'jpeg':
                $res = @imagecreatefromjpeg($filepath);
                break;

            case 'png':
                $res = @imagecreatefrompng($filepath);
                break;

            case 'gif':
                $res = @imagecreatefromgif ($filepath);
                break;

        }

        // kontrola nacteni
        if (!is_resource($res)) {
            $code = 5;
            break;
        }

        // vsechno je ok, vratit vysledek
        return array('status' => true, 'code' => $code, 'resource' => $res, 'ext' => $ext);

    } while (false);

    // chyba
    global $_lang;
    $output = array('status' => false, 'code' => $code, 'msg' => $_lang['pic.load.' . $code], 'ext' => $ext);

    // uprava vystupu
    switch ($code) {
        case 3:
            $output['msg'] = str_replace('*maxsize*', _formatFilesize($limit['filesize']), $output['msg']);
            break;

        case 5:
            $lastError = error_get_last();
            if (null !== $lastError && !empty($lastError['message'])) {
                $output['msg'] .= " {$_lang['global.error']}: " . _e($lastError['message']);
            }
            break;

        case 6:
            $output['msg'] = str_replace(array('*maxw*', '*maxh*'), array($limit['dimensions']['x'], $limit['dimensions']['y']), $output['msg']);
            break;
    }

    // navrat
    return $output;
}

/**
 * Zmena velikosti obrazku
 *
 * Mozne klice v $opt:
 * -----------------------------------------------------
 * x (-)            pozadovana sirka obrazku (nepovinne pokud je uvedeno y)
 * y (-)            pozadovana vyska obrazku (nepovinne pokud je uvedeno x)
 * mode (-)         mod zpracovani - 'zoom', 'fit' nebo 'none' (zadna operace)
 * keep_smaller (0) zachovat mensi obrazky 1/0
 * bgcolor (-)      barva pozadi ve formatu array(r, g, b) (pouze mod 'fit')
 * pad (0)          doplnit rozmer obrazku prazdnym mistem 1/0 (pouze mod 'fit')
 *
 * trans            zachovat pruhlednost obrazku (ignoruje klic bgcolor) 1/0
 * trans_format     format obrazku (png/gif), vyzadovano pro trans = 1
 *
 * @param resource   $res  resource obrazku
 * @param array      $opt  pole s volbami procesu
 * @param array|null $size pole ve formatu array(sirka, vyska) nebo null (= bude nacteno)
 * @return array pole s klici (bool)status, (int)code, (string)msg, (resource)resource, (bool)changed
 */
function _pictureResize($res, array $opt, $size = null)
{
    global $_lang;

    // vychozi nastaveni
    $opt += array(
        'x' => null,
        'y' => null,
        'mode' => null,
        'keep_smaller' => false,
        'bgcolor' => null,
        'pad' => false,
        'trans' => false,
        'trans_format' => null,
    );

    Extend::call('fc.picture.resize', array(
        'res' => &$res,
        'options' => &$opt,
    ));

    // zadna operace?
    if ('none' === $opt['mode']) {
        return array('status' => true, 'code' => 0, 'resource' => $res, 'changed' => false);
    }

    // zjisteni rozmeru
    if (!isset($size)) {
        $x = imagesx($res);
        $y = imagesy($res);
    } else {
        list($x, $y) = $size;
    }

    // rozmery kompatibilita 0 => null
    if (0 == $opt['x']) {
        $opt['x'] = null;
    }
    if (0 == $opt['y']) {
        $opt['y'] = null;
    }

    // kontrola parametru
    if (null === $opt['x'] && null === $opt['y'] || null !== $opt['y'] && $opt['y'] < 1 || null !== $opt['x'] && $opt['x'] < 1) {
        return array('status' => false, 'code' => 2, 'msg' => $_lang['pic.resize.2']);
    }

    // proporcionalni dopocet chybejiciho rozmeru
    if (null === $opt['x']) {
        $opt['x'] = max(round($x / $y * $opt['y']), 1);
    } elseif (null === $opt['y']) {
        $opt['y'] = max(round($opt['x'] / ($x / $y)), 1);
    }

    // povolit mensi rozmer / stejny rozmer
    if (
        $opt['keep_smaller'] && $x < $opt['x'] && $y < $opt['y']
        || $x == $opt['x'] && $y == $opt['y']
    ) {
        return array('status' => true, 'code' => 0, 'resource' => $res, 'changed' => false);
    }

    // vypocet novych rozmeru
    $newx = $opt['x'];
    $newy = max(round($opt['x'] / ($x / $y)), 1);

    // volba finalnich rozmeru
    $xoff = $yoff = 0;
    if ($opt['mode'] === 'zoom') {
        if ($newy < $opt['y']) {
            $newx = max(round($x / $y * $opt['y']), 1);
            $newy = $opt['y'];
            $xoff = round(($opt['x'] - $newx) / 2);
        } elseif ($newy > $opt['y']) {
            $yoff = round(($opt['y'] - $newy) / 2);
        }
    } elseif ($opt['mode'] === 'fit') {
        if ($newy < $opt['y']) {
            if ($opt['pad']) {
                $yoff = round(($opt['y'] - $newy) / 2);
            } else {
                $opt['y'] = $newy;
            }
        } elseif ($newy > $opt['y']) {
            $newy = $opt['y'];
            $newx = max(round($x / $y * $opt['y']), 1);
            if ($opt['pad']) {
                $xoff = round(($opt['x'] - $newx) / 2);
            } else {
                $opt['x'] = $newx;
            }
        }
    } else {
        return array('status' => false, 'code' => 1, 'msg' => $_lang['pic.resize.1']);
    }

    // priprava obrazku
    $output = imagecreatetruecolor($opt['x'], $opt['y']);

    // prekresleni pozadi
    if ($opt['trans'] && null !== $opt['trans_format']) {
        // pruhledne
        _pictureAlpha($output, $opt['trans_format'], $res);
    } else {
        // nepruhledne
        if ($opt['mode'] === 'fit' && null !== $opt['bgcolor']) {
            $bgc = imagecolorallocate($output, $opt['bgcolor'][0], $opt['bgcolor'][1], $opt['bgcolor'][2]);
            imagefilledrectangle($output, 0, 0, $opt['x'], $opt['y'], $bgc);
        }
    }

    // zmena rozmeru a navrat
    if (imagecopyresampled($output, $res, $xoff, $yoff, 0, 0, $newx, $newy, $x, $y)) {
        return array('status' => true, 'code' => 0, 'resource' => $output, 'changed' => true);
    }
    imagedestroy($output);

    return array('status' => false, 'code' => 2, 'msg' => $_lang['pic.resize.2']);
}

/**
 * Aktivovat pruhlednost obrazku
 *
 * @param resource      $resource     resource obrazku
 * @param string        $format       vystupni format obrazku (png / gif)
 * @param resource|null $tColorSource resource obrazku jako zdroj transp. barvy (jinak $resource)
 * @return bool
 */
function _pictureAlpha($resource, $format, $tColorSource = null)
{
    // paleta?
    $trans = imagecolortransparent(null !== $tColorSource ? $tColorSource : $resource);
    if ($trans >= 0) {
        $transColor = imagecolorsforindex($resource, $trans);
        $transColorAl = imagecolorallocate($resource, $transColor['red'], $transColor['green'], $transColor['blue']);
        imagefill($resource, 0, 0, $transColorAl);
        imagecolortransparent($resource, $transColorAl);

        return true;
    }

    // png alpha?
    if ('png' === $format) {
        imagealphablending($resource, false);
        $transColorAl = imagecolorallocatealpha($resource, 0, 0, 0, 127);
        imagefill($resource, 0, 0, $transColorAl);
        imagesavealpha($resource, true);

        return true;
    }

    return false;
}

/**
 * Rozebrat definici rozmeru pro zmenu velikosti obrazku
 *
 * Format je: FLAGS:WIDTHxHEIGHT
 *
 * FLAGS je nepovinna cast slozena z jednotlivych znaku:
 *
 *      z   'zoom' rezim
 *      f   'fit' rezim
 *      k   zachovat mensi obrazky
 *      p   vyplnit zbyvajici misto cernou barvou (pouze v rezimu 'fit')
 *      w   pouzit bilou barvu pro vypln
 *      s   nezachovavat pruhlednost obrazku
 *
 * WIDTH je pozadovana sirka nebo "?" (bez uvozovek)
 * HEIGHT je pozadovana vyska nebo "?" (bez uvozovek)
 *
 * (pokud jsou oba rozmery "?", je pouzita vychozi hodnota)
 *
 * Priklady:
 * ---------
 * 128x96
 * 128x?
 * ?x96
 * z:640x480
 * zk:320x?
 *
 * @param string   $input         vstupni retezec
 * @param string   $defaultMode   vychozi "mode" pro {@see _pictureResize()}
 * @param int|null $defaultWidth  vychozi sirka
 * @param int|null $defaultHeight vychozi vyska
 * @return array pole pro {@see _pictureResize()}
 */
function _pictureResizeOptions($input, $defaultMode = 'fit', $defaultWidth = 96, $defaultHeight = null)
{
    $mode = $defaultMode;
    $pad = false;
    $bgColor = null;
    $keepSmaller = false;
    $width = null;
    $height = null;
    $trans = true;

    if ($input) {
        // rozdelit nastaveni a rozmery
        $parts = explode(':', $input, 2);
        if (isset($parts[1])) {
            list($flags, $size) = $parts;
        } else {
            $flags = null;
            $size = $parts[0];
        }

        // zpracovat nastaveni
        if ($flags) {
            for ($i = 0; isset($flags[$i]); ++$i) {
                switch ($flags[$i]) {
                    case 'z': $mode = 'zoom'; break;
                    case 'f': $mode = 'fit'; break;
                    case 'k': $keepSmaller = true; break;
                    case 'p': $pad = true; break;
                    case 'w': $bgColor = array(255, 255, 255); break;
                    case 's': $trans = false; break;
                }
            }
        }

        // zpracovat rozmery
        $sizes = explode('x', $size, 2);
        $width = ('?' === $sizes[0] ? null : (int) $sizes[0]);
        $height = (isset($sizes[1]) ? ('?' === $sizes[1] ? null : (int) $sizes[1]) : $defaultHeight);
    }

    if (null === $width && null === $height) {
        // vychozi rozmery pokud jsou oba null
        $width = $defaultWidth;
        $height = $defaultHeight;
    } else {
        // minimalni hodnoty rozmeru
        if ($width !== null && $width < 1) {
            $width = 1;
        }
        if ($height !== null && $height < 1) {
            $height = 1;
        }
    }

    return array(
        'x' => $width,
        'y' => $height,
        'mode' => $mode,
        'pad' => $pad,
        'bgcolor' => $bgColor,
        'keep_smaller' => $keepSmaller,
        'trans' => $trans,
        'trans_format' => null,
    );
}

/**
 * Ulozit obrazek do uloziste
 *
 * @param resource    $res         resource obrazku
 * @param string      $path        cesta k adresari uloziste vcetne lomitka
 * @param string|null subcesta     v adresari uloziste vcetne lomitka nebo null
 * @param string      $format      pozadovany format obrazku
 * @param int         $jpg_quality kvalita JPG obrazku
 * @param string|null $uid         UID obrazku nebo null (= vygeneruje se automaticky)
 * @return array pole s klici (bool)status, (int)code, (string)path, (string)uid
 */
function _pictureStoragePut($res, $path, $home_path, $format, $jpg_quality = 80, $uid = null)
{
    // vygenerovani uid
    if (!isset($uid)) {
        $uid = uniqid('');
    }

    // udalost
    Extend::call('fc.picture.storage.put', array(
        'res' => &$res,
        'path' => $path,
        'home_path' => $home_path,
        'uid' => &$uid,
        'format' => &$format,
        'jpg_quality' => &$jpg_quality,
    ));

    // sestaveni cesty
    if (isset($home_path)) {
        $path .= $home_path;
    }

    // proces
    $code = 0;
    do {

        // kontrola adresare
        if (!is_dir($path) && !@mkdir($path, 0777, true)) {
            $code = 1;
            break;
        }

        // kontrola formatu
        if (!_checkGD($format)) {
            $code = 2;
            break;
        }

        // sestaveni nazvu
        $fname = $path . $uid . '.' . $format;

        // zapsani souboru
        switch ($format) {

            case 'jpg':
            case 'jpeg':
                $write = @imagejpeg($res, $fname, $jpg_quality);
                break;

            case 'png':
                $write = @imagepng($res, $fname);
                break;

            case 'gif':
                $write = @imagegif ($res, $fname);
                break;

        }

        // uspech?
        if ($write) {
            return array('status' => true, 'code' => $code, 'path' => $fname, 'uid' => $uid); // jo
        }
        $code = 3; // ne

    } while (false);

    // chyba
    global $_lang;

    return array('status' => false, 'code' => $code, 'msg' => $_lang['pic.put.' . $code]);
}

/**
 * Ziskat cestu k obrazku v ulozisti
 *
 * @param string      $path    cesta k adresari uloziste vcetne lomitka
 * @param string|null subcesta v adresari uloziste vcetne lomitka nebo null
 * @param string      $uid     UID obrazku
 * @param string      $format  format ulozeneho obrazku
 * @return string
 */
function _pictureStorageGet($path, $home_path, $uid, $format)
{
    Extend::call('fc.picture.storage.get', array(
        'path' => $path,
        'home_path' => $home_path,
        'uid' => &$uid,
        'format' => &$format,
    ));

    return $path . (isset($home_path) ? $home_path : '') . $uid . '.' . $format;
}

/**
 * Zpracovat obrazek
 *
 * Navratova hodnota
 * -----------------
 * false        je vraceno v pripade neuspechu
 * string       UID je vraceno v pripade uspesneho ulozeni
 * resource     je vraceno v pripade uspesneho zpracovani bez ulozeni (target_path je null)
 * mixed        pokud je uveden target_callback a vrati jinou hodnotu nez null
 *
 * Dostupne klice v $args :
 * -----------------------------------------------------
 * Nacteni a zpracovani
 *
 *  file_path       realna cesta k souboru s obrazkem
 *  [file_name]     vlastni nazev souboru pro detekci formatu (jinak se pouzije file_path)
 *  [limit]         omezeni pri nacitani obrazku, viz _pictureLoad() - $limit
 *  [resize]        pole s argumenty pro zmenu velikosti obrazku, viz _pictureResize()
 *  [callback]      callback(resource, format, opt) pro zpracovani vysledne resource
 *                  (pokud vrati jinou hodnotu nez null, obrazek nebude ulozen a funkce
 *                   vrati tuto hodnotu)
 *  [destroy]       pokud je nastaveno na false, neni resource obrazku znicena (po ulozeni / volani callbacku)
 *
 * Ukladani
 *
 *  [target_path]       cesta do adresare, kam ma byt obrazek ulozen, s lomitkem na konci (!) nebo null (neukladat)
 *  [target_format]     cilovy format (JPG/JPEG, PNG, GIF), pokud neni uveden, je zachovan stavajici format
 *  [target_uid]        vlastni unikatni identifikator, jinak bude vygenerovan automaticky
 *  [jpg_quality]       kvalita pro ukladani JPG/JPEG formatu
 *
 * @param array         $opt       volby zpracovani
 * @param string        &$error    promenna pro ulozeni chybove hlasky v pripade neuspechu
 * @param string        &$format   promenna pro ulozeni formatu nacteneho obrazku
 * @param resource|null &$resource promenna pro ulozeni resource vysledneho obrazku (pouze pokud 'destroy' = false)
 * @return mixed viz popis funkce
 */
function _pictureProcess(array $opt, &$error = null, &$format = null, &$resource = null)
{
    $opt += array(
        'file_name' => null,
        'limit' => array(),
        'resize' => null,
        'callback' => null,
        'destroy' =>  true,
        'target_path' => null,
        'target_format' => null,
        'target_uid' => null,
        'jpg_quality' => 90,
    );

    Extend::call('fc.picture.process', array('options' => &$opt));

    try {

        // nacteni
        $load = _pictureLoad(
            $opt['file_path'],
            $opt['limit'],
            $opt['file_name']
        );
        if (!$load['status']) {
            throw new RuntimeException($load['msg']);
        }
        $format = $load['ext'];

        // zmena velikosti
        if (null !== $opt['resize']) {

            // zachovat pruhlednost, neni-li uvedeno jinak
            if (
                !isset($opt['resize']['trans'], $opt['resize']['trans_format'])
                && ('png' === $format || 'gif' === $format)
                && (null === $opt['target_format'] || $opt['target_format'] === $format)
            ) {
                $opt['resize']['trans'] = true;
                $opt['resize']['trans_format'] = $format;
            }

            // zmenit velikost
            $resize = _pictureResize($load['resource'], $opt['resize']);
            if (!$resize['status']) {
                throw new RuntimeException($resize['msg']);
            }

            // nahrada puvodni resource
            if ($resize['changed']) {
                // resource se zmenila
                imagedestroy($load['resource']);
                $load['resource'] = $resize['resource'];
            }

            $resize = null;

        }

        // callback
        if (null !== $opt['callback']) {
            $targetCallbackResult = call_user_func($opt['callback'], $load['resource'], $load['ext'], $opt);
            if (null !== $targetCallbackResult) {
                // smazani obrazku z pameti
                if ($opt['destroy']) {
                    imagedestroy($load['resource']);
                    $resource = null;
                } else {
                    $resource = $load['resource'];
                }

                // navrat vystupu callbacku
                return $targetCallbackResult;
            }
        }

        // akce s vysledkem
        if (null !== $opt['target_path']) {
            // ulozeni
            $put = _pictureStoragePut(
                $load['resource'],
                $opt['target_path'],
                null,
                null !== $opt['target_format'] ? $opt['target_format'] : $load['ext'],
                $opt['jpg_quality'],
                $opt['target_uid']
            );
            if (!$put['status']) {
                throw new RuntimeException($put['msg']);
            }

            // smazani obrazku z pameti
            if ($opt['destroy']) {
                imagedestroy($load['resource']);
                $resource = null;
            } else {
                $resource = $load['resource'];
            }

            // vratit UID
            return $put['uid'];
        } else {
            // vratit resource
            return $load['resource'];
        }

    } catch (RuntimeException $e) {
        $error = $e->getMessage();

        return false;
    }
}

/**
 * Vygenerovat cachovanou miniaturu obrazku
 *
 * @param string $source          cesta ke zdrojovemu obrazku
 * @param array  $resize_opts     volby pro zmenu velikosti, {@see _pictureResize()} (mode je prednastaven na zoom)
 * @param bool   $use_error_image vratit chybovy obrazek pri neuspechu namisto false
 * @param string &$error          promenna, kam bude ulozena pripadna chybova hlaska
 * @return string|bool cesta k miniature nebo chybovemu obrazku nebo false pri neuspechu
 */
function _pictureThumb($source, array $resize_opts, $use_error_image = true, &$error = null)
{
    // zjistit priponu
    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    if (!in_array($ext, Core::$imageExt)) {
        return $use_error_image ? Core::$imageError : false;
    }

    // sestavit cestu do adresare
    $path = _root . 'images/thumb/';

    // extend pro nastaveni velikosti
    Extend::call('fc.thumb.resize', array('options' => &$resize_opts));

    // vychozi nastaveni zmenseni
    $resize_opts += array(
        'mode' => 'zoom',
        'trans' => 'png' === $ext || 'gif' === $ext,
        'trans_format' => $ext,
    );

    // normalizovani nastaveni zmenseni
    if (!isset($resize_opts['x']) || 0 == $resize_opts['x']) {
        $resize_opts['x'] = null;
    } else {
         $resize_opts['x'] = (int) $resize_opts['x'];
    }
    if (!isset($resize_opts['y']) || 0 == $resize_opts['y']) {
        $resize_opts['y'] = null;
    } else {
        $resize_opts['y'] = (int) $resize_opts['y'];
    }

    // vygenerovat hash
    ksort($resize_opts);
    if (isset($resize_opts['bgcolor'])) {
        ksort($resize_opts['bgcolor']);
    }
    $hash = md5(realpath($source) . '$' . serialize($resize_opts));

    // sestavit cestu k obrazku
    $image_path = $path . $hash . '.' . $ext;

    // zkontrolovat cache
    if (file_exists($image_path)) {
        // obrazek jiz existuje
        if (time() - filemtime($image_path) >= _thumb_touch_threshold) {
            touch($image_path);
        }

        return $image_path;
    } else {
        // obrazek neexistuje
        $options = array(
            'file_path' => $source,
            'resize' => $resize_opts,
            'target_path' => $path,
            'target_uid' => $hash,
        );

        // extend
        Extend::call('fc.thumb.process', array('options' => &$options));

        // vygenerovat
        if (false !== _pictureProcess($options, $error)) {
            // uspech
            return $image_path;
        } else {
            // chyba
            return $use_error_image ? Core::$imageError : false;
        }
    }
}

/**
 * Smazat nepouzivane miniatury
 *
 * @param int $threshold minimalni doba v sekundach od posledniho vyzadani miniatury
 */
function _pictureThumbClean($threshold)
{
    $dir = _root . 'images/thumb/';
    $handle = opendir($dir);
    while ($item = readdir($handle)) {
        if (
            '.' !== $item
            && '..' !== $item
            && is_file($dir . $item)
            && in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), Core::$imageExt)
            && time() - filemtime($dir . $item) > $threshold
        ) {
            unlink($dir . $item);
        }
    }
    closedir($handle);
}

/**
 * Sestavit kod skryteho inputu pro XSRF ochranu
 *
 * @return string
 */
function _xsrfProtect()
{
    return '<input type="hidden" name="_security_token" value="' . _xsrfToken() . '">';
}

/**
 * Pridat XSRF ochranu do URL
 *
 * @param string $url    adresa
 * @param bool   $entity oddelit argument pomoci &amp; namisto & 1/0
 * @return string
 */
function _xsrfLink($url, $entity = true)
{
    return _addGetToLink($url, '_security_token=' . rawurlencode(_xsrfToken()), $entity);
}

/**
 * Vygenerovat XSRF token
 *
 * @param bool $forCheck token je ziskavan pro kontrolu (je bran ohled na situaci, ze mohlo zrovna dojit ke zmene ID session) 1/0
 * @return string
 */
function _xsrfToken($forCheck = false)
{
    // cache tokenu
    static $tokens = array(null, null);

    // typ tokenu (aktualni ci pro kontrolu)
    $type = ($forCheck ? 1 : 0);

    // vygenerovat token
    if (null === $tokens[$type]) {

        // zjistit ID session
        if (!Core::$sessionEnabled) {
            // session je deaktivovana
            $sessionId = 'none';
        } elseif ($forCheck && Core::$sessionRegenerate) {
            // ID session bylo prave pregenerovane
            $sessionId = Core::$sessionPreviousId;
        } else {
            // ID aktualni session
            $sessionId = session_id();
            if ('' === $sessionId) {
                $sessionId = 'none';
            }
        }

        // vygenerovat token
        $tokens[$type] = hash_hmac('sha256', $sessionId, Core::$secret);

    }

    // vystup
    return $tokens[$type];
}

/**
 * Zkontrolovat XSRF token
 *
 * @param bool $get zkontrolovat token v $_GET namisto $_POST 1/0
 * @return bool
 */
function _xsrfCheck($get = false)
{
    // determine data source variable
    if ($get) {
        $tvar = '_GET';
    } else {
        $tvar = '_POST';
    }

    // load used token
    if (isset($GLOBALS[$tvar]['_security_token'])) {
        $test = strval($GLOBALS[$tvar]['_security_token']);
        unset($GLOBALS[$tvar]['_security_token']);
    } else {
        $test = null;
    }

    // check
    if (null !== $test && _xsrfToken(true) === $test) {
        return true;
    }

    return false;
}

/**
 * Ziskat kod formulare pro opakovani POST requestu
 *
 * @param bool         $allow_login   umoznit znovuprihlaseni, neni-li uzivatel prihlasen 1/0
 * @param Message|null $login_message vlastni hlaska
 * @param string|null  $target_url    cil formulare (null = aktualni URL)
 * @param bool         $do_repeat     odeslat na cilovou adresu 1/0
 * @return string
 */
function _postRepeatForm($allow_login = true, Message $login_message = null, $target_url = null, $do_repeat = false)
{
    global $_lang;

    if (null === $target_url) {
        $target_url = $_SERVER['REQUEST_URI'];
    }

    if ($do_repeat) {
        $action = $target_url;
    } else {
        $action = _link('system/script/post_repeat.php?login=' . ($allow_login ? '1' : '0') . '&target=' . rawurlencode($target_url));
    }

    $output = "<form name='post_repeat' method='post' action='" . _e($action) . "'>\n";
    $output .= _renderHiddenPostInputs(null, $allow_login ? 'login_' : null);

    if ($allow_login && !_login) {
        if (null === $login_message) {
            $login_message = Message::ok($_lang['post_repeat.login']);
        }
        $login_message->append('<div class="hr"><hr></div>' . _userLoginForm(false, false, null, true), true);

        $output .= $login_message;
    } elseif (null !== $login_message) {
        $output .= $login_message;
    }

    $output .= "<p><input type='submit' value='" . $_lang[$do_repeat ? 'post_repeat.submit' : 'global.continue'] . "'></p>";
    $output .= _xsrfProtect() . "</form>\n";

    return $output;
}

/**
 * Zobrazit IP adresu bez posledni sekvence
 *
 * @param string $ip   ip adresa
 * @param string $repl retezec, kterym se ma nahradit posledni sekvence
 * @return string
 */
function _showIP($ip, $repl = 'x')
{
    if (_logingroup == 1) {
        // hlavni administratori vidi vzdy puvodni IP
        return $ip;
    }
    
    return substr($ip, 0, strrpos($ip, '.') + 1) . $repl;
}

/**
 * Wrapper funkce mail umoznujici odchyceni rozsirenim
 *
 * @param string $to      prijemce
 * @param string $subject predmet (automaticky formatovan jako UTF-8)
 * @param string $message zprava
 * @param array  $headers asociativni pole s hlavickami
 * @return bool
 */
function _mail($to, $subject, $message, array $headers = array())
{
    // zjistit veskere hlavicky, ktere byly uvedeny
    $definedHeaderMap = array();
    foreach (array_keys($headers) as $headerName) {
        $definedHeaderMap[strtolower($headerName)] = true;
    }

    // vychozi hlavicky
    if (_mailerusefrom && !isset($definedHeaderMap['from'])) {
        $headers['From'] = _sysmail;
    }
    if (!isset($definedHeaderMap['content-type'])) {
        $headers['Content-Type'] = 'text/plain; charset=UTF-8';
    }
    if (!isset($definedHeaderMap['x-mailer'])) {
        $headers['X-Mailer'] = sprintf('PHP/%s', phpversion());
    }

    // udalost
    $result = null;
    Extend::call('fc.mail', array(
        'to' => &$to,
        'subject' => &$subject,
        'message' => &$message,
        'headers' => &$headers,
        'result' => &$result,
    ));
    if (null !== $result) {
        // odchyceno rozsirenim
        return $result;
    }

    // predmet
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // zpracovani hlavicek
    $headerString = '';
    foreach ($headers as $headerName => $headerValue) {
        $headerString .= sprintf("%s: %s\n", $headerName, strtr($headerValue, array("\r" => '', "\n" => '')));
    }

    // odeslani
    return @mail(
        $to,
        $subject,
        $message,
        $headerString
     );
}

/**
 * Nastavit odesilatele emailu pomoci hlavicky
 *
 * @param array       &$headers reference na pole s hlavickami
 * @param string      $sender   emailova adresa odesilatele
 * @param string|null $name     jmeno odesilatele
 */
function _setMailSender(array &$headers, $sender, $name = null)
{
    if (_mailerusefrom) {
        $headerName = 'From';
    } else {
        $headerName = 'Reply-To';
    }

    if (null !== $name) {
        $headerValue = sprintf('%s <%s>', $name, $sender);
    } else {
        $headerValue = $sender;
    }

    $headers[$headerName] = $headerValue;
}

/**
 * Bufferovat a vratit vystup daneho callbacku
 *
 * @param callable $callback
 * @param array    $arguments
 * @return string
 */
function _buffer($callback, array $arguments = array())
{
    ob_start();

    $e = null;
    try {
        call_user_func_array($callback, $arguments);
    } catch (Exception $e) {
    } catch (Throwable $e) {
    }
    
    if (null !== $e) {
        ob_end_clean();
        throw $e;
    }

    return ob_get_clean();
}

/**
 * Ziskat instanci aktualniho motivu
 *
 * @return TemplatePlugin
 */
function _getCurrentTemplate()
{
    // pouzit globalni promennou
    // (index)
    if (_env_web && isset($GLOBALS['_template']) && $GLOBALS['_template'] instanceof TemplatePlugin) {
        
        return $GLOBALS['_template'];
    }

    // pouzit argument z GET
    // (moznost pro skripty mimo index)
    $request_template = _get('current_template');
    if (null !== $request_template && TemplateHelper::templateExists($request_template)) {
        return TemplateHelper::getTemplate($request_template);
    }

    // pouzit vychozi
    return TemplateHelper::getDefaultTemplate();
}

/**
 * Pridat nazev aktualniho motivu do URL
 *
 * @param string $url
 * @param bool   $entity {@see _addGetToLink()}
 * @return string
 */
function _addCurrentTemplateToURL($url, $entity = true)
{
    return _addGetToLink($url, 'current_template=' . _getCurrentTemplate()->getName(), $entity);
}
