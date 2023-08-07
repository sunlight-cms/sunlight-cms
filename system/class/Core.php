<?php

namespace Sunlight;

use Kuria\Cache\Cache;
use Kuria\Cache\Driver\Filesystem\Entry\EntryFactory;
use Kuria\Cache\Driver\Filesystem\FilesystemDriver;
use Kuria\Cache\Driver\Memory\MemoryDriver;
use Kuria\ClassLoader\ClassLoader;
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
    const VERSION = '8.0.0';
    /** CMS distribution type */
    const DIST = 'GIT'; // GIT / STABLE / BETA
    /** Web environment (frontend - index.php) */
    const ENV_WEB = 'web';
    /** Administration environment (backend) */
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
    static $sessionEnabled;
    /** @var bool */
    static $sessionRegenerate;
    /** @var string|null */
    static $sessionPreviousId;

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
    /** @var bool */
    private static $ready = false;

    /**
     * Initialize the system
     *
     * Supported options:
     * ------------------
     * config_file (-)          path to the configuration file, null (= default) or false (= skip)
     * minimal_mode (0)         stop after initializing base components and environment (= no plugins, db, settings, session, etc.) 1/0
     * session_enabled (1)      initialize session 1/0
     * session_regenerate (0)   force new session ID 1/0
     * content_type (-)         content type, FALSE = disabled (default is "text/html; charset=UTF-8")
     * env ("script")           environment identifier, see Core::ENV_* constants
     *
     * @param string $root relative path to the system root directory (with a trailing slash)
     * @param array{
     *     config_file?: string|false|null,
     *     minimal_mode?: bool,
     *     session_enabled?: bool,
     *     session_regenerate?: bool,
     *     content_type?: string|false,
     *     env?: string,
     * } $options see description
     */
    static function init(string $root, array $options = []): void
    {
        if (self::$ready) {
            throw new \LogicException('Already initialized');
        }

        self::$start = microtime(true);

        // change working directory in CLI
        if (Environment::isCli()) {
            chdir(dirname($_SERVER['SCRIPT_FILENAME']));
        }

        // functions
        require __DIR__ . '/../functions.php';

        // base components
        self::initBaseComponents();

        // configuration
        self::initConfiguration($root, $options);

        // environment
        self::initEnvironment($options);

        // set URLs
        self::$baseUrl = self::determineBaseUrl();
        self::$currentUrl = RequestInfo::getUrl();

        // components
        self::initComponents($options['cache']);

        // stop when minimal mode is enabled
        if ($options['minimal_mode']) {
            return;
        }

        // first init phase
        self::initDatabase($options);
        self::initPlugins();
        self::initSettings();
        Logger::init();

        // check system phase
        self::checkSystemState($options);

        // second init phase
        self::initSession();
        self::initLocalization();

        // finalize
        self::$ready = true;
        Extend::call('core.ready');

        // run cron tasks on shutdown
        if (self::$env !== self::ENV_SCRIPT && Settings::get('cron_auto')) {
            register_shutdown_function(function () {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                chdir(dirname($_SERVER['SCRIPT_FILENAME'])); // fix working directory

                Cron::run();
            });
        }
    }

    /**
     * Initialize configuration
     */
    private static function initConfiguration(string $root, array &$options): void
    {
        // defaults
        $options += [
            'config_file' => null,
            'minimal_mode' => false,
            'session_enabled' => true,
            'session_regenerate' => false,
            'content_type' => null,
            'env' => self::ENV_SCRIPT,
        ];

        // load config file
        if ($options['config_file'] !== false) {
            if ($options['config_file'] === null) {
                $options['config_file'] = $root . 'config.php';
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
            'db.server' => null,
            'db.port' => null,
            'db.user' => null,
            'db.password' => null,
            'db.name' => null,
            'db.prefix' => null,
            'secret' => null,
            'fallback_lang' => 'en',
            'debug' => false,
            'cache' => true,
            'timezone' => 'Europe/Prague',
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
        self::$sessionEnabled = $options['session_enabled'];
        self::$sessionRegenerate = $options['session_regenerate'] || isset($_POST['_session_force_regenerate']);

        // define constants
        define('SL_ROOT', $root);
    }

    private static function determineBaseUrl(): Url
    {
        $baseDir = RequestInfo::getBaseDir();

        if (SL_ROOT !== './') {
            // drop subdirs beyond root
            $baseDir = implode('/', array_slice(explode('/', $baseDir), 0, -substr_count(SL_ROOT, '../')));
        }

        $url = RequestInfo::getUrl();
        $url->setPath($baseDir);
        $url->setQuery([]);
        $url->setFragment(null);

        return $url;
    }

    /**
     * Init base components that don't depend on configuration
     */
    private static function initBaseComponents(): void
    {
        // error handler
        self::$errorHandler = new ErrorHandler();
        self::$errorHandler->register();

        // event emitter
        self::$eventEmitter = new EventEmitter();
    }

    /**
     * Initialize components
     */
    private static function initComponents(bool $enableCache): void
    {
        // class loader
        self::$classLoader->setDebug(self::$debug);

        // error handler
        self::$errorHandler->setDebug(self::$debug || Environment::isCli());

        // cache
        if (self::$cache === null) {
            self::$cache = new Cache(
                $enableCache
                    ? new FilesystemDriver(
                        SL_ROOT . 'system/cache/core',
                        new EntryFactory(null, null, SL_ROOT . 'system/tmp')
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
                $options['db.prefix']
            );
        } catch (DatabaseException $e) {
            self::fail(
                'Připojení k databázi se nezdařilo. Důvodem je pravděpodobně výpadek serveru nebo chybné přístupové údaje.',
                'Could not connect to the database. This may have been caused by the database server being temporarily unavailable or an error in the configuration.',
                null,
                $e->getMessage()
            );
        }
    }

    /**
     * Initialize settings
     */
    private static function initSettings(): void
    {
        try {
            Settings::init();
        } catch (DatabaseException $e) {
            self::fail(
                'Připojení k databázi proběhlo úspěšně, ale dotaz na databázi selhal. Zkontrolujte, zda je databáze správně nainstalovaná.',
                'Successfully connected to the database, but the database query has failed. Make sure the database is installed correctly.',
                null,
                $e->getMessage()
            );
        }
    }

    /**
     * Check system state after initialization
     */
    private static function checkSystemState(array $options): void
    {
        // check database version
        if (Settings::get('dbversion') !== self::VERSION) {
            self::fail(
                'Verze nainstalované databáze není kompatibilní s verzí systému.',
                'Database version is not compatible with the current system version.'
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

                Settings::update('install_check', $installCheckKey);
            }
        }
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

    /**
     * Initialize plugins
     */
    private static function initPlugins(): void
    {
        self::$pluginManager->initialize();
        Extend::call('plugins.ready');
    }

    /**
     * Initialize session
     */
    private static function initSession(): void
    {
        // start session
        if (self::$sessionEnabled) {
            // cookie parameters
            $cookieParams = [
                'lifetime' => 0,
                'path' => self::$baseUrl->getPath() . '/',
                'domain' => '',
                'secure' => self::isHttpsEnabled(),
                'httponly' => true,
                'samesite' => 'Lax',
            ];

            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params($cookieParams);
            } else {
                session_set_cookie_params(
                    $cookieParams['lifetime'],
                    $cookieParams['path'],
                    $cookieParams['domain'],
                    $cookieParams['secure'],
                    $cookieParams['httponly']
                );
            }

            // set session name and start it
            session_name(User::COOKIE_SESSION);
            session_start();

            if (self::$sessionRegenerate) {
                self::$sessionPreviousId = session_id();
                session_regenerate_id(true);
            }

            // init user
            User::init();
        } else {
            // no session
            $_SESSION = [];
        }
    }

    /**
     * Initialize localization
     */
    private static function initLocalization(): void
    {
        // language choice
        if (User::isLoggedIn() && Settings::get('language_allowcustom') && User::$data['language'] !== '') {
            $lang = User::$data['language'];
            $usedLoginLanguage = true;
        } else {
            $lang = Settings::get('language');
            $usedLoginLanguage = false;
        }

        // load language plugin
        $languagePlugin = self::$pluginManager->getPlugins()->getLanguage($lang);

        if ($languagePlugin !== null) {
            // load localization entries from the plugin
            $entries = $languagePlugin->getLocalizationEntries(self::$env === Core::ENV_ADMIN);

            if ($entries !== null) {
                self::$dictionary->add($entries);
            }
        } else {
            // language plugin was not found
            if ($usedLoginLanguage) {
                DB::update('user', 'id=' . User::getId(), ['language' => '']);
            } else {
                Settings::update('language', self::$fallbackLang);
            }

            if ($lang !== self::$fallbackLang) {
                self::fail(
                    'Jazykový balíček "%s" nebyl nalezen.',
                    'Language plugin "%s" was not found.',
                    [$lang]
                );
            }
        }

        self::$lang = $lang;
        self::$langPlugin = $languagePlugin;
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
