<?php

namespace Sunlight;

/**
 * Extend event system
 */
class Extend
{
    /** @var array event idt => array(array(0 - callback, 1 - priority), ...) */
    private static $data = array();
    /** @var array event_idt => sorted 1/0 */
    private static $sortStates = array();
    /** @var array array(array(0 - callback, 1 - priority), ...) */
    private static $globalData = array();
    /** @var bool */
    private static $globalSortState = false;

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Register callback for an event
     *
     * @param string   $event
     * @param callable $callback
     * @param int      $priority
     */
    public static function reg($event, $callback, $priority = 0)
    {
        static::$data[$event][] = array($callback, $priority);
        static::$sortStates[$event] = false;
    }

    /**
     * Register multiple callbacks
     *
     * @param array $callbacks array(event1 => callback1, ...)
     * @param int   $priority
     */
    public static function regm(array $callbacks, $priority = 0)
    {
        foreach ($callbacks as $event => $callback) {
            static::$data[$event][] = array($callback, $priority);
            static::$sortStates[$event] = false;
        }
    }

    /**
     * Register a global callback
     *
     * @param callable $callback callback(event, args)
     * @param int      $priority
     */
    public static function regGlobal($callback, $priority = 0)
    {
        static::$globalData[] = array($callback, $priority);
        static::$globalSortState = false;
    }

    /**
     * Create normalized event arguments
     *
     * @param string &$output output variable reference
     * @param array  $args    array with additional arguments
     * @return array
     */
    public static function args(&$output, $args = array())
    {
        return array('output' => &$output) + $args;
    }

    /**
     * Trigger an event
     *
     * @param string $event
     * @param array  $args
     */
    public static function call($event, array $args = array())
    {
        // single handler
        if (isset(static::$data[$event])) {
            // sort
            if (!static::$sortStates[$event]) {
                usort(static::$data[$event], array(__CLASS__, 'sortEvents'));
                static::$sortStates[$event] = true;
            }

            // call
            foreach (static::$data[$event] as $listener) {
                $call = call_user_func($listener[0], $args);
                if (false === $call) {
                    break;
                }
            }
        }

        // global handlers
        if (!empty(static::$globalData)) {
            // sort
            if (!static::$globalSortState) {
                usort(static::$globalData, array(__CLASS__, 'sortEvents'));
                static::$globalSortState = true;
            }

            // call
            foreach (static::$globalData as $listener) {
                $call = call_user_func($listener[0], $event, $args);
                if (false === $call) {
                    break;
                }
            }
        }
    }

    /**
     * Trigger an event and fetch a value
     *
     * @param string $event
     * @param array  $args  ('value' is added automatically)
     * @param mixed  $value initial value
     * @return mixed
     */
    public static function fetch($event, array $args = array(), $value = null)
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
    public static function buffer($event, array $args = array())
    {
        $output = '';
        $args['output'] = &$output;
        static::call($event, $args);

        return $output;
    }

    /**
     * [CALLBACK] Sort extend events by priority
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private static function sortEvents(array $a, array $b)
    {
        if ($a[1] === $b[1]) {
            return 0;
        }
        
        if ($a[1] < $b[1]) {
            return 1;
        }

        return -1;
    }
}
