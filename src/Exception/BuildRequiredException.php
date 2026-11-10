<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Exception;

use RuntimeException;
use Throwable;

/**
 * Reports generated metadata that is missing, unreadable, or out of date, and names the command that rebuilds it.
 */
final class BuildRequiredException extends RuntimeException implements MetadataExceptionInterface
{
    /**
     * @param string $reason One sentence describing the missing or mismatching artifact
     */
    public function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct($reason . ' Run `skernel build` to generate the project metadata.', 0, $previous);
    }
}
