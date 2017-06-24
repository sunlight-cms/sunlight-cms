<?php

namespace Sunlight\Option;

class OptionSet
{
    /** @var array */
    private $definition;
    /** @var array */
    private $knownIndexMap = array();
    /** @var bool */
    private $ignoreExtraIndexes = false;

    /**
     * Definition format:
     * ------------------
     * array(
     *      index1 => array(
     *          type            => scalar / boolean / integer / double / string / array / object / resource / NULL
     *          required        => true / false
     *          [nullable]      => true / false (false)
     *          [default]       => anything (null)
     *          [normalizer]    => callback(mixed value, mixed context): mixed that should return the normalized value
     *                             (it is applied to the default value too and can throw OptionSetNormalizerException)
     *      ),
     *      ...
     * )
     *
     * @param array    $definition
     */
    public function __construct(array $definition)
    {
        $this->definition = $definition;
    }

    /**
     * Get list of indexes that are valid for this set
     *
     * @return string[]
     */
    public function getIndexes()
    {
        return array_merge(
            array_keys($this->definition),
            array_keys($this->knownIndexMap)
        );
    }

    /**
     * Add known indexes (additional allowed indexes)
     *
     * @param string[] $knownIndexes
     * @return static
     */
    public function addKnownIndexes(array $knownIndexes)
    {
        $this->knownIndexMap += array_flip($knownIndexes);

        return $this;
    }

    /**
     * Set or replace known indexes (additional allowed indexes)
     *
     * @param string[] $knownIndexes
     * @return static
     */
    public function setKnownIndexes(array $knownIndexes)
    {
        $this->knownIndexMap = array_flip($knownIndexes);

        return $this;
    }

    /**
     * See whether extra indexes are ignored
     *
     * @return bool
     */
    public function getIgnoreExtraIndexes()
    {
        return $this->ignoreExtraIndexes;
    }

    /**
     * Set whether to ignore unknown indexes
     *
     * @param bool $ignoreExtraIndexes
     * @return static
     */
    public function setIgnoreExtraIndexes($ignoreExtraIndexes)
    {
        $this->ignoreExtraIndexes = $ignoreExtraIndexes;

        return $this;
    }

    /**
     * Process given data
     *
     * The data may be modified by default values.
     *
     * @param array      &$data   data to process
     * @param mixed      $context normalizer context
     * @param array|null &$errors variable for error messages
     * @return bool true on success, false if there are errors
     */
    public function process(&$data, $context = null, &$errors = null)
    {
        $errors = array();

        if (!is_array($data)) {
            $errors['_'] = sprintf('option data must be an array (got %s)', gettype($data));
            
            return false;
        }

        foreach ($this->definition as $index => $entry) {
            $indexIsValid = true;

            // validate
            if (array_key_exists($index, $data)) {
                // type
                if (
                    $entry['type'] === 'scalar' && !is_scalar($data[$index])
                    || (
                        $entry['type'] !== 'scalar'
                        && ($type = gettype($data[$index])) !== $entry['type']
                        && (
                            $data[$index] !== null
                            || !isset($entry['nullable'])
                            || !$entry['nullable']
                        )
                    )
                ) {
                    // invalid type
                    $errors[$index] = sprintf(
                        'expected "%s" to be "%s", got "%s"',
                        $index,
                        $entry['type'],
                        $type
                    );
                    $indexIsValid = false;
                }
            } elseif ($entry['required']) {
                // missing required
                $errors[$index] = sprintf('"%s" is required', $index);
                $indexIsValid = false;
            } else {
                // default value
                $data[$index] = isset($entry['default'])
                    ? $entry['default']
                    : null;
            }

            // normalize
            if ($indexIsValid && isset($entry['normalizer'])) {
                try {
                    $data[$index] = call_user_func($entry['normalizer'], $data[$index], $context);
                } catch (OptionSetNormalizerException $e) {
                    $errors[$index] = $e->getMessage();
                }
            }
        }

        if (!$this->ignoreExtraIndexes) {
            foreach (array_keys(array_diff_key($data, $this->definition, $this->knownIndexMap)) as $extraKey) {
                $errors[$extraKey] = sprintf('unknown option "%s"', $extraKey);
            }
        }

        return sizeof($errors) === 0;
    }
}
