<?php declare(strict_types=1);

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
