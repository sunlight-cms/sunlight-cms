<?php

namespace Sunlight\Util;

use Sunlight\Core;

/**
 * URL parser, manipulator and generator
 * 
 * @property string|null $scheme   scheme (e.g. http)
 * @property string|null $host     host name (e.g. example.com)
 * @property string|null $port     port
 * @property string|null $user     user name
 * @property string|null $pass     password
 * @property string|null $path     path (e.g. /example)
 * @property string|null $fragment fragment (e.g. #fragment - without the hash)
 * @property array       $query    query attributes as an array
 */
class Url
{
    /** @var Url|null */
    private static $current;
    /** @var Url|null */
    private static $base;
    /** @var string|null */
    private static $cachedBaseUrl;
    /** @var array */
    private $components;

    private function __construct(array $components)
    {
        $this->components = $components + [
            'scheme' => null,
            'host' => null,
            'port' => null,
            'user' => null,
            'pass' => null,
            'path' => null,
            'query' => [],
            'fragment' => null,
        ];
    }

    /**
     * Create a blank instance
     */
    static function create(): self
    {
        return new self([]);
    }

    /**
     * Create instance from a string
     *
     * @throws \InvalidArgumentException if the URL is invalid
     */
    static function parse(string $url): self
    {
        $components = parse_url($url);

        if ($components === false) {
            throw new \InvalidArgumentException('Invalid URL');
        }

        if (isset($components['query'])) {
            parse_str($components['query'], $components['query']);
        }

        return new self($components);
    }

    /**
     * Get instance of the current URL
     *
     * Returns a different instance each time so it may be modified.
     */
    static function current(): self
    {
        if (self::$current === null) {
            try {
                $requestUri = self::parse(!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');

                $path = $requestUri->path;
                $query = $requestUri->query;

                if ($path === '' || $path[0] !== '/') {
                    $path = "/{$path}";
                }
            } catch (\InvalidArgumentException $e) {
                $path = '/';
                $query = [];
            }

            $url = sprintf(
                '%s://%s%s',
                !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http',
                !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost',
                $path
            );

            self::$current = self::parse($url);
            self::$current->query = $query;
        }

        return clone self::$current;
    }

    /**
     * Get instance of the system base URL
     *
     * Returns a different instance each time so it may be modified.
     */
    static function base(): self
    {
        if (self::$base === null || self::$cachedBaseUrl !== Core::$url) {
            self::$base = self::parse(Core::$url);
            self::$cachedBaseUrl = Core::$url;
        }

        return clone self::$base;
    }

    /**
     * Convert to string
     *
     * @return string
     */
    function __toString(): string
    {
        return $this->generate();
    }

    /**
     * Generate the URL
     *
     * @param bool|null $absolute generate absolute URL 1/0
     * @throws \LogicException if the URL could not be generated
     * @return string
     */
    function generate(?bool $absolute = null): string
    {
        $output = '';

        if ($absolute === null) {
            $absolute = !empty($this->components['host']) && !empty($this->components['scheme']);
        }

        if ($absolute) {
            if (empty($this->components['host']) || empty($this->components['scheme'])) {
                throw new \LogicException('Cannot generate absolute URL without host and scheme being set');
            }

            // scheme
            if (!empty($this->components['scheme'])) {
                $output .= $this->components['scheme'] . '://';
            }

            // user
            if (!empty($this->components['user'])) {
                $output .= $this->components['user'];
            }
            if (!empty($this->components['pass'])) {
                $output .= ':' . $this->components['pass'];
            }
            if (!empty($this->components['user']) || !empty($this->components['pass'])) {
                $output .= '@';
            }

            // host and port
            $output .= $this->getFullHost();
        }

        // path
        if (!empty($this->components['path'])) {
            $output .= (($this->components['path'][0] !== '/') ? '/' : '') . $this->components['path'];
        }

        // query
        if (!empty($this->components['query'])) {
            $output .= '?';
            $output .= http_build_query($this->components['query'], '', '&');
        }

        // fragment
        if (!empty($this->components['fragment'])) {
            $output .= '#' . $this->components['fragment'];
        }

        return $output;
    }

    /**
     * Generate a relative URL
     *
     * @return string
     */
    function generateRelative(): string
    {
        return $this->generate(false);
    }

    /**
     * Generate an absolute URL
     *
     * @return string
     */
    function generateAbsolute(): string
    {
        return $this->generate(true);
    }

    /**
     * Get query parameters as string
     *
     * @return string
     */
    function getQueryString(): string
    {
        return http_build_query($this->components['query'], '', '&');
    }

    /**
     * Get host name, including the port if it's non-default (e.g. example.com:8080)
     *
     * @return string|null
     */
    function getFullHost(): ?string
    {
        if ($this->components['host'] !== null) {
            $fullHost = $this->components['host'];

            if (!empty($this->components['port']) && $this->components['port'] != 80) {
                $fullHost .= ':' . $this->components['port'];
            }

            return $fullHost;
        }

        return null;
    }

    /**
     * Check for a component
     *
     * @param string $name
     * @return bool
     */
    function __isset(string $name): bool
    {
        return isset($this->components[$name]) && !empty($this->components[$name]);
    }

    /**
     * Get component
     *
     * @param string $name
     * @throws \OutOfBoundsException
     * @return mixed
     */
    function __get(string $name)
    {
        if (!array_key_exists($name, $this->components)) {
            throw new \OutOfBoundsException(sprintf('Unknown URL component "%s"', $name));
        }

        return $this->components[$name];
    }

    /**
     * Set component
     *
     * @param string $name
     * @param mixed  $value
     * @throws \OutOfBoundsException
     */
    function __set(string $name, $value): void
    {
        if (!array_key_exists($name, $this->components)) {
            throw new \OutOfBoundsException(sprintf('Unknown URL component "%s"', $name));
        }

        if ($name === 'query') {
            $value = (array) $value;
        } elseif ($value !== null) {
            $value = (string) $value;
        }

        $this->components[$name] = $value;
    }

    /**
     * Check query parameter
     *
     * @param string $name
     * @return bool
     */
    function has(string $name): bool
    {
        return isset($this->components['query'][$name]);
    }

    /**
     * Get query parameter
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    function get(string $name, $default = null)
    {
        return
            $this->components['query'][$name] ?? $default;
    }

    /**
     * Set query parameter
     *
     * @param string $name
     * @param mixed  $value
     */
    function set(string $name, $value): self
    {
        if ($value === null) {
            unset($this->components['query'][$name]);
        } else {
            $this->components['query'][$name] = $value;
        }

        return $this;
    }

    /**
     * Remove query parameter
     *
     * @param string $name
     * @return $this
     */
    function remove(string $name): self
    {
        unset($this->components['query'][$name]);

        return $this;
    }

    /**
     * Remove multiple query parameters
     *
     * @param array $names
     * @return $this
     */
    function removeArr(array $names): self
    {
        foreach ($names as $name) {
            unset($this->components['query'][$name]);
        }

        return $this;
    }

    /**
     * Merge set of query parameters
     *
     * @param array $query
     * @return $this
     */
    function add(array $query): self
    {
        $this->components['query'] = $query + $this->components['query'];

        return $this;
    }
}
