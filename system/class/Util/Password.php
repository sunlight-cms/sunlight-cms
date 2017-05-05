<?php

namespace Sunlight\Util;

/**
 * Password utility
 */
class Password
{
    /** Default algorithm */
    const PREFERRED_ALGO = 'sha256';
    /** Old MD5 algorithm */
    const MD5_LEGACY_ALGO = 'md5_legacy';
    /** Number of PBKDF2 iterations */
    const PBKDF2_ITERATIONS = 10000;
    /** Length of generated salts */
    const GENERATED_SALT_LENGTH = 64;

    /** @var string */
    protected $algo;
    /** @var int */
    protected $iterations;
    /** @var string */
    protected $salt;
    /** @var string */
    protected $hash;

    /**
     * @param string $algo
     * @param int    $iterations
     * @param string $salt
     * @param string $hash
     */
    public function __construct($algo, $iterations, $salt, $hash)
    {
        $this->algo = $algo;
        $this->iterations = $iterations;
        $this->salt = $salt;
        $this->hash = $hash;
    }

    /**
     * Parse a stored password
     *
     * @param string $storedPassword
     * @throws \InvalidArgumentException if the value is not valid
     * @return static
     */
    public static function load($storedPassword)
    {
        $segments = explode(':', $storedPassword, 4);

        if (
            4 !== sizeof($segments)
            || !ctype_digit($segments[1])
            || '' === $segments[2]
            || '' === $segments[3]
        ) {
            throw new \InvalidArgumentException('Invalid password format');
        }

        return new static($segments[0], (int) $segments[1], $segments[2], $segments[3]);
    }

    /**
     * Create new instance from the given plain password
     *
     * @param string $plainPassword
     * @return static
     */
    public static function create($plainPassword)
    {
        $algo = static::PREFERRED_ALGO;
        $iterations = static::PBKDF2_ITERATIONS;
        $salt = StringGenerator::generateHash(static::GENERATED_SALT_LENGTH);
        $hash = static::hash($algo, $iterations, $salt, $plainPassword);

        return new static($algo, $iterations, $salt, $hash);
    }

    /**
     * Create a hash
     *
     * @param string $algo
     * @param int    $iterations
     * @param string $salt
     * @param string $plainPassword
     * @throws \InvalidArgumentException on invalid arguments
     * @return string
     */
    protected static function hash($algo, $iterations, $salt, $plainPassword)
    {
        if (!is_string($plainPassword)) {
            throw new \InvalidArgumentException('Password must be a string');
        }
        if ('' === $plainPassword) {
            throw new \InvalidArgumentException('Password must not be empty');
        }

        if (static::MD5_LEGACY_ALGO === $algo) {
            // backward compatibility
            if (0 !== $iterations) {
                throw new \InvalidArgumentException(sprintf('Iterations is expected to be 0 if algo = "%s"', $algo));
            }

            $hash = md5($salt . $plainPassword . $salt);
        } else {
            $hash = hash_pbkdf2($algo, $plainPassword, $salt, $iterations);
        }

        return $hash;
    }

    /**
     * Convert to a string
     *
     * This methods calls build() internally
     *
     * @return string
     */
    public function __toString()
    {
        return $this->build();
    }

    /**
     * Build the password string
     *
     * @return string
     */
    public function build()
    {
        return sprintf(
            '%s:%d:%s:%s',
            $this->algo,
            $this->iterations,
            $this->salt,
            $this->hash
        );
    }

    /**
     * Match the given plain password against this instance
     *
     * @param string $plainPassword
     * @return bool
     */
    public function match($plainPassword)
    {
        if ('' === $plainPassword) {
            return false;
        }

        $hash = static::hash($this->algo, $this->iterations, $this->salt, $plainPassword);

        return
            is_string($this->hash)
            && '' !== $this->hash
            && is_string($hash)
            && '' !== $hash
            && $hash === $this->hash
        ;
    }

    /**
     * See if the password should be updated
     *
     * @return bool
     */
    public function shouldUpdate()
    {
        return
            static::PREFERRED_ALGO !== $this->algo
            || static::PBKDF2_ITERATIONS > $this->iterations
        ;
    }

    /**
     * Update the password
     *
     * This method updates the algo (if needed), salt and the hash.
     *
     * @param string $plainPassword
     */
    public function update($plainPassword)
    {
        $this->algo = static::PREFERRED_ALGO;
        $this->iterations = max(static::PBKDF2_ITERATIONS, $this->iterations);
        $this->salt = StringGenerator::generateHash(static::GENERATED_SALT_LENGTH);
        $this->hash = static::hash($this->algo, $this->iterations, $this->salt, $plainPassword);
    }
}
