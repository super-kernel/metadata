<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Build;

use SuperKernel\Contract\BasePathInterface;

use function hash_file;
use function is_file;
use function rtrim;

/**
 * Hashes the project's `composer.lock`, which ties generated metadata to one locked dependency set.
 *
 * @internal
 */
final class LockHash
{
    /**
     * @return non-empty-string|null Lowercase hexadecimal SHA-256 of the file bytes, or null when it cannot be read
     */
    public static function of(BasePathInterface $basePath): ?string
    {
        $path = rtrim((string) $basePath, '/') . '/composer.lock';
        $hash = is_file($path) ? @hash_file('sha256', $path) : false;

        return false === $hash ? null : $hash;
    }
}
