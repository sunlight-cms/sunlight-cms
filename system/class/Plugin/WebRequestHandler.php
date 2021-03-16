<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Util\Url;

/**
 * Web request handling abstraction for plugins
 *
 * Cannot be used outside of the "web" environment
 */
abstract class WebRequestHandler
{
    const OUTPUT_NOT_FOUND = 1;
    const OUTPUT_UNAUTHORIZED = 2;
    const OUTPUT_GUEST_ONLY = 3;

    /** @var static|null helps ensure a single active handler per request */
    private static $currentHandler;
    /** @var string|null only available when handling a request */
    protected $path;
    /** @var string[]|null only available when handling a request */
    protected $segments;

    /**
     * Register the request handler
     */
    function register(): void
    {
        if (_env !== Core::ENV_WEB) {
            throw new \LogicException('Request handlers are meant for the web environment');
        }

        Extend::reg('index.plugin', [$this, 'onIndexPlugin']);
    }

    /**
     * Handle the index.plugin event
     *
     * @param array $args
     */
    final function onIndexPlugin(array $args): void
    {
        $path = (string) $args['index']['slug'];

        if ($this->shouldHandle($path, $args['segments'])) {
            $args['index']['is_plugin'] = true;
            $this->prepareToHandleRequest($path, $args['segments']);
        }
    }

    /**
     * Handle the index.prepare event
     *
     * @param array $args
     */
    final function onIndexPrepare(array $args): void
    {
        $output = $this->getOutput();

        if (is_int($output)) {
            $this->handleOutputCode($args['index'], $output);
        } elseif ($output instanceof Url) {
            $args['index']['redirect_to'] = $output->generateAbsolute();
        } else {
            $args['index']['output'] = (string) $output;
        }
    }

    /**
     * Handle the index.ready event
     *
     * @param array $args
     */
    final function onIndexReady(array $args): void
    {
        if ($args['index']['redirect_to'] !== null) {
            // do not output if a redirect has been set up
            return;
        }

        if (!$this->shouldUseTemplate()) {
            // output raw content without a template
            $args['index']['template_enabled'] = false;
            echo $args['index']['output'];
        }
    }

    /**
     * @param string   $path
     * @param string[] $segments
     * @return bool
     */
    abstract protected function shouldHandle(string $path, array $segments): bool;

    /**
     * @return bool
     */
    protected function shouldUseTemplate(): bool
    {
        return true;
    }

    /**
     * Get output for the current request
     *
     * The resulting behavior depends on the returned type:
     *
     *  - int: one of RequestHandler::OUTPUT_* codes
     *  - Url instance: redirect to the given url
     *  - other: cast to a string and use as output
     *
     * @return Url|int|mixed
     */
    abstract protected function getOutput();

    /**
     * @param string   $path
     * @param string[] $segments
     */
    protected function prepareToHandleRequest(string $path, array $segments): void
    {
        if (self::$currentHandler !== null) {
            throw new \LogicException(sprintf(
                'The request is already being handled by another handler - %s',
                get_class(self::$currentHandler)
            ));
        }

        self::$currentHandler = $this;

        $this->path = $path;
        $this->segments = $segments;

        Extend::reg('index.prepare', [$this, 'onIndexPrepare']);
        Extend::reg('index.ready', [$this, 'onIndexReady']);
        Extend::call('index.request_handler.prepare', [
            'handler' => $this,
            'path' => $path,
            'segments' => $segments,
        ]);
    }

    /**
     * @param array $index
     * @param int   $code  see WebRequestHandler::OUTPUT_* constants
     */
    protected function handleOutputCode(array &$index, int $code): void
    {
        switch ($code) {
            case self::OUTPUT_NOT_FOUND: $index['is_found'] = false; break;
            case self::OUTPUT_UNAUTHORIZED: $index['is_accessible'] = false; break;
            case self::OUTPUT_GUEST_ONLY: $index['is_guest_only'] = false; break;
            default: throw new \OutOfBoundsException('Invalid output code %d', $code);
        }
    }
}
