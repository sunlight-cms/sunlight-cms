<?php

namespace Sunlight\Util;

/**
 * Configuration helper that loads and saves configuration into a PHP file
 */
class ConfigurationFile implements \ArrayAccess
{
    /** @var string */
    private $path;
    /** @var array */
    private $defaults;
    /** @var array|null */
    private $data;

    /**
     * @param string $path
     * @param array $defaults
     */
    function __construct(string $path, array $defaults = [])
    {
        $this->path = $path;
        $this->defaults = $defaults;
    }

    /**
     * Save configuration to the file
     */
    function save(): void
    {
        if ($this->data === null) {
            return;
        }

        file_put_contents(
            $this->path,
            sprintf("<?php if (defined('SL_ROOT')) return %s;\n", var_export($this->data, true)),
            LOCK_EX
        );
    }

    /**
     * Clear any modified values, leaving only the defaults
     */
    function reset(): void
    {
        $this->data = [];
    }

    /**
     * Get data stored in this file
     *
     * @return array
     */
    function toArray(): array
    {
        $this->ensureLoaded();

        return $this->data + $this->defaults;
    }

    function offsetExists($offset): bool
    {
        $this->ensureLoaded();

        return isset($this->data[$offset]) || isset($this->defaults[$offset]);
    }

    function offsetGet($offset)
    {
        $this->ensureLoaded();

        if (array_key_exists($offset, $this->data)) {
            $value = $this->data[$offset];
        } elseif (array_key_exists($offset, $this->defaults)) {
            $value = $this->defaults[$offset];
        } else {
            throw new \OutOfBoundsException(sprintf('The configuration key "%s" is not defined', $offset));
        }

        return $value;
    }

    function offsetSet($offset, $value): void
    {
        $this->ensureLoaded();

        $this->data[$offset] = $value;
    }

    function offsetUnset($offset): void
    {
        $this->ensureLoaded();

        unset($this->data[$offset]);
    }

    private function ensureLoaded(): void
    {
        if ($this->data !== null) {
            return;
        }

        if (is_file($this->path)) {
            $this->data = (array) include $this->path;
        } else {
            $this->data = [];
        }
    }
}
