<?php

namespace Sunlight;

use Kuria\Cache\Cache;
use Kuria\Cache\Extension\BoundFile\BoundFileExtension;
use Kuria\Cache\Driver\FilesystemDriver;
use Kuria\Cache\Driver\MemoryDriver;
use Kuria\ClassLoader\ClassLoader;
use Kuria\Debug\Error;
use Kuria\Error\Util\Debug;
use Kuria\Error\ErrorHandler;
use Kuria\Error\Screen\WebErrorScreen;
use Sunlight\Database\Database as DB;
use Sunlight\Plugin\PluginManager;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Url;

/**
 * Hlavni systemovy singleton
 *
 * Spravuje prostredi systemu.
 */
class Core
{
    /** Verze systemu */
    const VERSION = '8.0.0';
    /** Stav systemu */
    const STATE = 'BETA';

    /** @var float */
    public static $start;
    /** @var string */
    public static $appId;
    /** @var string */
    public static $secret;
    /** @var string */
    public static $url;
    /** @var string */
    public static $fallbackLang;
    /** @var bool */
    public static $sessionEnabled;
    /** @var bool */
    public static $sessionRegenerate;
    /** @var string|null */
    public static $sessionPreviousId;

    /** @var ClassLoader */
    public static $classLoader;
    /** @var ErrorHandler */
    public static $errorHandler;
    /** @var PluginManager */
    public static $pluginManager;
    /** @var Cache */
    public static $cache;

    /** @var array|null */
    public static $userData;
    /** @var array|null */
    public static $groupData;

    /** @var array */
    public static $dangerousServerSideExt = array('php', 'php3', 'php4', 'php5', 'phtml', 'shtml', 'asp', 'py', 'cgi', 'htaccess');
    /** @var array */
    public static $imageExt = array('png', 'jpeg', 'jpg', 'gif');
    /** @var string */
    public static $imageError;
    /** @var int */
    public static $hcmUid = 0;
    /** @var array */
    public static $settings = array();
    /** @var array id => seconds */
    public static $cronIntervals = array();

    /**
     * Staticka trida
     */
    private function __construct()
    {
    }

    /**
     * Inicializovat system
     *
     * Mozne klice v $options:
     * -----------------------
     * config_file          cesta ke konfiguracnimu skriptu nebo null(= vychozi)
     * session_enabled      inicializovat session 1/0
     * session_regenerate   vynutit zmenu session ID 1/0
     * run_cron             automaticky spustit cron, je-li aktivovan 1/0
     * content_type         typ obsahu (pro header), false pro deaktivaci, vychozi je "text/html; charset=UTF-8"
     * env                  prostredi ("web" nebo "admin")
     *
     * @param string $root    relativni cesta do korenoveho adresare
     * @param array  $options pole s moznostmi
     */
    public static function init($root, array $options = array())
    {
        static::$start = microtime(true);

        // hlavni inicializace
        static::initConfiguration($root, $options);
        static::initComponents($options);
        static::initEnvironment($options);
        static::initDatabase($options);
        static::initPlugins();
        static::initSettings();

        // kontrola
        static::initCheck();

        // druha cast inicializace
        static::initSession();
        static::initLanguageFile();

        // extend udalost po inicializaci
        Extend::call('core.ready');

        // cron
        Extend::reg('cron.maintenance', array(__CLASS__, 'doMaintenance'));
        if (_cron_auto && $options['run_cron']) {
            static::runCron();
        }
    }

    /**
     * Inicializovat konfiguraci
     *
     * @param string $root
     * @param array  &$options
     */
    private static function initConfiguration($root, array &$options)
    {
        // nacteni konfiguracniho souboru
        if (!isset($options['config_file'])) {
            $configFile = $root . 'config.php';
        } else {
            $configFile = $options['config_file'];
        }

        $options += require $configFile;

        // vychozi nastaveni
        $options += array(
            'db.server' => null,
            'db.port' => null,
            'db.user' => null,
            'db.password' => null,
            'db.name' => null,
            'db.prefix' => null,
            'url' => null,
            'secret' => null,
            'app_id' => null,
            'fallback_lang' => 'en',
            'dev' => false,
            'cache' => true,
            'locale' => null,
            'timezone' => 'Europe/Prague',
            'geo.latitude' => 50.5,
            'geo.longitude' => 14.26,
            'geo.zenith' => 90.583333,
            'config_file' => null,
            'light_mode' => false,
            'session_enabled' => true,
            'session_regenerate' => false,
            'run_cron' => true,
            'content_type' => null,
            'env' => 'web',
        );

        $requiredOptions = array(
            'db.server',
            'db.name',
            'db.prefix',
            'url',
            'secret',
            'app_id',
        );

        foreach ($requiredOptions as $requiredOption) {
            if (empty($options[$requiredOption])) {
                static::systemFailure(
                    "Konfigurační volba \"{$requiredOption}\" nesmí být prázdná.",
                    "The configuration option \"{$requiredOption}\" must not be empty."
                );
            }
        }

        if ('admin' !== $options['env'] && 'web' !== $options['env']) {
            static::systemFailure(
                'Konfigurační volba "env" musí být "admin" nebo "web".',
                'The configuration option "env" must be either "admin" or "web".'
            );
        }

        $url = Url::current();

        // definice promennych
        static::$appId = $options['app_id'];
        static::$secret = $options['secret'];
        static::$url = $options['url'];
        static::$fallbackLang = $options['fallback_lang'];
        static::$sessionEnabled = $options['session_enabled'];
        static::$sessionRegenerate = $options['session_regenerate'] || isset($_POST['_session_force_regenerate']);
        static::$imageError = $root . 'images/image_error.png';

        // systemove konstanty
        define('_root', $root);
        define('_env', $options['env']);
        define('_env_web', 'web' === $options['env']);
        define('_env_admin', 'admin' === $options['env']);
        define('_dev', (bool) $options['dev']);
        define('_dbprefix', $options['db.prefix'] . '_');
        define('_dbname', $options['db.name']);
        define('_upload_dir', _root . 'upload/');
        define('_geo_latitude', $options['geo.latitude']);
        define('_geo_longitude', $options['geo.longitude']);
        define('_geo_zenith', $options['geo.zenith']);
    }

    /**
     * Inicializovat komponenty
     *
     * @param array $options
     */
    private static function initComponents(array $options)
    {
        // globalni promenne
        $GLOBALS['_lang'] = array();

        // alias db tridy
        class_alias('Sunlight\Database\Database', 'DB'); // zpetna kompatibilita

        // error handler
        static::$errorHandler = new ErrorHandler();
        static::$errorHandler->register();
        static::$errorHandler->setDebug(_dev);

        if (($exceptionHandler = static::$errorHandler->getExceptionHandler()) instanceof WebErrorScreen) {
            static::configureWebExceptionHandler($exceptionHandler);
        }

        // cache
        if (null === static::$cache) {
            static::$cache = new Cache(
                $options['cache']
                    ? new FilesystemDriver(
                        _root . 'system/cache/' . (_dev ? 'dev' : 'prod'),
                        _root . 'system/tmp'
                    )
                    : new MemoryDriver()

            );

            $boundFileExtension = new BoundFileExtension();
            static::$cache->subscribe($boundFileExtension);
        }

        // plugin manager
        static::$pluginManager = new PluginManager(
            static::$cache->getNamespace('plugins.')
        );

        // konstanty
        require _root . 'system/constants.php';

        // funkce
        require _root . 'system/functions.php';
    }

    /**
     * Inicializovat databazi
     *
     * @param array $options
     */
    private static function initDatabase(array $options)
    {
        $connectError = DB::connect($options['db.server'], $options['db.user'], $options['db.password'], $options['db.name'], $options['db.port']);

        if (null !== $connectError) {
            static::systemFailure(
                'Připojení k databázi se nezdařilo. Důvodem je pravděpodobně výpadek serveru nebo chybné přístupové údaje. Zkontrolujte přístupové údaje v souboru config.php.',
                'Could not connect to the database. This may have been caused by the database server being temporarily unavailable or an error in the configuration. Check your config.php file for errors.',
                null,
                $connectError
            );
        }
    }

    /**
     * Inicializovat nastaveni
     */
    private static function initSettings()
    {
        // nacist z databaze
        $query = DB::query('SELECT var,val,constant FROM ' . _settings_table . ' WHERE preload=1 AND ' . _env . '=1', true);

        if (DB::error()) {
            static::systemFailure(
                'Připojení k databázi proběhlo úspěšně, ale dotaz na databázi selhal. Zkontrolujte, zda je databáze správně nainstalovaná.',
                'Successfully connected to the database, but the database query has failed. Make sure the database is installed correctly.',
                null,
                DB::error()
            );
        }

        $settings = DB::rows($query, 'var');
        DB::free($query);

        // extend udalost
        Extend::call('core.settings', array('settings' => &$settings));

        // aplikovat nastaveni
        foreach ($settings as $setting) {
            if ($setting['constant']) {
                define("_{$setting['var']}", $setting['val']);
            } else {
                static::$settings[$setting['var']] = $setting['val'];
            }
        }
        
        // nastavit interval pro maintenance
        static::$cronIntervals['maintenance'] = _maintenance_interval;

        // ip adresa klienta
        if (empty($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
        if (_proxy_mode && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        define('_userip', trim((($addr_comma = strrpos($ip, ',')) === false) ? $ip : substr($ip, $addr_comma + 1)));
    }

    /**
     * Zkontrolovat stav systemu po hlavni inicializaci
     */
    private static function initCheck()
    {
        // kontrola verze databaze
        if (!defined('_dbversion') || Core::VERSION !== _dbversion) {
            static::systemFailure(
                'Verze nainstalované databáze není kompatibilní s verzí systému.',
                'Database version is not compatible with the current system version.'
            );
        }

        // poinstalacni kontrola
        if (_install_check) {
            $systemChecker = new SystemChecker();
            $systemChecker->check();

            if ($systemChecker->hasErrors()) {
                static::systemFailure(
                    'Při kontrole instalace byly detekovány následující problémy:',
                    'The installation check has detected the following problems:',
                    null,
                    $systemChecker->renderErrors()
                );
            }

            static::updateSetting('install_check', 0);
        }

        // kontrola aktualni adresy
        $currentUrl = Url::current();
        $baseUrl = Url::base();

        if ($currentUrl->host !== $baseUrl->host) {
            // neplatna domena
            $currentUrl->host = $baseUrl->host;
            _redirectHeader($currentUrl->generateAbsolute());
            exit;
        }

        if ($currentUrl->scheme !== $baseUrl->scheme) {
            // neplatny protokol
            $currentUrl->scheme = $baseUrl->scheme;
            _redirectHeader($currentUrl->generateAbsolute());
        }
    }

    /**
     * Inicializovat PHP prostredi
     *
     * @param array $options
     */
    private static function initEnvironment(array $options)
    {
        // mbstring kodovani
        mb_internal_encoding('UTF-8');

        // kontrola a nastaveni $_SERVER['REQUEST_URI']
        if (!isset($_SERVER['REQUEST_URI'])) {
            if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
                $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL']; // ISAPI_Rewrite 3.x
            } elseif (isset($_SERVER['HTTP_REQUEST_URI'])) {
                $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REQUEST_URI']; // ISAPI_Rewrite 2.x
            } else {
                if (isset($_SERVER['SCRIPT_NAME'])) {
                    $_SERVER['HTTP_REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
                } else {
                    $_SERVER['HTTP_REQUEST_URI'] = $_SERVER['PHP_SELF'];
                }
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $_SERVER['HTTP_REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
                }
                $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REQUEST_URI'];
            }
        }

        // vyruseni register_globals
        if (ini_get('register_globals')) {
            foreach (array_keys($_REQUEST) as $key) {
                unset($GLOBALS[$key]);
            }
        }

        // vypnuti magic_quotes
        if (get_magic_quotes_gpc()) {
            $search = array(&$_GET, &$_POST, &$_COOKIE);
            for ($i = 0; isset($search[$i]); ++$i) {
                foreach ($search[$i] as &$value) {
                    if (is_array($value)) {
                        $search[] = &$value;
                    } else {
                        $value = stripslashes($value);
                    }
                }
                unset($search[$i]);
            }

            if (function_exists('set_magic_quotes_runtime')) {
                @set_magic_quotes_runtime(0);
            }

            unset($search, $i, $value);
        }

        // hlaseni chyb
        $err_rep = E_ALL | E_STRICT;
        if (!_dev) {
            $err_rep &= ~(E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_STRICT);
        }
        error_reporting($err_rep);

        // casove pasmo
        if (!empty($options['locale'])) {
            @setlocale(LC_TIME, $options['locale']);
        }
        if (!empty($options['timezone'])) {
            date_default_timezone_set($options['timezone']);
        }

        // hlavicky
        if (null === $options['content_type']) {
            // vychozi hlavicky
            header('Content-Type: text/html; charset=UTF-8');
            header('Expires: ' . _httpDate(-604800, true));
        } elseif (false !== $options['content_type']) {
            header('Content-Type: ' . $options['content_type']);
        }
    }

    /**
     * Inicializovat pluginy
     */
    private static function initPlugins()
    {
        // extend pluginy
        foreach (static::$pluginManager->getAllExtends() as $extendPlugin) {
            $extendPlugin->initialize();
        }

        Extend::call('plugins.ready');
    }

    /**
     * Inicializovat session
     */
    private static function initSession()
    {
        // spusteni session
        if (static::$sessionEnabled) {
            // nastaveni cookie
            $cookieParams = session_get_cookie_params();
            $cookieParams['httponly'] = 1;
            $cookieParams['secure'] = 'https' === Url::current()->scheme ? 1 : 0;
            session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);

            // nastaveni session a start
            session_name(static::$appId . '_session');
            session_start();

            if (static::$sessionRegenerate) {
                static::$sessionPreviousId = session_id();
                session_regenerate_id(true);
            }
        } else {
            // session je neaktivni
            $_SESSION = array();
        }

        // proces autorizace
        $authorized = false;
        $isPersistentSession = false;
        $errorCode = null;

        if (static::$sessionEnabled) do {
            $userData = null;
            $sessionDataExists = isset(
                $_SESSION['user_id'],
                $_SESSION['user_auth'],
                $_SESSION['user_ip']
            );

            // kontrola cookie trvaleho prihlaseni, neexistujou-li data session
            if (!$sessionDataExists) {
                // kontrola existence cookie
                $persistentCookieName = static::$appId . '_persistent_key';
                if (isset($_COOKIE[$persistentCookieName]) && is_string($_COOKIE[$persistentCookieName])) {

                    // proces cookie autorizace
                    do {
                        // zpracovani cookie
                        $cookie = explode('$', $_COOKIE[$persistentCookieName], 2);
                        if (2 !== sizeof($cookie)) {
                            // neplatny format cookie
                            $errorCode = 1;
                            break;
                        }
                        $cookie = array(
                            'id' => (int) $cookie[0],
                            'hash' => $cookie[1],
                        );

                        // nacist data uzivatele
                        $userData = DB::queryRow('SELECT * FROM ' . _users_table . ' WHERE id=' . DB::val($cookie['id']));
                        if (false === $userData) {
                            // uzivatel nenalezen
                            $errorCode = 2;
                            break;
                        }

                        // kontrola hashe
                        if (!_iplogCheck(_iplog_failed_login_attempt)) {
                            // prekrocen pocet pokusu o prihlaseni
                            $errorCode = 3;
                            break;
                        }

                        $validHash = _userPersistentLoginHash($cookie['id'], _userAuthHash($userData['password']), $userData['email']);
                        if ($validHash !== $cookie['hash']) {
                            // hash neni validni
                            _iplogUpdate(_iplog_failed_login_attempt);
                            $errorCode = 4;
                            break;
                        }

                        // vse je ok! pouzit data z cookie pro prihlaseni
                        _userLogin($cookie['id'], $userData['password'], $userData['email']);
                        $sessionDataExists = true;
                        $isPersistentSession = true;
                    } while (false);

                    // kontrola vysledku
                    if (null !== $errorCode) {
                        // autorizace se nepodarila, smazat neplatnou cookie
                        setcookie(static::$appId . '_persistent_key', '', (time() - 3600), '/');
                        break;
                    }
                }
            }

            // kontrola existence dat prihlaseni
            if (!$sessionDataExists) {
                // data prihlaseni neexistuji - uzivatel neni prihlasen
                $errorCode = 5;
                break;
            }

            // nacist data uzivatele
            if (!$userData) {
                $userData = DB::queryRow('SELECT * FROM ' . _users_table . ' WHERE id=' . DB::val($_SESSION['user_id']));
                if (false === $userData) {
                    // uzivatel nenalezen
                    $errorCode = 6;
                    break;
                }
            }

            // kontrola dat prihlaseni
            if ($_SESSION['user_auth'] !== _userAuthHash($userData['password'])) {
                // neplatny hash
                $errorCode = 7;
                break;
            }

            // kontrola blokace uzivatele
            if ($userData['blocked']) {
                // uzivatel je zablokovan
                $errorCode = 8;
                break;
            }

            // nacist data skupiny
            $groupData = DB::queryRow('SELECT * FROM ' . _groups_table . ' WHERE id=' . DB::val($userData['group_id']));
            if (false === $groupData) {
                // skupina nenalezena
                $errorCode = 9;
                break;
            }

            // kontrola blokace skupiny
            if ($groupData['blocked']) {
                // skupina je zablokovana
                $errorCode = 10;
                break;
            }

            // vse ok! uzivatele je mozne prihlasit
            $authorized = true;
        } while (false);

        // prihlaseni
        if ($authorized) {
            // zvyseni urovne pro superuzivatele
            if ($userData['levelshift']) {
                $groupData['level'] += 1;
            }

            // zaznamenani casu aktivity (max 1x za 30 sekund)
            if (time() - $userData['activitytime'] > 30) {
                DB::update(_users_table, 'id=' . DB::val($userData['id']), array(
                    'activitytime' => time(),
                    'ip' => _userip,
                ));
            }

            // event
            Extend::call('user.auth.success', array(
                'user' => &$userData,
                'group' => &$groupData,
                'persistent_session' => $isPersistentSession,
            ));

            // nastaveni promennych
            static::$userData = $userData;
            static::$groupData = $groupData;
        } else {
            // anonymni uzivatel
            $userData = array(
                'id' => -1,
                'username' => '',
                'publicname' => null,
                'email' => '',
                'levelshift' => false,
            );

            // nacteni dat skupiny pro neprihlasene uziv.
            $groupData = DB::queryRow('SELECT * FROM ' . _groups_table . ' WHERE id=2');
            if (false === $groupData) {
                throw new \RuntimeException('Anonymous user group was not found (id=2)');
            }

            // event
            Extend::call('user.auth.failure', array(
                'error_code' => $errorCode,
                'user' => &$userData,
                'group' => &$groupData,
            ));
        }

        // konstanty
        define('_login', $authorized);
        define('_loginid', $userData['id']);
        define('_loginname', $userData['username']);
        define('_loginpublicname', $userData[null !== $userData['publicname'] ? 'publicname' : 'username']);
        define('_loginemail', $userData['email']);
        define('_logingroup', $groupData['id']);

        foreach(_getPrivileges() as $item) {
            define('_priv_' . $item, $groupData[$item]);
        }

        define('_priv_super_admin', $userData['levelshift'] && _group_admin == $groupData['id']);
    }

    /**
     * Inicializovat jazykovy soubor
     */
    private static function initLanguageFile()
    {
        // volba jazyka
        if (_login && _language_allowcustom && '' !== static::$userData['language']) {
            $language = static::$userData['language'];
            $usedLoginLanguage = true;
        } else {
            $language = static::$settings['language'];
            $usedLoginLanguage = false;
        }

        // nacteni jazyka
        if (static::$pluginManager->has(PluginManager::LANGUAGE, $language)) {
            $languagePlugin = static::$pluginManager->getLanguage($language);
        } else {
            $languagePlugin = null;
        }

        if (null !== $languagePlugin) {
            $GLOBALS['_lang'] += $languagePlugin->load();
        } else {
            if ($usedLoginLanguage) {
                DB::update(_users_table, 'id=' . _loginid, array('language' => ''));
            } else {
                static::updateSetting('language', static::$fallbackLang);
            }

            static::systemFailure(
                'Jazykový soubor "%s" nebyl nalezen.',
                'Language file "%s" was not found.',
                array($language)
            );
        }

        define('_language', $language);
    }

    /**
     * Spustit CRON
     */
    public static function runCron()
    {
        $cronNow = time();
        $cronUpdate = false;
        $cronLockFileHandle = null;
        if (static::$settings['cron_times']) {
            $cronTimes = unserialize(static::$settings['cron_times']);
        } else {
            $cronTimes = false;
        }
        if (false === $cronTimes) {
            $cronTimes = array();
            $cronUpdate = true;
        }

        foreach (static::$cronIntervals as $cronIntervalName => $cronIntervalSeconds) {
            if (isset($cronTimes[$cronIntervalName])) {
                // posledni cas je zaznamenan
                if ($cronNow - $cronTimes[$cronIntervalName] >= $cronIntervalSeconds) {
                    // kontrola lock file
                    if (null === $cronLockFileHandle) {
                        $cronLockFile = _root . 'system/cron.lock';
                        $cronLockFileHandle = fopen($cronLockFile, 'r');
                        if (!flock($cronLockFileHandle, LOCK_EX | LOCK_NB)) {
                            // lock soubor je nepristupny
                            fclose($cronLockFileHandle);
                            $cronLockFileHandle = null;
                            $cronUpdate = false;
                            break;
                        }
                    }

                    // udalost
                    $cronEventArgs = array(
                        'last' => $cronTimes[$cronIntervalName],
                        'name' => $cronIntervalName,
                        'seconds' => $cronIntervalSeconds,
                        'delay' => $cronNow - $cronTimes[$cronIntervalName],
                    );
                    Extend::call('cron', $cronEventArgs);
                    Extend::call('cron.' . $cronIntervalName, $cronEventArgs);

                    // aktualizovat posledni cas
                    $cronTimes[$cronIntervalName] = $cronNow;
                    $cronUpdate = true;
                }
            } else {
                // posledni cas neni zaznamenan
                $cronTimes[$cronIntervalName] = $cronNow;
                $cronUpdate = true;
            }
        }

        // aktualizovat casy
        if ($cronUpdate) {
            // odstranit nezname intervaly
            foreach (array_keys($cronTimes) as $cronTimeKey) {
                if (!isset(static::$cronIntervals[$cronTimeKey])) {
                    unset($cronTimes[$cronTimeKey]);
                }
            }

            // ulozit
            static::updateSetting('cron_times', serialize($cronTimes));
        }

        // uvolnit lockfile
        if (null !== $cronLockFileHandle) {
            flock($cronLockFileHandle, LOCK_UN);
            fclose($cronLockFileHandle);
            $cronLockFileHandle = null;
        }
    }

    /**
     * Provest udrzbu systemu
     */
    public static function doMaintenance()
    {
        // cisteni miniatur
        _pictureThumbClean(_thumb_cleanup_threshold);

        // promazat stare soubory v docasnem adresari
        Filesystem::purgeDirectory(_root . 'system/tmp', array(
            'keep_dir' => true,
            'files_only' => true,
            'file_callback' => function (\SplFileInfo $file) {
                // posledni zmena souboru byla pred vice nez 24h
                // a nejedna se o skryty soubor
                return
                    '.' !== substr($file->getFilename(), 0, 1)
                    && time() - $file->getMTime() > 86400;
            },
        ));
    }

    /**
     * Nacist jednu konfiguracni direktivu
     *
     * @param string $name    nazev direktivy
     * @param mixed  $default vychozi hodnota
     * @return mixed
     */
    public static function loadSetting($name, $default = null)
    {
        $result = DB::queryRow('SELECT val FROM ' . _settings_table . ' WHERE var=' . DB::val($name));
        if (false !== $result) {
            return $result['val'];
        } else {
            return $default;
        }
    }

    /**
     * Nacist konfiguracni direktivy
     *
     * @param string|array $names nazvy direktiv
     * @return array
     */
    public static function loadSettings(array $names)
    {
        $names = (array) $names;

        $settings = array();
        $query = DB::query('SELECT var,val FROM ' . _settings_table . ' WHERE var IN(' . DB::arr($names) . ')');
        while ($row = DB::row($query)) {
            $settings[$row['var']] = $row['val'];
        }

        return $settings;
    }

    /**
     * Nacist konfiguracni direktivy dle typu
     *
     * @param string|null $type
     * @return array
     */
    public static function loadSettingsByType($type)
    {
        $settings = array();
        $query = DB::query('SELECT var,val FROM ' . _settings_table . ' WHERE type' . (null === $type ? ' IS NULL' : '=' . DB::val($type)));
        while ($row = DB::row($query)) {
            $settings[$row['var']] = $row['val'];
        }

        return $settings;
    }

    /**
     * Aktualizovat hodnotu konfiguracni direktivy
     *
     * @param string $name
     * @param string $newValue
     */
    public static function updateSetting($name, $newValue)
    {
        DB::update(_settings_table, 'var=' . DB::val($name), array('val' => (string) $newValue));
    }

    /**
     * Ziskat systemove javascript deklarace
     *
     * @param array $customVariables asociativni pole s vlastnimi promennymi
     * @param bool  $scriptTags      obalit do <script> tagu 1/0
     * @return string
     */
    public static function getJavascript(array $customVariables = array(), $scriptTags = true)
    {
        global $_lang;

        $output = '';

        // opening script tag
        if ($scriptTags) {
            $output .= "<script type=\"text/javascript\">";
        }

        // prepare variables
        $variables = array(
            'basePath' => Url::base()->path . '/',
            'currentTemplate' => _getCurrentTemplate()->getId(),
            'labels' => array(
                'alertConfirm' => $_lang['javascript.alert.confirm'],
                'loading' => $_lang['javascript.loading'],
            ),
            'settings' => array(
                'atReplace' => _atreplace,
            ),
        );
        if (!empty($customVariables)) {
            $variables = array_merge_recursive($variables, $customVariables);
        }
        Extend::call('core.javascript', array('variables' => &$variables));

        // output variables
        $output .= 'var SunlightVars = ' . json_encode($variables) . ';';

        // closing script tags
        if ($scriptTags) {
            $output .= '</script>';
        }

        return $output;
    }

    /**
     * Vyhodit lokalizovanou vyjimku tykajici se nejake neocekavane situace
     *
     * @param string      $msgCs    zprava cesky
     * @param string      $msgEn    zprava anglicky
     * @param array|null  $msgArgs  argumenty sprintf() formatovani
     * @param string|null $msgExtra extra obsah pod zpravou (nelokalizovany)
     * @throws CoreException
     */
    public static function systemFailure($msgCs, $msgEn, array $msgArgs = null, $msgExtra = null)
    {
        if ('cs' === static::$fallbackLang) {
            $message = !empty($msgArgs) ? vsprintf($msgCs, $msgArgs) : $msgCs;
        } else {
            $message = !empty($msgArgs) ? vsprintf($msgEn, $msgArgs) : $msgEn;
        }

        if (!empty($msgExtra)) {
            $message .= "\n\n" . $msgExtra;
        }

        throw new CoreException($message);
    }

    /**
     * Vykreslit vyjimku pro zobrazeni uzivateli
     *
     * @param \Exception $e
     * @param bool       $showTrace
     * @param bool       $showPrevious
     * @return string
     */
    public static function renderException(\Exception $e, $showTrace = true, $showPrevious = true)
    {
        return '<pre class="exception">' . _e(Error::renderException($e, $showTrace, $showPrevious)) . "</pre>\n";
    }

    protected static function configureWebExceptionHandler(WebErrorScreen $errorScreen)
    {
        $errorScreen->on('layout.css', function ($params) {
            $params['css'] .= <<<'CSS'
body {background-color: #ededed; color: #000000;}
a {color: #ff6600;}
.core-exception-info {opacity: 0.8;}
.website-link {display: block; margin: 1em 0; text-align: center; color: #000000; opacity: 0.5;}
CSS;
        });

        if (!_dev) {
            $errorScreen->on('render', function ($view) {
                $view['title'] = $view['heading'] = Core::$fallbackLang === 'cs'
                    ? 'Chyba serveru'
                    : 'Something went wrong';

                $view['text'] = Core::$fallbackLang === 'cs'
                    ? 'Omlouváme se, ale při zpracovávání Vašeho požadavku došlo k neočekávané chybě.'
                    : 'We are sorry, but an unexpected error has occurred while processing your request.';

                if ($view['exception'] instanceof CoreException) {
                    $view['extras'] .= '<div class="group core-exception-info"><div class="section">';
                    $view['extras'] .=  '<p class="message">' . nl2br(_e($view['exception']->getMessage())) . '</p>';
                    $view['extras'] .= '</div></div>';
                    $view['extras'] .= '<a class="website-link" href="https://sunlight-cms.org/" target="_blank">SunLight CMS ' . Core::VERSION . '</a>';
                }
            });
        }
    }
}
