<?php

namespace Sunlight\Plugin;

use Sunlight\Option\OptionSetNormalizerException;
use Sunlight\Util\Filesystem;
use Sunlight\Util\UrlHelper;

abstract class PluginOptionNormalizer
{
    /**
     * @param string|null $namespace
     * @param array       $context
     * @return string
     */
    static function normalizeNamespace(?string $namespace, array $context): string
    {
        return $namespace ?? $context['type']['default_base_namespace'] . '\\' . $context['plugin']['camel_id'];
    }

    /**
     * @param string|null $path
     * @param array       $context
     * @return string
     */
    static function normalizePath(?string $path, array $context): string
    {
        if ($path !== null) {
            return Filesystem::normalizeWithBasePath($context['plugin']['dir'], $path);
        }
    }

    /**
     * @param array $paths
     * @param array $context
     * @throws OptionSetNormalizerException
     * @return array
     */
    static function normalizePathArray(array $paths, array $context): array
    {
        $normalized = [];

        foreach ($paths as $key => $path) {
            if (!is_string($path)) {
                throw new OptionSetNormalizerException(sprintf('[%s] must be a string', $key));
            }

            $normalized[$key] = Filesystem::normalizeWithBasePath($context['plugin']['dir'], $path);
        }

        return $normalized;
    }

    /**
     * @param string|null $path
     * @param array       $context
     * @return string
     */
    static function normalizeWebPath(?string $path, array $context): string
    {
        if ($path !== null) {
            return $context['plugin']['web_path'] . '/' . $path;
        }
    }

    /**
     * @param array $paths
     * @param array $context
     * @throws OptionSetNormalizerException
     * @return array
     */
    static function normalizeWebPathArray(array $paths, array $context): array
    {
        $normalized = [];

        foreach ($paths as $key => $path) {
            if (!is_string($path)) {
                throw new OptionSetNormalizerException(sprintf('[%s] must be a string', $key));
            }

            $normalized[$key] = UrlHelper::isAbsolute($path)
                ? $path
                : $context['plugin']['web_path'] . '/' . $path;
        }

        return $normalized;
    }

    /**
     * @param array $autoload
     * @param array $context
     * @throws OptionSetNormalizerException
     * @return array
     */
    static function normalizeAutoload(array $autoload, array $context): array
    {
        $normalized = [];

        $validTypes = [
            'psr-0' => true,
            'psr-4' => true,
            'classmap' => true,
        ];

        foreach ($autoload as $type => $entries) {
            // check type
            if (!isset($validTypes[$type])) {
                throw new OptionSetNormalizerException(sprintf('[%s] is not a valid key', $type));
            }

            // check entries
            if (!is_array($entries)) {
                throw new OptionSetNormalizerException(sprintf('[%s] must be an array', $type));
            }

            $normalized[$type] = [];

            // iterate entires
            foreach ($entries as $key => $entry) {
                if (!is_string($key)) {
                    throw new OptionSetNormalizerException(sprintf('[%s][%s] is not a valid key (expected a string key)', $type, $key));
                }

                switch ($type) {
                    case 'psr-0':
                    case 'psr-4':
                        if (is_array($entry)) {
                            $normalizedEntry = [];
                            foreach ($entry as $pathKey => $path) {
                                if (!is_string($path)) {
                                    throw new OptionSetNormalizerException(sprintf('[%s][%s][%s] must be a string', $type, $key, $pathKey));
                                }

                                $normalizedEntry[] = Filesystem::normalizeWithBasePath($context['plugin']['dir'], $path);
                            }

                            $normalized[$type][$key] = $normalizedEntry;
                        } elseif (is_string($entry)) {
                            $normalized[$type][$key] = Filesystem::normalizeWithBasePath($context['plugin']['dir'], $entry);
                        } else {
                            throw new OptionSetNormalizerException(sprintf('[%s][%s] must be a string or an array of strings', $type, $key));
                        }
                        break;

                    case 'classmap':
                        if (!is_string($entry)) {
                            throw new OptionSetNormalizerException(sprintf('[%s][%s] must be a string', $type, $key));
                        }

                        $normalized[$type][$key] = Filesystem::normalizeWithBasePath($context['plugin']['dir'], $entry);
                        break;

                    default:
                        throw new \LogicException('Invalid type');
                }
            }
        }

        return $normalized;
    }

    /**
     * @param array $events
     * @throws OptionSetNormalizerException
     * @return array
     */
    static function normalizeEvents(array $events): array
    {
        $normalized = [];
        foreach ($events as $key => $entry) {
            if (!is_array($entry) || !isset($entry[0], $entry[1])) {
                throw new OptionSetNormalizerException(sprintf('[%s] invalid event entry (expected an array with 2 elements)', $key));
            }

            [$event, $callback] = $entry;

            // event
            if (!is_string($event)) {
                throw new OptionSetNormalizerException(sprintf('[%s] invalid event name (expected string)', $key));
            }

            // callback
            if (!is_callable($callback, true)) {
                throw new OptionSetNormalizerException(sprintf('[%s] invalid callback', $key));
            }
            if (is_array($callback) && $callback[0] === '$this') {
                $callback = $callback[1];
                $useThis = true;
            } elseif (
                ($doubleColonPos = strpos($callback, '::')) !== false
                && substr($callback, 0, $doubleColonPos) === '$this'
            ) {
                $callback = substr($callback, $doubleColonPos + 2);
                $useThis = true;
            } else {
                $useThis = false;
            }

            // priority
            if (isset($entry[2])) {
                if (!is_int($entry[2])) {
                    throw new OptionSetNormalizerException(sprintf('[%s] invalid priority (expected integer)', $key));
                }

                $priority = $entry[2];
            } else {
                $priority = 0;
            }

            $normalized[] = [
                'event' => $event,
                'use_this' => $useThis,
                'callback' => $callback,
                'priority' => $priority,
            ];
        }

        return $normalized;
    }

    /**
     * @param array $layouts
     * @param array $context
     * @return array
     */
    static function normalizeTemplateLayouts(array $layouts, array $context): array
    {
        $normalized = [];

        foreach ($layouts as $layout => $options) {
            if (!is_array($options)) {
                throw new OptionSetNormalizerException(sprintf('[%s] invalid entry (expected array)', $layout));
            }

            $entry = [];

            if (!preg_match('{[a-zA-Z0-9_.]+$}AD', $layout)) {
                throw new OptionSetNormalizerException(sprintf('[%s] the layout name is empty or contains invalid characters', $layout));
            }

            // template
            $entry['template'] = $options['template'] ?? 'template.php';

            if (!is_string($entry['template'])) {
                throw new OptionSetNormalizerException(sprintf('[%s][template] invalid value (expected string)', $layout));
            }

            $entry['template'] = Filesystem::normalizeWithBasePath($context['plugin']['dir'], $entry['template']);

            if (!is_file($entry['template'])) {
                throw new OptionSetNormalizerException(sprintf('[%s][template] the template file "%s" was not found', $layout, $entry['template']));
            }

            // slots
            $entry['slots'] = isset($options['slots']) ? array_values($options['slots']) : [];

            if (!is_array($entry['slots'])) {
                throw new OptionSetNormalizerException(sprintf('[%s][slots] invalid value (expected array)', $layout));
            }

            foreach ($entry['slots'] as $index => $slot) {
                if (!is_string($slot)) {
                    throw new OptionSetNormalizerException(sprintf('[%s][slots][%s] invalid value (expected string)', $layout, $index));
                }
                if (!preg_match('{[a-zA-Z0-9_.]+$}AD', $slot)) {
                    throw new OptionSetNormalizerException(sprintf('[%s][slots][%s] the slot name is empty or contains invalid characters', $layout, $index));
                }
            }

            $normalized[$layout] = $entry;
        }

        if (!isset($normalized[TemplatePlugin::DEFAULT_LAYOUT])) {
            throw new OptionSetNormalizerException(sprintf('the "%s" layout is missing', TemplatePlugin::DEFAULT_LAYOUT));
        }

        return $normalized;
    }
}
