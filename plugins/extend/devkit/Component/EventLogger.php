<?php

namespace SunlightPlugins\Extend\Devkit\Component;

/**
 * Devkit event logger
 *
 * @author ShiraNai7 <shira.cz>
 */
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
    private $log = array();

    /**
     * Log extend event
     *
     * @param string $event
     * @param array  $args
     */
    public function log($event, array $args)
    {
        if (isset($this->log[$event])) {
            ++$this->log[$event][0];
        } else {
            $argsInfo = array();
            foreach ($args as $argName => $argValue) {
                $argsInfo[$argName] = gettype($argValue);
            }
            $this->log[$event] = array(1, $argsInfo);
        }
    }

    /**
     * Get log
     *
     * @return array
     */
    public function getLog()
    {
        return $this->log;
    }
}
