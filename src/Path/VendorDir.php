<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Path;

use SuperKernel\Contract\VendorDirInterface;

final readonly class VendorDir implements VendorDirInterface
{
    /**
     * @param non-empty-string $directory Canonical vendor directory, retained without checks
     */
    public function __construct(private string $directory)
    {
    }

    public function __toString(): string
    {
        return $this->directory;
    }
}
