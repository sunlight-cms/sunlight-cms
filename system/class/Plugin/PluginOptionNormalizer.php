<?php

namespace Sunlight\Plugin;

use Kuria\Options\Error\InvalidOptionError;
use Kuria\Options\Exception\NormalizerException;
use Kuria\Options\Node;
use Sunlight\Util\Filesystem;
use Sunlight\Util\UrlHelper;

abstract class PluginOptionNormalizer
{
    static function normalizePath(string $path, PluginData $plugin): ?string
    {
        return Filesystem::normalizeWithBasePath($plugin->dir, $path);
    }

    static function normalizePathArray(array $paths, PluginData $plugin): array
    {
        $normalized = [];

        foreach ($paths as $key => $path) {
            $normalized[$key] = self::normalizePath($path, $plugin);
        }

        return $normalized;
    }

    static function normalizeWebPath(string $path, PluginData $plugin): string
    {
        return UrlHelper::isAbsolute($path)
            ? $path
            : $plugin->webPath . '/' . $path;
    }

    static function normalizeWebPathArray(array $paths, PluginData $plugin): array
    {
        $normalized = [];

        foreach ($paths as $key => $path) {
            $normalized[$key] = self::normalizeWebPath($path, $plugin);
        }

        return $normalized;
    }

    static function normalizeAutoload(Node $autoload, PluginData $plugin): array
    {
        foreach ($autoload as $type => $entries) {
            $normalized[$type] = [];

            // iterate entires
            foreach ($entries as $key => $entry) {
                if (!is_string($key)) {
                    self::fail('[%s][%s] is not a valid key (expected a string key)', $type, $key);
                }

                switch ($type) {
                    case 'psr-0':
                    case 'psr-4':
                        if (is_array($entry)) {
                            $normalizedEntry = [];
                            foreach ($entry as $pathKey => $path) {
                                if (!is_string($path)) {
                                    self::fail('[%s][%s][%s] must be a string', $type, $key, $pathKey);
                                }

                                $normalizedEntry[] = Filesystem::normalizeWithBasePath($plugin->dir, $path);
                            }

                            $normalized[$type][$key] = $normalizedEntry;
                        } elseif (is_string($entry)) {
                            $normalized[$type][$key] = Filesystem::normalizeWithBasePath($plugin->dir, $entry);
                        } else {
                            self::fail('[%s][%s] must be a string or an array of strings', $type, $key);
                        }
                        break;

                    case 'classmap':
                        $normalized[$type][$key] = Filesystem::normalizeWithBasePath($plugin->dir, $entry);
                        break;

                    default:
                        throw new \LogicException('Invalid type');
                }
            }
        }

        return $normalized;
    }

    static function normalizeTemplateLayouts(array $layouts): array
    {
        foreach ($layouts as $layout => $options) {
            if (!is_string($layout)) {
                self::fail('[%s] the layout name must be a string', $layout);
            }

            if (!preg_match('{[a-zA-Z0-9_.]+$}AD', $layout)) {
                self::fail('[%s] the layout name is empty or contains invalid characters', $layout);
            }

            if (!is_file($options['template'])) {
                self::fail('[%s][template] the template file "%s" was not found', $layout, $options['template']);
            }

            // slots
            foreach ($options['slots'] as $index => $slot) {
                if (!preg_match('{[a-zA-Z0-9_.]+$}AD', $slot)) {
                    self::fail('[%s][slots][%s] the slot name is empty or contains invalid characters', $layout, $index);
                }
            }
        }

        if (!isset($layouts[TemplatePlugin::DEFAULT_LAYOUT])) {
            self::fail('the "%s" layout is missing', TemplatePlugin::DEFAULT_LAYOUT);
        }

        return $layouts;
    }

    private static function fail(string $message, ...$args): void
    {
        throw new NormalizerException('', [new InvalidOptionError(vsprintf($message, $args))]);
    }
}
