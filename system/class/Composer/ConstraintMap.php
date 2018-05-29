<?php

namespace Sunlight\Composer;

class ConstraintMap
{
    /** @var array package => string[] */
    private $constraintMap;
    /** @var array package => array(array(Repository[, package]), ...] corresponding to $constraintMap */
    private $sourceMap;

    function __construct(Repository $repository)
    {
        foreach ($repository->getDefinition()->require as $requiredPackage => $constraints) {
            $this->constraintMap[$requiredPackage][] = $constraints;
            $this->sourceMap[$requiredPackage][] = array($repository);
        }

        foreach ($repository->getInstalledPackages() as $package) {
            foreach ($package->require as $requiredPackage => $constraints) {
                $this->constraintMap[$requiredPackage][] = $constraints;
                $this->sourceMap[$requiredPackage][] = array($repository, $package);
            }
        }
    }

    /**
     * Add all constraints from another map
     */
    function add(self $constraintMap)
    {
        $this->constraintMap = array_merge_recursive($this->constraintMap, $constraintMap->constraintMap);
        $this->sourceMap = array_merge_recursive($this->sourceMap, $constraintMap->sourceMap);
    }

    /**
     * See if a package is known
     *
     * @param string $packageName
     * @return bool
     */
    function has($packageName)
    {
        return isset($this->constraintMap[$packageName]);
    }

    /**
     * Get all constraints imposed on a package with the given name
     *
     * @param string $packageName
     * @throws \OutOfBoundsException if no such package is known
     * @return string[]
     */
    function getConstraints($packageName)
    {
        if (!isset($this->constraintMap[$packageName])) {
            throw new \OutOfBoundsException(sprintf('Package "%s" is not known', $packageName));
        }

        return $this->constraintMap[$packageName];
    }

    /**
     * Get sources for the given package name
     *
     * Return value format:
     *
     *      array(
     *          array(
     *              'repository' => Repository
     *              'package' => \stdClass or NULL
     *              'constraints' => string
     *          )
     *          ...
     *      )
     *
     * @param string $packageName
     * @throws \OutOfBoundsException if no such package is known
     * @return array[]
     */
    function getSources($packageName)
    {
        $sources = array();

        if (!isset($this->sourceMap[$packageName])) {
            throw new \OutOfBoundsException(sprintf('Package "%s" is not known', $packageName));
        }

        foreach ($this->sourceMap[$packageName] as $index => $source) {
            $sources[] = array(
                'repository' => $source[0],
                'package' => isset($source[1]) ? $source[1] : null,
                'constraints' => $this->constraintMap[$packageName][$index],
            );
        }

        return $sources;
    }
}
