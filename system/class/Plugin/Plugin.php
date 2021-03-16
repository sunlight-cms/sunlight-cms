<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Plugin\Action\PluginAction;
use Sunlight\Router;
use Sunlight\Util\ConfigurationFile;

abstract class Plugin
{
    /** ID pattern */
    const ID_PATTERN = '[a-zA-Z][a-zA-Z0-9_.\-]*';
    /** Name of the plugin definition file */
    const FILE = 'plugin.json';
    /** Name of the plugin deactivating file */
    const DEACTIVATING_FILE = 'DISABLED';

    /** Plugin status - OK */
    const STATUS_OK = 0;
    /** Plugin status - has error messages */
    const STATUS_HAS_ERRORS = 1;
    /** Plugin status - not installed */
    const STATUS_NEEDS_INSTALLATION = 2;
    /** Plugin status - disabled */
    const STATUS_DISABLED = 3;

    /** Plugin type definition - defined by children */
    const TYPE_DEFINITION = [];

    /** @var array */
    static $commonOptions = [
        'name' => ['type' => 'string', 'required' => true],
        'description' => ['type' => 'string'],
        'author' => ['type' => 'string'],
        'url' => ['type' => 'string'],
        'version' => ['type' => 'string', 'required' => true],
        'api' => ['type' => 'string', 'required' => true],
        'php' => ['type' => 'string'],
        'extensions' => ['type' => 'array', 'default' => []],
        'requires' => ['type' => 'array', 'default' => []],
        'installer' => ['type' => 'boolean', 'nullable' => true, 'default' => false],
        'autoload' => ['type' => 'array', 'default' => [], 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizeAutoload']],
        'debug' => ['type' => 'boolean', 'nullable' => true],
        'class' => ['type' => 'string'],
        'namespace' => ['type' => 'string', 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizeNamespace']],
        'inject_composer' => ['type' => 'boolean', 'default' => true],
    ];

    /** @var string */
    protected $type;
    /** @var string */
    protected $id;
    /** @var string */
    protected $camelId;
    /** @var int */
    protected $status;
    /** @var bool|null */
    protected $installed;
    /** @var string[] */
    protected $errors;
    /** @var string[] */
    protected $definitionErrors;
    /** @var string */
    protected $dir;
    /** @var string */
    protected $file;
    /** @var string */
    protected $webPath;
    /** @var array */
    protected $options;
    /** @var PluginManager */
    protected $manager;
    /** @var ConfigurationFile|null */
    private $config;

    /**
     * @param array         $data
     * @param PluginManager $manager
     */
    function __construct(array $data, PluginManager $manager)
    {
        $this->type = $data['type'];
        $this->id = $data['id'];
        $this->camelId = $data['camel_id'];
        $this->status = $data['status'];
        $this->installed = $data['installed'];
        $this->errors = $data['errors'];
        $this->definitionErrors = $data['definition_errors'];
        $this->dir = $data['dir'];
        $this->file = $data['file'];
        $this->webPath = $data['web_path'];
        $this->options = $data['options'];
        $this->manager = $manager;
    }

    /**
     * See if this plugin is currently active
     *
     * @return bool
     */
    static function isActive(): bool
    {
        return Core::$pluginManager->hasInstance(get_called_class());
    }

    /**
     * Get plugin instance
     *
     * @throws \OutOfBoundsException if the plugin is not currently active
     */
    static function getInstance(): self
    {
        return Core::$pluginManager->getInstance(get_called_class());
    }

    /**
     * Get plugin identifier
     *
     * @return string
     */
    function getId(): string
    {
        return $this->id;
    }

    /**
     * Get camel cased plugin identifier
     *
     * @return string
     */
    function getCamelId(): string
    {
        return $this->camelId;
    }

    /**
     * @return string
     */
    function getType(): string
    {
        return $this->type;
    }

    /**
     * @return int
     */
    function getStatus(): int
    {
        return $this->status;
    }

    /**
     * See if the plugin is disabled
     *
     * @return bool
     */
    function isDisabled(): bool
    {
        return self::STATUS_DISABLED === $this->status;
    }

    /**
     * See if the plugin can be disabled
     *
     * @return bool
     */
    function canBeDisabled(): bool
    {
        return !$this->isDisabled();
    }

    /**
     * See if the plugin has been installed
     *
     * @return bool|null null if the plugin has no installer
     */
    function isInstalled(): ?bool
    {
        return $this->installed;
    }

    /**
     * See if the plugin has an installer
     *
     * @return bool
     */
    function hasInstaller(): bool
    {
        return $this->options['installer'];
    }

    /**
     * Get installer for this plugin
     *
     * @throws \LogicException if the plugin has no installer
     * @return PluginInstaller
     */
    function getInstaller(): PluginInstaller
    {
        if (!$this->hasInstaller()) {
            throw new \LogicException('Plugin has no installer');
        }

        return PluginInstaller::load($this->dir, $this->options['namespace'], $this->camelId);
    }

    /**
     * See if the plugin needs installation be activated
     *
     * @return bool
     */
    function needsInstallation(): bool
    {
        return self::STATUS_NEEDS_INSTALLATION === $this->status;
    }

    /**
     * See if the plugin can be installed
     *
     * @return bool
     */
    function canBeInstalled(): bool
    {
        return $this->hasInstaller() && $this->installed === false;
    }

    /**
     * See if the plugin can be uninstalled
     *
     * @return bool
     */
    function canBeUninstalled(): bool
    {
        return $this->hasInstaller() && $this->installed === true;
    }

    /**
     * See if the plugin can be removed
     *
     * @return bool
     */
    function canBeRemoved(): bool
    {
        return !$this->hasInstaller() || $this->installed === false;
    }

    /**
     * See if the plugin has errors
     *
     * @return bool
     */
    function hasErrors(): bool
    {
        return self::STATUS_HAS_ERRORS === $this->status;
    }

    /**
     * @return string[]
     */
    function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string[]
     */
    function getDefinitionErrors(): array
    {
        return $this->definitionErrors;
    }

    /**
     * @return string
     */
    function getDirectory(): string
    {
        return $this->dir;
    }

    /**
     * @return string
     */
    function getFile(): string
    {
        return $this->file;
    }

    /**
     * @param bool $absolute
     * @return string
     */
    function getWebPath(bool $absolute = false): string
    {
        return Router::generate($this->webPath, $absolute);
    }

    /**
     * @param string $name
     * @throws \OutOfBoundsException if the option does not exist
     * @return mixed
     */
    function getOption(string $name)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \OutOfBoundsException(sprintf('Option "%s" does not exist', $name));
        }

        return $this->options[$name];
    }

    /**
     * @return array
     */
    function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get plugin configuration
     *
     * @return ConfigurationFile
     */
    function getConfig(): ConfigurationFile
    {
        if ($this->config === null) {
            $defaults = $this->getConfigDefaults();

            if (empty($defaults)) {
                throw new \LogicException('To use the configuration file, defaults must be specified by overriding the getConfigDefaults() method');
            }

            $this->config = new ConfigurationFile($this->getConfigPath(), $defaults);
        }

        return $this->config;
    }

    /**
     * @param string $key
     * @return string
     */
    function getConfigLabel(string $key): string
    {
        return $key;
    }

    /**
     * @return array
     */
    protected function getConfigDefaults(): array
    {
        return [];
    }

    /**
     * @return string
     */
    protected function getConfigPath(): string
    {
        return $this->dir . '/config.php';
    }

    /**
     * @param string $name
     * @return PluginAction|null
     */
    function getAction(string $name): ?PluginAction
    {
        switch ($name) {
            case 'info':
                return new Action\InfoAction($this);
            case 'config':
                return new Action\ConfigAction($this);
            case 'install':
                return new Action\InstallAction($this);
            case 'uninstall':
                return new Action\UninstallAction($this);
            case 'disable':
                return new Action\DisableAction($this);
            case 'enable':
                return new Action\EnableAction($this);
            case 'remove':
                return new Action\RemoveAction($this);
        }
    }

    /**
     * Get list of currently available actions
     *
     * @throws \RuntimeException if run outside of administration environment
     * @return string[] name => label
     */
    function getActionList(): array
    {
        if (_env !== Core::ENV_ADMIN) {
            throw new \RuntimeException('Plugin actions require administration environment');
        }

        $actions = [];

        $actions['info'] = _lang('admin.plugins.action.do.info');
        $actions += $this->getCustomActionList();
        if (count($this->getConfigDefaults())) {
            $actions['config'] = _lang('admin.plugins.action.do.config');
        }
        if ($this->canBeInstalled()) {
            $actions['install'] = _lang('admin.plugins.action.do.install');
        }
        if ($this->canBeUninstalled()) {
            $actions['uninstall'] = _lang('admin.plugins.action.do.uninstall');
        }
        if ($this->canBeDisabled()) {
            $actions['disable'] = _lang('admin.plugins.action.do.disable');
        }
        if ($this->isDisabled()) {
            $actions['enable'] = _lang('admin.plugins.action.do.enable');
        }
        if ($this->canBeRemoved()) {
            $actions['remove'] = _lang('admin.plugins.action.do.remove');
        }

        return $actions;
    }

    /**
     * Get list of custom actions
     *
     * @return string[] name => label
     */
    protected function getCustomActionList(): array
    {
        return [];
    }
}
