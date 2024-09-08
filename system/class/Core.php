<?php

namespace Sunlight;

use Composer\Autoload\ClassLoader;
use Composer\Semver\VersionParser;
use Kuria\Cache\Cache;
use Kuria\Cache\Driver;
use Kuria\Cache\Driver\Memory\MemoryDriver;
use Kuria\Event\EventEmitter;
use Kuria\RequestInfo\RequestInfo;
use Kuria\RequestInfo\TrustedProxies;
use Kuria\Url\Url;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseException;
use Sunlight\ErrorHandler\ErrorHandler;
use Sunlight\Exception\CoreException;
use Sunlight\Localization\LocalizationDictionary;
use Sunlight\Plugin\LanguagePlugin;
use Sunlight\Plugin\PluginManager;
use Sunlight\Util\DateTime;
use Sunlight\Util\Environment;
use Sunlight\Util\Json;

/**
 * Main system singleton
 *
 * Manages core components and configuration.
 */
abstract class Core
{
    /** CMS version */
    const VERSION = '8.2.0-dev';
    /** Database structure version */
    const DB_VERSION = 'sl8db-002';
    /** Web environment (index.php) */
    const ENV_WEB = 'web';
    /** Administration environment (admin/index.php or admin scripts) */
    const ENV_ADMIN = 'admin';
    /** Script environment */
    const ENV_SCRIPT = 'script';

    /** @var float */
    static $start;
    /** @var string */
    static $env;
    /** @var bool */
    static $debug;
    /** @var string */
    static $secret;
    /** @var string */
    static $lang;
    /** @var string */
    static $fallbackLang;
    /** @var LanguagePlugin */
    static $langPlugin;
    /** @var bool */
    static $safeMode;

    /** @var ClassLoader */
    static $classLoader;
    /** @var ErrorHandler */
    static $errorHandler;
    /** @var EventEmitter */
    static $eventEmitter;
    /** @var PluginManager */
    static $pluginManager;
    /** @var Cache */
    static $cache;
    /** @var LocalizationDictionary */
    static $dictionary;

    /** @var Url */
    private static $baseUrl;
    /** @var Url */
    private static $currentUrl;
    /** @var string|null */
    private static $stability;
    /** @var bool */
    private static $ready = false;

    /**
     * Initialize the system
     *
     * Supported options:
     * ------------------
     * - config_file (-)          path to the configuration file, null (= default) or false (= skip)
     * - minimal_mode (0)         stop after initializing base components and environment (= no plugins, db, settings, session, etc.) 1/0
     * - session_enabled          initialize session 1/0 (defaults to false in CLI, otherwise true)
     * - content_type (-)         content type, FALSE = disabled (default is "text/html; charset=UTF-8")
     * - env ("script")           environment identifier, see Core::ENV_* constants
     * - base_url (-)             override the base URL (can be absolute or relative to override only the path)
     * - error_handler (1)        enable the default error handler 1/0
     * - debug (0)                enable debug mode 1/0
     *
     * @param array{
     *     config_file?: string|false|null,
     *     minimal_mode?: bool,
     *     session_enabled?: bool,
     *     content_type?: string|false,
     *     env?: string,
     *     base_url?: string|null,
     *     error_handler?: bool,
     *     debug?: bool,
     * } $options see description
     */
    static function init(array $options = []): void
    {
        if (self::$ready) {
            throw new \LogicException('Already initialized');
        }

        self::$start = microtime(true);

        // define SL_ROOT
        $rootPath = realpath(__DIR__ . '/../..');

        if ($rootPath === false) {
            throw new \RuntimeException('Could not resolve root path');
        }
        
        if ($rootPath !== '/') {
            $rootPath .= DIRECTORY_SEPARATOR;
        }

        define('SL_ROOT', $rootPath);

        // initialization
        require __DIR__ . '/../functions.php';
        self::initBaseComponents($options);
        self::initConfiguration($options);
        self::initEnvironment($options);
        self::initUrls($options);
        self::initComponents($options);

        if ($options['minimal_mode']) {
            return; // minimal mode enabled
        }

        self::initDatabase($options);
        self::$pluginManager->initialize();
        Extend::call('plugins.ready');
        Settings::init();
        Logger::init();
        self::checkSystemState($options);
        Session::init($options['session_enabled']);
        User::init();
        self::initLocalization();

        self::$ready = true;
        Extend::call('core.ready');

        // run cron tasks on shutdown
        if (self::$env !== self::ENV_SCRIPT && Settings::get('cron_auto')) {
            register_shutdown_function(function () {
                if (!Settings::get('cron_auto')) {
                    return; // setting has been changed or overridden during request
                }

                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                Session::close(); // don't block session writes
                chdir(dirname($_SERVER['SCRIPT_FILENAME'])); // fix working directory
                Cron::run();
            });
        }
    }

    /**
     * Init base components that don't depend on configuration
     */
    private static function initBaseComponents(array $options): void
    {
        // error handler
        self::$errorHandler = new ErrorHandler();

        if ($options['error_handler'] ?? true) {
            self::$errorHandler->register();
        }

        // event emitter
        self::$eventEmitter = new EventEmitter();
    }

    /**
     * Initialize configuration
     */
    private static function initConfiguration(array &$options): void
    {
        // defaults
        $options += [
            'config_file' => null,
            'minimal_mode' => false,
            'session_enabled' => !Environment::isCli(),
            'content_type' => null,
            'env' => self::ENV_SCRIPT,
        ];

        // load config file
        if ($options['config_file'] !== false) {
            if ($options['config_file'] === null) {
                $options['config_file'] = SL_ROOT . 'config.php';
            }

            $configFileOptions = @include $options['config_file'];

            if ($configFileOptions === false) {
                self::fail(
                    'Chybí soubor "config.php". Otevřete /install pro instalaci.',
                    'The "config.php" file is missing. Open /install to create it.'
                );
            }

            $options += $configFileOptions;
        }

        // config defaults
        $options += [
            'debug' => false,
            'db.server' => null,
            'db.port' => null,
            'db.user' => null,
            'db.password' => null,
            'db.name' => null,
            'db.prefix' => null,
            'db.engine' => DB::ENGINE_INNODB,
            'secret' => null,
            'base_url' => null,
            'fallback_lang' => 'en',
            'fallback_base_url' => null,
            'cache' => true,
            'timezone' => 'Europe/Prague',
            'safe_mode' => false,
        ];

        // check required options
        if (!$options['minimal_mode']) {
            $requiredOptions = [
                'db.server',
                'db.name',
                'db.prefix',
                'secret',
            ];

            foreach ($requiredOptions as $requiredOption) {
                if (empty($options[$requiredOption])) {
                    self::fail(
                        "Konfigurační volba \"{$requiredOption}\" nesmí být prázdná.",
                        "The configuration option \"{$requiredOption}\" must not be empty."
                    );
                }
            }
        }

        // define variables
        self::$env = $options['env'];
        self::$debug = (bool) $options['debug'];
        self::$secret = $options['secret'];
        self::$fallbackLang = $options['fallback_lang'];
        self::$safeMode = (bool) $options['safe_mode'];
    }

    /**
     * Initialize environment
     */
    private static function initEnvironment(array $options): void
    {
        // ensure correct encoding for mb_*() functions
        mb_internal_encoding('UTF-8');

        // set error_reporting
        $err_rep = E_ALL;

        if (!self::$debug) {
            $err_rep &= ~(E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_STRICT);
        }

        error_reporting($err_rep);

        if (!empty($options['timezone'])) {
            date_default_timezone_set($options['timezone']);
        }

        // send default headers
        if (!Environment::isCli()) {
            if ($options['content_type'] === null) {
                header('Content-Type: text/html; charset=UTF-8');
                header('Expires: ' . DateTime::formatForHttp(-604800, true));
            } elseif ($options['content_type'] !== false) {
                header('Content-Type: ' . $options['content_type']);
            }
        }

        // set trusted proxies
        if (isset($options['trusted_proxies'], $options['trusted_proxy_headers'])) {
            switch ($options['trusted_proxy_headers']) {
                case 'forwarded':
                    $trustedProxyHeaders = TrustedProxies::HEADER_FORWARDED;
                    break;
                case 'x-forwarded':
                    $trustedProxyHeaders = TrustedProxies::HEADER_X_FORWARDED_ALL;
                    break;
                case 'all':
                    $trustedProxyHeaders = TrustedProxies::HEADER_FORWARDED | TrustedProxies::HEADER_X_FORWARDED_ALL;
                    break;
                default:
                    self::fail(
                        'Konfigurační volba "trusted_proxy_headers" má neplatnou hodnotu',
                        'The configuration option "trusted_proxy_headers" has an invalid value'
                    );
            }

            RequestInfo::setTrustedProxies(new TrustedProxies((array) $options['trusted_proxies'], $trustedProxyHeaders));
        }
    }

    private static function initUrls(array $options): void
    {
        if (Environment::isCli()) {
            // use provided or fallback base URL in CLI
            $base = Url::parse(
                $options['base_url'] 
                ?? $options['fallback_base_url']
                ?? 'http://localhost/'
            );

            if (!$base->hasScheme()) {
                $base->setScheme('http');
            }

            if (!$base->hasHost()) {
                $base->setHost('localhost');
            }

            $current = $base;
        } elseif (isset($options['base_url'])) {
            // use provided base URL and detect current URL
            $base = Url::parse($options['base_url']);
            $current = RequestInfo::getUrl();

            if (!$base->hasScheme()) {
                $base->setScheme($current->getScheme());
            }

            if (!$base->hasHost()) {
                $base->setHost($current->getHost());
                $base->setPort($current->getPort());
            }
        } else {
            // automatically determine URLs
            $base = new Url();
            $current = RequestInfo::getUrl();
            
            $base->setScheme($current->getScheme());
            $base->setHost($current->getHost());
            $base->setPort($current->getPort());
            $base->setPath(RequestInfo::getBaseDir());

            // drop possible subdirectories from base path
            if (
                isset($_SERVER['SCRIPT_FILENAME'])
                && ($scriptPath = realpath($_SERVER['SCRIPT_FILENAME'])) !== false
                && strncmp(SL_ROOT, $scriptPath, strlen(SL_ROOT)) === 0
                && ($subDirCount = substr_count($scriptPath, DIRECTORY_SEPARATOR, strlen(SL_ROOT))) > 0
            ) {
                $base->setPath(implode('/', array_slice(explode('/', $base->getPath()), 0, -$subDirCount)));
            }
        }

        self::$baseUrl = $base;
        self::$currentUrl = $current;
    }

    /**
     * Initialize components
     */
    private static function initComponents(array $options): void
    {
        // error handler
        self::$errorHandler->setDebug(self::$debug || Environment::isCli());

        // cache
        if (self::$cache === null) {
            self::$cache = new Cache(
                $options['cache'] && !self::$safeMode
                    ? new Driver\Filesystem\FilesystemDriver(
                        SL_ROOT . 'system/cache/core',
                        new Driver\Filesystem\Entry\EntryFactory(null, null, SL_ROOT . 'system/tmp')
                    )
                    : new MemoryDriver()
            );
        }

        // plugin manager
        self::$pluginManager = new PluginManager();

        // localization
        self::$dictionary = new LocalizationDictionary();
    }

    /**
     * Initialize database
     */
    private static function initDatabase(array $options): void
    {
        try {
            DB::connect(
                $options['db.server'],
                $options['db.user'],
                $options['db.password'],
                $options['db.name'],
                $options['db.port'],
                $options['db.prefix'],
                $options['db.engine']
            );
        } catch (DatabaseException $e) {
            self::fail(
                'Připojení k databázi se nezdařilo. Důvodem je pravděpodobně výpadek serveru nebo chybné přístupové údaje.',
                'Could not connect to the database. This may have been caused by the database server being temporarily unavailable or an error in the configuration.',
                null,
                self::$debug ? $e->getMessage() : null
            );
        }
    }

    /**
     * Check system state after initialization
     */
    private static function checkSystemState(array $options): void
    {
        // check database version
        if (Settings::get('dbversion') !== self::DB_VERSION) {
            self::fail(
                "Verze databáze %s není kompatibilní s verzí systému.\n\nJe požadována verze databáze %s.",
                "Database version %s is not compatible with system version.\n\nDatabase version %s is required.",
                [Settings::get('dbversion'), self::DB_VERSION]
            );
        }

        // installation check
        if ($options['config_file'] !== false) {
            $installCheckKey = sprintf('%s-%d', self::VERSION, filemtime($options['config_file']));

            if (Settings::get('install_check') !== $installCheckKey) {
                $systemChecker = new SystemChecker();
                $systemChecker->check();

                if ($systemChecker->hasErrors()) {
                    self::fail(
                        'Při kontrole instalace byly detekovány následující problémy:',
                        'The installation check has detected the following problems:',
                        null,
                        $systemChecker->renderErrors()
                    );
                }

                Settings::update('install_check', $installCheckKey, false);
            }
        }
    }

    /**
     * Initialize localization
     */
    private static function initLocalization(): void
    {
        // choose language
        if (self::$safeMode) {
            $lang = self::$fallbackLang;
        } elseif (User::isLoggedIn() && Settings::get('language_allowcustom') && User::$data['language'] !== '') {
            $lang = User::$data['language'];
        } else {
            $lang = Settings::get('language');
        }

        // load language plugin
        $langPlugin = self::$pluginManager->getPlugins()->getLanguage($lang)
            ?? self::$pluginManager->getPlugins()->getLanguage(self::$fallbackLang);

        if ($langPlugin === null) {
            self::fail(
                'Záložní jazykový plugin "%s" nebyl nalezen.',
                'Fallback language plugin "%s" was not found.',
                [self::$fallbackLang]
            );
        }

        $langPlugin->load();
        self::$lang = $langPlugin->getName();
        self::$langPlugin = $langPlugin;
    }

    static function isReady(): bool
    {
        return self::$ready;
    }

    /**
     * Get base URL
     *
     * The returned instance is a clone which may be modified.
     */
    static function getBaseUrl(): Url
    {
        return clone self::$baseUrl;
    }

    /**
     * Get current request URL
     *
     * The returned instance is a clone which may be modified.
     */
    static function getCurrentUrl(): Url
    {
        return clone self::$currentUrl;
    }

    /**
     * Check if the current request has been done via HTTPS
     */
    static function isHttpsEnabled(): bool
    {
        return self::$baseUrl->getScheme() === 'https';
    }

    /**
     * Get current client's IP address
     */
    static function getClientIp(): string
    {
        return RequestInfo::getClientIp() ?? '127.0.0.1';
    }

    /**
     * See if a persistent cache is used
     */
    static function isCacheEnabled(): bool
    {
        return !Core::$cache->getDriver() instanceof Driver\Memory\MemoryDriver
            && !Core::$cache->getDriver() instanceof Driver\BlackHole\BlackHoleDriver;
    }

    /**
     * Get global JavaScript definitions
     *
     * @param array $customVariables map of custom variables
     * @param bool $scriptTags wrap in a <script> tag 1/0
     */
    static function getJavascript(array $customVariables = [], bool $scriptTags = true): string
    {
        $output = '';

        // opening script tag
        if ($scriptTags) {
            $output .= '<script>';
        }

        // prepare variables
        $variables = [
            'basePath' => self::$baseUrl->getPath() . '/',
            'labels' => [
                'alertConfirm' => _lang('javascript.alert.confirm'),
                'loading' => _lang('javascript.loading'),
            ],
            'settings' => [
                'atReplace' => Settings::get('atreplace'),
            ],
        ];

        if (!empty($customVariables)) {
            $variables = array_merge_recursive($variables, $customVariables);
        }

        Extend::call('core.javascript', ['variables' => &$variables]);

        // output variables
        $output .= 'var SunlightVars = ' . Json::encodeForInlineJs($variables) . ';';

        // closing script tags
        if ($scriptTags) {
            $output .= '</script>';
        }

        return $output;
    }

    /**
     * Get system stability
     * 
     * @return 'stable'|'RC'|'beta'|'alpha'|'dev'
     */
    static function getStability(): string
    {
        return self::$stability ?? (self::$stability = VersionParser::parseStability(self::VERSION));
    }

    /**
     * Throw a localized core exception
     *
     * @param string $msgCs czech message
     * @param string $msgEn english message
     * @param array|null $msgArgs arguments for sprintf() formatting
     * @param string|null $msgExtra extra content below the message (not localized)
     * @throws CoreException
     */
    static function fail(string $msgCs, string $msgEn, ?array $msgArgs = null, ?string $msgExtra = null): void
    {
        $messages = [];

        if (self::$fallbackLang === 'cs' || empty(self::$fallbackLang)) {
            $messages[] = !empty($msgArgs) ? vsprintf($msgCs, $msgArgs) : $msgCs;
        }

        if (self::$fallbackLang !== 'cs' || empty(self::$fallbackLang)) {
            $messages[] = !empty($msgArgs) ? vsprintf($msgEn, $msgArgs) : $msgEn;
        }

        if (!empty($msgExtra)) {
            $messages[] = $msgExtra;
        }

        throw new CoreException(implode("\n\n", $messages));
    }
}
