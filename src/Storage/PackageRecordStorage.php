<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Storage;

use SuperKernel\Contract\BasePathInterface;
use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\Metadata\Constants\ArtifactLayout;
use SuperKernel\Metadata\Exception\BuildRequiredException;
use SuperKernel\Metadata\Exception\MetadataException;
use SuperKernel\Metadata\Manager\PackageManager;
use SuperKernel\Metadata\Model\EnvironmentRequirement;
use SuperKernel\Metadata\Model\Package;
use SuperKernel\Metadata\Path\PathResolver;
use Throwable;

use function array_is_list;
use function array_keys;
use function array_map;
use function array_shift;
use function hash;
use function hash_equals;
use function is_array;
use function is_string;
use function serialize;
use function sprintf;
use function unserialize;

/**
 * Reads and writes the relocatable package records of one project.
 *
 * The root package is stored at `.`, other packages relative to the vendor directory, or absolute when they lie
 * outside it, and class-map paths relative to their package, so the records stay valid when the project moves or is
 * packaged into a Phar.
 *
 * @internal
 */
final readonly class PackageRecordStorage
{
    private ArtifactFile $file;

    public function __construct(private VendorDirInterface $vendorDir)
    {
        $this->file = new ArtifactFile($vendorDir, ArtifactLayout::PackageRecords);
    }

    /**
     * Restores the records through an explicit class allow-list, verifies their checksum, rebuilds every model
     * through its validating constructor, and resolves stored paths against the current locations.
     *
     * @throws BuildRequiredException When the records are missing, unreadable, corrupted, or invalid
     */
    public function read(BasePathInterface $basePath): PackageManager
    {
        $contents = $this->file->read()
            ?? throw new BuildRequiredException(sprintf('The package records %s are missing.', $this->file->path()));

        try {
            $record = @unserialize($contents, [
                'allowed_classes' => [
                    Package::class,
                    EnvironmentRequirement::class,
                ],
            ]);

            if (!is_array($record) || !is_string($record['checksum'] ?? null)) {
                throw new BuildRequiredException('The package records are invalid.');
            }

            $checksum = $record['checksum'];
            unset($record['checksum']);

            if (
                [
                    'root',
                    'packages',
                    'classes',
                ] !== array_keys($record)
                || !hash_equals($checksum, hash('sha256', serialize($record)))
                || !$record['root'] instanceof Package
                || !is_array($record['packages'])
                || !array_is_list($record['packages'])
                || !is_array($record['classes'])
            ) {
                throw new BuildRequiredException('The package records are invalid.');
            }

            $base = (string) $basePath;
            $vendor = (string) $this->vendorDir;
            $located = [
                $record['root']->withLocatedPath($base),
            ];

            foreach ($record['packages'] as $package) {
                if (!$package instanceof Package) {
                    throw new BuildRequiredException('The package records are invalid.');
                }

                $location = $package->locatedPath();
                $located[] = $package->withLocatedPath(
                    null === $location ? null : PathResolver::resolve($vendor, $location),
                );
            }

            $classes = [];

            foreach ($located as $package) {
                $classMap = $record['classes'][$package->name()] ?? [];
                $location = $package->locatedPath();

                if (!is_array($classMap)) {
                    throw new BuildRequiredException('The package records are invalid.');
                }

                $classes[$package->name()] = null === $location
                    ? $classMap
                    : array_map(static fn(string $file): string => PathResolver::resolve($location, $file), $classMap);
            }

            $root = array_shift($located);

            return new PackageManager($root, $located, $classes);
        } catch (BuildRequiredException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new BuildRequiredException('The package records cannot be restored.', $exception);
        }
    }

    /**
     * Publishes the records through a same-directory temporary file and an atomic rename.
     *
     * @throws MetadataException When the root is not located at the base path or the records cannot be published
     */
    public function write(PackageManager $manager, BasePathInterface $basePath): void
    {
        $base = (string) $basePath;
        $vendor = (string) $this->vendorDir;
        $root = $manager->root();

        if ($base !== $root->locatedPath()) {
            throw new MetadataException('The root package must be located at the base path.');
        }

        $packages = [];
        $classes = [];

        foreach ($manager->all() as $package) {
            $location = $package->locatedPath();

            if ($package !== $root) {
                $packages[] = $package->withLocatedPath(
                    null === $location ? null : PathResolver::relativize($location, $vendor),
                );
            }

            $classMap = $manager->classes($package->name()) ?? [];
            $classes[$package->name()] = null === $location
                ? $classMap
                : array_map(static fn(string $file): string => PathResolver::relativize($file, $location), $classMap);
        }

        $record = [
            'root'     => $root->withLocatedPath('.'),
            'packages' => $packages,
            'classes'  => $classes,
        ];
        $record['checksum'] = hash('sha256', serialize($record));

        $this->file->write(serialize($record));
    }

    /**
     * @throws MetadataException When existing records cannot be removed
     */
    public function delete(): void
    {
        $this->file->delete();
    }
}
