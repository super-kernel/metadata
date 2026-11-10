<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Provider;

use Composer\Autoload\ClassLoader;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\Metadata\Exception\MetadataException;
use SuperKernel\Metadata\Path\VendorDir;

use function class_exists;
use function realpath;
use function str_replace;
use function str_starts_with;

#[FactoryProvider(VendorDirInterface::class)]
final readonly class VendorDirProvider
{
    /**
     * Selects the vendor directory of the registered Composer class loader that serves this package, so a moved
     * project and a Phar resolve their own vendor directory without configuration.
     *
     * @return VendorDirInterface A fresh value; local directories are canonicalized, Phar paths kept as registered
     *
     * @throws MetadataException When no registered Composer class loader serves this package
     */
    public function __invoke(): VendorDirInterface
    {
        if (class_exists(ClassLoader::class, false)) {
            foreach (ClassLoader::getRegisteredLoaders() as $directory => $loader) {
                if (false === $loader->findFile(self::class)) {
                    continue;
                }

                $directory = str_replace('\\', '/', $directory);
                $canonical = str_starts_with($directory, 'phar://') ? false : realpath($directory);

                return new VendorDir(false === $canonical ? $directory : str_replace('\\', '/', $canonical));
            }
        }

        throw new MetadataException('No registered Composer class loader serves super-kernel/metadata.');
    }
}
