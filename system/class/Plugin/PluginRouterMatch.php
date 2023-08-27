<?php

namespace Sunlight\Plugin;

class PluginRouterMatch
{
    /** @var callable */
    public $callback;
    /** @var array */
    public $params;

    /**
     * @param callable $callback
     */
    function __construct($callback, array $params)
    {
        $this->callback = $callback;
        $this->params = $params;
    }
}
