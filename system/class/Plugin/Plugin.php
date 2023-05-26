<?php

namespace Sunlight\Plugin;

use Kuria\Cache\NamespacedCache;
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
    const STATUS_OK = 'ok';
    /** Plugin status - error */
    const STATUS_ERROR = 'error';
    /** Plugin status - not installed */
    const STATUS_NEEDS_INSTALLATION = 'needs_install';
    /** Plugin status - disabled */
    const STATUS_DISABLED = 'disabled';
    /** Plugin status - unavailable */
    const STATUS_UNAVAILABLE = 'unavailable';

    /** @var string */
    protected $id;
    /** @var string */
    protected $name;
    /** @var string */
    protected $camelCasedName;
    /** @var string */
    protected $type;
    /** @var string */
    protected $status;
    /** @var bool|null */
    protected $installed;
    /** @var string */
    protected $dir;
    /** @var string */
    protected $file;
    /** @var string */
    protected $webPath;
    /** @var string[] */
    protected $errors;
    /** @var array */
    protected $options;
    /** @var PluginManager */
    protected $manager;
    /** @var ConfigurationFile|null */
    private $config;
    /** @var NamespacedCache|null */
    private $cache;

    function __construct(PluginData $data, PluginManager $manager)
    {
        $this->id = $data->id;
        $this->name = $data->name;
        $this->camelCasedName = $data->camelCasedName;
        $this->type = $data->type;
        $this->status = $data->status;
        $this->installed = $data->installed;
        $this->dir = $data->dir;
        $this->file = $data->file;
        $this->webPath = $data->webPath;
        $this->errors = $data->errors;
        $this->options = $data->options;
        $this->manager = $manager;
    }

    static function isLoaded(): bool
    {
        return Core::$pluginManager->getPlugins()->hasClass(static::class);
    }

    /**
     * @return static
     */
    static function getInstance(): self
    {
        $inst = Core::$pluginManager->getPlugins()->getByClass(static::class);

        if ($inst === null) {
            throw new \RuntimeException(sprintf('Could not get instance of plugin "%s". Plugin is unavailable, disabled or does not have a custom class.', static::class));
        }

        return $inst;
    }

    function getId(): string
    {
        return $this->id;
    }

    function getName(): string
    {
        return $this->name;
    }

    function getCamelCasedName(): string
    {
        return $this->camelCasedName;
    }

    function getType(): string
    {
        return $this->type;
    }

    function getStatus(): string
    {
        return $this->status;
    }

    function hasStatus(string $status): bool
    {
        return $this->status === $status;
    }

    function canBeDisabled(): bool
    {
        return !$this->hasStatus(self::STATUS_DISABLED);
    }

    /**
     * @return bool|null null if the plugin has no installer
     */
    function isInstalled(): ?bool
    {
        return $this->installed;
    }

    function hasInstaller(): bool
    {
        return $this->options['installer'] !== null;
    }

    /**
     * @throws \LogicException if the plugin has no installer
     */
    function getInstaller(): PluginInstaller
    {
        if (!$this->hasInstaller()) {
            throw new \LogicException('Plugin has no installer');
        }

        return require $this->options['installer'];
    }

    function canBeInstalled(): bool
    {
        return $this->hasInstaller() && $this->installed === false;
    }

    function canBeUninstalled(): bool
    {
        return $this->hasInstaller() && $this->installed === true;
    }

    function canBeRemoved(): bool
    {
        return !$this->hasInstaller() || $this->installed === false;
    }

    /**
     * @return string[]
     */
    function getErrors(): array
    {
        return $this->errors;
    }

    function getDirectory(): string
    {
        return $this->dir;
    }

    function getFile(): string
    {
        return $this->file;
    }

    function getWebPath(?array $routerOptions = null): string
    {
        return Router::path($this->webPath, $routerOptions);
    }

    function getAssetPath(string $path, ?array $routerOptions = null): string
    {
        return $this->getWebPath($routerOptions) . '/' . $path;
    }

    /**
     * @throws \OutOfBoundsException if the option does not exist
     */
    function getOption(string $name)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \OutOfBoundsException(sprintf('Option "%s" does not exist', $name));
        }

        return $this->options[$name];
    }

    /**
     * @return mixed null if not defined
     */
    function getExtraOption(string $name)
    {
        return $this->options['extra'][$name] ?? null;
    }

    function getOptions(): array
    {
        return $this->options;
    }

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

    function getConfigLabel(string $key): string
    {
        return $key;
    }

    protected function getConfigDefaults(): array
    {
        return [];
    }

    protected function getConfigPath(): string
    {
        return $this->dir . '/config.php';
    }

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

        return null;
    }

    /**
     * Get list of currently available actions
     *
     * @throws \RuntimeException if run outside of administration environment
     * @return string[] name => label
     */
    function getActionList(): array
    {
        if (Core::$env !== Core::ENV_ADMIN) {
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

        if ($this->hasStatus(self::STATUS_DISABLED)) {
            $actions['enable'] = _lang('admin.plugins.action.do.enable');
        }

        if ($this->canBeRemoved()) {
            $actions['remove'] = _lang('admin.plugins.action.do.remove');
        }

        return $actions;
    }

    /**
     * @return array<string, string> name => label
     */
    protected function getCustomActionList(): array
    {
        return [];
    }

    function getCache(): NamespacedCache
    {
        return $this->cache ?? ($this->cache = $this->manager->createCacheForPlugin($this));
    }
}
