<?php

namespace SunlightExtend\Devkit\Component;

use Kuria\Debug\Dumper;

class EventLogger
{
    /**
     * array(
     *      event => array(
     *          0 => array(argName => array(argType1 => true, ...), ...),   // argument type map
     *          1 => array(array(argName => dumpString, ...))               // call arg dump list
     *      ),
     *      ...
     * )
     *
     * @var array
     */
    private $log = [];

    /** @var int */
    private $eventCount;

    function log(string $event, ...$eventArgs): void
    {
        if (count($eventArgs) === 1 && is_array($eventArgs[0])) {
            // standard extend arguments
            $eventArgs = $eventArgs[0];
        }

        if (!isset($this->log[$event])) {
            $this->log[$event] = [[], []];
        }

        $logEntry = &$this->log[$event];
        $callIndex = count($logEntry[1]);
        $logEntry[1][$callIndex] = [];

        foreach ($eventArgs as $argName => $argValue) {
            if (is_object($argValue)) {
                $argType = get_class($argValue);
            } elseif ($argValue === null) {
                $argType = 'null';
            } else {
                $argType = gettype($argValue);
            }

            $logEntry[0][$argName][$argType] = true;
            $logEntry[1][$callIndex][$argName] = Dumper::dump($argValue);
        }

        ++$this->eventCount;
    }

    function getLog(): array
    {
        return $this->log;
    }

    function getEventCount(): int
    {
        return $this->eventCount;
    }
}
