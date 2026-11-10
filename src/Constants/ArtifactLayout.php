<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Constants;

/**
 * Names the generated artifacts owned by this package, relative to the vendor directory.
 */
enum ArtifactLayout: string
{
    /** Build stamp, removed first and written last by a build. */
    case BuildStamp = '.cache/build.json';

    /** Every package record with its relocatable class map, in one checksummed file. */
    case PackageRecords = '.cache/metadata.ser';
}
