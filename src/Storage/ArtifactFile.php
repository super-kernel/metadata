<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Storage;

use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\Metadata\Constants\ArtifactLayout;
use SuperKernel\Metadata\Exception\MetadataException;

use function chmod;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function realpath;
use function rename;
use function rtrim;
use function sprintf;
use function strlen;
use function tempnam;
use function umask;
use function unlink;

/**
 * Reads, atomically publishes, and removes one generated artifact below the vendor directory.
 *
 * @internal
 */
final readonly class ArtifactFile
{
    private string $path;

    public function __construct(VendorDirInterface $vendorDir, ArtifactLayout $artifact)
    {
        $this->path = rtrim((string) $vendorDir, '/') . '/' . $artifact->value;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return string|null The contents, or null when the artifact is missing, a link, or unreadable
     */
    public function read(): ?string
    {
        if (is_link($this->path) || !is_file($this->path)) {
            return null;
        }

        $contents = @file_get_contents($this->path);

        return false === $contents ? null : $contents;
    }

    /**
     * Publishes the contents through a same-directory temporary file and an atomic rename, with the permissions
     * of a newly created file under the current umask.
     *
     * @throws MetadataException When the parent directory or the file cannot be published
     */
    public function write(string $contents): void
    {
        $directory = dirname($this->path);

        if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
            throw new MetadataException(sprintf('The artifact directory %s is not a regular directory.', $directory));
        }

        if (!is_dir($directory) && !@mkdir($directory, 0o777) && !is_dir($directory)) {
            throw new MetadataException(sprintf('Unable to create the artifact directory %s.', $directory));
        }

        $temporary = @tempnam($directory, '.metadata-');

        if (false === $temporary) {
            throw new MetadataException(sprintf('Unable to create a temporary file in %s.', $directory));
        }

        try {
            if (
                realpath($directory) !== realpath(dirname($temporary))
                || strlen($contents) !== @file_put_contents($temporary, $contents)
                || !@chmod($temporary, 0o666 & ~umask())
                || !@rename($temporary, $this->path)
            ) {
                throw new MetadataException(sprintf('Unable to publish the artifact %s.', $this->path));
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Removes the artifact; a missing artifact is ignored.
     *
     * @throws MetadataException When an existing artifact cannot be removed
     */
    public function delete(): void
    {
        if ((is_link($this->path) || file_exists($this->path)) && !@unlink($this->path)) {
            throw new MetadataException(sprintf('Unable to remove the artifact %s.', $this->path));
        }
    }
}
