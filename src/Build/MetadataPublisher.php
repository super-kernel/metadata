<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Build;

use InvalidArgumentException;
use SuperKernel\Contract\BasePathInterface;
use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\Metadata\Constants\Protocol;
use SuperKernel\Metadata\Exception\BuildRequiredException;
use SuperKernel\Metadata\Exception\MetadataException;
use SuperKernel\Metadata\Manager\PackageManager;
use SuperKernel\Metadata\Model\BuildStamp;
use SuperKernel\Metadata\Storage\BuildStampStorage;
use SuperKernel\Metadata\Storage\PackageRecordStorage;

use function array_fill;
use function count;
use function explode;
use function implode;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Publishes generated package metadata for one project; the build generator calls it inside that project's process.
 *
 * Call invalidate(), then publishPackages(), then publish the annotation records, and commit() last. Until commit()
 * writes the build stamp, the runtime refuses the artifacts, so an interrupted build never looks complete.
 */
final readonly class MetadataPublisher
{
    private BuildStampStorage $stamp;

    private PackageRecordStorage $records;

    /**
     * @param VendorDirInterface $vendorDir Canonical absolute vendor directory of the project
     * @param BasePathInterface  $basePath  Canonical absolute project directory
     */
    public function __construct(private VendorDirInterface $vendorDir, private BasePathInterface $basePath)
    {
        $this->stamp = new BuildStampStorage($vendorDir);
        $this->records = new PackageRecordStorage($vendorDir);
    }

    /**
     * Removes the build stamp, then the package records.
     *
     * @throws MetadataException When an existing artifact cannot be removed
     */
    public function invalidate(): void
    {
        $this->stamp->delete();
        $this->records->delete();
    }

    /**
     * Writes the package records of the manager, whose root package must be located at the base path.
     *
     * @throws MetadataException When the root is located elsewhere or the records cannot be published
     */
    public function publishPackages(PackageManager $manager): void
    {
        $this->records->write($manager, $this->basePath);
    }

    /**
     * Verifies that the published package records restore, then writes the build stamp for the current protocol and
     * the project's `composer.lock`.
     *
     * @param string $skernelVersion SKERNEL version that ran the build, such as `1.0.0-alpha.2` or `dev`
     *
     * @throws InvalidArgumentException When the version is empty
     * @throws MetadataException        When `composer.lock` is unreadable, the records do not restore, or the stamp
     *                                  cannot be published
     */
    public function commit(string $skernelVersion): void
    {
        if ('' === $skernelVersion) {
            throw new InvalidArgumentException('The SKERNEL version must be non-empty.');
        }

        $lock = LockHash::of($this->basePath)
            ?? throw new MetadataException('The project composer.lock cannot be read.');

        try {
            $this->records->read($this->basePath);
        } catch (BuildRequiredException $exception) {
            throw new MetadataException('The published package records do not restore.', 0, $exception);
        }

        $base = rtrim((string) $this->basePath, '/');
        $vendor = (string) $this->vendorDir;
        $relativeBase = str_starts_with($vendor, $base . '/')
            ? implode('/', array_fill(0, count(explode('/', substr($vendor, strlen($base) + 1))), '..'))
            : $base;

        $this->stamp->write(new BuildStamp(Protocol::VERSION, $skernelVersion, $lock, $relativeBase));
    }
}
