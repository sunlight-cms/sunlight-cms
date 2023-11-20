<?php

namespace Sunlight\Plugin;

use Kuria\Cache\NamespacedCache;
use Sunlight\Callback\CallbackObjectInterface;
use Sunlight\Core;
use Sunlight\Plugin\Action\PluginAction;
use Sunlight\Router;
use Sunlight\Util\ConfigurationFile;

abstract class Plugin implements CallbackObjectInterface
{
    /** ID pattern */
    const ID_PATTERN = '[a-zA-Z][a-zA-Z0-9_.\-]+';
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

    private const DEFAULT_ACTIONS = [
        'info' => Action\InfoAction::class,
        'config' => Action\ConfigAction::class,
        'install' => Action\InstallAction::class,
        'uninstall' => Action\UninstallAction::class,
        'enable' => Action\EnableAction::class,
        'disable' => Action\DisableAction::class,
        'remove' => Action\RemoveAction::class,
    ];

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

    function isEssential(): bool
    {
        return false;
    }

    /**
     * @return bool|null null if the plugin has no installer
     */
    function isInstalled(): ?bool
    {
        return $this->installed;
    }

    /**
     * @throws \LogicException if the plugin has no installer
     */
    function getInstaller(): PluginInstaller
    {
        if ($this->options['installer'] === null) {
            throw new \LogicException('Plugin has no installer');
        }

        return require $this->options['installer'];
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
        return Router::path($this->webPath . '/' . $path, $routerOptions);
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

    function hasConfig(): bool
    {
        return !empty($this->options['config_defaults']);
    }

    function getConfig(): ConfigurationFile
    {
        if ($this->config === null) {
            $defaults = $this->options['config_defaults'];

            if (empty($defaults)) {
                throw new \LogicException('To use the configuration file, defaults must be specified using the "config_defaults" option');
            }

            $this->config = new ConfigurationFile($this->getConfigPath(), $defaults);
        }

        return $this->config;
    }

    protected function getConfigPath(): string
    {
        return $this->dir . '/config.php';
    }

    function getAction(string $name): ?PluginAction
    {
        if ($this->hasStatus(self::STATUS_OK) && isset($this->options['actions'][$name])) {
            return new $this->options['actions'][$name]($this);
        }

        if (isset(self::DEFAULT_ACTIONS[$name])) {
            $actionClass = self::DEFAULT_ACTIONS[$name];

            return new $actionClass($this);
        }

        return null;
    }

    /**
     * @return array<string, PluginAction> name => action
     */
    function getActions(): array
    {
        $actions = [];

        foreach (self::DEFAULT_ACTIONS as $name => $class) {
            $actions[$name] = $this->getAction($name);

            // append custom actions after config action
            if ($name === 'config' && $this->hasStatus(self::STATUS_OK)) {
                foreach ($this->options['actions'] as $customName => $customClass) {
                    // don't add default action overrides (to keep the order)
                    if (!isset(self::DEFAULT_ACTIONS[$customName])) {
                        $actions[$customName] = $this->getAction($customName);
                    }
                }
            }
        }

        return $actions;
    }

    function getCache(): NamespacedCache
    {
        return $this->cache ?? ($this->cache = $this->manager->createCacheForPlugin($this));
    }

    function getCallbackCacheKey(): string
    {
        return "plugin:{$this->id}";
    }
}
