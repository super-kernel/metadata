<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Tests\Unit;

use InvalidArgumentException;
use SuperKernel\Metadata\Model\EnvironmentRequirement;
use SuperKernel\Metadata\Model\Package;
use SuperKernel\Metadata\Tests\Support\TestCase;

final class PackageTest extends TestCase
{
    public function testRequirementsAreSortedAndRelocationKeepsEveryOtherField(): void
    {
        $zts = new EnvironmentRequirement('php-zts', '*', 'fixture/library', false);
        $php = new EnvironmentRequirement('php', '>=8.4', 'fixture/library', false);
        $package = new Package('fixture/library', '1.2.3', 'abc', 'library', '/project/vendor/fixture/library', [
            $zts,
            $php,
        ]);

        self::assertSame([
            $php,
            $zts,
        ], $package->environmentRequirements());

        $moved = $package->withLocatedPath('/moved/vendor/fixture/library');

        self::assertSame('fixture/library', $moved->name());
        self::assertSame('1.2.3', $moved->version());
        self::assertSame('abc', $moved->reference());
        self::assertSame('library', $moved->composerType());
        self::assertSame('/moved/vendor/fixture/library', $moved->locatedPath());
        self::assertSame($package->environmentRequirements(), $moved->environmentRequirements());
        self::assertSame('/project/vendor/fixture/library', $package->locatedPath());
    }

    public function testUnlocatedPackageKeepsNullPath(): void
    {
        $package = new Package('fixture/meta', null, null, 'metapackage', null);

        self::assertNull($package->locatedPath());
        self::assertNull($package->version());
        self::assertSame([], $package->environmentRequirements());
    }

    public function testInvalidNameIsRejected(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): Package => new Package('Fixture/Library', null, null, 'library', null),
            'normalized Composer package name',
        );
    }

    public function testEmptyTypeIsRejected(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): Package => new Package('fixture/library', null, null, '', null),
            'type must be non-empty',
        );
    }

    public function testPathWithNulByteIsRejected(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): Package => new Package('fixture/library', null, null, 'library', "/project\0"),
            'without NUL bytes',
        );
    }

    public function testRequirementOfAnotherPackageIsRejected(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): Package => new Package('fixture/library', null, null, 'library', null, [
                new EnvironmentRequirement('php', '*', 'fixture/other', false),
            ]),
            'declared by its owning package',
        );
    }

    public function testNonPlatformRequirementIsRejected(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): Package => new Package('fixture/library', null, null, 'library', null, [
                new EnvironmentRequirement('fixture/other', '*', 'fixture/library', false),
            ]),
            'Composer platform package',
        );
    }

    public function testRequirementOfAnotherImplementationIsRejected(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): Package => new Package('fixture/library', null, null, 'library', null, [
                'php',
            ]),
            'EnvironmentRequirement model',
        );
    }
}
