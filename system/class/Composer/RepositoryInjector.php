<?php

namespace Sunlight\Composer;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Sunlight\Util\Environment;

class RepositoryInjector
{
    /** @var \stdClass[] name-indexed */
    private $packages;
    /** @var array indicates packages that cannot be overridden */
    private $rootMap;
    /** @var array package name => Repository */
    private $sourceMap;
    /** @var ConstraintMap */
    private $constraintMap;

    function __construct(Repository $rootRepository)
    {
        $this->packages = $rootRepository->getInstalledPackages();
        $this->rootMap = array_flip(array_keys($this->packages));
        $this->sourceMap = array_fill_keys(array_keys($this->packages), $rootRepository);
        $this->constraintMap = new ConstraintMap($rootRepository);
    }

    /**
     * Attempt to inject repository dependencies into the current state
     *
     * @param string[] $errors
     */
    function inject(Repository $repository, array &$errors = null): bool
    {
        $errors = [];
        $toInject = [];
        $constraintMap = new ConstraintMap($repository);

        // check PHP version
        if (
            $constraintMap->has('php')
            && !$this->satisfies(Environment::getPhpVersion(), $constraintMap->getConstraints('php'), $failedConstraints)
        ) {
            $errors[] = sprintf(
                'current PHP version %s is not compatible with constraints %s',
                Environment::getPhpVersion(),
                $this->getConstraintSourceInfo($constraintMap->getSources('php'), $failedConstraints)
            );

            return false;
        }

        // iterate packages
        foreach ($repository->getInstalledPackages() as $name => $package) {
            if (isset($this->rootMap[$name])) {
                // there is such root package already
                // check if it is compatible with requirements of this repository
                if (!$this->existingPackageSatisfiesConstraints($name, $constraintMap->getConstraints($name), $failedConstraints)) {
                    $errors[] = sprintf(
                        'root package %s is not compatible with constraints %s',
                        $this->getExistingPackageInfo($name),
                        $this->getConstraintSourceInfo($constraintMap->getSources($name), $failedConstraints)
                    );
                }

                continue;
            }

            if (isset($this->packages[$name])) {
                // there is such package already injected
                $isCompatible = $this->packageSatisfiesExistingConstraints($package, $failedConstraints);
                $isNewer = Comparator::compare($package->version, '>', $this->packages[$name]->version);

                if ($isCompatible && $isNewer) {
                    // this package is compatible with existing requirements and it is a newer version
                    $toInject[] = $package;
                } elseif (!$this->existingPackageSatisfiesConstraints($name, $constraintMap->getConstraints($name), $failedExistingPackageConstraints)) {
                    // this package is older or incompatible and the existing package is also not compatible
                    if ($isCompatible) {
                        $errors[] = sprintf(
                            'package %s is older than existing %s, which is not compatible with constraints %s',
                            $this->getPackageInfo($package, $repository),
                            $this->getExistingPackageInfo($name),
                            $this->getConstraintSourceInfo($constraintMap->getSources($name), $failedExistingPackageConstraints)
                        );
                    } else {
                        $errors[] = sprintf(
                            'package %s is not compatible with constraints %s',
                            $this->getPackageInfo($package, $repository),
                            $this->getConstraintSourceInfo($this->constraintMap->getSources($name), $failedConstraints)
                        );
                    }
                }
            } else {
                // there is no such package yet, just inject it
                $toInject[] = $package;
            }
        }

        // fail if there are errors
        if (!empty($errors)) {
            return false;
        }

        // inject valid packages
        foreach ($toInject as $package) {
            $this->packages[$package->name] = $package;
            $this->sourceMap[$package->name] = $repository;
        }

        $this->constraintMap->add($constraintMap);

        return true;
    }

    /**
     * Get all packages that were successfully injected
     *
     * @return \stdClass[]
     */
    function getInjectedPackages(): array
    {
        $packages = [];

        foreach ($this->packages as $name => $package) {
            if (!isset($this->rootMap[$name])) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * Get source of a package
     *
     * @throws \OutOfBoundsException if no such package is known
     */
    function getSource(string $packageName): Repository
    {
        if (!isset($this->sourceMap[$packageName])) {
            throw new \OutOfBoundsException(sprintf('Package "%s" is not known', $packageName));
        }

        return $this->sourceMap[$packageName];
    }

    /**
     * Get current constraint map
     */
    function getConstraintMap(): ConstraintMap
    {
        return $this->constraintMap;
    }

    /**
     * @param array $failedIndexes
     */
    private function packageSatisfiesExistingConstraints(\stdClass $package, ?array &$failedIndexes): bool
    {
        return $this->satisfies($package->version, $this->constraintMap->getConstraints($package->name), $failedIndexes);
    }

    /**
     * @param array $failedIndexes
     */
    private function existingPackageSatisfiesConstraints(string $packageName, array $constraints, ?array &$failedIndexes): bool
    {
        return $this->satisfies($this->packages[$packageName]->version, $constraints, $failedIndexes);
    }

    private function satisfies(string $version, array $constraints, ?array &$failedIndexes): bool
    {
        $success = true;

        $failedIndexes = [];

        foreach ($constraints as $index => $constraint) {
            if (!Semver::satisfies($version, $constraint)) {
                $success = false;
                $failedIndexes[] = $index;
            }
        }

        return $success;
    }

    private function getPackageInfo(\stdClass $package, Repository $source): string
    {
        return $package->name . " ({$package->version} @ {$source->getPackagePath($package)})";
    }

    private function getExistingPackageInfo(string $name): string
    {
        return $this->getPackageInfo($this->packages[$name], $this->sourceMap[$name]);
    }

    /**
     * @param array[] $sources
     */
    private function getConstraintSourceInfo(array $sources, array $indexesToShow): string
    {
        $info = '';

        foreach ($indexesToShow as $index) {
            if ($info !== '') {
                $info .= ', ';
            }

            $source = $sources[$index];

            if ($source['package']) {
                $location = $source['repository']->getPackageComposerJsonPath($source['package']);
            } else {
                $location = $source['repository']->getComposerJsonPath();
            }

            $info .= $source['constraints'] . " (required by {$location})";
        }

        return $info;
    }
}
