<?php

namespace Sunlight\Plugin;

interface InitializableInterface
{
    /**
     * Initialize the plugin
     *
     * Called when a plugin has been fully loaded.
     */
    function initialize(): void;
}
