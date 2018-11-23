<?php

namespace Sunlight\Util;

abstract class ArgList
{
    protected static $separator = ',';
    protected static $quote = '"';
    protected static $quote2 = "'";
    protected static $escape = '\\';
    protected static $wsMap = array("\n" => 0, "\r" => 1, "\t" => 2, " " => 3);
    protected static $keywordMap = array('null' => 0, 'true' => true, 'false' => false);

    /**
     * Rozebrat retezec na parametry a vratit jako pole
     *
     * @param string $input vstupni retezec
     * @return array
     */
    static function parse($input)
    {
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
                        if ($char === static::$separator) {
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
                        if ($char === static::$quote || $char === static::$quote2) {
                            $val_quote = $char;
                            break;
                        } else {
                            $val_quote = null;
                        }
                    }

                    // zpracovat znak
                    if (isset($val_quote)) {

                        // v retezci s uvozovkami
                        if ($char === static::$escape) {

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
                                $output[] = trim($val);
                                $mode = 2; // najit konec
                            }

                        } else {
                            // normalni znak
                            if ($escaped) {
                                // escapovany normalni znak
                                $val .= static::$escape;
                                $escaped = false;
                            }
                            $val .= $char;
                        }

                    } else {

                        // mimo uvozovky
                        if ($char === static::$separator || $i === $last) {
                            // konec hodnoty
                            $ws_buffer = '';
                            if ($i === $last) {
                                $val .= $char;
                            }

                            // detekovat klicova slova
                            if (isset($keywords[$val])) {
                                if (static::$keywordMap[$val] === 0) {
                                    $val = null;
                                } else {
                                    $val = static::$keywordMap[$val];
                                }
                            }

                            $output[] = trim($val);
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
                    if ($char === static::$separator) {
                        $mode = 0;
                    }
                    break;

            }

        }

        // vystup
        return $output;
    }
}
