<?php

namespace Sunlight;

use Kuria\Event\EventEmitterInterface;

/**
 * Extend event system
 */
abstract class Extend
{
    /**
     * Register callback for an event
     *
     * @param string   $event
     * @param callable $callback
     * @param int      $priority
     */
    static function reg(string $event, callable $callback, int $priority = 0): void
    {
        Core::$eventEmitter->on($event, $callback, $priority);
    }

    /**
     * Register multiple callbacks
     *
     * @param array $callbacks array(event1 => callback1, ...)
     * @param int   $priority
     */
    static function regm(array $callbacks, int $priority = 0): void
    {
        foreach ($callbacks as $event => $callback) {
            Core::$eventEmitter->on($event, $callback, $priority);
        }
    }

    /**
     * Register a global callback
     *
     * @param callable $callback callback(event, args)
     * @param int      $priority
     */
    static function regGlobal(callable $callback, int $priority = 0): void
    {
        Core::$eventEmitter->on(EventEmitterInterface::ANY_EVENT, $callback, $priority);
    }

    /**
     * Create normalized event arguments
     *
     * @param string     &$output output variable reference
     * @param array|null $args    array with additional arguments
     * @return array
     */
    static function args(string &$output, array $args = []): array
    {
        $args['output'] = &$output;

        return $args;
    }

    /**
     * Trigger an event
     *
     * @param string $event
     * @param array  $args
     */
    static function call(string $event, array $args = []): void
    {
        Core::$eventEmitter->emit($event, $args);
    }

    /**
     * Trigger an event and fetch a value
     *
     * @param string $event
     * @param array  $args  ('value' is added automatically)
     * @param mixed  $value initial value
     * @return mixed
     */
    static function fetch(string $event, array $args = [], $value = null)
    {
        $args['value'] = &$value;
        self::call($event, $args);

        return $value;
    }

    /**
     * Trigger an event and fetch a string
     *
     * @param string $event
     * @param array  $args  ('output' is added automatically)
     * @return string
     */
    static function buffer(string $event, array $args = []): string
    {
        $output = '';
        $args['output'] = &$output;
        self::call($event, $args);

        return $output;
    }
}
