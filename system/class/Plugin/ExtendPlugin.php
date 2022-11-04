<?php

namespace Sunlight\Plugin;

use Sunlight\CallbackHandler;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDirectory;

class ExtendPlugin extends Plugin implements InitializableInterface
{
    function initialize(): void
    {
        // register events
        foreach ($this->options['events'] as $subscriber) {
            Extend::reg(
                $subscriber['event'],
                CallbackHandler::fromArray($subscriber, $this),
                $subscriber['priority']
            );
        }

        if (Core::$env === Core::ENV_WEB || Core::$env === Core::ENV_ADMIN) {
            foreach ($this->options['events.' . Core::$env] as $subscriber) {
                Extend::reg(
                    $subscriber['event'],
                    CallbackHandler::fromArray($subscriber, $this),
                    $subscriber['priority']
                );
            }
        }

        // register language packs
        foreach ($this->options['langs'] as $key => $dir) {
            Core::$dictionary->registerSubDictionary($key, new LocalizationDirectory($dir));
        }

        // register HCM modules
        foreach ($this->options['hcm'] as $name => $definition) {
            Extend::reg("hcm.run.{$name}", function (array $args) use ($definition) {
                $args['output'] = (string) CallbackHandler::fromArray($definition, $this)(...$args['arg_list']);
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

    private function loadScript(string $script): void
    {
        include $script;
    }
}
