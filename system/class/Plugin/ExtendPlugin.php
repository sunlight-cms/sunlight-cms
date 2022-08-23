<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Hcm;
use Sunlight\Localization\LocalizationDirectory;

class ExtendPlugin extends Plugin implements InitializableInterface
{
    function initialize(): void
    {
        // register events
        foreach ($this->options['events'] as $subscriber) {
            Extend::reg(
                $subscriber['event'],
                $subscriber['method'] !== null
                    ? [$this, $subscriber['method']]
                    : $subscriber['callback'],
                $subscriber['priority']
            );
        }
        if (Core::$env === Core::ENV_WEB || Core::$env === Core::ENV_ADMIN) {
            foreach ($this->options['events.' . Core::$env] as $subscriber) {
                Extend::reg(
                    $subscriber['event'],
                    $subscriber['method'] !== null
                        ? [$this, $subscriber['method']]
                        : $subscriber['callback'],
                    $subscriber['priority']
                );
            }
        }

        // register language packs
        foreach ($this->options['langs'] as $key => $dir) {
            Core::$dictionary->registerSubDictionary($key, new LocalizationDirectory($dir));
        }

        // register HCM modules
        foreach ($this->options['hcm'] as $name => $script) {
            Hcm::register((string) $name, $script);
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
                    $route['method'] !== null
                        ? [$this, $route['method']]
                        : $route['callback']
                );
            }
        }
    }

    private function loadScript(string $script): void
    {
        include $script;
    }
}
