<?php

namespace Sunlight;

use Sunlight\Exception\ContentPrivilegeException;
use Sunlight\Util\ArgList;

abstract class Hcm
{
    /** @var int unique HCM identifier */
    public static $uid = 0;
    /** @var array<string, string> */
    private static $registry = [
        'articles' => __DIR__ . '/../hcm/articles.php',
        'countart' => __DIR__ . '/../hcm/countart.php',
        'countusers' => __DIR__ . '/../hcm/countusers.php',
        'date' => __DIR__ . '/../hcm/date.php',
        'file' => __DIR__ . '/../hcm/file.php',
        'filesize' => __DIR__ . '/../hcm/filesize.php',
        'galimg' => __DIR__ . '/../hcm/galimg.php',
        'gallery' => __DIR__ . '/../hcm/gallery.php',
        'img' => __DIR__ . '/../hcm/img.php',
        'iperex' => __DIR__ . '/../hcm/iperex.php',
        'lang' => __DIR__ . '/../hcm/lang.php',
        'levelcontent' => __DIR__ . '/../hcm/levelcontent.php',
        'levelcontent2' => __DIR__ . '/../hcm/levelcontent2.php',
        'linkart' => __DIR__ . '/../hcm/linkart.php',
        'linkpage' => __DIR__ . '/../hcm/linkpage.php',
        'mailform' => __DIR__ . '/../hcm/mailform.php',
        'mailto' => __DIR__ . '/../hcm/mailto.php',
        'menu' => __DIR__ . '/../hcm/menu.php',
        'menu_subtree' => __DIR__ . '/../hcm/menu_subtree.php',
        'menu_tree' => __DIR__ . '/../hcm/menu_tree.php',
        'msg' => __DIR__ . '/../hcm/msg.php',
        'notpublic' => __DIR__ . '/../hcm/notpublic.php',
        'path' => __DIR__ . '/../hcm/path.php',
        'php' => __DIR__ . '/../hcm/php.php',
        'phpsource' => __DIR__ . '/../hcm/phpsource.php',
        'poll' => __DIR__ . '/../hcm/poll.php',
        'randomfile' => __DIR__ . '/../hcm/randomfile.php',
        'recentposts' => __DIR__ . '/../hcm/recentposts.php',
        'sbox' => __DIR__ . '/../hcm/sbox.php',
        'search' => __DIR__ . '/../hcm/search.php',
        'source' => __DIR__ . '/../hcm/source.php',
        'usermenu' => __DIR__ . '/../hcm/usermenu.php',
        'users' => __DIR__ . '/../hcm/users.php',
    ];
    /** @var array<string, \Closure> */
    private static $cache = [];

    static function register(string $name, string $scriptPath): void
    {
        self::$registry[$name] = $scriptPath;
    }

    /**
     * Parse HCM modules in a string
     */
    static function parse(string $input, $handler = [__CLASS__, 'evaluateMatch']): string
    {
        return preg_replace_callback('{\[hcm\](.*?)\[/hcm\]}s', $handler, $input);
    }

    /**
     * @internal
     */
    static function evaluateMatch(array $match): string
    {
        $params = ArgList::parse($match[1]);

        if (isset($params[0])) {
            return self::run($params[0], array_slice($params, 1));
        }

        return '';
    }

    /**
     * Run a single HCM module
     */
    static function run(string $name, array $args = []): string
    {
        if (Core::$env !== Core::ENV_WEB) {
            return ''; // HCM modules can't be run outside of web env
        }

        $closure = self::$cache[$name] ?? (
            isset(self::$registry[$name])
                ? self::load(self::$registry[$name])
                : null
        );

        if ($closure === null) {
            return '';
        }

        ++self::$uid;

        return $closure(...$args);
    }

    /**
     * Filter HCM modules in the given content according to user privileges
     *
     * @throws ContentPrivilegeException if $exception is TRUE and a denied HCM module is found
     */
    static function filter(string $content, bool $exception = false): string
    {
        $deniedModules = [];
        if (!User::hasPrivilege('adminhcmphp')) {
            $deniedModules[] = 'php';
        }

        $allowedModules = preg_split('{\s*,\s*}', User::$group['adminhcm']);
        if (count($allowedModules) === 1 && $allowedModules[0] === '*') {
            $allowedModules = null; // vsechny HCM moduly povoleny
        }

        Extend::call('hcm.filter', [
            'denied_modules' => &$deniedModules,
            'allowed_modules' => &$allowedModules,
        ]);

        $deniedMap = $deniedModules !== null ? array_flip($deniedModules) : null;
        $allowedMap = $allowedModules !== null ? array_flip($allowedModules) : null;

        return self::parse($content, function ($match) use ($deniedMap, $allowedMap, $exception) {
            $params = ArgList::parse($match[1]);
            $module = isset($params[0]) ? mb_strtolower($params[0]) : '';

            if (
                $allowedMap !== null && !isset($allowedMap[$module])
                || $deniedMap === null
                || isset($deniedMap[$module])
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
     * Remove all HCM modules from content
     */
    static function remove(string $content): string
    {
        return self::parse($content, function () { return ''; });
    }

    /**
     * Normalize HCM argument
     *
     * In case of failure, the variable is set to NULL.
     *
     * @param string $type {@see settype()}
     * @param bool $emptyToNull if the value is an empty string, set it to NULL
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

    private static function load(string $scriptPath): \Closure
    {
        $closure = require $scriptPath;

        if (!$closure instanceof \Closure) {
            throw new \UnexpectedValueException(sprintf('HCM script "%s" did not return a closure', $scriptPath));
        }

        return $closure;
    }
}
