<?php

namespace Sunlight\Installer;

use Kuria\Debug\Output;
use Kuria\Error\ErrorHandler;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Database\SqlReader;
use Sunlight\Util\Password;
use Sunlight\Util\PhpTemplate;
use Sunlight\Util\StringGenerator;
use Sunlight\Util\Url;

define('_dev', true);
define(__NAMESPACE__ . '\CONFIG_PATH', __DIR__ . '/../config.php');
define(__NAMESPACE__ . '\DEFAULT_TIMEZONE', @date_default_timezone_get());

// set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// bootstrap
require __DIR__ . '/../system/bootstrap.php';

// load functions
require __DIR__ . '/../system/functions.php';

// set error handler
$errorHandler = new ErrorHandler();
$errorHandler->setDebug(true);
$errorHandler->register();

/**
 * Configuration
 */
class Config
{
    /** @var array|null */
    public static $config;

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Attempt to load the configuration file
     */
    public static function load()
    {
        if (is_file(CONFIG_PATH)) {
            static::$config = require CONFIG_PATH;
        }
    }

    /**
     * See whether the configuration file is loaded
     *
     * @return bool
     */
    public static function isLoaded()
    {
        return null !== static::$config;
    }
}

/**
 * Installer labels
 */
class Labels
{
    /** @var string */
    private static $language = '_none';
    /** @var string[] */
    private static $labels = array(
        // no language set
        '_none' => array(
            'step.submit' => 'Pokračovat / Continue',
            
            'language.title' => 'Jazyk / Language',
            'language.text' => 'Choose a language / zvolte jazyk:',
        ),

        // czech
        'cs' => array(
            'step.submit' => 'Pokračovat',
            'step.reset' => 'Začít znovu',
            'step.exception' => 'Chyba',

            'config.title' => 'Konfigurace systému',
            'config.text' => 'Tento krok vygeneruje / přepíše soubor config.php.',
            'config.error.db.port.invalid' => 'neplatný port',
            'config.error.db.prefix.empty' => 'prefix nesmí být prázdný',
            'config.error.db.prefix.invalid' => 'prefix obsahuje nepovolené znaky',
            'config.error.db.connect.error' => 'nepodařilo se připojit k databázi, chyba: %error%',
            'config.error.url.empty' => 'adresa webu nesmí být prázdná',
            'config.error.url.invalid' => 'adresa webu nemá platný formát',
            'config.error.secret.empty' => 'tajný hash nesmí být prázdný',
            'config.error.app_id.empty' => 'ID aplikace nesmí být prázdné',
            'config.error.app_id.invalid' => 'ID aplikace obsahuje nepovolené znaky',
            'config.db' => 'Přístup k MySQL databázi',
            'config.db.server' => 'Server',
            'config.db.server.help' => 'host (např. localhost nebo 127.0.0.1)',
            'config.db.port' => 'Port',
            'config.db.port.help' => 'pokud je potřeba nestandardní port, uveďte jej',
            'config.db.user' => 'Uživatel',
            'config.db.user.help' => 'uživatelské jméno',
            'config.db.password' => 'Heslo',
            'config.db.password.help' => 'heslo (je-li vyžadováno)',
            'config.db.name' => 'Databáze',
            'config.db.name.help' => 'název databáze',
            'config.db.prefix' => 'Prefix',
            'config.db.prefix.help' => 'předpona názvu tabulek',
            'config.system' => 'Nastavení systému',
            'config.url' => 'Adresa webu',
            'config.url.help' => 'absolutní URL ke stránkám',
            'config.secret' => 'Tajný hash',
            'config.secret.help' => 'náhodný tajný hash (používáno mj. jako součást XSRF ochrany)',
            'config.app_id' => 'ID aplikace',
            'config.app_id.help' => 'unikátní identifikátor v rámci serveru (používáno pro název session, cookies, ...)',
            'config.timezone' => 'Časové pásmo',
            'config.timezone.help' => 'časové pásmo (prázdné = spoléhat na nastavení serveru), viz',
            'config.locale' => 'Lokalizace',
            'config.locale.help' => 'nastavení lokalizace (prázdné = spoléhat na nastavení serveru), viz',
            'config.geo.latitude' => 'Zeměpisná šířka',
            'config.geo.longitude' => 'Zeměpisná délka',
            'config.geo.zenith' => 'Zenit',
            'config.dev' => 'Vývojový režim',
            'config.dev.help' => 'aktivovat vývojový režim (zobrazování chyb - nepoužívat na ostrém webu!)',

            'import.title' => 'Vytvoření databáze',
            'import.text' => 'Tento krok vytvoří potřebné tabulky a účet hlavního administrátora v databázi.',
            'import.error.settings.title.empty' => 'titulek webu nesmí být prázdný',
            'import.error.admin.username.empty' => 'uživatelské jméno nesmí být prázdné',
            'import.error.admin.password.empty' => 'heslo nesmí být prázdné',
            'import.error.admin.email.empty' => 'email nesmí být prázdný',
            'import.error.admin.email.invalid' => 'neplatná e-mailová adresa',
            'import.error.overwrite.required' => 'tabulky v databázi již existují, je potřeba potvrdit jejich přepsání',
            'import.settings' => 'Nastavení systému',
            'import.settings.title' => 'Titulek webu',
            'import.settings.title.help' => 'hlavní titulek stránek',
            'import.settings.description' => 'Popis webu',
            'import.settings.description.help' => 'krátký popis stránek',
            'import.settings.keywords' => 'Klíčová slova',
            'import.settings.keywords.help' => 'klíčová slova oddělená čárkou',
            'import.settings.latest_version_check' => 'Kontrola verze',
            'import.settings.latest_version_check.help' => 'kontrolovat, zda je verze systému aktuální (pouze na hlavní straně administrace)',
            'import.admin' => 'Účet administrátora',
            'import.admin.username' => 'Uživ. jméno',
            'import.admin.username.help' => 'povolené znaky jsou: a-z, tečka, pomlčka, podtržítko',
            'import.admin.email' => 'E-mail',
            'import.admin.email.help' => 'e-mailová adresa (pro obnovu hesla, atp.)',
            'import.admin.password' => 'Heslo',
            'import.admin.password.help' => 'nesmí být prázdné',
            'import.overwrite' => 'Přepis tabulek',
            'import.overwrite.text' => 'Pozor! V databázi již existují tabulky s prefixem "%prefix%". Přejete si je ODSTRANIT?',
            'import.overwrite.confirmation' => 'ano, nenávratně odstranit existující tabulky',

            'complete.title' => 'Hotovo',
            'complete.success' => 'Instalace byla úspěšně dokončena!',
            'complete.installdir_warning' => 'Pozor! Než budete pokračovat, je potřeba odstranit adresář install ze serveru.',
            'complete.installdir_warning.dev' => 'Vývojový režim je aktivní - není nutné odstranit adresář install. Ale MĚLI BYSTE jej odstranit, pokud bude tato instalace systému přístupná ostatním.',
            'complete.goto.web' => 'zobrazit stránky',
            'complete.goto.admin' => 'přihlásit se do administrace',
        ),

        // english
        'en' => array(
            'step.submit' => 'Continue',
            'step.reset' => 'Start over',
            'step.exception' => 'Error',

            'config.title' => 'System configuration',
            'config.text' => 'This step will generate / overwrite the config.php file.',
            'config.error.db.port.invalid' => 'invalid port',
            'config.error.db.prefix.empty' => 'prefix must not be empty',
            'config.error.db.prefix.invalid' => 'prefix contains invalid characters',
            'config.error.db.connect.error' => 'could not connect to the database, error: %error%',
            'config.error.url.empty' => 'web URL must not be empty',
            'config.error.url.invalid' => 'invalid web URL',
            'config.error.secret.empty' => 'secret hash must not be empty',
            'config.error.app_id.empty' => 'app ID must not be empty',
            'config.error.app_id.invalid' => 'app ID contains invalid characters',
            'config.db' => 'MySQL database access',
            'config.db.server' => 'Server',
            'config.db.server.help' => 'host (e.g. localhost or 127.0.0.1)',
            'config.db.port' => 'Port',
            'config.db.port.help' => 'if a non-standard port is needed, enter it',
            'config.db.user' => 'User',
            'config.db.user.help' => 'user name',
            'config.db.password' => 'Password',
            'config.db.password.help' => 'password (if required)',
            'config.db.name' => 'Database',
            'config.db.name.help' => 'name of the database',
            'config.db.prefix' => 'Prefix',
            'config.db.prefix.help' => 'table name prefix',
            'config.system' => 'System configuration',
            'config.url' => 'Web URL',
            'config.url.help' => 'absolute URL of the website',
            'config.secret' => 'Secret hash',
            'config.secret.help' => 'random secret hash (used for XSRF protection etc.)',
            'config.app_id' => 'App ID',
            'config.app_id.help' => 'unique identifier (server-wide) (used as part of the session name, cookies, etc.)',
            'config.timezone' => 'Timezone',
            'config.timezone.help' => 'timezone (empty = rely on server settings), see',
            'config.locale' => 'Localisation',
            'config.locale.help' => 'localisation settings (empty = rely on server settings), see',
            'config.geo.latitude' => 'Latitude',
            'config.geo.longitude' => 'Longitude',
            'config.geo.zenith' => 'Zenith',
            'config.dev' => 'Dev mode',
            'config.dev.help' => 'enable development mode (displays errors - do not use in production!)',

            'import.title' => 'Create database',
            'import.text' => 'This step will create system tables and the admin account.',
            'import.error.settings.title.empty' => 'title must not be empty',
            'import.error.admin.username.empty' => 'username must not be empty',
            'import.error.admin.password.empty' => 'password must not be empty',
            'import.error.admin.email.empty' => 'email must not be empty',
            'import.error.admin.email.invalid' => 'invalid email address',
            'import.error.overwrite.required' => 'tables already exist in the database - overwrite confirmation is required',
            'import.settings' => 'System settings',
            'import.settings.title' => 'Website title',
            'import.settings.title.help' => 'main website title',
            'import.settings.description' => 'Description',
            'import.settings.description.help' => 'brief site description',
            'import.settings.keywords' => 'Keywords',
            'import.settings.keywords.help' => 'comma-separated list of keywords',
            'import.settings.latest_version_check' => 'Check version',
            'import.settings.latest_version_check.help' => 'check whether the system is up to date (only on the administration home page)',
            'import.admin' => 'Admin account',
            'import.admin.username' => 'Username',
            'import.admin.username.help' => 'allowed characters: a-z, dot, dash, underscore',
            'import.admin.email' => 'E-mail',
            'import.admin.email.help' => 'e-mail address (for password recovery and so on)',
            'import.admin.password' => 'Password',
            'import.admin.password.help' => 'must not be empty',
            'import.overwrite' => 'Overwrite tables',
            'import.overwrite.text' => 'Warning! The database already contains tables with "%prefix%" prefix. Do you wish to REMOVE them?',
            'import.overwrite.confirmation' => 'yes, remove the tables permanently',
            
            'complete.title' => 'Complete',
            'complete.success' => 'Installation has been completed successfully!',
            'complete.installdir_warning' => 'Warning! Before you continue, remove the install directory.',
            'complete.installdir_warning.dev' => 'Dev mode is enabled - removing the install directory is not neccessary. But you SHOULD remove it if this installation is accessible by others.',
            'complete.goto.web' => 'open the website',
            'complete.goto.admin' => 'log into administration',
        ),
    );

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Set the used language
     *
     * @param string $language
     */
    public static function setLanguage($language)
    {
        static::$language = $language;
    }

    /**
     * Get a label
     *
     * @param string     $key
     * @param array|null $replacements
     * @throws \RuntimeException     if the language has not been set
     * @throws \OutOfBoundsException if the key is not valid
     * @return string
     */
    public static function get($key, array $replacements = null)
    {
        if (null === static::$language) {
            throw new \RuntimeException('Language not set');
        }
        if (!isset(static::$labels[static::$language][$key])) {
            throw new \OutOfBoundsException(sprintf('Unknown key "%s[%s]"', static::$language, $key));
        }

        $value = static::$labels[static::$language][$key];

        if (!empty($replacements)) {
            $value = strtr($value, $replacements);
        }

        return $value;
    }

    /**
     * Render a label as HTML
     *
     * @param string     $key
     * @param array|null $replacements
     */
    public static function render($key, array $replacements = null)
    {
        echo _e(static::get($key, $replacements));
    }
}

/**
 * Installer errors
 */
class Errors
{
    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * @param array  $errors
     * @param string $mainLabelKey
     */
    public static function render(array $errors, $mainLabelKey)
    {
        if (!empty($errors)) {
            ?>
<ul class="errors">
    <?php foreach ($errors as $error): ?>
        <li><?php is_array($error)
    ? Labels::render("{$mainLabelKey}.error.{$error[0]}", $error[1])
    : Labels::render("{$mainLabelKey}.error.{$error}") ?></li>
    <?php endforeach ?>
</ul>
<?php
        }
    }
}

/**
 * Step runner
 */
class StepRunner
{
    /** @var Step|null */
    private $current;
    /** @var Step[] */
    private $steps;

    /**
     * @param Step[] $steps
     */
    public function __construct(array $steps)
    {
        $this->steps = $steps;

        // map step numbers
        $stepNumber = 0;
        foreach ($this->steps as $step) {
            $step->setNumber(++$stepNumber);
        }
    }

    /**
     * Run the steps
     *
     * @return string|null
     */
    public function run()
    {
        $this->current = null;
        $submittedNumber = (int) _post('step_number', 0);

        // gather vars
        $vars = array();
        foreach ($this->steps as $step) {
            foreach ($step->getVarNames() as $varName) {
                $vars[$varName] = _post($varName, null, true);
            }
        }

        // run
        foreach ($this->steps as $step) {
            $this->current = $step;

            $step->setVars($vars);
            $step->setSubmittedNumber($submittedNumber);

            if ($step->isSubmittable() && $step->getNumber() === $submittedNumber) {
                $step->handleSubmit();
            }

            if (!$step->isComplete()) {
                return $this->runStep($step, $vars);
            } else {
                $step->postComplete();
            }
        }
    }

    /**
     * Get current step
     *
     * @return Step|null
     */
    public function getCurrent()
    {
        return $this->current;
    }

    /**
     * Get total number of steps
     *
     * @return int
     */
    public function getTotal()
    {
        return sizeof($this->steps);
    }

    /**
     * @param Step  $step
     * @param array $vars
     * @return string
     */
    private function runStep(Step $step, array $vars)
    {
        ob_start();
        
        ?>
<form method="post" autocomplete="off">
    <?php if ($step->hasText()): ?>
        <p><?php Labels::render($step->getMainLabelKey() . '.text') ?></p>
    <?php endif ?>

    <?php Errors::render($step->getErrors(), $step->getMainLabelKey()) ?>

    <?php $step->run() ?>

    <p>
    <?php if ($step->getNumber() > 1): ?>
        <a class="btn btn-lg" id="start-over" href="."><?php Labels::render('step.reset') ?></a>
    <?php endif ?>
    <?php if ($step->isSubmittable()): ?>
        <input id="submit" name="step_submit" type="submit" value="<?php Labels::render('step.submit') ?>">
        <input type="hidden" name="<?php echo $step->getFormKeyVar() ?>" value="1">
        <input type="hidden" name="step_number" value="<?php echo $step->getNumber() ?>">
    <?php endif ?>
    </p>
    
    <?php foreach ($vars as $name => $value): ?>
        <?php if (null !== $value): ?>
            <input type="hidden" name="<?php echo _e($name) ?>" value="<?php echo _e($value) ?>">
        <?php endif ?>
    <?php endforeach ?>
</form>
<?php

        return ob_get_clean();
    }
}

/**
 * Base step
 */
abstract class Step
{
    /** @var int */
    protected $number;
    /** @var int */
    protected $submittedNumber;
    /** @var array */
    protected $vars = array();
    /** @var bool */
    protected $submitted = false;
    /** @var array */
    protected $errors = array();

    /**
     * @return string
     */
    abstract public function getMainLabelKey();

    /**
     * @return string
     */
    public function getFormKeyVar()
    {
        return "step_submit_{$this->number}";
    }

    /**
     * @return string[]
     */
    public function getVarNames()
    {
        return array();
    }

    /**
     * @param array $vars
     */
    public function setVars(array $vars)
    {
        $this->vars = $vars;
    }

    /**
     * @param int $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }

    /**
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param int $submittedNumber
     */
    public function setSubmittedNumber($submittedNumber)
    {
        $this->submittedNumber = $submittedNumber;
    }

    /**
     * @return int
     */
    public function getSubmittedNumber()
    {
        return $this->submittedNumber;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return Labels::get($this->getMainLabelKey() . '.title');
    }

    /**
     * @return bool
     */
    public function isComplete()
    {
        return
            (
                (!$this->isSubmittable() || $this->submitted)
                && empty($this->errors)
            ) || (
                $this->submittedNumber > $this->number
            );
    }

    /**
     * @return bool
     */
    public function hasText()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isSubmittable()
    {
        return true;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Handle step submission
     */
    public function handleSubmit()
    {
        if ($this->isSubmittable()) {
            $this->doSubmit();
            $this->submitted = true;
        }
    }

    /**
     * Process the step form submission
     */
    protected function doSubmit()
    {
    }

    /**
     * Run the step
     */
    abstract public function run();

    /**
     * Execute some logic after the step has been completed
     * (e.g. before the next step is run)
     */
    public function postComplete()
    {
    }

    /**
     * Get configuration value
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function getConfig($key, $default = null)
    {
        if (Config::isLoaded() && array_key_exists($key, Config::$config)) {
            return Config::$config[$key];
        } else {
            return $default;
        }
    }
}

/**
 * Choose a language step
 */
class ChooseLanguageStep extends Step
{
    public function getMainLabelKey()
    {
        return 'language';
    }

    public function getVarNames()
    {
        return array('language');
    }

    public function isComplete()
    {
        return
            parent::isComplete()
            && isset($this->vars['language'])
            && in_array($this->vars['language'], array('cs', 'en'), true);
    }

    public function run()
    {
        ?>
<ul class="major nobullets">
    <li><label><input type="radio" name="language" value="cs"> Čeština</label></li>
    <li><label><input type="radio" name="language" value="en"> English</label></li>
</ul>
<?php
    }

    public function postComplete()
    {
        Labels::setLanguage($this->vars['language']);
    }
}

/**
 * Configuration step
 */
class ConfigurationStep extends Step
{
    public function getMainLabelKey()
    {
        return 'config';
    }

    protected function doSubmit()
    {
        // load data
        $config = array(
            'db.server' => trim(_post('config_db_server', '')),
            'db.port' => (int) trim(_post('config_db_port', '')) ?: null,
            'db.user' => trim(_post('config_db_user', '')),
            'db.password' => trim(_post('config_db_password', '')),
            'db.name' => trim(_post('config_db_name', '')),
            'db.prefix' => trim(_post('config_db_prefix', '')),
            'url' => _removeSlashesFromEnd(trim(_post('config_url', ''))),
            'secret' => trim(_post('config_secret', '')),
            'app_id' => trim(_post('config_app_id', '')),
            'fallback_lang' => $this->vars['language'],
            'dev' => (bool) _checkboxLoad('config_dev'),
            'locale' => $this->getArrayConfigFromString(trim(_post('config_locale', ''))),
            'timezone' => trim(_post('config_timezone', '')) ?: null,
            'geo.latitude' => (float) trim(_post('config_geo_latitude')),
            'geo.longitude' => (float) trim(_post('config_geo_longitude')),
            'geo.zenith' => (float) trim(_post('config_geo_zenith')),
        );

        // validate
        if (null !== $config['db.port'] && $config['db.port'] <= 0) {
            $this->errors[] = 'db.port.invalid';
        }

        if ('' === $config['db.prefix']) {
            $this->errors[] = 'db.prefix.empty';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $config['db.prefix'])) {
            $this->errors[] = 'db.prefix.invalid';
        }

        if ('' === $config['url']) {
            $this->errors[] = 'url.empty';
        } elseif (!preg_match('~^https?://[a-zA-Z0-9_.\-]+(:\d+)?((?:/[^/]+)*)$~', $config['url'])) {
            $this->errors[] = 'url.invalid';
        }

        if ('' === $config['secret']) {
            $this->errors[] = 'secret.empty';
        }

        if ('' === $config['app_id']) {
            $this->errors[] = 'app_id.empty';
        } elseif (!ctype_alnum($config['app_id'])) {
            $this->errors[] = 'app_id.invalid';
        }

        // connect to the database
        if (empty($this->errors)) {
            $connectError = DB::connect($config['db.server'], $config['db.user'], $config['db.password'], $config['db.name'], $config['db.port']);

            if (null !== $connectError) {
                $this->errors[] = array('db.connect.error', array('%error%' => $connectError));
            }
        }

        // generate config file
        if (empty($this->errors)) {
            $configTemplate = PhpTemplate::fromFile(__DIR__ . '/../system/config_template.php');

            file_put_contents(CONFIG_PATH, $configTemplate->compile($config));

            // reload
            Config::load();
        }
    }

    public function isComplete()
    {
        return
            parent::isComplete()
            && is_file(CONFIG_PATH)
            && Config::isLoaded()
            && null === DB::connect(Config::$config['db.server'], Config::$config['db.user'], Config::$config['db.password'], Config::$config['db.name'], Config::$config['db.port']);
    }

    public function run()
    {
        // prepare defaults
        $url = Url::current();

        if (preg_match('~(/.+)/install/?$~', $url->path, $match)) {
            $path = $match[1];
        } else {
            $path = '/';
        }

        $defaultUrl = sprintf('%s://%s%s', $url->scheme, $url->getFullHost(), $path);
        $defaultSecret = StringGenerator::generateHash(64);
        $defaultTimezone = date_default_timezone_get();
        $defaultGeoLatitude = 50.5;
        $defaultGeoLongitude = 14.26;
        $defaultGeoZenith = 90.583333;
        $defaultDev = $this->getConfig('dev', false);

        ?>

<fieldset>
    <legend><?php Labels::render('config.db') ?></legend>
    <table>
        <tr>
            <th><?php Labels::render('config.db.server') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_db_server', $this->getConfig('db.server', 'localhost')) ?>></td>
            <td class="help"><?php Labels::render('config.db.server.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.port') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_db_port', $this->getConfig('db.port')) ?>></td>
            <td class="help"><?php Labels::render('config.db.port.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.user') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_db_user', $this->getConfig('db.user')) ?>></td>
            <td class="help"><?php Labels::render('config.db.user.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.password') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_db_password') ?>></td>
            <td class="help"><?php Labels::render('config.db.password.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.name') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_db_name', $this->getConfig('db.name')) ?>></td>
            <td class="help"><?php Labels::render('config.db.name.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.prefix') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_db_prefix', $this->getConfig('db.prefix', 'sunlight')) ?>></td>
            <td class="help"><?php Labels::render('config.db.prefix.help') ?></td>
        </tr>
    </table>
</fieldset>

<fieldset>
    <legend><?php Labels::render('config.system') ?></legend>
    <table>
        <tr>
            <th><?php Labels::render('config.url') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_url', $this->getConfig('url', $defaultUrl)) ?>></td>
            <td class="help"><?php Labels::render('config.url.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.secret') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_secret', $this->getConfig('secret', $defaultSecret)) ?>></td>
            <td class="help"><?php Labels::render('config.secret.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.app_id') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_app_id', $this->getConfig('app_id', 'sunlight')) ?>></td>
            <td class="help"><?php Labels::render('config.app_id.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.timezone') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_timezone', $this->getConfig('timezone', $defaultTimezone)) ?>></td>
            <td class="help">
                <?php Labels::render('config.timezone.help') ?>
                <a href="http://php.net/timezones" target="_blank">PHP timezones</a>
            </td>
        </tr>
        <tr>
            <th><?php Labels::render('config.locale') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('config_locale', $this->getArrayConfigAsString('locale')) ?>></td>
            <td class="help">
                <?php Labels::render('config.locale.help') ?>
                <a href="http://php.net/setlocale" target="_blank">setlocale()</a>
            </td>
        </tr>
        <tr>
            <th><?php Labels::render('config.geo.latitude') ?></th>
            <td colspan="2"><input type="text"<?php echo _restorePostValueAndName('config_geo_latitude', $this->getConfig('geo.latitude', $defaultGeoLatitude)) ?>></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.geo.longitude') ?></th>
            <td colspan="2"><input type="text"<?php echo _restorePostValueAndName('config_geo_longitude', $this->getConfig('geo.longitude', $defaultGeoLongitude)) ?>></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.geo.zenith') ?></th>
            <td colspan="2"><input type="text"<?php echo _restorePostValueAndName('config_geo_zenith', $this->getConfig('geo.zenith', $defaultGeoZenith)) ?>></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.dev') ?></th>
            <td><input type="checkbox"<?php echo _restoreCheckedAndName($this->getFormKeyVar(), 'config_dev', $this->getConfig('dev', $defaultDev)) ?>></td>
            <td class="help"><?php Labels::render('config.dev.help') ?></td>
        </tr>
    </table>
</fieldset>
<?php
    }

    /**
     * Convert string representation of an array config to an array
     *
     * @param string        $value
     * @param callable|null $mapper
     * @return array|null
     */
    private function getArrayConfigFromString($value, $mapper = null)
    {
        $array = preg_split('/\s*,\s*/', $value, null, PREG_SPLIT_NO_EMPTY);

        if (null !== $mapper) {
            $array = array_map($mapper, $array);
        }

        return empty($array) ? null : $array;
    }

    /**
     * Get string representation of an array config option
     *
     * @param string $key
     * @param array  $default
     * @return string
     */
    private function getArrayConfigAsString($key, array $default = null)
    {
        if (!Config::isLoaded()) {
            $value = $default;
        } else {
            $value = $this->getConfig($key);
        }

        return null !== $value
            ? implode(', ', $value)
            : '';
    }
}

/**
 * Import database step
 */
class ImportDatabaseStep extends Step
{
    /** @var string[] */
    private static $baseTableNames = array(
        'articles',
        'boxes',
        'groups',
        'images',
        'iplog',
        'pm',
        'polls',
        'posts',
        'root',
        'sboxes',
        'settings',
        'users',
        'user_activation',
        'redir',
    );
    /** @var array|null */
    private $existingTableNames;

    public function getMainLabelKey()
    {
        return 'import';
    }
    
    protected function doSubmit()
    {
        $overwrite = (bool) _post('import_overwrite', false);
        
        $settings = array(
            'title' => trim(_post('import_settings_title')),
            'description' => trim(_post('import_settings_description')),
            'keywords' => trim(_post('import_settings_keywords')),
            'language' => $this->vars['language'],
            'atreplace' => 'cs' === $this->vars['language'] ? '[zavinac]' : '[at]',
            'latest_version_check' => _post('import_settings_latest_version_check') ? 1 : 0,
        );

        $admin = array(
            'username' => _slugify(_post('import_admin_username'), false),
            'password' => _post('import_admin_password'),
            'email' => trim(_post('import_admin_email')),
        );

        // validate
        if ('' === $settings['title']) {
            $this->errors[] = 'settings.title.empty';
        }

        if ('' === $admin['username']) {
            $this->errors[] = 'admin.username.empty';
        }

        if ('' === $admin['password']) {
            $this->errors[] = 'admin.password.empty';
        }

        if ('' === $admin['email']) {
            $this->errors[] = 'admin.email.empty';
        } elseif (!_validateEmail($admin['email'])) {
            $this->errors[] = 'admin.email.invalid';
        }

        if (!$overwrite && sizeof($this->getExistingTableNames()) > 0) {
            $this->errors[] = 'overwrite.required';
        }

        // import the database
        if (empty($this->errors)) {

            // drop existing tables
            DatabaseLoader::dropTables($this->getExistingTableNames());
            $this->existingTableNames = null;

            // prepare
            $prefix = Config::$config['db.prefix'] . '_';

            // load the dump
            DatabaseLoader::load(
                SqlReader::fromFile(__DIR__ . '/database.sql'),
                'sunlight_',
                $prefix
            );
            
            // update settings
            foreach ($settings as $name => $value) {
                DB::update($prefix . 'settings', 'var=' . DB::val($name), array('val' => _e($value)));
            }
            
            // update admin account
            DB::update($prefix . 'users', 'id=0', array(
                'username' => $admin['username'],
                'password' => Password::create($admin['password'])->build(),
                'email' => $admin['email'],
                'activitytime' => time(),
                'registertime' => time(),
            ));

            // alter initial content
            foreach ($this->getInitialContent() as $table => $rowMap) {
                foreach ($rowMap as $id => $changeset) {
                    DB::update($prefix . $table, 'id=' . DB::val($id), $changeset);
                }
            }
        }
    }

    private function getInitialContent()
    {
        if ('cs' === $this->vars['language']) {
            return array(
                'boxes' => array(
                    1 => array('title' => 'Menu'),
                    2 => array('title' => 'Vyhledávání'),
                ),
                'groups' => array(
                    1 => array('title' => 'Hlavní administrátoři'),
                    2 => array('title' => 'Neregistrovaní'),
                    3 => array('title' => 'Registrovaní'),
                    4 => array('title' => 'Administrátoři'),
                    5 => array('title' => 'Moderátoři'),
                    6 => array('title' => 'Redaktoři'),
                ),
                'root' => array(
                    1 => array(
                        'title' => 'Úvod',
                        'content' => '<p>Instalace redakčního systému SunLight CMS ' . Core::VERSION . ' ' . Core::DIST . ' byla úspěšně dokončena!<br />
Nyní se již můžete <a href="admin/">přihlásit do administrace</a> (jméno a heslo bylo zvoleno při instalaci).</p>
<p>Podporu, diskusi a doplňky ke stažení naleznete na oficiálních webových stránkách <a href="https://sunlight-cms.org/">sunlight-cms.org</a>.</p>',
                    ),
                ),
            );
        } else {
            return array(
                'boxes' => array(
                    1 => array('title' => 'Menu'),
                    2 => array('title' => 'Search'),
                ),
                'groups' => array(
                    1 => array('title' => 'Super administrators'),
                    2 => array('title' => 'Guests'),
                    3 => array('title' => 'Registered'),
                    4 => array('title' => 'Administrators'),
                    5 => array('title' => 'Moderators'),
                    6 => array('title' => 'Editors'),
                ),
                'root' => array(
                    1 => array(
                        'title' => 'Home',
                        'content' => '<p>Installation of SunLight CMS ' . Core::VERSION . ' ' . Core::DIST . ' has been a success!<br />
Now you can <a href="admin/">log in to the administration</a> (username and password has been setup during installation).</p>
<p>Support, forums and plugins are available at the official website <a href="https://sunlight-cms.org/">sunlight-cms.org</a>.</p>',
                    ),
                ),
            );
        }
    }

    public function isComplete()
    {
        return
            parent::isComplete()
            && $this->isDatabaseInstalled();
    }

    public function run()
    {
        ?>
<fieldset>
    <legend><?php Labels::render('import.settings') ?></legend>
    <table>
        <tr>
            <th><?php Labels::render('import.settings.title') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('import_settings_title') ?>></td>
            <td class="help"><?php Labels::render('import.settings.title.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.settings.description') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('import_settings_description') ?>></td>
            <td class="help"><?php Labels::render('import.settings.description.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.settings.keywords') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('import_settings_keywords') ?>></td>
            <td class="help"><?php Labels::render('import.settings.keywords.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.settings.latest_version_check') ?></th>
            <td><input type="checkbox"<?php echo _restoreCheckedAndName($this->getFormKeyVar(), 'import_settings_latest_version_check', true) ?>></td>
            <td class="help"><?php Labels::render('import.settings.latest_version_check.help') ?></td>
        </tr>
    </table>
</fieldset>

<fieldset>
    <legend><?php Labels::render('import.admin') ?></legend>
    <table>
        <tr>
            <th><?php Labels::render('import.admin.username') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('import_admin_username', 'admin') ?>></td>
            <td class="help"><?php Labels::render('import.admin.username.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.admin.password') ?></th>
            <td><input type="password" name="import_admin_password"></td>
            <td class="help"><?php Labels::render('import.admin.password.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.admin.email') ?></th>
            <td><input type="text"<?php echo _restorePostValueAndName('import_admin_email', '@') ?>></td>
            <td class="help"><?php Labels::render('import.admin.email.help') ?></td>
        </tr>
    </table>
</fieldset>

<?php if (sizeof($this->getExistingTableNames()) > 0): ?>
<fieldset>
    <legend><?php Labels::render('import.overwrite') ?></legend>
    <p class="msg warning"><?php Labels::render('import.overwrite.text', array('%prefix%' => Config::$config['db.prefix'] . '_')) ?></p>
    <p><label><input type="checkbox"<?php echo _restoreCheckedAndName($this->getFormKeyVar(), 'import_overwrite') ?>> <?php Labels::render('import.overwrite.confirmation') ?></label></p>
</fieldset>
<?php endif ?>
<?php
    }

    /**
     * @return bool
     */
    private function isDatabaseInstalled()
    {
        return 0 === sizeof(array_diff($this->getTableNames(), $this->getExistingTableNames()));
    }

    /**
     * @return string[]
     */
    private function getExistingTableNames()
    {
        if (null === $this->existingTableNames) {
            $this->existingTableNames = DB::queryRows(
                'SHOW TABLES LIKE ' . DB::val(Config::$config['db.prefix'] . '_%'),
                null,
                0,
                false
            );
        }

        return $this->existingTableNames;
    }

    /**
     * @return string[]
     */
    private function getTableNames()
    {
        $prefix = Config::$config['db.prefix'] . '_';

        return array_map(function ($baseTableName) use ($prefix) {
            return $prefix . $baseTableName;
        }, static::$baseTableNames);
    }
}

/**
 * Complete step
 */
class CompleteStep extends Step
{
    public function getMainLabelKey()
    {
        return 'complete';
    }

    public function isSubmittable()
    {
        return false;
    }

    public function hasText()
    {
        return false;
    }

    public function isComplete()
    {
        return false;
    }

    public function run()
    {
        $isDev = Config::$config['dev'];

        ?>
<p class="msg success"><?php Labels::render('complete.success') ?></p>

<p class="msg <?php echo $isDev ? 'notice' : 'warning' ?>"><?php Labels::render('complete.installdir_warning' . ($isDev ? '.dev' : '')) ?></p>

<ul class="major">
    <li><a href="<?php echo _e(Config::$config['url']) ?>" target="_blank"><?php Labels::render('complete.goto.web') ?></a></li>
    <li><a href="<?php echo _e(Config::$config['url']) ?>/admin/" target="_blank"><?php Labels::render('complete.goto.admin') ?></a></li>
</ul>
<?php
    }
}

// load configuration
Config::load();

// create step runner
$stepRunner= new StepRunner(array(
    new ChooseLanguageStep(),
    new ConfigurationStep(),
    new ImportDatabaseStep(),
    new CompleteStep(),
));

// run
try {
    $content = $stepRunner->run();
} catch (\Exception $e) {
    Output::cleanBuffers();

    ob_start();
    ?>
<h2><?php Labels::render('step.exception') ?></h2>
<pre><?php echo _e((string) $e) ?></pre>
<?php
    $content = ob_get_clean();
}
$step = $stepRunner->getCurrent();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style type="text/css">
        * {margin: 0; padding: 0;}
        body {margin: 1em; background-color: #ededed; font-family: sans-serif; font-size: 13px; color: #000;}
        a {color: #f60; text-decoration: none;}
        a:hover {color: #000;}
        h1, h2, h3, p, ol, ul, pre {margin: 1em 0; line-height: 1.5;}
        h1 {margin: 0; padding: 0.5em 1em; font-size: 1.5em;}
        #step span {padding: 0 0.3em; margin-right: 0.2em; background-color: #fff;}
        #system-name {float: right; color: #f60;}
        h2 {font-size: 1.3em;}
        h3 {font-size: 1.1em;}
        h2:first-child, h3:first-child {margin-top: 0;}
        ul, ol {padding-left: 40px;}
        .major {font-size: 1.5em;}
        .nobullets {list-style-type: none; padding-left: 0;}
        ul.errors {padding-top: 10px; padding-bottom: 10px; background-color: #eee;}
        ul.errors li {font-size: 1.1em; color: red;}
        select, input[type=text], input[type=password], input[type=reset], input[type=button], button {padding: 5px;}
        .btn {display: inline-block;}
        .btn, input[type=submit], input[type=button], input[type=reset], button {cursor: pointer; padding: 4px 16px; border: 1px solid #bbbbbb; background: #ededed; background: linear-gradient(to bottom, #f5f5f5, #ededed); color: #000; line-height: normal;}
        .btn:hover, input[type=submit]:hover, input[type=button]:hover, input[type=reset]:hover, button:hover {color: #fff; background: #fe5300; background: linear-gradient(to bottom, #fe7b3b, #ea4c00); border-color: #ea4c00; outline: none;}
        .btn-lg, input[type=submit] {padding: 10px; font-size: 1.2em;}
        fieldset {margin: 2em 0; border: 1px solid #ccc; padding: 10px;}
        legend {padding: 0 10px; font-weight: bold;}
        th {white-space: nowrap;}
        th, td {padding: 3px 5px;}
        form tbody th {text-align: right;}
        form td.help {color: #777;}
        pre {overflow: auto;}
        p.msg {padding: 10px;}
        p.msg.success {color: #080; background-color: #d9ffd9;}
        p.msg.notice {color: #000; background-color: #d9e3ff;}
        p.msg.warning {color: #c00; background-color: #ffd9d9;}
        #wrapper {margin: 0 auto; min-width: 600px; max-width: 950px;}
        #content {padding: 15px 30px 25px 30px; background-color: #fff;}
        #start-over {}
        #submit {float: right;}
        .cleaner {clear: both;}
    </style>
    <title><?php echo _e("[{$step->getNumber()}/{$stepRunner->getTotal()}]: {$step->getTitle()}") ?></title>
</head>

<body>

    <div id="wrapper">

        <h1>
            <span id="step">
                <span><?php echo $step->getNumber(), '/', $stepRunner->getTotal() ?></span>
                <?php echo _e($step->getTitle()) ?>
            </span>
            <span id="system-name">
                SunLight CMS <?php echo Core::VERSION ?> <small><?php echo Core::DIST ?></small>
            </span>
        </h1>

        <div id="content">
            <?php echo $content ?>

            <div class="cleaner"></div>
        </div>

    </div>

</body>
</html>
