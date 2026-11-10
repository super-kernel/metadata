<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Model;

use InvalidArgumentException;
use SuperKernel\Contract\PackageMetadataInterface;
use SuperKernel\Metadata\Validation\PackageName;

use function array_values;
use function str_contains;
use function usort;

final readonly class Package implements PackageMetadataInterface
{
    /** @var list<EnvironmentRequirement> */
    private array $environmentRequirements;

    /**
     * @param string                       $name                    Lowercase Composer package name
     * @param string|null                  $version                 Version exactly as declared, or null when omitted
     * @param string|null                  $reference               Distribution reference, falling back to source
     * @param string                       $composerType            Declared Composer type, `library` when omitted
     * @param string|null                  $locatedPath             Canonical package directory, or null when unlocated
     * @param list<EnvironmentRequirement> $environmentRequirements Platform requirements declared by this package
     *
     * @throws InvalidArgumentException When a name, type, path, or requirement violates the package contract
     */
    public function __construct(
        private string  $name,
        private ?string $version,
        private ?string $reference,
        private string  $composerType,
        private ?string $locatedPath,
        array           $environmentRequirements = [],
    )
    {
        if (!PackageName::isPackage($name)) {
            throw new InvalidArgumentException('The package name must be a normalized Composer package name.');
        }

        if ('' === $composerType) {
            throw new InvalidArgumentException('The Composer package type must be non-empty.');
        }

        if (null !== $locatedPath && ('' === $locatedPath || str_contains($locatedPath, "\0"))) {
            throw new InvalidArgumentException('The located package path must be a non-empty path without NUL bytes.');
        }

        foreach ($environmentRequirements as $requirement) {
            if (!$requirement instanceof EnvironmentRequirement) {
                throw new InvalidArgumentException(
                    'Every environment requirement must be an EnvironmentRequirement model.',
                );
            }

            if (!PackageName::isPlatform($requirement->name())) {
                throw new InvalidArgumentException(
                    'Every environment requirement name must identify a Composer platform package.',
                );
            }

            if ($requirement->requiredBy() !== $name) {
                throw new InvalidArgumentException(
                    'Every environment requirement must be declared by its owning package.',
                );
            }
        }

        usort(
            $environmentRequirements,
            static fn(EnvironmentRequirement $left, EnvironmentRequirement $right): int => [
                    $left->name(),
                    $left->isDevRequirement(),
                    $left->constraint(),
                ] <=> [
                    $right->name(),
                    $right->isDevRequirement(),
                    $right->constraint(),
                ],
        );

        $this->environmentRequirements = array_values($environmentRequirements);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    /**
     * Returns the declared Composer type, `library` when the manifest omits it, including for the root package.
     */
    public function composerType(): string
    {
        return $this->composerType;
    }

    public function locatedPath(): ?string
    {
        return $this->locatedPath;
    }

    /**
     * Returns the platform requirements this package declares, sorted by platform package name.
     *
     * @return list<EnvironmentRequirement>
     */
    public function environmentRequirements(): array
    {
        return $this->environmentRequirements;
    }

    /**
     * Returns a copy located at the given path, validated like a newly constructed package.
     *
     * @throws InvalidArgumentException When the path or a restored requirement violates the package contract
     */
    public function withLocatedPath(?string $locatedPath): self
    {
        return new self(
            $this->name,
            $this->version,
            $this->reference,
            $this->composerType,
            $locatedPath,
            $this->environmentRequirements,
        );
    }
}
