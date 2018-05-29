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
    static function reg($event, $callback, $priority = 0)
    {
        Core::$eventEmitter->on($event, $callback, $priority);
    }

    /**
     * Register multiple callbacks
     *
     * @param array $callbacks array(event1 => callback1, ...)
     * @param int   $priority
     */
    static function regm(array $callbacks, $priority = 0)
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
    static function regGlobal($callback, $priority = 0)
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
    static function args(&$output, array $args = array())
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
    static function call($event, array $args = array())
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
    static function fetch($event, array $args = array(), $value = null)
    {
        $args['value'] = &$value;
        static::call($event, $args);

        return $value;
    }

    /**
     * Trigger an event and fetch a string
     *
     * @param string $event
     * @param array  $args  ('output' is added automatically)
     * @return string
     */
    static function buffer($event, array $args = array())
    {
        $output = '';
        $args['output'] = &$output;
        static::call($event, $args);

        return $output;
    }
}
