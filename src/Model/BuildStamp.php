<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Model;

use InvalidArgumentException;
use JsonException;
use SuperKernel\Metadata\Exception\BuildRequiredException;

use function array_keys;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function str_contains;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Records which protocol, SKERNEL version, and `composer.lock` produced the generated metadata, and where the
 * project root lies relative to the vendor directory.
 */
final readonly class BuildStamp
{
    /**
     * @param positive-int     $protocol Build protocol of the package version that wrote the artifacts
     * @param non-empty-string $skernel  SKERNEL version that ran the build, used only in messages
     * @param non-empty-string $lock     Lowercase hexadecimal SHA-256 of the `composer.lock` bytes
     * @param non-empty-string $basePath Project root relative to the vendor directory, or an absolute path
     *
     * @throws InvalidArgumentException When a field is out of its documented range
     */
    public function __construct(
        private int    $protocol,
        private string $skernel,
        private string $lock,
        private string $basePath,
    )
    {
        if (1 > $protocol) {
            throw new InvalidArgumentException('The build protocol must be a positive integer.');
        }

        if ('' === $skernel) {
            throw new InvalidArgumentException('The SKERNEL version must be non-empty.');
        }

        if (1 !== preg_match('~\\A[0-9a-f]{64}\\z~', $lock)) {
            throw new InvalidArgumentException('The lock hash must be a lowercase hexadecimal SHA-256 digest.');
        }

        if ('' === $basePath || str_contains($basePath, "\0")) {
            throw new InvalidArgumentException('The base path must be a non-empty path without NUL bytes.');
        }
    }

    /**
     * Restores a stamp from its JSON form.
     *
     * @throws BuildRequiredException When the JSON is invalid or does not contain exactly the stamp fields
     */
    public static function decode(string $json): self
    {
        try {
            $data = json_decode($json, true, 2, JSON_THROW_ON_ERROR);

            if (
                !is_array($data)
                || [
                    'protocol',
                    'skernel',
                    'lock',
                    'base-path',
                ] !== array_keys($data)
                || !is_int($data['protocol'])
                || !is_string($data['skernel'])
                || !is_string($data['lock'])
                || !is_string($data['base-path'])
            ) {
                throw new BuildRequiredException('The build stamp has an unexpected structure.');
            }

            return new self($data['protocol'], $data['skernel'], $data['lock'], $data['base-path']);
        } catch (JsonException|InvalidArgumentException $exception) {
            throw new BuildRequiredException('The build stamp is invalid.', $exception);
        }
    }

    /**
     * Returns the pretty-printed JSON form with a final newline.
     *
     * @throws JsonException When a string field cannot be encoded as JSON
     */
    public function encode(): string
    {
        return json_encode([
            'protocol'  => $this->protocol,
            'skernel'   => $this->skernel,
            'lock'      => $this->lock,
            'base-path' => $this->basePath,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @return positive-int
     */
    public function protocol(): int
    {
        return $this->protocol;
    }

    /**
     * @return non-empty-string
     */
    public function skernel(): string
    {
        return $this->skernel;
    }

    /**
     * @return non-empty-string
     */
    public function lock(): string
    {
        return $this->lock;
    }

    /**
     * @return non-empty-string
     */
    public function basePath(): string
    {
        return $this->basePath;
    }
}
