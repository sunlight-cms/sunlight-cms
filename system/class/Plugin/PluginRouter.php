<?php

namespace Sunlight\Plugin;

class PluginRouter
{
    /** @var array<array{type: string, pattern: string, attrs: array, callback: callable}> */
    private $routes = [];

    /**
     * @param callable $callback
     */
    function register(string $type, string $pattern, array $attrs, $callback): void
    {
        $this->routes[] = [
            'type' => $type,
            'pattern' => "{{$pattern}\$}AD",
            'attrs' => $attrs,
            'callback' => $callback,
        ];
    }

    function match(string $type, string $path): ?PluginRouterMatch
    {
        foreach ($this->routes as $route) {
            if ($route['type'] === $type && preg_match($route['pattern'], $path, $match)) {
                return new PluginRouterMatch(
                    $route['callback'],
                    $route['attrs'] + $match
                );
            }
        }

        return null;
    }
}
