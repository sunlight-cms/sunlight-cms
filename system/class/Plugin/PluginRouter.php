<?php

namespace Sunlight\Plugin;

use Sunlight\WebState;

abstract class PluginRouter
{
    /** @var array pattern => callback */
    private static $routes = [];

    static function register(string $pattern, $callback): void
    {
        self::$routes["{{$pattern}\$}AD"] = $callback; // TODO: always anchor?
    }

    static function handle(WebState $index): bool
    {
        if ($index->slug === null) {
            return false;
        }

        foreach (self::$routes as $pattern => $callback) {
            if (preg_match($pattern, $index->slug, $match)) {
                $index->type = WebState::PLUGIN;
                $callback($index, $match);

                return true;
            }
        }

        return false;
    }
}
