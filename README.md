<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Metadata

`super-kernel/metadata` restores the package metadata that `skernel build` generates for a project: the root and installed packages, their environment requirements, and their class maps, so an application starts without reading Composer files or scanning sources.

## Installation

```bash
composer require super-kernel/metadata
```

The package needs a project installed with Composer, whose `vendor/autoload.php` loads it. The metadata it restores exists only after `skernel build` has run in the project.

## What it provides

- The vendor directory of the Composer class loader that loads the package, also after the project moves and inside a Phar.
- The project directory recorded by the build.
- The root and installed packages with their versions, references, Composer types, and environment requirements.
- The package that owns a class, and the class map of each package.
- Detection of metadata that is missing, damaged, or older than the project's `composer.lock`.
- The publisher that `skernel build` calls to write the metadata.

It does not:

- read the contents of `composer.json` or `composer.lock`, or scan class files;
- register an autoloader or include Composer `files` autoloads; `vendor/autoload.php` keeps doing that;
- rebuild outdated metadata; `skernel build` does.

## Quick start

Any class of the project serves to look up its package. Save one as `src/Billing/Invoice.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Billing;

final class Invoice
{
}
```

Restore the metadata and ask who owns which class. Save the script as `bin/packages.php`:

```php
<?php

declare(strict_types=1);

use Src\Billing\Invoice;
use SuperKernel\Metadata\Provider\BasePathProvider;
use SuperKernel\Metadata\Provider\PackageManagerProvider;
use SuperKernel\Metadata\Provider\VendorDirProvider;

require dirname(__DIR__) . '/vendor/autoload.php';

$vendorDir = new VendorDirProvider()();
$basePath = new BasePathProvider()($vendorDir);
$packages = new PackageManagerProvider()($vendorDir, $basePath);

echo $packages->packageForClass(VendorDirProvider::class)?->name(), PHP_EOL;
echo $packages->root()->composerType(), PHP_EOL;
var_dump($packages->packageForClass(Invoice::class) === $packages->root());
var_dump($packages->root()->locatedPath() === (string) $basePath);
```

Build the project and run the script:

```bash
skernel build
php bin/packages.php
```

It prints:

```text
super-kernel/metadata
project
bool(true)
bool(true)
```

## Public surface

| Name | Kind | Purpose |
| --- | --- | --- |
| `SuperKernel\Metadata\Provider\VendorDirProvider` | Class | Supplies `VendorDirInterface`: the vendor directory of the Composer class loader that loads this package. |
| `SuperKernel\Contract\VendorDirInterface` | Interface | The vendor directory; casting it to a string gives the path. |
| `SuperKernel\Metadata\Provider\BasePathProvider` | Class | Supplies `BasePathInterface`: the project directory recorded by the build. |
| `SuperKernel\Contract\BasePathInterface` | Interface | The project directory; casting it to a string gives the path. |
| `SuperKernel\Metadata\Provider\PackageManagerProvider` | Class | Supplies `PackageManagerInterface`, restored from the generated metadata. |
| `SuperKernel\Contract\PackageManagerInterface` | Interface | Lists the packages and finds a package by name, by Composer type, or by one of its classes. |
| `SuperKernel\Contract\PackageMetadataInterface` | Interface | One package: name, version, reference, and located path. |
| `SuperKernel\Contract\EnvironmentRequirementInterface` | Interface | One requirement on a Composer platform package, such as PHP or an extension, with the package that declares it. |
| `SuperKernel\Metadata\Build\MetadataPublisher` | Class | Writes the metadata; `skernel build` calls it in the project's own process. |
| `SuperKernel\Metadata\Constants\Protocol` | Class | `VERSION` is the build protocol this version reads and writes. |

## Behavior to know

- Run `skernel build` after `composer install` or `composer update` and after changing autoload rules or annotated classes, or start with `skernel run`, which builds first. Switching between `composer install` and `composer install --no-dev` without a change to `composer.lock` is not detected; build again after switching.
- `PackageManagerProvider` and `BasePathProvider` raise `SuperKernel\Metadata\Exception\BuildRequiredException` when the metadata is missing or invalid, was written under another build protocol, or does not match the current `composer.lock`. Its message names the problem and ends with ``Run `skernel build` to generate the project metadata.``
- `VendorDirProvider` raises `SuperKernel\Metadata\Exception\MetadataException` with the message `No registered Composer class loader serves super-kernel/metadata.` when no Composer class loader loads the package.
- Every provider call returns a new value; a SuperKernel container keeps one instance of each.
- A project whose vendor directory lies inside it can move with its vendor directory, and the metadata stays valid.

## Documentation

The developer documentation describes every lookup with complete examples, the generated files and how to ship them, publishing metadata, and the errors and their messages: [SuperKernel Metadata](https://github.com/super-kernel/.github/blob/main/docs/components/metadata.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).
