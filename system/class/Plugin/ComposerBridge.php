<?php

namespace Sunlight\Plugin;

use Sunlight\Plugin\PluginLoader;
use Sunlight\Plugin\PluginManager;
use Sunlight\Util\Filesystem;
use Sunlight\Util\Json;

class ComposerBridge
{
    public static function linkPluginDependencies()
    {
        // init stuff necessary to use the plugin loader
        if (!defined('_root')) {
            define('_root', __DIR__ . '/../../../');
            require __DIR__ . '/../../functions.php';
        }

        // prepare plugin package cache
        $packageCachePath = _root . 'plugins/.composer';
        
        if (is_dir($packageCachePath)) {
            Filesystem::purgeDirectory($packageCachePath, array('keep_dir' => true));
        }

        // generate packages
        $rootRequirements = array();
        $pluginLoader = new PluginLoader(PluginManager::getTypeDefinitions());

        foreach (current($pluginLoader->load(false, false)) as $pluginType => $plugins) {
            foreach ($plugins as $pluginId => $plugin) {
                if ($plugin['status'] !== Plugin::STATUS_OK) {
                    continue;
                }

                if (empty($plugin['options']['requires.composer'])) {
                    continue;
                }

                $pluginComposerName = sprintf('sunlight-local-plugins/%s-%s', $pluginType, $pluginId);

                static::createDummyPackage(
                    sprintf('%s/%s.%s', $packageCachePath, $pluginType, $pluginId),
                    $pluginComposerName,
                    $plugin['options']['requires.composer']
                );

                $rootRequirements[$pluginComposerName] = 'dev-master';
            }
        }

        // generate root package
        static::createDummyPackage(
            $packageCachePath . '/all',
            'sunlight-local-plugins/root',
            $rootRequirements
        );

        // done
        echo 'Linked dependencies of ', sizeof($rootRequirements), " local plugins\n";
    }

    /**
     * @param string $path
     * @param string $name
     * @param array $requirements
     */
    protected static function createDummyPackage($path, $name, array $requirements)
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }

        file_put_contents(
            $path . '/composer.json',
            Json::encode(array(
                'name' => $name,
                'description' => 'THIS FILE IS GENERATED AUTOMATICALLY, DO NOT EDIT!',
                'require' => $requirements,
            ))
        );
    }
}
