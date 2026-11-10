<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Tests\Unit;

use InvalidArgumentException;
use SuperKernel\Contract\PackageManagerInterface;
use SuperKernel\Metadata\Manager\PackageManager;
use SuperKernel\Metadata\Model\EnvironmentRequirement;
use SuperKernel\Metadata\Model\Package;
use SuperKernel\Metadata\Tests\Support\TestCase;

use function array_map;

final class PackageManagerTest extends TestCase
{
    public function testManagerOrdersPackagesAndIndexesClasses(): void
    {
        $root = new Package('fixture/application', null, null, 'project', '/project', [
            new EnvironmentRequirement('php', '>=8.4', 'fixture/application', false),
        ]);
        $zeta = new Package('fixture/zeta', '1.0.0', 'z', 'library', '/project/vendor/fixture/zeta', [
            new EnvironmentRequirement('ext-json', '*', 'fixture/zeta', false),
        ]);
        $alpha = new Package('fixture/alpha', '2.0.0', 'a', 'super-kernel-plugin', '/project/vendor/fixture/alpha');
        $manager = new PackageManager($root, [
            $zeta,
            $alpha,
        ], [
            'Fixture/Zeta' => [
                'Zeta\\Second' => '/project/vendor/fixture/zeta/src/Second.php',
                'Zeta\\First'  => '/project/vendor/fixture/zeta/src/First.php',
                'Shared\\Name' => '/project/vendor/fixture/zeta/src/Shared.php',
            ],
            'fixture/application' => [
                'Shared\\Name' => '/project/src/Shared.php',
            ],
        ]);

        self::assertInstanceOf(PackageManagerInterface::class, $manager);
        self::assertSame([
            $root,
            $alpha,
            $zeta,
        ], $manager->all());
        self::assertSame([
            $alpha,
            $zeta,
        ], $manager->packages());
        self::assertSame($root, $manager->root());
        self::assertSame($zeta, $manager->get('FIXTURE/ZETA'));
        self::assertNull($manager->get('fixture/missing'));
        self::assertSame([
            $alpha,
        ], $manager->ofComposerType('super-kernel-plugin'));
        self::assertSame([
            'ext-json',
            'php',
        ], array_map(static fn($requirement): string => $requirement->name(), $manager->environmentRequirements()));
        self::assertTrue($manager->hasEnvironmentRequirements());
        self::assertSame([
            'Shared\\Name' => '/project/vendor/fixture/zeta/src/Shared.php',
            'Zeta\\First'  => '/project/vendor/fixture/zeta/src/First.php',
            'Zeta\\Second' => '/project/vendor/fixture/zeta/src/Second.php',
        ], $manager->classes('fixture/zeta'));
        self::assertSame([], $manager->classes('fixture/alpha'));
        self::assertNull($manager->classes('fixture/missing'));
        self::assertSame('/project/src/Shared.php', $manager->allClasses()['Shared\\Name']);
        self::assertSame($root, $manager->packageForClass('Shared\\Name'));
        self::assertSame($zeta, $manager->packageForClass('Zeta\\First'));
        self::assertNull($manager->packageForClass('Missing\\Name'));
    }

    public function testUnlocatedRootIsRejected(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): PackageManager => new PackageManager(
                new Package('fixture/application', null, null, 'project', null),
                [],
            ),
            'root package must be located',
        );
    }

    public function testDuplicatePackageIsRejected(): void
    {
        $root = new Package('fixture/application', null, null, 'project', '/project');

        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): PackageManager => new PackageManager($root, [
                new Package('fixture/application', '1.0.0', null, 'library', null),
            ]),
            'occurs more than once',
        );
    }

    public function testClassMapOfUnknownPackageIsRejected(): void
    {
        $root = new Package('fixture/application', null, null, 'project', '/project');

        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): PackageManager => new PackageManager($root, [], [
                'fixture/unknown' => [],
            ]),
            'keyed by a managed package name',
        );
    }

    public function testInvalidClassMapEntryIsRejected(): void
    {
        $root = new Package('fixture/application', null, null, 'project', '/project');

        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): PackageManager => new PackageManager($root, [], [
                'fixture/application' => [
                    'App\\Name' => '',
                ],
            ]),
            'non-empty string class and path pairs',
        );
    }
}
