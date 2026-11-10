<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Tests\Unit;

use SuperKernel\Metadata\Path\PathResolver;
use SuperKernel\Metadata\Tests\Support\TestCase;

final class PathResolverTest extends TestCase
{
    public function testResolveNormalizesRelativeAndAbsolutePaths(): void
    {
        self::assertSame('/project', PathResolver::resolve('/project/vendor', '..'));
        self::assertSame('/project', PathResolver::resolve('/project/lib/vendor/', '../..'));
        self::assertSame(
            '/project/vendor/fixture/library',
            PathResolver::resolve('/project/vendor', 'fixture/library'),
        );
        self::assertSame('/elsewhere/library', PathResolver::resolve('/project/vendor', '/elsewhere/./library/'));
        self::assertSame('phar:///tmp/app.phar', PathResolver::resolve('phar:///tmp/app.phar/vendor', '..'));
        self::assertSame('C:/project', PathResolver::resolve('C:\\project\\vendor', '..'));
        self::assertSame('/', PathResolver::resolve('/project', '../../..'));
        self::assertSame('/project', PathResolver::resolve('/project', '.'));
    }

    public function testRelativizeKeepsPathsOutsideTheDirectory(): void
    {
        self::assertSame('.', PathResolver::relativize('/project', '/project/'));
        self::assertSame('src/Name.php', PathResolver::relativize('/project/src/Name.php', '/project'));
        self::assertSame('/projects/other', PathResolver::relativize('/projects/other', '/project'));
    }

    public function testAbsolutePathsAreRecognized(): void
    {
        self::assertTrue(PathResolver::isAbsolute('/project'));
        self::assertTrue(PathResolver::isAbsolute('phar://app.phar/vendor'));
        self::assertTrue(PathResolver::isAbsolute('D:/project'));
        self::assertFalse(PathResolver::isAbsolute('vendor'));
        self::assertFalse(PathResolver::isAbsolute('..'));
    }
}
