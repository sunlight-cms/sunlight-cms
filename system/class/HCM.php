<?php

namespace Sunlight;

use Sunlight\Exception\ContentPrivilegeException;

abstract class HCM
{
    /** @var array loaded system modules */
    protected static $modules;

    /**
     * Vyhodnotit HCM moduly v retezci
     *
     * @param string $input   vstupni retezec
     * @param string $handler callback vyhodnocovace modulu
     * @return string
     */
    static function parse($input, $handler = array(__CLASS__, 'evaluateMatch'))
    {
        return preg_replace_callback('{\[hcm\](.*?)\[/hcm\]}s', $handler, $input);
    }

    /**
     * Spustit modul
     *
     * @param array $match
     * @return string
     */
    static function evaluateMatch($match)
    {
        $params = _parseArguments($match[1]);
        if (isset($params[0])) {
            return static::run($params[0], array_splice($params, 1));
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
    static function run($name, array $args = array())
    {
        if (_env !== Core::ENV_WEB) {
            // HCM moduly vyzaduji frontendove prostredi
            return '';
        }

        $module = explode('/', $name, 2);

        if (!isset($module[1])) {
            // systemovy modul
            if (!isset(static::$modules[$name])) {
                $file = _root . 'system/hcm/' . basename($module[0]) . '.php';

                static::$modules[$name] = is_file($file) ? require $file : false;
            }

            if (static::$modules[$name] !== false) {
                ++Core::$hcmUid;

                return call_user_func_array(static::$modules[$name], $args);
            }

            return '';
        } else {
            // extend modul
            ++Core::$hcmUid;

            return Extend::buffer("hcm.{$module[0]}.{$module[1]}", array(
                'args' => $args,
            ));
        }
    }

    /**
     * Filtrovat HCM moduly v obsahu na zakladne opravneni
     *
     * @param string $content   obsah, ktery ma byt filtrovan
     * @param bool   $exception emitovat vyjimku v pripade nalezeni nepovoleneho HCM modulu 1/0
     * @throws ContentPrivilegeException
     * @return string
     */
    static function filter($content, $exception = false)
    {
        // pripravit seznamy
        $blacklist = array();
        if (!_priv_adminhcmphp) {
            $blacklist[] = 'php';
        }

        $whitelist = preg_split('{\s*,\s*}', _priv_adminhcm);
        if (sizeof($whitelist) === 1 && $whitelist[0] === '*') {
            $whitelist = null; // vsechny HCM moduly povoleny
        }

        Extend::call('hcm.filter', array(
            'blacklist' => &$blacklist,
            'whitelist' => &$blacklist,
        ));

        // pripravit mapy
        $blacklistMap = $blacklist !== null ? array_flip($blacklist) : null;
        $whitelistMap = $whitelist !== null ? array_flip($whitelist) : null;

        // filtrovat
        return static::parse($content, function ($match) use ($blacklistMap, $whitelistMap, $exception) {
            $params = _parseArguments($match[1]);
            $module = isset($params[0]) ? mb_strtolower($params[0]) : '';

            if (
                $whitelistMap !== null && !isset($whitelistMap[$module])
                || $blacklistMap === null
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
    static function remove($content)
    {
        return static::parse($content, function () {
            return '';
        });
    }
}
