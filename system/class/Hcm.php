<?php

namespace Sunlight;

use Sunlight\Database\Database as DB;
use Sunlight\Exception\ContentPrivilegeException;
use Sunlight\Util\ArgList;

abstract class Hcm
{
    private static $systemModuleCache;

    /**
     * Vyhodnotit HCM moduly v retezci
     *
     * @param string $input   vstupni retezec
     * @param string $handler callback vyhodnocovace modulu
     * @return string
     */
    static function parse(string $input, $handler = [__CLASS__, 'evaluateMatch']): string
    {
        return preg_replace_callback('{\[hcm\](.*?)\[/hcm\]}s', $handler, $input);
    }

    /**
     * Spustit modul
     *
     * @param array $match
     * @return string
     */
    static function evaluateMatch(array $match): string
    {
        $params = ArgList::parse($match[1]);
        if (isset($params[0])) {
            return (string) self::run($params[0], array_splice($params, 1));
        }

        return '';
    }

    /**
     * Zavolat konkretni HCM modul
     *
     * @param string $name nazev hcm modulu
     * @param array  $args pole s argumenty
     * @return mixed vystup HCM modulu
     */
    static function run(string $name, array $args = [])
    {
        if (_env !== Core::ENV_WEB) {
            // HCM moduly vyzaduji frontendove prostredi
            return '';
        }

        $module = explode('/', $name, 2);

        if (!isset($module[1])) {
            // systemovy modul
            if (!isset(self::$systemModuleCache[$name])) {
                $file = _root . 'system/hcm/' . basename($module[0]) . '.php';

                self::$systemModuleCache[$name] = is_file($file) ? require $file : false;
            }

            if (self::$systemModuleCache[$name] !== false) {
                ++Core::$hcmUid;

                return (self::$systemModuleCache[$name])(...$args);
            }

            return '';
        }

        // extend modul
        ++Core::$hcmUid;

        return Extend::buffer("hcm.{$module[0]}.{$module[1]}", [
            'args' => $args,
        ]);
    }

    /**
     * Filtrovat HCM moduly v obsahu na zakladne opravneni
     *
     * @param string $content   obsah, ktery ma byt filtrovan
     * @param bool   $exception emitovat vyjimku v pripade nalezeni nepovoleneho HCM modulu 1/0
     * @throws ContentPrivilegeException
     * @return string
     */
    static function filter(string $content, bool $exception = false): string
    {
        // pripravit seznamy
        $blacklist = [];
        if (!User::hasPrivilege('adminhcmphp')) {
            $blacklist[] = 'php';
        }

        $whitelist = preg_split('{\s*,\s*}', User::hasPrivilege('adminhcm'));
        if (count($whitelist) === 1 && $whitelist[0] === '*') {
            $whitelist = null; // vsechny HCM moduly povoleny
        }

        Extend::call('hcm.filter', [
            'blacklist' => &$blacklist,
            'whitelist' => &$whitelist,
        ]);

        // pripravit mapy
        $blacklistMap = $blacklist !== null ? array_flip($blacklist) : null;
        $whitelistMap = $whitelist !== null ? array_flip($whitelist) : null;

        // filtrovat
        return self::parse($content, function ($match) use ($blacklistMap, $whitelistMap, $exception) {
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
    static function remove(string $content): string
    {
        return self::parse($content, function () {
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
    static function createColumnInSqlCondition(string $column, $values): string
    {
        if ($values !== 'all') {
            if (!is_array($values)) {
                $values = explode('-', $values);
            }
            return $column . ' IN(' . DB::val($values, true) . ')';
        }

        return '1';
    }

    /**
     * Normalizovat promennou
     *
     * V pripade chyby bude promenna nastavena na null.
     *
     * @param mixed $variable    promenna
     * @param string $type        pozadovany typ, viz PHP funkce settype()
     * @param bool   $emptyToNull je-li hodnota prazdna ("" nebo null), nastavit na null 1/0
     */
    static function normalizeArgument(&$variable, string $type, bool $emptyToNull = true): void
    {
        if (
            $emptyToNull && ($variable === null || $variable === '')
            || !settype($variable, $type)
        ) {
            $variable = null;
        }
    }
}
