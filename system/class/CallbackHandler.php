<?php

namespace Sunlight;

use Kuria\Options\Option;

abstract class CallbackHandler
{
    /** @var array<string, callable> */
    private static $scriptCache = [];

    /**
     * Get callback definition options (read-only)
     *
     * @return Option\OptionDefinition[]
     */
    static function getDefinitionOptions(): array
    {
        static $options;

        return $options ?? ($options = [
            Option::string('method')->default(null),
            Option::any('callback')->default(null),
            Option::string('script')->default(null),
        ]);
    }

    /**
     * Create a callable from the given callback definition
     *
     * @param array{method?: string, callback?: string|array, script?: string} $definition
     * @param object $object object to call methods on
     * @return callable
     */
    static function fromArray(array $definition, $object)
    {
        if (isset($definition['method'])) {
            return [$object, $definition['method']];
        }

        if (isset($definition['callback'])) {
            return $definition['callback'];
        }

        if (isset($definition['script'])) {
            return self::fromScriptLazy($definition['script']);
        }

        throw new \InvalidArgumentException('Invalid callback definition');
    }

    /**
     * Get callback defined by the given PHP script
     *
     * @return callable
     */
    static function fromScript(string $script)
    {
        $fullPath = realpath($script);

        if ($fullPath === false) {
            throw new \InvalidArgumentException(sprintf('Script "%s" does not exist or is not accessible', $script));
        }

        return self::$scriptCache[$fullPath] ?? (self::$scriptCache[$fullPath] = self::loadScript($fullPath));
    }

    /**
     * Get a lazy callable defined by the given PHP script
     *
     * The script is not loaded until the closure is run.
     */
    static function fromScriptLazy(string $script): \Closure
    {
        return function (...$args) use ($script) {
            return self::fromScript($script)(...$args);
        };
    }

    /**
     * @return callable
     */
    private static function loadScript(string $fullPath)
    {
        $callback = require $fullPath;

        if (Core::$debug && !is_callable($callback)) {
            throw new \UnexpectedValueException(sprintf('Script "%s" should return a callable, got %s', $fullPath, gettype($callback)));
        }

        return $callback;
    }
}
