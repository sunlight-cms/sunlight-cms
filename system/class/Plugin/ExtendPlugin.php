<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDirectory;

class ExtendPlugin extends Plugin
{
    /**
     * Initialize the plugin
     */
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
        if (_env === Core::ENV_WEB || _env === Core::ENV_ADMIN) {
            foreach ($this->options['events.' . _env] as $subscriber) {
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
            Core::$lang->registerSubDictionary($key, new LocalizationDirectory($dir));
        }

        // load scripts
        foreach ($this->options['scripts'] as $script) {
            $this->loadScript($script);
        }
        if (_env === Core::ENV_WEB || _env === Core::ENV_ADMIN) {
            foreach ($this->options['scripts.' . _env] as $script) {
                $this->loadScript($script);
            }
        }
    }

    /**
     * Load a script
     *
     * @param string $script
     */
    private function loadScript(string $script): void
    {
        include $script;
    }
}
