<?php

namespace Sunlight;

use Kuria\Cache\Cache;
use Kuria\Cache\Driver\Filesystem\Entry\EntryFactory;
use Kuria\Cache\Driver\Filesystem\FilesystemDriver;
use Kuria\Cache\Driver\Memory\MemoryDriver;
use Kuria\ClassLoader\ClassLoader;
use Kuria\Debug\Exception;
use Kuria\Error\ErrorHandler;
use Kuria\Error\Screen\WebErrorScreen;
use Kuria\Error\Screen\WebErrorScreenEvents;
use Kuria\Event\EventEmitter;
use Kuria\RequestInfo\RequestInfo;
use Kuria\RequestInfo\TrustedProxies;
use Kuria\Url\Url;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseException;
use Sunlight\Exception\CoreException;
use Sunlight\Image\ImageService;
use Sunlight\Localization\LocalizationDictionary;
use Sunlight\Plugin\PluginManager;
use Sunlight\Util\DateTime;
use Sunlight\Util\Environment;
use Sunlight\Util\Filesystem;

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
    static $appId;
    /** @var string */
    static $secret;
    /** @var string */
    static $lang;
    /** @var string */
    static $fallbackLang;
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

    /** @var string */
    static $imageError;
    /** @var int */
    static $hcmUid = 0;
    /** @var array */
    static $settings = [];
    /** @var array id => seconds */
    static $cronIntervals = [];

    /** @var Url */
    private static $baseUrl;
    /** @var Url */
    private static $currentUrl;
    /** @var bool */
    private static $ready = false;

    /**
     * Initialize the system
     *
     * Supported $options keys:
     * ------------------------
     * config_file          path to the configuration file, null (= default) or false (= skip)
     * minimal_mode         stop after initializing base components and environment (= no plugins, db, settings, session, etc.) 1/0
     * skip_components      do not initalize any components 1/0
     * session_enabled      initialize session 1/0
     * session_regenerate   force new session ID 1/0
     * allow_cron_auto      allow running cron tasks automatically 1/0
     * content_type         content type, FALSE = disabled (default is "text/html; charset=UTF-8")
     * env                  environment identifier, see Core::ENV_* constants
     *
     * @param string $root relative path to the system root directory (with a trailing slash)
     * @param array  $options
     */
    static function init(string $root, array $options = []): void
    {
        if (self::$ready) {
            throw new \LogicException('Already initialized');
        }

        self::$start = microtime(true);
        $initComponents = empty($options['skip_components']);

        // functions
        require __DIR__ . '/../functions.php';

        // base components
        if ($initComponents) {
            self::initBaseComponents();
        }

        // configuration
        self::initConfiguration($root, $options);

        // components
        if ($initComponents) {
            self::initComponents($options['cache']);
        }

        // environment
        self::initEnvironment($options);

        // set URLs
        self::$baseUrl = self::determineBaseUrl();
        self::$currentUrl = RequestInfo::getUrl();

        // stop when minimal mode is enabled
        if ($options['minimal_mode']) {
            self::$ready = true;

            return;
        }

        // first init phase
        self::initDatabase($options);
        self::initPlugins();
        self::initSettings();

        // check system phase
        self::checkSystemState();

        // second init phase
        self::initSession();
        self::initLocalization();

        // finalize
        self::$ready = true;
        Extend::call('core.ready');

        // cron tasks
        Extend::reg('cron.maintenance', [__CLASS__, 'doMaintenance']);
        if (Settings::get('cron_auto') && $options['allow_cron_auto']) {
            self::runCronTasks();
        }
    }

    /**
     * Initialize configuration
     *
     * @param string $root
     * @param array  &$options
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
            'allow_cron_auto' => isset($options['env']) && $options['env'] !== self::ENV_SCRIPT,
            'env' => self::ENV_SCRIPT,
        ];

        // load config file
        $configFile = $options['config_file'] ?? $root . 'config.php';

        if ($configFile !== false) {
            $configFileOptions = @include $configFile;

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
            'app_id' => null,
            'fallback_lang' => 'en',
            'debug' => false,
            'cache' => true,
            'locale' => null,
            'timezone' => 'Europe/Prague',
        ];

        // check required options
        if (!$options['minimal_mode']) {
            $requiredOptions = [
                'db.server',
                'db.name',
                'db.prefix',
                'secret',
                'app_id',
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
        self::$appId = $options['app_id'];
        self::$secret = $options['secret'];
        self::$fallbackLang = $options['fallback_lang'];
        self::$sessionEnabled = $options['session_enabled'];
        self::$sessionRegenerate = $options['session_regenerate'] || isset($_POST['_session_force_regenerate']);
        self::$imageError = $root . 'system/image_error.png';

        // define constants
        define('SL_ROOT', $root);
    }

    /**
     * @return Url
     */
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
        // class loader
        if (self::$classLoader === null) {
            self::$classLoader = new ClassLoader();
        }

        // error handler
        self::$errorHandler = new ErrorHandler();
        self::$errorHandler->register();

        if (($exceptionHandler = self::$errorHandler->getErrorScreen()) instanceof WebErrorScreen) {
            self::configureWebExceptionHandler($exceptionHandler);
        }

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
        self::$errorHandler->setDebug(self::$debug || PHP_SAPI === 'cli');

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
        self::$pluginManager = new PluginManager(
            self::$cache->getNamespace('plugins.')
        );

        // localization
        self::$dictionary = new LocalizationDictionary();
    }

    /**
     * Initialize database
     *
     * @param array $options
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

        // define maintenance cron interval
        self::$cronIntervals['maintenance'] = Settings::get('maintenance_interval');
    }

    /**
     * Check system state after initialization
     */
    private static function checkSystemState(): void
    {
        // check database version
        if (Settings::get('dbversion') !== self::VERSION) {
            self::fail(
                'Verze nainstalované databáze není kompatibilní s verzí systému.',
                'Database version is not compatible with the current system version.'
            );
        }

        // installation check
        if (Settings::get('install_check')) {
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

            Settings::update('install_check', '0');
        }
    }

    /**
     * Initialize environment
     *
     * @param array $options
     */
    private static function initEnvironment(array $options): void
    {
        // ensure correct encoding for mb_*() functions
        mb_internal_encoding('UTF-8');

        // make sure $_SERVER['REQUEST_URI'] is defined
        if (!isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
            $_SERVER['REQUEST_URI'] = $requestUri;
        }

        // set error_reporting
        $err_rep = E_ALL;
        if (!self::$debug) {
            $err_rep &= ~(E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_STRICT);
        }
        error_reporting($err_rep);

        // set locale
        if (!empty($options['locale'])) {
            @setlocale(LC_TIME, $options['locale']);
        }
        if (!empty($options['timezone'])) {
            date_default_timezone_set($options['timezone']);
        }

        // send default headers
        if (!Environment::isCli()) {
            if ($options['content_type'] === null) {
                // vychozi hlavicky
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
        foreach (self::$pluginManager->getAllExtends() as $extendPlugin) {
            $extendPlugin->initialize();
        }

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
            $cookieParams = session_get_cookie_params();
            $cookieParams['httponly'] = 1;
            $cookieParams['secure'] = self::$currentUrl->getScheme() === 'https' ? 1 : 0;
            session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);

            // set session name and start it
            session_name(self::$appId . '_session');
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
        if (self::$pluginManager->has(PluginManager::LANGUAGE, $lang)) {
            $languagePlugin = self::$pluginManager->getLanguage($lang);
        } else {
            $languagePlugin = null;
        }

        if ($languagePlugin !== null) {
            // load localization entries from the plugin
            $entries = $languagePlugin->getLocalizationEntries();

            if ($entries !== false) {
                self::$dictionary->add($entries);
            }
        } else {
            // language plugin was not found
            if ($usedLoginLanguage) {
                DB::update('user', 'id=' . User::getId(), ['language' => '']);
            } else {
                Settings::update('language', self::$fallbackLang);
            }

            self::fail(
                'Jazykový balíček "%s" nebyl nalezen.',
                'Language plugin "%s" was not found.',
                [$lang]
            );
        }

        self::$lang = $lang;
    }

    /**
     * Run CRON tasks
     */
    static function runCronTasks(): void
    {
        $cronNow = time();
        $cronUpdate = false;
        $cronLockFileHandle = null;
        $cronTimes = Settings::get('cron_times');

        if ($cronTimes !== '') {
            $cronTimes = unserialize($cronTimes);
        } else {
            $cronTimes = [];
            $cronUpdate = true;
        }

        foreach (self::$cronIntervals as $cronIntervalName => $cronIntervalSeconds) {
            if (isset($cronTimes[$cronIntervalName])) {
                // last run time is known
                if ($cronNow - $cronTimes[$cronIntervalName] >= $cronIntervalSeconds) {
                    // check lock file
                    if ($cronLockFileHandle === null) {
                        $cronLockFile = SL_ROOT . 'system/cron.lock';
                        $cronLockFileHandle = fopen($cronLockFile, 'r');
                        if (!flock($cronLockFileHandle, LOCK_EX | LOCK_NB)) {
                            // lock soubor je nepristupny
                            fclose($cronLockFileHandle);
                            $cronLockFileHandle = null;
                            $cronUpdate = false;
                            break;
                        }
                    }

                    // event
                    $cronEventArgs = [
                        'last' => $cronTimes[$cronIntervalName],
                        'name' => $cronIntervalName,
                        'seconds' => $cronIntervalSeconds,
                        'delay' => $cronNow - $cronTimes[$cronIntervalName],
                    ];
                    Extend::call('cron', $cronEventArgs);
                    Extend::call('cron.' . $cronIntervalName, $cronEventArgs);

                    // update last run time
                    $cronTimes[$cronIntervalName] = $cronNow;
                    $cronUpdate = true;
                }
            } else {
                // unknown last run time
                $cronTimes[$cronIntervalName] = $cronNow;
                $cronUpdate = true;
            }
        }

        // update run times
        if ($cronUpdate) {
            // remove unknown intervals
            foreach (array_keys($cronTimes) as $cronTimeKey) {
                if (!isset(self::$cronIntervals[$cronTimeKey])) {
                    unset($cronTimes[$cronTimeKey]);
                }
            }

            // save
            Settings::update('cron_times', serialize($cronTimes));
        }

        // free lock file
        if ($cronLockFileHandle !== null) {
            flock($cronLockFileHandle, LOCK_UN);
            fclose($cronLockFileHandle);
        }
    }

    static function isReady(): bool
    {
        return self::$ready;
    }

    /**
     * Get base URL
     *
     * The returned instance is a clone which may be modified.
     *
     * @return Url
     */
    static function getBaseUrl(): Url
    {
        return clone self::$baseUrl;
    }

    /**
     * Get current request URL
     *
     * The returned instance is a clone which may be modified.
     *
     * @return Url
     */
    static function getCurrentUrl(): Url
    {
        return clone self::$currentUrl;
    }

    /**
     * Get current client's IP address
     */
    static function getClientIp(): string
    {
        return RequestInfo::getClientIp() ?? '127.0.0.1';
    }

    /**
     * Run system maintenance
     */
    static function doMaintenance(): void
    {
        // clean thumbnails
        ImageService::cleanThumbnails(Settings::get('thumb_cleanup_threshold'));

        // remove old files in the temporary directory
        Filesystem::purgeDirectory(SL_ROOT . 'system/tmp', [
            'keep_dir' => true,
            'files_only' => true,
            'file_callback' => function (\SplFileInfo $file) {
                // posledni zmena souboru byla pred vice nez 24h
                // a nejedna se o skryty soubor
                return
                    substr($file->getFilename(), 0, 1) !== '.'
                    && time() - $file->getMTime() > 86400;
            },
        ]);

        // check version
        VersionChecker::check();

        // cleanup the cache
        if (self::$cache->supportsCleanup()) {
            self::$cache->cleanup();
        }
    }

    /**
     * Get global JavaScript definitions
     *
     * @param array $customVariables asociativni pole s vlastnimi promennymi
     * @param bool  $scriptTags      obalit do <script> tagu 1/0
     * @return string
     */
    static function getJavascript(array $customVariables = [], bool $scriptTags = true): string
    {
        $output = '';

        // opening script tag
        if ($scriptTags) {
            $output .= "<script>";
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
        $output .= 'var SunlightVars = ' . json_encode($variables) . ';';

        // closing script tags
        if ($scriptTags) {
            $output .= '</script>';
        }

        return $output;
    }

    /**
     * Throw a localized core exception
     *
     * @param string      $msgCs    zprava cesky
     * @param string      $msgEn    zprava anglicky
     * @param array|null  $msgArgs  argumenty sprintf() formatovani
     * @param string|null $msgExtra extra obsah pod zpravou (nelokalizovany)
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

    /**
     * Render an exception
     *
     * @param \Throwable $e
     * @param bool $showTrace
     * @param bool $showPrevious
     * @return string
     */
    static function renderException(\Throwable $e, bool $showTrace = true, bool $showPrevious = true): string
    {
        return '<pre class="exception">' . _e(Exception::render($e, $showTrace, $showPrevious)) . "</pre>\n";
    }

    private static function configureWebExceptionHandler(WebErrorScreen $errorScreen): void
    {
        $errorScreen->on(WebErrorScreenEvents::CSS, function () {
            echo <<<'CSS'
body {background-color: #ededed; color: #000000;}
a {color: #ff6600;}
.core-exception-info {opacity: 0.8;}
.website-link {display: block; margin: 1em 0; text-align: center; color: #000000; opacity: 0.5;}
CSS;
        });


        if (!self::$errorHandler->isDebugEnabled()) {
            $errorScreen->on(WebErrorScreenEvents::RENDER, function ($view) {
                $view['title'] = $view['heading'] = self::$fallbackLang === 'cs'
                    ? 'Chyba serveru'
                    : 'Something went wrong';

                $view['text'] = self::$fallbackLang === 'cs'
                    ? 'Omlouváme se, ale při zpracovávání Vašeho požadavku došlo k neočekávané chybě.'
                    : 'We are sorry, but an unexpected error has occurred while processing your request.';

                if ($view['exception'] instanceof CoreException) {
                    $view['extras'] .= '<div class="group core-exception-info"><div class="section">';
                    $view['extras'] .=  '<p class="message">' . nl2br(_e($view['exception']->getMessage()), false) . '</p>';
                    $view['extras'] .= '</div></div>';
                    $view['extras'] .= '<a class="website-link" href="https://sunlight-cms.cz/" target="_blank">SunLight CMS ' . self::VERSION . '</a>';
                }
            });
        }
    }
}
