<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Constants;

/**
 * Identifies the build protocol: the publish API and the generated artifact format of this package version.
 *
 * A generator publishes only into a project whose installed version declares the protocol number it supports.
 */
final class Protocol
{
    public const int VERSION = 1;
}
