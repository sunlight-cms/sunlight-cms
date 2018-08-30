<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Exception\ContentPrivilegeException;
use Sunlight\Util\ArgList;

abstract class Hcm
{
    protected static $systemModuleCache;

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
        $params = ArgList::parse($match[1]);
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
            if (!isset(static::$systemModuleCache[$name])) {
                $file = _root . 'system/hcm/' . basename($module[0]) . '.php';

                static::$systemModuleCache[$name] = is_file($file) ? require $file : false;
            }

            if (static::$systemModuleCache[$name] !== false) {
                ++Core::$hcmUid;

                return call_user_func_array(static::$systemModuleCache[$name], $args);
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
        if (count($whitelist) === 1 && $whitelist[0] === '*') {
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
            $params = ArgList::parse($match[1]);
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

    /**
     * Sestaveni casti SQL dotazu po WHERE pro filtrovani zaznamu podle moznych hodnot daneho sloupce
     *
     * @param string       $column nazev sloupce v tabulce
     * @param string|array $values mozne hodnoty sloupce v poli, oddelene pomlckami nebo "all" pro vypnuti limitu
     * @return string
     */
    static function createColumnInSqlCondition($column, $values)
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
     * Normalizovat promennou
     *
     * V pripade chyby bude promenna nastavena na null.
     *
     * @param &mixed $variable    promenna
     * @param string $type        pozadovany typ, viz PHP funkce settype()
     * @param bool   $emptyToNull je-li hodnota prazdna ("" nebo null), nastavit na null 1/0
     */
    static function normalizeArgument(&$variable, $type, $emptyToNull = true)
    {
        if (
            $emptyToNull && ($variable === null || $variable === '')
            || !settype($variable, $type)
        ) {
            $variable = null;
        }
    }
}
