<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Manager;

use InvalidArgumentException;
use SuperKernel\Contract\EnvironmentRequirementInterface;
use SuperKernel\Contract\PackageManagerInterface;
use SuperKernel\Metadata\Model\Package;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;
use function ksort;
use function sprintf;
use function str_contains;
use function strtolower;
use function usort;

use const SORT_STRING;

/**
 * Indexes the root package, the installed lock-file packages, and their class maps for one project.
 */
final readonly class PackageManager implements PackageManagerInterface
{
    /** @var list<Package> */
    private array $all;

    /** @var list<Package> */
    private array $packages;

    /** @var array<string, Package> */
    private array $byName;

    /** @var list<EnvironmentRequirementInterface> */
    private array $environmentRequirements;

    /** @var array<string, array<class-string, non-empty-string>> */
    private array $classesByPackage;

    /** @var array<class-string, non-empty-string> */
    private array $allClasses;

    /** @var array<class-string, Package> */
    private array $packageByClass;

    /**
     * @param Package                                              $rootPackage      Project package at the base path
     * @param list<Package>                                        $packages         Installed lock-file packages
     * @param array<string, array<class-string, non-empty-string>> $classesByPackage Absolute class maps by package
     *
     * @throws InvalidArgumentException When package or class-map data violates the manager contract
     */
    public function __construct(
        private Package $rootPackage,
        array           $packages,
        array           $classesByPackage = [],
    )
    {
        if (null === $rootPackage->locatedPath()) {
            throw new InvalidArgumentException('The root package must be located at the project directory.');
        }

        foreach ($packages as $package) {
            if (!$package instanceof Package) {
                throw new InvalidArgumentException('Every managed package must be a Package model.');
            }
        }

        usort($packages, static fn(Package $left, Package $right): int => $left->name() <=> $right->name());

        $byName = [
            $rootPackage->name() => $rootPackage,
        ];
        $environmentRequirements = $rootPackage->environmentRequirements();

        foreach ($packages as $package) {
            if (isset($byName[$package->name()])) {
                throw new InvalidArgumentException(sprintf(
                    'Package name "%s" occurs more than once.',
                    $package->name(),
                ));
            }

            $byName[$package->name()] = $package;

            foreach ($package->environmentRequirements() as $requirement) {
                $environmentRequirements[] = $requirement;
            }
        }

        $providedClassMaps = [];

        foreach ($classesByPackage as $packageName => $classMap) {
            $normalizedPackageName = is_string($packageName) ? strtolower($packageName) : null;

            if (null === $normalizedPackageName || !isset($byName[$normalizedPackageName])) {
                throw new InvalidArgumentException('Class maps must be keyed by a managed package name.');
            }

            if (!is_array($classMap)) {
                throw new InvalidArgumentException('Every package class map must be an array.');
            }

            if (isset($providedClassMaps[$normalizedPackageName])) {
                throw new InvalidArgumentException(sprintf(
                    'A class map for package "%s" occurs more than once.',
                    $normalizedPackageName,
                ));
            }

            $providedClassMaps[$normalizedPackageName] = $classMap;
        }

        usort(
            $environmentRequirements,
            static fn(EnvironmentRequirementInterface $left, EnvironmentRequirementInterface $right): int => [
                $left->name(),
                $left->requiredBy(),
                $left->isDevRequirement(),
                $left->constraint(),
            ] <=> [
                $right->name(),
                $right->requiredBy(),
                $right->isDevRequirement(),
                $right->constraint(),
            ],
        );

        $classMaps = [];
        $allClasses = [];
        $packageByClass = [];

        foreach ($byName as $packageName => $package) {
            $classMap = $providedClassMaps[$packageName] ?? [];

            foreach ($classMap as $class => $file) {
                if (
                    !is_string($class)
                    || '' === $class
                    || str_contains($class, "\0")
                    || !is_string($file)
                    || '' === $file
                    || str_contains($file, "\0")
                ) {
                    throw new InvalidArgumentException(
                        'Class maps must contain non-empty string class and path pairs.',
                    );
                }

                if (!isset($allClasses[$class])) {
                    $allClasses[$class] = $file;
                    $packageByClass[$class] = $package;
                }
            }

            ksort($classMap, SORT_STRING);
            $classMaps[$packageName] = $classMap;
        }

        ksort($allClasses, SORT_STRING);

        $this->packages = array_values($packages);
        $this->all = [
            $rootPackage,
            ...$this->packages,
        ];
        $this->byName = $byName;
        $this->environmentRequirements = array_values($environmentRequirements);
        $this->classesByPackage = $classMaps;
        $this->allClasses = $allClasses;
        $this->packageByClass = $packageByClass;
    }

    /** @return list<Package> */
    public function all(): array
    {
        return $this->all;
    }

    /** @return list<Package> */
    public function packages(): array
    {
        return $this->packages;
    }

    public function environmentRequirements(): array
    {
        return $this->environmentRequirements;
    }

    public function hasEnvironmentRequirements(): bool
    {
        return [] !== $this->environmentRequirements;
    }

    /** @return list<Package> */
    public function ofComposerType(string $composerType): array
    {
        return array_values(array_filter(
            $this->all,
            static fn(Package $package): bool => $package->composerType() === $composerType,
        ));
    }

    public function get(string $name): ?Package
    {
        return $this->byName[strtolower($name)] ?? null;
    }

    public function root(): Package
    {
        return $this->rootPackage;
    }

    public function classes(string $packageName): ?array
    {
        return $this->classesByPackage[strtolower($packageName)] ?? null;
    }

    public function allClasses(): array
    {
        return $this->allClasses;
    }

    public function packageForClass(string $class): ?Package
    {
        return $this->packageByClass[$class] ?? null;
    }
}
