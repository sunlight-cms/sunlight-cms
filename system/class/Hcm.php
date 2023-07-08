<?php

namespace Sunlight;

use Sunlight\Callback\CallbackHandler;
use Sunlight\Exception\ContentPrivilegeException;
use Sunlight\Util\ArgList;
use Sunlight\Util\Filesystem;

abstract class Hcm
{
    /** @var int unique HCM identifier */
    public static $uid = 0;
    /** @var array<string, string> */
    private static $modules = [
        'articles' => __DIR__ . '/../hcm/articles.php',
        'countart' => __DIR__ . '/../hcm/countart.php',
        'countusers' => __DIR__ . '/../hcm/countusers.php',
        'date' => __DIR__ . '/../hcm/date.php',
        'file' => __DIR__ . '/../hcm/file.php',
        'filelist' => __DIR__ . '/../hcm/filelist.php',
        'filesize' => __DIR__ . '/../hcm/filesize.php',
        'galimg' => __DIR__ . '/../hcm/galimg.php',
        'gallery' => __DIR__ . '/../hcm/gallery.php',
        'img' => __DIR__ . '/../hcm/img.php',
        'perex' => __DIR__ . '/../hcm/perex.php',
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
        'php_include' => __DIR__ . '/../hcm/php_include.php',
        'php_highlight' => __DIR__ . '/../hcm/php_highlight.php',
        'poll' => __DIR__ . '/../hcm/poll.php',
        'randomfile' => __DIR__ . '/../hcm/randomfile.php',
        'recentposts' => __DIR__ . '/../hcm/recentposts.php',
        'sbox' => __DIR__ . '/../hcm/sbox.php',
        'search' => __DIR__ . '/../hcm/search.php',
        'source' => __DIR__ . '/../hcm/source.php',
        'usermenu' => __DIR__ . '/../hcm/usermenu.php',
        'users' => __DIR__ . '/../hcm/users.php',
    ];

    /**
     * Parse HCM modules in a string
     */
    static function parse(string $input, $handler = [__CLASS__, 'evaluate']): string
    {
        $output = '';

        for ($offset = 0, $length = strlen($input); $offset < $length;) {
            $start = strpos($input, '[hcm]', $offset);

            if ($start === false) {
                break;
            }

            $argsStart = $start + 5;

            $end = strpos($input, '[/hcm]', $argsStart);

            if ($end === false) {
                break;
            }

            $output .= substr($input, $offset, $start - $length);
            $output .= $handler(substr($input, $argsStart, $end - $argsStart));
            $offset = $end + 6;
        }

        if ($offset === 0) {
            return $input;
        }

        if ($offset < $length) {
            $output .= substr($input, $offset);
        }

        return $output;
    }

    static function evaluate(string $argList): string
    {
        $argList = ArgList::parse($argList);

        if (isset($argList[0])) {
            return self::run((string) $argList[0], array_slice($argList, 1));
        }

        return '';
    }

    /**
     * Run a single HCM module
     */
    static function run(string $name, array $argList = []): string
    {
        if (Core::$env !== Core::ENV_WEB) {
            return ''; // HCM modules can't be run outside of web env
        }

        ++self::$uid;

        $output = null;

        Extend::call("hcm.run.{$name}", [
            'name' => $name,
            'arg_list' => &$argList,
            'output' => &$output,
        ]);

        // try to run system module (unless overridden by a plugin)
        if ($output === null) {
            if (isset(self::$modules[$name])) {
                $output = (string) CallbackHandler::fromScript(self::$modules[$name])(...$argList);
            } else {
                // unknown module
                Logger::warning('hcm', sprintf('Unknown HCM module "%s"', $name));
                $output = '';
            }
        }

        return $output;
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
            $deniedModules[] = 'php_include';
        }

        $allowedModules = preg_split('{\s*,\s*}', User::$group['adminhcm']);

        if (count($allowedModules) === 1 && $allowedModules[0] === '*') {
            $allowedModules = null; // all modules allowed
        }

        Extend::call('hcm.filter', [
            'denied_modules' => &$deniedModules,
            'allowed_modules' => &$allowedModules,
        ]);

        $deniedMap = $deniedModules !== null ? array_flip($deniedModules) : null;
        $allowedMap = $allowedModules !== null ? array_flip($allowedModules) : null;

        return self::parse($content, function ($argList) use ($deniedMap, $allowedMap, $exception) {
            $module = (string) (ArgList::parse($argList)[0] ?? '');

            if (
                $allowedMap !== null && !isset($allowedMap[$module])
                || $deniedMap === null
                || isset($deniedMap[$module])
            ) {
                if ($exception) {
                    throw new ContentPrivilegeException(sprintf('HCM module "%s"', $module));
                }

                return '';
            }

            return self::compose($argList);
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
     * Compose HCM module
     */
    static function compose(string $argList): string
    {
        return '[hcm]' . $argList . '[/hcm]';
    }

    /**
     * Normalize HCM argument
     *
     * @param string $type {@see settype()}
     * @param bool $nullable allow null value 1/0
     */
    static function normalizeArgument(&$variable, string $type, bool $nullable = false): void
    {
        // convert empty string to null if the type is a nullable string
        if ($type === 'string' && $nullable && $variable === '') {
            $variable = null;
            return;
        }

        // check for allowed null value
        if ($nullable && $variable === null) {
            return;
        }

        settype($variable, $type);
    }

    /**
     * Normalize HCM path argument
     *
     * - paths are restricted to upload/
     * - nonexistent, invalid or unsafe paths result in failure
     * - if normalization fails, the argument is set to NULL
     */
    static function normalizePathArgument(&$variable, bool $isFile, bool $allowUnsafeFiles = false): void
    {
        self::normalizeArgument($variable, 'string');

        $variable = Filesystem::resolvePath(SL_ROOT . $variable, $isFile, SL_ROOT . 'upload/');

        if ($variable === null) {
            return; // invalid path
        }

        if (
            $isFile && (!$allowUnsafeFiles && !Filesystem::isSafeFile($variable) || !is_file($variable)) // reject unsafe or nonexistent files
            || !$isFile && !is_dir($variable) // reject nonexistent dirs
        ) {
            $variable = null;
        }
    }
}
