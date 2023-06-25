<?php

namespace Sunlight\Plugin;

use Sunlight\Callback\CallbackHandler;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDirectory;

class ExtendPlugin extends Plugin implements InitializableInterface
{
    /** @var array|null */
    private $enabledEventGroups;

    function initialize(): void
    {
        // register events
        foreach ($this->options['events'] as $subscriber) {
            if ($subscriber['group'] === null) {
                Extend::reg(
                    $subscriber['event'],
                    CallbackHandler::fromArray($subscriber, $this),
                    $subscriber['priority']
                );
            }
        }

        if (Core::$env === Core::ENV_WEB || Core::$env === Core::ENV_ADMIN) {
            foreach ($this->options['events.' . Core::$env] as $subscriber) {
                if ($subscriber['group'] === null) {
                    Extend::reg(
                        $subscriber['event'],
                        CallbackHandler::fromArray($subscriber, $this),
                        $subscriber['priority']
                    );
                }
            }
        }

        // register language packs
        foreach ($this->options['langs'] as $key => $dir) {
            Core::$dictionary->registerSubDictionary($key, new LocalizationDirectory($dir));
        }

        // register HCM modules
        foreach ($this->options['hcm'] as $name => $definition) {
            Extend::reg("hcm.run.{$name}", new PluginHcmHandler(CallbackHandler::fromArray($definition, $this)));
        }

        // register cron tasks
        if (!empty($this->options['cron'])) {
            Extend::reg('cron.init', function (array $args) {
                foreach ($this->options['cron'] as $name => $definition) {
                    $args['tasks']["{$this->getId()}.{$name}"] = [
                        'interval' => $definition['interval'],
                        'callback' => CallbackHandler::fromArray($definition, $this),
                    ];
                }
            });
        }

        // load scripts
        foreach ($this->options['scripts'] as $script) {
            $this->loadScript($script);
        }

        if (Core::$env === Core::ENV_WEB || Core::$env === Core::ENV_ADMIN) {
            foreach ($this->options['scripts.' . Core::$env] as $script) {
                $this->loadScript($script);
            }
        }

        // register routes
        if (Core::$env === Core::ENV_WEB) {
            foreach ($this->options['routes'] as $route) {
                PluginRouter::register(
                    $route['pattern'],
                    CallbackHandler::fromArray($route, $this)
                );
            }
        }
    }

    /**
     * Enable a specific event group
     *
     * Can be called multiple times - subsequent calls will be no-op.
     */
    function enableEventGroup(string $group): void
    {
        if (!isset($this->enabledEventGroups[$group])) {
            $this->enabledEventGroups[$group] = true;

            foreach ($this->options['events'] as $subscriber) {
                if ($subscriber['group'] === $group) {
                    Extend::reg(
                        $subscriber['event'],
                        CallbackHandler::fromArray($subscriber, $this),
                        $subscriber['priority']
                    );
                }
            }

            if (Core::$env === Core::ENV_WEB || Core::$env === Core::ENV_ADMIN) {
                foreach ($this->options['events.' . Core::$env] as $subscriber) {
                    if ($subscriber['group'] === $group) {
                        Extend::reg(
                            $subscriber['event'],
                            CallbackHandler::fromArray($subscriber, $this),
                            $subscriber['priority']
                        );
                    }
                }
            }
        }
    }

    private function loadScript(string $script): void
    {
        include $script;
    }
}
