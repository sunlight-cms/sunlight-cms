<?php

namespace Sunlight\Installer;

use Kuria\Debug\Output;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Database\DatabaseException;
use Sunlight\Database\DatabaseLoader;
use Sunlight\Database\SqlReader;
use Sunlight\Email;
use Sunlight\User;
use Sunlight\Util\ConfigurationFile;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\StringGenerator;

const CONFIG_PATH = __DIR__ . '/../config.php';

// bootstrap
require __DIR__ . '/../system/bootstrap.php';
Core::init('../', [
    'minimal_mode' => true,
    'config_file' => false,
    'debug' => true,
]);

// config
$config = new ConfigurationFile(CONFIG_PATH);
$defaults = require __DIR__ . '/../system/config_template.php';
$defaults['secret'] = StringGenerator::generateString(64);

foreach ($defaults as $key => $value) {
    if (!$config->isDefined($key)) {
        $config[$key] = $value;
    }
}

/**
 * Installer labels
 */
abstract class Labels
{
    /** @var string */
    private static $language = '_none';
    /** @var string[][] */
    private static $labels = [
        // no language set
        '_none' => [
            'step.submit' => 'Pokračovat / Continue',
            
            'language.title' => 'Jazyk / Language',
            'language.text' => 'Choose a language / zvolte jazyk:',
        ],

        // czech
        'cs' => [
            'step.submit' => 'Pokračovat',
            'step.reset' => 'Začít znovu',
            'step.exception' => 'Chyba',

            'config.title' => 'Konfigurace systému',
            'config.text' => 'Tento krok vygeneruje / přepíše soubor config.php.',
            'config.error.db.port.invalid' => 'neplatný port',
            'config.error.db.name.empty' => 'název databáze nesmí být prázdný',
            'config.error.db.prefix.empty' => 'prefix nesmí být prázdný',
            'config.error.db.prefix.invalid' => 'prefix obsahuje nepovolené znaky',
            'config.error.db.connect.error' => 'nepodařilo se připojit k databázi, chyba: %error%',
            'config.error.db.create.error' => 'nepodařilo se vytvořit databázi (možná ji bude nutné vytvořit manuálně ve správě vašeho webhostingu): %error%',
            'config.error.secret.empty' => 'tajný hash nesmí být prázdný',
            'config.error.write_failed' => 'Nepodařilo se zapsat %config_path%. Zkontrolujte přístupová práva.',
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
            'config.db.name.help' => 'název databáze (pokud neexistuje, bude vytvořena)',
            'config.db.prefix' => 'Prefix',
            'config.db.prefix.help' => 'předpona názvu tabulek',
            'config.system' => 'Nastavení systému',
            'config.secret' => 'Tajný hash',
            'config.secret.help' => 'náhodný tajný hash (používáno mj. jako součást XSRF ochrany)',
            'config.timezone' => 'Časové pásmo',
            'config.timezone.help' => 'časové pásmo (prázdné = spoléhat na nastavení serveru), viz',
            'config.debug' => 'Vývojový režim',
            'config.debug.help' => 'aktivovat vývojový režim (zobrazování chyb - nepoužívat na ostrém webu!)',

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
            'import.settings.version_check' => 'Kontrola verze',
            'import.settings.version_check.help' => 'kontrolovat, zda je verze systému aktuální',
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
            'complete.whats_next' => 'Co dál?',
            'complete.success' => 'Instalace byla úspěšně dokončena!',
            'complete.installdir_warning' => 'Než budete pokračovat, je potřeba odstranit adresář install ze serveru.',
            'complete.goto.web' => 'zobrazit stránky',
            'complete.goto.admin' => 'přihlásit se do administrace',
        ],

        // english
        'en' => [
            'step.submit' => 'Continue',
            'step.reset' => 'Start over',
            'step.exception' => 'Error',

            'config.title' => 'System configuration',
            'config.text' => 'This step will generate / overwrite the config.php file.',
            'config.error.db.port.invalid' => 'invalid port',
            'config.error.db.name.empty' => 'database name must not be empty',
            'config.error.db.prefix.empty' => 'prefix must not be empty',
            'config.error.db.prefix.invalid' => 'prefix contains invalid characters',
            'config.error.db.connect.error' => 'could not connect to the database, error: %error%',
            'config.error.db.create.error' => 'could not create database (perhaps you need to create it manually via your webhosting\'s management page): %error%',
            'config.error.secret.empty' => 'secret hash must not be empty',
            'config.error.write_failed' => 'Could not write %config_path%. Check filesystem permissions.',
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
            'config.db.name.help' => 'name of the database (if it doesn\'t exist, it will be created)',
            'config.db.prefix' => 'Prefix',
            'config.db.prefix.help' => 'table name prefix',
            'config.system' => 'System configuration',
            'config.secret' => 'Secret hash',
            'config.secret.help' => 'random secret hash (used for XSRF protection etc.)',
            'config.timezone' => 'Timezone',
            'config.timezone.help' => 'timezone (empty = rely on server settings), see',
            'config.debug' => 'Debug mode',
            'config.debug.help' => 'enable debug mode (displays errors - do not use on a live website!)',

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
            'import.settings.version_check' => 'Check version',
            'import.settings.version_check.help' => 'check whether the system is up to date',
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
            'complete.whats_next' => 'What\'s next?',
            'complete.success' => 'Installation has been completed successfully!',
            'complete.installdir_warning' => 'Before you continue, you must remove the install directory.',
            'complete.goto.web' => 'open the website',
            'complete.goto.admin' => 'log into administration',
        ],
    ];

    /**
     * Set the used language
     */
    static function setLanguage(string $language): void
    {
        self::$language = $language;
    }

    /**
     * Get a label
     *
     * @throws \RuntimeException if the language has not been set
     * @throws \OutOfBoundsException if the key is not valid
     */
    static function get(string $key, ?array $replacements = null): string
    {
        if (self::$language === null) {
            throw new \RuntimeException('Language not set');
        }

        if (!isset(self::$labels[self::$language][$key])) {
            throw new \OutOfBoundsException(sprintf('Unknown key "%s[%s]"', self::$language, $key));
        }

        $value = self::$labels[self::$language][$key];

        if (!empty($replacements)) {
            $value = strtr($value, $replacements);
        }

        return $value;
    }

    /**
     * Render a label as HTML
     */
    static function render(string $key, ?array $replacements = null): void
    {
        echo _e(self::get($key, $replacements));
    }
}

/**
 * Installer errors
 */
abstract class Errors
{
    static function render(array $errors, string $mainLabelKey): void
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
    function __construct(array $steps)
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
     */
    function run(): ?string
    {
        $this->current = null;
        $submittedNumber = (int) Request::post('step_number', 0);

        // gather vars
        $vars = [];

        foreach ($this->steps as $step) {
            foreach ($step->getVarNames() as $varName) {
                $vars[$varName] = Request::post($varName, null, true);
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
            }

            $step->postComplete();
        }

        return null;
    }

    /**
     * Get current step
     */
    function getCurrent(): ?Step
    {
        return $this->current;
    }

    /**
     * Get total number of steps
     */
    function getTotal(): int
    {
        return count($this->steps);
    }

    private function runStep(Step $step, array $vars): string
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
        <a class="btn btn-lg" id="start-over" href="<?= Core::getBaseUrl() ?>/install/"><?php Labels::render('step.reset') ?></a>
    <?php endif ?>
    <?php if ($step->isSubmittable()): ?>
        <input id="submit" name="step_submit" type="submit" value="<?php Labels::render('step.submit') ?>">
        <input type="hidden" name="<?= $step->getFormKeyVar() ?>" value="1">
        <input type="hidden" name="step_number" value="<?= $step->getNumber() ?>">
    <?php endif ?>
    </p>
    
    <?php foreach ($vars as $name => $value): ?>
        <?php if ($value !== null): ?>
            <input type="hidden" name="<?= _e($name) ?>" value="<?= _e($value) ?>">
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
    protected $vars = [];
    /** @var bool */
    protected $submitted = false;
    /** @var array */
    protected $errors = [];
    /** @var ConfigurationFile */
    protected $config;

    function __construct(ConfigurationFile $config)
    {
        $this->config = $config;
    }

    abstract function getMainLabelKey(): string;

    function getFormKeyVar(): string
    {
        return "step_submit_{$this->number}";
    }

    /**
     * @return string[]
     */
    function getVarNames(): array
    {
        return [];
    }

    function setVars(array $vars): void
    {
        $this->vars = $vars;
    }

    function setNumber(int $number): void
    {
        $this->number = $number;
    }

    function getNumber(): int
    {
        return $this->number;
    }

    function setSubmittedNumber(int $submittedNumber): void
    {
        $this->submittedNumber = $submittedNumber;
    }

    function getSubmittedNumber(): int
    {
        return $this->submittedNumber;
    }

    function getTitle(): string
    {
        return Labels::get($this->getMainLabelKey() . '.title');
    }

    function isComplete(): bool
    {
        return
            (
                (!$this->isSubmittable() || $this->submitted)
                && empty($this->errors)
            ) || (
                $this->submittedNumber > $this->number
            );
    }

    function hasText(): bool
    {
        return true;
    }

    function isSubmittable(): bool
    {
        return true;
    }

    function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Handle step submission
     */
    function handleSubmit(): void
    {
        if ($this->isSubmittable()) {
            $this->doSubmit();
            $this->submitted = true;
        }
    }

    /**
     * Process the step form submission
     */
    protected function doSubmit(): void
    {
    }

    /**
     * Run the step
     */
    abstract function run(): void;

    /**
     * Execute some logic after the step has been completed
     * (e.g. before the next step is run)
     */
    function postComplete(): void
    {
    }
}

/**
 * Choose a language step
 */
class ChooseLanguageStep extends Step
{
    function getMainLabelKey(): string
    {
        return 'language';
    }

    function getVarNames(): array
    {
        return ['language'];
    }

    function isComplete(): bool
    {
        return
            parent::isComplete()
            && isset($this->vars['language'])
            && in_array($this->vars['language'], ['cs', 'en'], true);
    }

    function run(): void
    {
        ?>
<ul class="big-list nobullets">
    <li><label><input type="radio" name="language" value="cs" checked> Čeština</label></li>
    <li><label><input type="radio" name="language" value="en"> English</label></li>
</ul>
<?php
    }

    function postComplete(): void
    {
        Labels::setLanguage($this->vars['language']);
    }
}

/**
 * Configuration step
 */
class ConfigurationStep extends Step
{
    function getMainLabelKey(): string
    {
        return 'config';
    }

    protected function doSubmit(): void
    {
        // load data
        $config = [
            'db.server' => trim(Request::post('config_db_server', '')),
            'db.port' => (int) trim(Request::post('config_db_port', '')) ?: null,
            'db.user' => trim(Request::post('config_db_user', '')),
            'db.password' => trim(Request::post('config_db_password', '')),
            'db.name' => trim(Request::post('config_db_name', '')),
            'db.prefix' => trim(Request::post('config_db_prefix', '')),
            'secret' => trim(Request::post('config_secret', '')),
            'fallback_lang' => $this->vars['language'],
            'debug' => (bool) Form::loadCheckbox('config_debug'),
            'timezone' => trim(Request::post('config_timezone', '')) ?: null,
        ];

        // validate
        if ($config['db.port'] !== null && $config['db.port'] <= 0) {
            $this->errors[] = 'db.port.invalid';
        }

        if ($config['db.name'] === '') {
            $this->errors[] = 'db.name.empty';
        }

        if ($config['db.prefix'] === '') {
            $this->errors[] = 'db.prefix.empty';
        } elseif (!preg_match('{[a-zA-Z0-9_]+$}AD', $config['db.prefix'])) {
            $this->errors[] = 'db.prefix.invalid';
        }

        if ($config['secret'] === '') {
            $this->errors[] = 'secret.empty';
        }

        // connect to the database
        if (empty($this->errors)) {
            try {
                DB::connect($config['db.server'], $config['db.user'], $config['db.password'], '', $config['db.port'], $config['db.prefix']);
            } catch (DatabaseException $e) {
                $this->errors[] = ['db.connect.error', ['%error%' => $e->getMessage()]];
            }

            if (empty($this->errors)) {
                // attempt to create the database if it does not exist
                try {
                    DB::query('CREATE DATABASE IF NOT EXISTS ' . DB::escIdt($config['db.name']) . ' COLLATE \'utf8mb4_unicode_ci\'');
                } catch (DatabaseException $e) {
                    $this->errors[] = ['db.create.error', ['%error%' => $e->getMessage()]];
                }
            }
        }

        // generate config file
        if (empty($this->errors)) {
            foreach ($config as $key => $value) {
                $this->config[$key] = $value;
            }

            try {
                $this->config->save();
            } catch (\Throwable $e) {
                $this->errors[] = ['write_failed', ['%config_path%' => CONFIG_PATH]];
            }
        }
    }

    function isComplete(): bool
    {
        if (parent::isComplete() && is_file(CONFIG_PATH)) {
            try {
                DB::connect($this->config['db.server'], $this->config['db.user'], $this->config['db.password'], '', $this->config['db.port'], $this->config['db.prefix']);

                return true;
            } catch (DatabaseException $e) {
            }
        }

        return false;
    }

    function run(): void
    {
        ?>

<fieldset>
    <legend><?php Labels::render('config.db') ?></legend>
    <table>
        <tr>
            <th><?php Labels::render('config.db.server') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('config_db_server', $this->config['db.server']) ?>></td>
            <td class="help"><?php Labels::render('config.db.server.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.port') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('config_db_port', $this->config['db.port']) ?>></td>
            <td class="help"><?php Labels::render('config.db.port.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.user') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('config_db_user', $this->config['db.user']) ?>></td>
            <td class="help"><?php Labels::render('config.db.user.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.password') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('config_db_password') ?>></td>
            <td class="help"><?php Labels::render('config.db.password.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.name') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('config_db_name', $this->config['db.name']) ?>></td>
            <td class="help"><?php Labels::render('config.db.name.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.db.prefix') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('config_db_prefix', $this->config['db.prefix']) ?>></td>
            <td class="help"><?php Labels::render('config.db.prefix.help') ?></td>
        </tr>
    </table>
</fieldset>

<fieldset>
    <legend><?php Labels::render('config.system') ?></legend>
    <table>
        <tr>
            <th><?php Labels::render('config.secret') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('config_secret', $this->config['secret']) ?>></td>
            <td class="help"><?php Labels::render('config.secret.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('config.timezone') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('config_timezone', $this->config['timezone']) ?>></td>
            <td class="help">
                <?php Labels::render('config.timezone.help') ?>
                <a href="https://php.net/timezones" target="_blank">PHP timezones</a>
            </td>
        </tr>
        <tr>
            <th><?php Labels::render('config.debug') ?></th>
            <td><input type="checkbox"<?= Form::restoreCheckedAndName($this->getFormKeyVar(), 'config_debug', $this->config['debug']) ?>></td>
            <td class="help"><?php Labels::render('config.debug.help') ?></td>
        </tr>
    </table>
</fieldset>
<?php
    }
}

/**
 * Import database step
 */
class ImportDatabaseStep extends Step
{
    /** @var string[] */
    private static $baseTableNames = [
        'article',
        'box',
        'gallery_image',
        'iplog',
        'log',
        'page',
        'pm',
        'poll',
        'post',
        'redirect',
        'setting',
        'shoutbox',
        'user',
        'user_activation',
        'user_group',
    ];
    /** @var array|null */
    private $existingTableNames;

    function getMainLabelKey(): string
    {
        return 'import';
    }
    
    protected function doSubmit(): void
    {
        $overwrite = (bool) Request::post('import_overwrite', false);
        
        $settings = [
            'title' => trim(Request::post('import_settings_title', '')),
            'description' => trim(Request::post('import_settings_description', '')),
            'language' => $this->vars['language'],
            'atreplace' => $this->vars['language'] === 'cs' ? '[zavinac]' : '[at]',
            'version_check' => Request::post('import_settings_version_check') ? 1 : 0,
            'pretty_urls' => function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules(), true) ? 1 : 0,
        ];

        $admin = [
            'username' => User::normalizeUsername(Request::post('import_admin_username', '')),
            'password' => Request::post('import_admin_password'),
            'email' => trim(Request::post('import_admin_email', '')),
        ];

        // validate
        if ($settings['title'] === '') {
            $this->errors[] = 'settings.title.empty';
        }

        if ($admin['username'] === '') {
            $this->errors[] = 'admin.username.empty';
        }

        if ($admin['password'] === '') {
            $this->errors[] = 'admin.password.empty';
        }

        if ($admin['email'] === '') {
            $this->errors[] = 'admin.email.empty';
        } elseif (!Email::validate($admin['email'])) {
            $this->errors[] = 'admin.email.invalid';
        }

        if (!$overwrite && count($this->getExistingTableNames()) > 0) {
            $this->errors[] = 'overwrite.required';
        }

        // import the database
        if (empty($this->errors)) {
            // use database
            DB::query('USE '. DB::escIdt($this->config['db.name']));

            DB::transactional(function () use ($settings, $admin) {
                // drop existing tables
                DatabaseLoader::dropTables($this->getExistingTableNames());
                $this->existingTableNames = null;

                // load the dump
                DatabaseLoader::load(
                    SqlReader::fromFile(__DIR__ . '/database.sql'),
                    'sunlight_',
                    DB::$prefix
                );

                // update settings
                foreach ($settings as $name => $value) {
                    DB::update('setting', 'var=' . DB::val($name), ['val' => _e($value)]);
                }

                // update admin account
                DB::update('user', 'id=1', [
                    'username' => $admin['username'],
                    'password' => Password::create($admin['password'])->build(),
                    'email' => $admin['email'],
                    'activitytime' => time(),
                    'registertime' => time(),
                ]);

                // alter initial content
                foreach ($this->getInitialContent() as $table => $rowMap) {
                    foreach ($rowMap as $id => $changeset) {
                        DB::update($table, 'id=' . DB::val($id), $changeset);
                    }
                }
            });
        }
    }

    private function getInitialContent(): array
    {
        if ($this->vars['language'] === 'cs') {
            return [
                'box' => [
                    1 => ['title' => 'Menu'],
                    2 => ['title' => 'Vyhledávání'],
                ],
                'user_group' => [
                    1 => ['title' => 'Hlavní administrátoři'],
                    2 => ['title' => 'Neregistrovaní'],
                    3 => ['title' => 'Registrovaní'],
                    4 => ['title' => 'Administrátoři'],
                    5 => ['title' => 'Moderátoři'],
                    6 => ['title' => 'Redaktoři'],
                ],
                'page' => [
                    1 => [
                        'title' => 'Úvod',
                        'content' => '<p>Instalace redakčního systému SunLight CMS ' . Core::VERSION . ' byla úspěšně dokončena!<br>
Nyní se již můžete <a href="admin/">přihlásit do administrace</a> (s účtem nastaveným při instalaci).</p>
<p>Podporu, diskusi a doplňky ke stažení naleznete na oficiálních webových stránkách <a href="https://sunlight-cms.cz/">sunlight-cms.cz</a>.</p>',
                    ],
                ],
            ];
        } else {
            return [
                'box' => [
                    1 => ['title' => 'Menu'],
                    2 => ['title' => 'Search'],
                ],
                'user_group' => [
                    1 => ['title' => 'Super administrators'],
                    2 => ['title' => 'Guests'],
                    3 => ['title' => 'Registered'],
                    4 => ['title' => 'Administrators'],
                    5 => ['title' => 'Moderators'],
                    6 => ['title' => 'Editors'],
                ],
                'page' => [
                    1 => [
                        'title' => 'Home',
                        'content' => '<p>Installation of SunLight CMS ' . Core::VERSION . ' has been a success!<br>
Now you can <a href="admin/">log in to the administration</a> (with the account set up during installation).</p>
<p>Support, forums and plugins are available at the official website <a href="https://sunlight-cms.cz/">sunlight-cms.cz</a>.</p>',
                    ],
                ],
            ];
        }
    }

    function isComplete(): bool
    {
        return
            parent::isComplete()
            && $this->isDatabaseInstalled();
    }

    function run(): void
    {
        ?>
<fieldset>
    <legend><?php Labels::render('import.settings') ?></legend>
    <table>
        <tr>
            <th><?php Labels::render('import.settings.title') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('import_settings_title') ?>></td>
            <td class="help"><?php Labels::render('import.settings.title.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.settings.description') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('import_settings_description') ?>></td>
            <td class="help"><?php Labels::render('import.settings.description.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.settings.version_check') ?></th>
            <td><input type="checkbox"<?= Form::restoreCheckedAndName($this->getFormKeyVar(), 'import_settings_version_check', true) ?>></td>
            <td class="help"><?php Labels::render('import.settings.version_check.help') ?></td>
        </tr>
    </table>
</fieldset>

<fieldset>
    <legend><?php Labels::render('import.admin') ?></legend>
    <table>
        <tr>
            <th><?php Labels::render('import.admin.username') ?></th>
            <td><input type="text"<?= Form::restorePostValueAndName('import_admin_username', 'admin') ?>></td>
            <td class="help"><?php Labels::render('import.admin.username.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.admin.password') ?></th>
            <td><input type="password" name="import_admin_password"></td>
            <td class="help"><?php Labels::render('import.admin.password.help') ?></td>
        </tr>
        <tr>
            <th><?php Labels::render('import.admin.email') ?></th>
            <td><input type="email"<?= Form::restorePostValueAndName('import_admin_email', $this->config['debug'] ? 'admin@localhost' : '@') ?>></td>
            <td class="help"><?php Labels::render('import.admin.email.help') ?></td>
        </tr>
    </table>
</fieldset>

<?php if (count($this->getExistingTableNames()) > 0): ?>
<fieldset>
    <legend><?php Labels::render('import.overwrite') ?></legend>
    <p class="msg warning"><?php Labels::render('import.overwrite.text', ['%prefix%' => $this->config['db.prefix'] . '_']) ?></p>
    <p>
        <label>
            <input type="checkbox"<?= Form::restoreCheckedAndName($this->getFormKeyVar(), 'import_overwrite') ?>>
            <?php Labels::render('import.overwrite.confirmation') ?>
        </label>
    </p>
</fieldset>
<?php endif ?>
<?php
    }

    private function isDatabaseInstalled(): bool
    {
        return count(array_diff($this->getTableNames(), $this->getExistingTableNames())) === 0;
    }

    /**
     * @return string[]
     */
    private function getExistingTableNames(): array
    {
        if ($this->existingTableNames === null) {
            $this->existingTableNames = DB::queryRows(
                'SHOW TABLES FROM ' . DB::escIdt($this->config['db.name']) . ' LIKE ' . DB::val($this->config['db.prefix'] . '_%'),
                null,
                0,
                false,
                true
            ) ?: [];
        }

        return $this->existingTableNames;
    }

    /**
     * @return string[]
     */
    private function getTableNames(): array
    {
        $prefix = $this->config['db.prefix'] . '_';

        return array_map(function ($baseTableName) use ($prefix) {
            return $prefix . $baseTableName;
        }, self::$baseTableNames);
    }
}

/**
 * Complete step
 */
class CompleteStep extends Step
{
    function getMainLabelKey(): string
    {
        return 'complete';
    }

    function isSubmittable(): bool
    {
        return false;
    }

    function hasText(): bool
    {
        return false;
    }

    function isComplete(): bool
    {
        return false;
    }

    function run(): void
    {
        Core::$cache->clear();

        ?>
<p class="msg success"><?php Labels::render('complete.success') ?></p>

<?php if (!$this->config['debug']): ?>
    <p class="msg warning"><?php Labels::render('complete.installdir_warning') ?></p>
<?php endif ?>

<h2><?php Labels::render('complete.whats_next') ?></h2>

<ul class="big-list">
    <li><a href="<?= _e(Core::getBaseUrl()->getPath()) ?>/" target="_blank"><?php Labels::render('complete.goto.web') ?></a></li>
    <li><a href="<?= _e(Core::getBaseUrl()->getPath()) ?>/admin/" target="_blank"><?php Labels::render('complete.goto.admin') ?></a></li>
</ul>
<?php
    }
}

// create step runner
$stepRunner = new StepRunner([
    new ChooseLanguageStep($config),
    new ConfigurationStep($config),
    new ImportDatabaseStep($config),
    new CompleteStep($config),
]);

// run
try {
    $content = $stepRunner->run();
} catch (\Throwable $e) {
    Output::cleanBuffers();

    ob_start();
    ?>
<h2><?php Labels::render('step.exception') ?></h2>
<pre><?= _e((string) $e) ?></pre>
<?php
    $content = ob_get_clean();
}

$step = $stepRunner->getCurrent();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {margin: 0; padding: 0;}
        body {margin: 1em; background-color: #ededed; font-family: sans-serif; font-size: 13px; color: #000;}
        a {color: #f60; text-decoration: none;}
        a:hover {color: #000;}
        h1, h2, h3, p, ol, ul, pre {line-height: 1.5;}
        h1 {margin: 0; padding: 0.5em 1em; font-size: 1.5em;}
        h2, h3 {margin: 0.5em 0;}
        p, ol, ul, pre {margin: 1em 0;}
        #step span {padding: 0 0.3em; margin-right: 0.2em; background-color: #fff;}
        #system-name {float: right; color: #f60;}
        h2 {font-size: 1.3em;}
        h3 {font-size: 1.1em;}
        h2:first-child, h3:first-child {margin-top: 0;}
        ul, ol {padding-left: 40px;}
        .big-list {margin: 0.5em 0; font-size: 1.5em;}
        .nobullets {list-style-type: none; padding-left: 0;}
        ul.errors {padding-top: 10px; padding-bottom: 10px; background-color: #eee;}
        ul.errors li {font-size: 1.1em; color: red;}
        select, input[type=text], input[type=password], input[type=reset], input[type=button], input[type=email], button {padding: 5px;}
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
    <title><?= _e("[{$step->getNumber()}/{$stepRunner->getTotal()}]: {$step->getTitle()}") ?></title>
</head>

<body>

    <div id="wrapper">

        <h1>
            <span id="step">
                <span><?= $step->getNumber(), '/', $stepRunner->getTotal() ?></span>
                <?= _e($step->getTitle()) ?>
            </span>
            <span id="system-name">
                SunLight CMS <?= Core::VERSION ?>
            </span>
        </h1>

        <div id="content">
            <?= $content ?>

            <div class="cleaner"></div>
        </div>

    </div>

</body>
</html>
