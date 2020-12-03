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
    protected static $current;
    /** @var Url|null */
    protected static $base;
    /** @var string|null */
    protected static $cachedBaseUrl;
    /** @var array */
    protected $components;

    /**
     * @param array $components
     */
    protected function __construct(array $components)
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
    static function create()
    {
        return new static([]);
    }

    /**
     * Create instance from a string
     *
     * @param string $url
     * @throws \InvalidArgumentException if the URL is invalid
     * @return static
     */
    static function parse($url)
    {
        $components = parse_url($url);

        if ($components === false) {
            throw new \InvalidArgumentException('Invalid URL');
        }

        if (isset($components['query'])) {
            parse_str($components['query'], $components['query']);
        }

        return new static($components);
    }

    /**
     * Get instance of the current URL
     *
     * Returns a different instance each time so it may be modified.
     *
     * @return static
     */
    static function current()
    {
        if (static::$current === null) {
            try {
                $requestUri = static::parse(!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');

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

            static::$current = static::parse($url);
            static::$current->query = $query;
        }

        return clone static::$current;
    }

    /**
     * Get instance of the system base URL
     *
     * Returns a different instance each time so it may be modified.
     *
     * @return static
     */
    static function base()
    {
        if (static::$base === null || static::$cachedBaseUrl !== Core::$url) {
            static::$base = static::parse(Core::$url);
            static::$cachedBaseUrl = Core::$url;
        }

        return clone static::$base;
    }

    /**
     * Convert to string
     *
     * @return string
     */
    function __toString()
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
    function generate($absolute = null)
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
    function generateRelative()
    {
        return $this->generate(false);
    }

    /**
     * Generate an absolute URL
     *
     * @return string
     */
    function generateAbsolute()
    {
        return $this->generate(true);
    }

    /**
     * Get query parameters as string
     *
     * @return string
     */
    function getQueryString()
    {
        return http_build_query($this->components['query'], '', '&');
    }

    /**
     * Get host name, including the port if it's non-default (e.g. example.com:8080)
     *
     * @return string|null
     */
    function getFullHost()
    {
        if ($this->components['host'] !== null) {
            $fullHost = $this->components['host'];

            if (!empty($this->components['port']) && $this->components['port'] != 80) {
                $fullHost .= ':' . $this->components['port'];
            }

            return $fullHost;
        }
    }

    /**
     * Check for a component
     *
     * @param string $name
     * @return bool
     */
    function __isset($name)
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
    function __get($name)
    {
        if (!key_exists($name, $this->components)) {
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
    function __set($name, $value)
    {
        if (!key_exists($name, $this->components)) {
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
    function has($name)
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
    function get($name, $default = null)
    {
        return
            isset($this->components['query'][$name])
                ? $this->components['query'][$name]
                : $default;
    }

    /**
     * Set query parameter
     *
     * @param string $name
     * @param mixed  $value
     * @return static
     */
    function set($name, $value)
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
    function remove($name)
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
    function removeArr(array $names)
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
    function add(array $query)
    {
        $this->components['query'] = $query + $this->components['query'];

        return $this;
    }
}
