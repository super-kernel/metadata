<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Tests\Support;

use FilesystemIterator;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Random\RandomException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_link;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

abstract class TestCase extends PhpUnitTestCase
{
    /**
     * @template T of Throwable
     *
     * @param class-string<T>   $expected        Expected throwable class
     * @param callable(): mixed $callback        Operation expected to throw
     * @param string|null       $messageContains Required substring in the throwable message
     *
     * @return T The captured throwable
     *
     * @throws AssertionFailedError When no throwable, a different throwable, or a mismatched message is observed
     */
    final protected function assertThrows(
        string   $expected,
        callable $callback,
        ?string  $messageContains = null,
    ): Throwable
    {
        try {
            $callback();
        } catch (Throwable $throwable) {
            $this->assertInstanceOf($expected, $throwable);

            if (null !== $messageContains) {
                $this->assertStringContainsString($messageContains, $throwable->getMessage());
            }

            return $throwable;
        }

        $this->fail(sprintf('Expected %s to be thrown.', $expected));
    }

    /**
     * @template T
     *
     * @param callable(string): T $callback
     *
     * @return T
     *
     * @throws RandomException  When a secure temporary-directory suffix cannot be generated
     * @throws RuntimeException When the temporary directory cannot be created
     * @throws Throwable        When the callback fails
     */
    final protected function withTemporaryDirectory(callable $callback): mixed
    {
        $directory = sys_get_temp_dir() . '/super-kernel-metadata-' . bin2hex(random_bytes(12));

        if (!mkdir($directory, 0o700)) {
            throw new RuntimeException(sprintf('Unable to create temporary directory %s.', $directory));
        }

        try {
            return $callback($directory);
        } finally {
            $this->removeTemporaryDirectory($directory);
        }
    }

    /**
     * @throws RuntimeException When the parent directory or file cannot be written
     */
    final protected function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory %s.', $directory));
        }

        if (false === file_put_contents($path, $contents)) {
            throw new RuntimeException(sprintf('Unable to write file %s.', $path));
        }
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isLink() || $item->isFile()) {
                unlink($path);
            } else {
                rmdir($path);
            }
        }

        rmdir($directory);
    }
}
