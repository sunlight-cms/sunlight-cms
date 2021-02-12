<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Localization\LocalizationDirectory;

class ExtendPlugin extends Plugin
{
    protected static $typeDefinition = [
        'type' => 'extend',
        'dir' => 'plugins/extend',
        'class' => __CLASS__,
        'default_base_namespace' => 'SunlightExtend',
        'options' => [
            'events' => ['type' => 'array', 'default' => [], 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizeEvents']],
            'events.web' => ['type' => 'array', 'default' => [], 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizeEvents']],
            'events.admin' => ['type' => 'array', 'default' => [], 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizeEvents']],
            'scripts' => ['type' => 'array', 'default' => [], 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizePathArray']],
            'scripts.web' => ['type' => 'array', 'default' => [], 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizePathArray']],
            'scripts.admin' => ['type' => 'array', 'default' => [], 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizePathArray']],
            'langs' => ['type' => 'array', 'default' => [], 'normalizer' => ['Sunlight\Plugin\PluginOptionNormalizer', 'normalizePathArray']],
        ],
    ];

    /**
     * Initialize the plugin
     */
    function initialize(): void
    {
        // register events
        foreach ($this->options['events'] as $subscriber) {
            Extend::reg(
                $subscriber['event'],
                $subscriber['use_this']
                    ? [$this, $subscriber['callback']]
                    : $subscriber['callback'],
                $subscriber['priority']
            );
        }
        if (_env === Core::ENV_WEB || _env === Core::ENV_ADMIN) {
            foreach ($this->options['events.' . _env] as $subscriber) {
                Extend::reg(
                    $subscriber['event'],
                    $subscriber['use_this']
                        ? [$this, $subscriber['callback']]
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
    protected function loadScript(string $script): void
    {
        include $script;
    }
}
