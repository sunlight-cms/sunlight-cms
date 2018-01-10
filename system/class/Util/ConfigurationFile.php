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
    public function __construct($path, array $defaults = array())
    {
        $this->path = $path;
        $this->defaults = $defaults;
    }

    /**
     * Save configuration to the file
     */
    public function save()
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
    public function reset()
    {
        $this->data = array();
    }

    /**
     * Get data stored in this file
     *
     * @return array
     */
    public function toArray()
    {
        $this->ensureLoaded();

        return $this->data + $this->defaults;
    }

    public function offsetExists($offset)
    {
        $this->ensureLoaded();

        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        $this->ensureLoaded();

        if (array_key_exists($offset, $this->data)) {
            $value = $this->data[$offset];
        } else {
            if (array_key_exists($offset, $this->defaults)) {
                $value = $this->defaults[$offset];
            } else {
                throw new \OutOfBoundsException(sprintf('The configuration key "%s" is not defined', $offset));
            }
        }

        return $value;
    }

    public function offsetSet($offset, $value)
    {
        $this->ensureLoaded();

        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset)
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
