<?php

namespace Sunlight\Callback;

class MiddlewareCallback
{
    /** @var callable */
    private $callback;
    /** @var callable[] */
    private $middlewares;

    function __construct($callback, array $middlewares)
    {
        $this->callback = $callback;
        $this->middlewares = $middlewares;
    }

    function __invoke(...$args)
    {
        $queue = new \SplQueue();

        foreach ($this->middlewares as $middleware) {
            $queue->enqueue($middleware);
        }

        $next = function (...$args) use (&$next, $queue) {
            if (!$queue->isEmpty()) {
                return $queue->dequeue()($next, ...$args);
            }

            return ($this->callback)(...$args);
        };

        return $next(...$args);
    }
}
