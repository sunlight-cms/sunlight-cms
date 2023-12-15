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
    /** Name pattern */
    const NAME_PATTERN = '[a-zA-Z][\w.\-]+';
    /** Name of the plugin definition file */
    const FILE = 'plugin.json';

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

    /** @var PluginData */
    protected $data;
    /** @var PluginManager */
    protected $manager;
    /** @var ConfigurationFile|null */
    private $config;
    /** @var NamespacedCache|null */
    private $cache;

    function __construct(PluginData $data, PluginManager $manager)
    {
        $this->data = $data;
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

    function getType(): string
    {
        return $this->data->type;
    }

    function getId(): string
    {
        return $this->data->id;
    }

    function getName(): string
    {
        return $this->data->name;
    }

    function getCamelCasedName(): string
    {
        return $this->data->camelCasedName;
    }

    function getDirectory(): string
    {
        return $this->data->dir;
    }

    function getFile(): string
    {
        return $this->data->file;
    }

    function getWebPath(?array $routerOptions = null): string
    {
        return Router::path($this->data->webPath, $routerOptions);
    }

    function getAssetPath(string $path, ?array $routerOptions = null): string
    {
        return Router::path($this->data->webPath . '/' . $path, $routerOptions);
    }

    function getStatus(): string
    {
        return $this->data->status;
    }

    function hasStatus(string $status): bool
    {
        return $this->data->status === $status;
    }

    /**
     * @return bool|null null if the plugin has no installer
     */
    function isInstalled(): ?bool
    {
        return $this->data->installed;
    }

    /**
     * @throws \LogicException if the plugin has no installer
     */
    function getInstaller(): PluginInstaller
    {
        if ($this->data->options['installer'] === null) {
            throw new \LogicException('Plugin has no installer');
        }

        return require $this->data->options['installer'];
    }

    function isVendor(): bool
    {
        return $this->data->vendor;
    }

    function isEssential(): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    function getErrors(): array
    {
        return $this->data->errors;
    }

    /**
     * @throws \OutOfBoundsException if the option does not exist
     */
    function getOption(string $name)
    {
        if (!array_key_exists($name, $this->data->options)) {
            throw new \OutOfBoundsException(sprintf('Option "%s" does not exist', $name));
        }

        return $this->data->options[$name];
    }

    /**
     * @return mixed null if not defined
     */
    function getExtraOption(string $name)
    {
        return $this->data->options['extra'][$name] ?? null;
    }

    function getOptions(): array
    {
        return $this->data->options;
    }

    function hasConfig(): bool
    {
        return $this->data->options['config_defaults'] !== null;
    }

    function getConfig(): ConfigurationFile
    {
        if ($this->config === null) {
            $defaults = $this->data->options['config_defaults'];

            if ($defaults === null) {
                throw new \LogicException('To use the configuration file, defaults must be specified using the "config_defaults" option');
            }

            $this->config = $this->manager->getConfigStore()->getConfigFile($this->data->id, $defaults);
        }

        return $this->config;
    }

    function getAction(string $name): ?PluginAction
    {
        if ($this->hasStatus(self::STATUS_OK) && isset($this->data->options['actions'][$name])) {
            return new $this->data->options['actions'][$name]($this);
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
                foreach ($this->data->options['actions'] as $customName => $customClass) {
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
        return "plugin:{$this->data->id}";
    }
}
