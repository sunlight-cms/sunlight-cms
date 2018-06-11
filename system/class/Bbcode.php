<?php

namespace Sunlight;

use Sunlight\Util\UrlHelper;

abstract class Bbcode
{
    /**
     * Entry format:
     *
     *      array(
     *           (bool) is pair tag
     *           (bool) has argument
     *           (bool) is nestable
     *           (bool) parse children
     *           (null|int|string) button icon (null = none, 1 = template, string = custom path)
     *      )
     */
    protected static $tags = array(
        'b' => array(true, false, true, true, 1), // bold
        'i' => array(true, false, true, true, 1), // italic
        'u' => array(true, false, true, true, 1), // underline
        'q' => array(true, false, true, true, null), // quote
        's' => array(true, false, true, true, 1), // strike
        'img' => array(true, true, false, false, 1), // image
        'code' => array(true, true, false, true, 1), // code
        'c' => array(true, false, true, true, null), // inline code
        'url' => array(true, true, true, false, 1), // link
        'hr' => array(false, false, false, false, 1), // horizontal rule
        'color' => array(true, true, true, true, null), // color
        'size' => array(true, true, true, true, null), // size
        'noformat' => array(true, false, true, false, null), // no format
    );

    protected static $syntax = array('[', ']', '/', '=', '"');

    protected static $extended = false;

    /**
     * Get known BBCode tags
     *
     * @return array
     */
    static function getTags()
    {
        self::$extended || static::extendTags();

        return static::$tags;
    }

    /**
     * Parse BBCode tags in string
     *
     * @param string $s input string (HTML)
     * @return string
     */
    static function parse($s)
    {
        self::$extended || static::extendTags();

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
                    if ($char === static::$syntax[0]) {
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
                        // tag character
                        $tag .= $char;
                    } elseif ($tag === '' && $char === static::$syntax[2]) {
                        // closing tag
                        $closing = true;
                        break;
                    } elseif ($char === static::$syntax[1]) {
                        // tag end
                        $tag = mb_strtolower($tag);
                        if (isset(static::$tags[$tag])) {
                            if ($parents_n === -1 || static::$tags[$tag][2] || static::$tags[$tag][0] && $closing) {
                                if (static::$tags[$tag][0]) {
                                    // paired tag
                                    if ($closing) {
                                        if ($parents_n === -1 || $parents[$parents_n][0] !== $tag) {
                                            // reset - invalid closing tag
                                            $reset = 2;
                                        } else {
                                            --$parents_n;
                                            $pop = array_pop($parents);
                                            $buffer = static::processTag($pop[0], $pop[1], $pop[2]);
                                            if ($parents_n === -1) {
                                                $output .= $buffer;
                                            } else {
                                                $parents[$parents_n][2] .= $buffer;
                                            }
                                            $reset = 1;
                                            $char = '';
                                        }
                                    } elseif ($parents_n === -1 || static::$tags[$parents[$parents_n][0]][3]) {
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
                                    $buffer = static::processTag($tag, $arg);
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
                    } elseif ($char === static::$syntax[3]) {
                        if (isset(static::$tags[$tag]) && static::$tags[$tag][1] === true && $arg === '' && !$closing) {
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
                        if ($char === static::$syntax[4]) {
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
                        if ($char !== static::$syntax[4]) {
                            // char ok
                            $arg .= $char;
                            break;
                        }
                    } elseif ($char !== static::$syntax[1]) {
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
                        if (isset($s[$i + 1]) && $s[$i + 1] === static::$syntax[1]) {
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

    protected static function processTag($tag, $arg = '', $buffer = null)
    {
        // load extend tag processors
        static $ext = null;
        if (!isset($ext)) {
            $ext = array();
            Extend::call('bbcode.init.proc', array('tags' => &$ext));
        }

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
                    $url = UrlHelper::isSafe($url) ? UrlHelper::addScheme($url) : '#';

                    return '<a href="' . $url . '" rel="nofollow" target="_blank">' . $buffer . '</a>';
                }
                break;

            case 'hr':
                return '<span class="hr"></span>';

            case 'color':
                static $colors = array('aqua' => 0, 'black' => 1, 'blue' => 2, 'fuchsia' => 3, 'gray' => 4, 'green' => 5, 'lime' => 6, 'maroon' => 7, 'navy' => 8, 'olive' => 9, 'orange' => 10, 'purple' => 11, 'red' => 12, 'silver' => 13, 'teal' => 14, 'white' => 15, 'yellow' => 16);
                if ($buffer !== '') {
                    if (preg_match('{#[0-9A-Fa-f]{3,6}$}AD', $arg) !== 1) {
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
                if ($buffer !== '' && UrlHelper::isSafe($buffer)) {
                    $src = UrlHelper::ensureValidScheme($buffer);
                    $link = ($arg !== '' && UrlHelper::isSafe($arg)) ? UrlHelper::addScheme($arg) : $src;

                    return '<a href="' . $link . '" rel="nofollow" target="_blank">'
                        . '<img src="' . $src . '" alt="img" class="bbcode-img">'
                        . '</a>';
                }
                break;

            case 'noformat':
                return $buffer;
        }

        return '';
    }

    protected static function extendTags()
    {
        Extend::call('bbcode.init.tags', array('tags' => &static::$tags));
        static::$extended = true;
    }
}
