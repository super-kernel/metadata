<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Path;

use function array_pop;
use function explode;
use function implode;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Converts slash-separated local and Phar paths between directory-relative and absolute form without file access.
 *
 * @internal
 */
final class PathResolver
{
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, 'phar://')
            || 1 === preg_match('~\\A[A-Za-z]:/~', $path);
    }

    /**
     * Resolves a relative path against a directory and removes `.` and `..` segments lexically.
     *
     * Absolute paths are normalized the same way; `..` never climbs above the root, a drive, or a Phar archive path.
     */
    public static function resolve(string $directory, string $path): string
    {
        $candidate = str_replace('\\', '/', self::isAbsolute($path) ? $path : rtrim($directory, '/') . '/' . $path);
        $prefix = '';

        if (str_starts_with($candidate, 'phar://')) {
            $prefix = 'phar://';
            $candidate = substr($candidate, 7);
        }

        if (1 === preg_match('~\\A[A-Za-z]:/~', $candidate)) {
            $prefix .= substr($candidate, 0, 3);
            $candidate = substr($candidate, 3);
        } elseif (str_starts_with($candidate, '/')) {
            $prefix .= '/';
            $candidate = ltrim($candidate, '/');
        }

        $segments = [];

        foreach (explode('/', $candidate) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $prefix . implode('/', $segments);
    }

    /**
     * Returns `.` for the directory itself, a relative path for a descendant, and any other path unchanged.
     */
    public static function relativize(string $path, string $directory): string
    {
        $directory = rtrim($directory, '/');

        if ($path === $directory) {
            return '.';
        }

        return str_starts_with($path, $directory . '/') ? substr($path, strlen($directory) + 1) : $path;
    }
}
