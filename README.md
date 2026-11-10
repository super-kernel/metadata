<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Metadata

`super-kernel/metadata` restores the package metadata that `skernel build` generates for a SuperKernel project: the root and installed packages, their environment requirements, and their class maps. It reads only generated files, so an application starts without reading Composer files or scanning sources, also after the project moves and inside a Phar.

## Installation

```bash
composer require super-kernel/metadata
```

The package needs PHP 8.4.27 or later in the 8.4 series and a project installed with Composer, whose `vendor/autoload.php` loads it. The metadata it restores exists only after `skernel build` has run in the project.

## What it provides

| Declaration                                    | Purpose                                                                                                         |
|------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| `Provider\VendorDirProvider`                   | Supplies `SuperKernel\Contract\VendorDirInterface`: the vendor directory of the Composer class loader that loads this package. |
| `Provider\BasePathProvider`                    | Supplies `SuperKernel\Contract\BasePathInterface`: the project directory recorded by the build, resolved against the vendor directory. |
| `Provider\PackageManagerProvider`              | Supplies `SuperKernel\Contract\PackageManagerInterface` restored from the generated records.                   |
| `Exception\BuildRequiredException`             | Raised when generated metadata is missing, invalid, or out of date; its message ends with ``Run `skernel build` to generate the project metadata.`` |
| `Build\MetadataPublisher`                      | Writes the metadata; SKERNEL calls it inside the project's own process during `skernel build`.                  |
| `Constants\Protocol::VERSION`                  | The build protocol this version reads and writes; SKERNEL builds only a project with the protocol it supports.  |

The three providers carry the `FactoryProvider` Attribute, so a SuperKernel container resolves the shared interfaces through them. Restored packages implement `SuperKernel\Contract\PackageMetadataInterface` and also expose `composerType()` and `environmentRequirements()`.

It does not read `composer.json` or `composer.lock` contents, scan class files, register an autoloader, or include Composer `files` autoloads; Composer's `vendor/autoload.php` keeps doing that.

## Example

```php
<?php

declare(strict_types=1);

use SuperKernel\Metadata\Provider\BasePathProvider;
use SuperKernel\Metadata\Provider\PackageManagerProvider;
use SuperKernel\Metadata\Provider\VendorDirProvider;

require __DIR__ . '/vendor/autoload.php';

$vendorDir = new VendorDirProvider()();
$basePath = new BasePathProvider()($vendorDir);
$packages = new PackageManagerProvider()($vendorDir, $basePath);

foreach ($packages->all() as $package) {
    echo $package->name(), ' ', $package->version() ?? '(root)', PHP_EOL;
}

echo $packages->packageForClass(App\Kernel::class)?->name() ?? 'not discovered', PHP_EOL;
```

## Generated files

`skernel build` writes these files below the vendor directory. Ship them, and the project's `composer.lock`, with the application; `skernel package` does.

| File                  | Content                                                                                         |
|-----------------------|-------------------------------------------------------------------------------------------------|
| `.cache/build.json`   | The build stamp: build protocol, SKERNEL version, SHA-256 of `composer.lock`, and the project directory relative to the vendor directory. |
| `.cache/metadata.ser` | Every package with its class map, stored relative to the project and vendor directories.        |

A build stamp looks like this:

```json
{
    "protocol": 1,
    "skernel": "1.0.0-alpha.2",
    "lock": "3f9c5d0e6a1b2c4d8e7f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5",
    "base-path": ".."
}
```

## Observable behavior

- `PackageManagerProvider` raises `BuildRequiredException` when `build.json` or `metadata.ser` is missing or corrupted, when the stamp was written under another build protocol, when the project has no readable `composer.lock`, or when `composer.lock` changed since the build. Run `skernel build` again after `composer install`, `composer update`, or any change to autoload rules or annotated classes.
- `BasePathProvider` raises `BuildRequiredException` when the stamp is missing, invalid, or of another protocol.
- `VendorDirProvider` raises `Exception\MetadataException` when no registered Composer class loader loads this package.
- Switching between `composer install` and `composer install --no-dev` without changing `composer.lock` is not detected; rebuild after switching.
- Restoration only reads. Every provider call returns a fresh value; a container keeps one instance per interface.
- When the vendor directory lies inside the project, the project and its vendor directory can move together and the metadata stays valid; inside a Phar, paths start with `phar://`. A vendor directory outside the project records the project directory as an absolute path.

## License

Released under the MIT License. See [LICENSE](LICENSE).
