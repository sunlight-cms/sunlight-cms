<?php

namespace Sunlight\Plugin;

use Sunlight\Option\OptionSetNormalizerException;
use Sunlight\Util\Filesystem;

class PluginOptionNormalizer
{
    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * @param string|null $namespace
     * @param array       $context
     * @return string
     */
    public static function normalizeNamespace($namespace, array $context)
    {
        if (null === $namespace) {
            return $context['type']['default_namespace'] . '\\' . $context['plugin']['camel_name'];
        } else {
            return $namespace;
        }
    }

    /**
     * @param string|null $path
     * @param array       $context
     * @return string
     */
    public static function normalizePath($path, array $context)
    {
        if (null !== $path) {
            return Filesystem::normalizePath($context['plugin']['dir'], $path);
        }
    }

    /**
     * @param array $paths
     * @param array $context
     * @throws OptionSetNormalizerException
     * @return array
     */
    public static function normalizePathArray(array $paths, array $context)
    {
        $normalized = array();

        foreach ($paths as $key => $path) {
            if (!is_string($path)) {
                throw new OptionSetNormalizerException(sprintf('[%s] must be a string', $key));
            }

            $normalized[$key] = Filesystem::normalizePath($context['plugin']['dir'], $path);
        }

        return $normalized;
    }

    /**
     * @param string|null $path
     * @param array       $context
     * @return string
     */
    public static function normalizeWebPath($path, array $context)
    {
        if (null !== $path) {
            return $context['plugin']['web_path'] . '/' . $path;
        }
    }

    /**
     * @param array $paths
     * @param array $context
     * @throws OptionSetNormalizerException
     * @return array
     */
    public static function normalizeWebPathArray(array $paths, array $context)
    {
        $normalized = array();

        foreach ($paths as $key => $path) {
            if (!is_string($path)) {
                throw new OptionSetNormalizerException(sprintf('[%s] must be a string', $key));
            }

            $normalized[$key] = $context['plugin']['web_path'] . '/' . $path;
        }

        return $normalized;
    }

    /**
     * @param array $autoload
     * @param array $context
     * @throws OptionSetNormalizerException
     * @return array
     */
    public static function normalizeAutoload(array $autoload, array $context)
    {
        $normalized = array();

        $validTypes = array(
            'psr-0' => Plugin::AUTOLOAD_PSR0,
            'psr-4' => Plugin::AUTOLOAD_PSR4,
            'classmap' => Plugin::AUTOLOAD_CLASSMAP,
        );

        foreach ($autoload as $typeName => $entries) {
            // check type
            if (!isset($validTypes[$typeName])) {
                throw new OptionSetNormalizerException(sprintf('[%s] is not a valid key', $typeName));
            }

            $type = $validTypes[$typeName];

            // check entries
            if (!is_array($entries)) {
                throw new OptionSetNormalizerException(sprintf('[%s] must be an array', $typeName));
            }

            $normalized[$type] = array();

            // iterate entires
            foreach ($entries as $key => $entry) {
                if (!is_string($key)) {
                    throw new OptionSetNormalizerException(sprintf('[%s][%s] is not a valid key (expected a string key)', $typeName, $key));
                }

                switch ($type) {
                    case Plugin::AUTOLOAD_PSR0:
                    case Plugin::AUTOLOAD_PSR4:
                        if (is_array($entry)) {
                            $normalizedEntry = array();
                            foreach ($entry as $pathKey => $path) {
                                if (!is_string($path)) {
                                    throw new OptionSetNormalizerException(sprintf('[%s][%s][%s] must be a string', $typeName, $key, $pathKey));
                                }

                                $normalizedEntry[] = Filesystem::normalizePath($context['plugin']['dir'], $path);
                            }

                            $normalized[$type][$key] = $normalizedEntry;
                        } elseif (is_string($entry)) {
                            $normalized[$type][$key] = Filesystem::normalizePath($context['plugin']['dir'], $entry);
                        } else {

                        }
                        break;

                    case Plugin::AUTOLOAD_CLASSMAP:
                        if (!is_string($entry)) {
                            throw new OptionSetNormalizerException(sprintf('[%s][%s] must be a string', $typeName, $key));
                        }

                        $normalized[$type][$key] = Filesystem::normalizePath($context['plugin']['dir'], $entry);
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
    public static function normalizeEvents(array $events)
    {
        $normalized = array();
        foreach ($events as $key => $entry) {
            if (!is_array($entry) || !isset($entry[0], $entry[1])) {
                throw new OptionSetNormalizerException(sprintf('[%s] is invalid', $key));
            }

            list($event, $callback) = $entry;

            // event
            if (!is_string($event)) {
                throw new OptionSetNormalizerException(sprintf('[%s] invalid event name (expected string)', $key));
            }

            // callback
            if (!is_callable($callback, true)) {
                throw new OptionSetNormalizerException(sprintf('[%s] invalid callback', $key));
            }
            if (is_array($callback) && '$this' === $callback[0]) {
                $callback = $callback[1];
                $useThis = true;
            } elseif (
                false !== ($doubleColonPos = strpos($callback, '::'))
                && '$this' === substr($callback, 0, $doubleColonPos)
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

            $normalized[] = array(
                'event' => $event,
                'use_this' => $useThis,
                'callback' => $callback,
                'priority' => $priority,
            );
        }

        return $normalized;
    }

    /**
     * @param array $layouts
     * @param array $context
     * @return array
     */
    public static function normalizeTemplateLayouts(array $layouts, array $context)
    {
        $normalized = array();

        foreach ($layouts as $layout => $options) {
            if (!is_array($options)) {
                throw new OptionSetNormalizerException(sprintf('[%s] invalid entry (expected array)', $layout));
            }

            $entry = array();

            if (!preg_match('/^[a-zA-Z0-9_.]+$/', $layout)) {
                throw new OptionSetNormalizerException(sprintf('[%s] the layout name is empty or contains invalid characters', $layout));
            }

            // template
            $entry['template'] = isset($options['template']) ? $options['template'] : 'template.php';

            if (!is_string($entry['template'])) {
                throw new OptionSetNormalizerException(sprintf('[%s][template] invalid value (expected string)', $layout));
            }

            $entry['template'] = Filesystem::normalizePath($context['plugin']['dir'], $entry['template']);

            if (!is_file($entry['template'])) {
                throw new OptionSetNormalizerException(sprintf('[%s][template] the template file "%s" was not found', $layout, $entry['template']));
            }

            // slots
            $entry['slots'] = isset($options['slots']) ? array_values($options['slots']) : array();

            if (!is_array($entry['slots'])) {
                throw new OptionSetNormalizerException(sprintf('[%s][slots] invalid value (expected array)', $layout));
            }

            foreach ($entry['slots'] as $index => $slot) {
                if (!is_string($slot)) {
                    throw new OptionSetNormalizerException(sprintf('[%s][slots][%s] invalid value (expected string)', $layout, $index));
                }
                if (!preg_match('/^[a-zA-Z0-9_.]+$/', $slot)) {
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
