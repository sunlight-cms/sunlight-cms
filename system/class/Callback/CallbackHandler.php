<?php

namespace Sunlight\Callback;

use Kuria\Options\Option;

abstract class CallbackHandler
{
    /** @var array<string, ScriptCallback> */
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
     * @param CallbackObjectInterface $object object to call methods on
     * @return callable
     */
    static function fromArray(array $definition, CallbackObjectInterface $object)
    {
        if (isset($definition['method'])) {
            return [$object, $definition['method']];
        }

        if (isset($definition['callback'])) {
            return $definition['callback'];
        }

        if (isset($definition['script'])) {
            return self::fromScript($definition['script'], $object);
        }

        throw new \InvalidArgumentException('Invalid callback definition');
    }

    /**
     * Get callback defined by the given PHP script
     */
    static function fromScript(string $script, ?CallbackObjectInterface $object = null): ScriptCallback
    {
        $fullPath = realpath($script);

        if ($fullPath === false) {
            throw new \InvalidArgumentException(sprintf('Script "%s" does not exist or is not accessible', $script));
        }

        if ($object !== null) {
            $cacheKey = $object->getCallbackCacheKey() . ':' . $fullPath;
        } else {
            $cacheKey = $fullPath;
        }

        return self::$scriptCache[$cacheKey] ?? (self::$scriptCache[$cacheKey] = new ScriptCallback($fullPath, $object));
    }
}
