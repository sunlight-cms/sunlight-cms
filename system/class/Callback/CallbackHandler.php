<?php

namespace Sunlight\Callback;

use Kuria\Options\Option;

/**
 * @psalm-type CallbackArray = array{
 *      method?: string|null,
 *      callback?: string|array|null,
 *      script?: string|null,
 *      middlewares?: array<array{
 *          method?: string|null,
 *          callback?: string|array|null,
 *          script?: string|null,
 *      }>|null,
 * }
 */
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

        if ($options === null) {
            $options = [
                Option::string('method')->default(null),
                Option::any('callback')->default(null),
                Option::string('script')->default(null),
            ];

            $options[] = Option::nodeList('middlewares', ...$options)
                ->default(null);
        }

        return $options;
    }

    /**
     * Create a callable from the given callback definition
     *
     * @param CallbackArray $definition
     * @param CallbackObjectInterface $object object to call methods on
     * @return callable
     */
    static function fromArray(array $definition, CallbackObjectInterface $object)
    {
        if (isset($definition['method'])) {
            $callable = [$object, $definition['method']];
        } elseif (isset($definition['callback'])) {
            $callable = $definition['callback'];
        } elseif (isset($definition['script'])) {
            $callable = self::fromScript($definition['script'], $object);
        } else {
            throw new \InvalidArgumentException('Invalid callback definition');
        }

        if (!empty($definition['middlewares'])) {
            $callable = new MiddlewareCallback(
                $callable,
                array_map(
                    function ($definition) use ($object) { return self::fromArray($definition, $object); },
                    $definition['middlewares']
                )
            );;
        }

        return $callable;
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
