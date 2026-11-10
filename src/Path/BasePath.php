<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Path;

use SuperKernel\Contract\BasePathInterface;

final readonly class BasePath implements BasePathInterface
{
    /**
     * @param non-empty-string $directory Canonical project directory or Phar root, retained without checks
     */
    public function __construct(private string $directory)
    {
    }

    public function __toString(): string
    {
        return $this->directory;
    }
}
