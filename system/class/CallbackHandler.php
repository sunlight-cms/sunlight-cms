<?php

namespace Sunlight;

use Kuria\Options\Option;

abstract class CallbackHandler
{
    /** @var array<string, \Closure> */
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
     * Get closure defined by the given PHP script
     */
    static function fromScript(string $script): \Closure
    {
        $fullPath = realpath($script);

        if ($fullPath === false) {
            throw new \InvalidArgumentException(sprintf('Script "%s" does not exist or is not accessible', $script));
        }

        return self::$scriptCache[$fullPath] ?? (self::$scriptCache[$fullPath] = self::loadScript($fullPath));
    }

    /**
     * Get a lazy closure defined by the given PHP script
     *
     * The script is not loaded until the closure is run.
     */
    static function fromScriptLazy(string $script): \Closure
    {
        return function (...$args) use ($script) {
            return self::fromScript($script)(...$args);
        };
    }

    private static function loadScript(string $fullPath): \Closure
    {
        $closure = require $fullPath;

        if (!$closure instanceof \Closure) {
            throw new \UnexpectedValueException(sprintf('Script "%s" should return a closure, got %s', $fullPath, gettype($closure)));
        }

        return $closure;
    }
}
