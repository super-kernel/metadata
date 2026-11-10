<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Provider;

use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\BasePathInterface;
use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\Metadata\Exception\BuildRequiredException;
use SuperKernel\Metadata\Path\BasePath;
use SuperKernel\Metadata\Path\PathResolver;
use SuperKernel\Metadata\Storage\BuildStampStorage;

#[FactoryProvider(BasePathInterface::class)]
final readonly class BasePathProvider
{
    /**
     * Resolves the project directory recorded by the build stamp against the supplied vendor directory.
     *
     * @return BasePathInterface A fresh value, the `phar://` archive root when the vendor directory lies in a Phar
     *
     * @throws BuildRequiredException When the build stamp is missing, invalid, or of another protocol
     */
    public function __invoke(VendorDirInterface $vendorDir): BasePathInterface
    {
        return new BasePath(
            PathResolver::resolve((string) $vendorDir, new BuildStampStorage($vendorDir)->read()->basePath()),
        );
    }
}
