<?php

namespace SunlightExtend\Devkit\Component;

class EventLogger
{
    /**
     * array(
     *      event => array(
     *          0 => count,
     *          1 => array(
     *              arg1_name => arg1_type,
     *              ...
     *          )
     *      ),
     *      ...
     * )
     *
     * @var array
     */
    private $log = [];

    /**
     * Log extend event
     */
    function log(string $event): void
    {
        $eventArgs = array_slice(func_get_args(), 1);

        if (count($eventArgs) === 1 && is_array($eventArgs[0])) {
            // standard extend arguments
            $eventArgs = $eventArgs[0];
        }

        if (isset($this->log[$event])) {
            ++$this->log[$event][0];
        } else {
            $argsInfo = [];

            foreach ($eventArgs as $argName => $argValue) {
                $argsInfo[$argName] = is_object($argValue) ? get_class($argValue) : gettype($argValue);
            }

            $this->log[$event] = [1, $argsInfo];
        }
    }

    /**
     * Get log
     */
    function getLog(): array
    {
        return $this->log;
    }
}
