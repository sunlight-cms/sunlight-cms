<?php

namespace Sunlight\Util;

/**
 * Configuration helper that loads and saves configuration into a PHP file
 */
class ConfigurationFile implements \ArrayAccess
{
    /** @var string */
    protected $path;
    /** @var array */
    protected $defaults;
    /** @var array|null */
    protected $data;

    /**
     * @param string $path
     * @param array $defaults
     */
    function __construct($path, array $defaults = array())
    {
        $this->path = $path;
        $this->defaults = $defaults;
    }

    /**
     * Save configuration to the file
     */
    function save()
    {
        if ($this->data === null) {
            return;
        }

        file_put_contents(
            $this->path,
            sprintf("<?php if (defined('_root')) return %s;\n", var_export($this->data, true)),
            LOCK_EX
        );
    }

    /**
     * Clear any modified values, leaving only the defaults
     */
    function reset()
    {
        $this->data = array();
    }

    /**
     * Get data stored in this file
     *
     * @return array
     */
    function toArray()
    {
        $this->ensureLoaded();

        return $this->data + $this->defaults;
    }

    function offsetExists($offset)
    {
        $this->ensureLoaded();

        return isset($this->data[$offset]);
    }

    function offsetGet($offset)
    {
        $this->ensureLoaded();

        if (key_exists($offset, $this->data)) {
            $value = $this->data[$offset];
        } else {
            if (key_exists($offset, $this->defaults)) {
                $value = $this->defaults[$offset];
            } else {
                throw new \OutOfBoundsException(sprintf('The configuration key "%s" is not defined', $offset));
            }
        }

        return $value;
    }

    function offsetSet($offset, $value)
    {
        $this->ensureLoaded();

        $this->data[$offset] = $value;
    }

    function offsetUnset($offset)
    {
        $this->ensureLoaded();

        unset($this->data[$offset]);
    }

    protected function ensureLoaded()
    {
        if ($this->data !== null) {
            return;
        }

        if (is_file($this->path)) {
            $this->data = (array) include $this->path;
        } else {
            $this->data = array();
        }
    }
}
