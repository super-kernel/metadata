<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Tests\Unit;

use InvalidArgumentException;
use JsonException;
use SuperKernel\Metadata\Exception\BuildRequiredException;
use SuperKernel\Metadata\Model\BuildStamp;
use SuperKernel\Metadata\Tests\Support\TestCase;

use function str_repeat;

final class BuildStampTest extends TestCase
{
    /** @throws JsonException When encoding fails */
    public function testStampEncodesExactJsonAndDecodesIt(): void
    {
        $lock = str_repeat('ab', 32);
        $stamp = new BuildStamp(1, '1.0.0-alpha.2', $lock, '../..');
        $json = $stamp->encode();

        self::assertSame(
            "{\n    \"protocol\": 1,\n    \"skernel\": \"1.0.0-alpha.2\",\n    \"lock\": \"" . $lock
            . "\",\n    \"base-path\": \"../..\"\n}\n",
            $json,
        );

        $decoded = BuildStamp::decode($json);

        self::assertSame(1, $decoded->protocol());
        self::assertSame('1.0.0-alpha.2', $decoded->skernel());
        self::assertSame($lock, $decoded->lock());
        self::assertSame('../..', $decoded->basePath());
    }

    public function testInvalidFieldsAreRejected(): void
    {
        $lock = str_repeat('0', 64);

        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): BuildStamp => new BuildStamp(0, 'dev', $lock, '..'),
        );
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): BuildStamp => new BuildStamp(1, '', $lock, '..'),
        );
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): BuildStamp => new BuildStamp(1, 'dev', 'ABC', '..'),
        );
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn(): BuildStamp => new BuildStamp(1, 'dev', $lock, ''),
        );
    }

    public function testMalformedJsonNamesTheBuildCommand(): void
    {
        foreach ([
            'not json',
            '[]',
            '{"protocol":1,"skernel":"dev","lock":"' . str_repeat('0', 64) . '"}',
            '{"skernel":"dev","protocol":1,"lock":"' . str_repeat('0', 64) . '","base-path":".."}',
            '{"protocol":"1","skernel":"dev","lock":"' . str_repeat('0', 64) . '","base-path":".."}',
            '{"protocol":1,"skernel":"dev","lock":"short","base-path":".."}',
        ] as $json) {
            $this->assertThrows(
                BuildRequiredException::class,
                static fn(): BuildStamp => BuildStamp::decode($json),
                'Run `skernel build`',
            );
        }
    }
}
