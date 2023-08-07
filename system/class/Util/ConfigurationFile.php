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

        file_put_contents($this->path, self::build($this->data), LOCK_EX);
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
     */
    function toArray(): array
    {
        $this->ensureLoaded();

        return $this->data + $this->defaults;
    }

    /**
     * See if a key is defined
     */
    function isDefined($key): bool
    {
        $this->ensureLoaded();

        return array_key_exists($key, $this->data) || array_key_exists($key, $this->defaults);
    }

    function offsetExists($offset): bool
    {
        $this->ensureLoaded();

        return isset($this->data[$offset]) || isset($this->defaults[$offset]);
    }

    #[\ReturnTypeWillChange]
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

    /**
     * Build PHP code to save to the configuration file
     */
    static function build(array $data): string
    {
        return sprintf("<?php\n\nreturn %s;\n", var_export($data, true));
    }
}
