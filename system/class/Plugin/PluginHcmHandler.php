<?php

namespace Sunlight\Plugin;

class PluginHcmHandler
{
    /** @var callable */
    public $callback;

    /**
     * @param callable $callback
     */
    function __construct($callback)
    {
        $this->callback = $callback;
    }

    function __invoke(array $eventArgs): void
    {
        $eventArgs['output'] = (string) ($this->callback)(...$eventArgs['arg_list']);
    }
}
