<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Metadata

`super-kernel/metadata` 恢复 `skernel build` 为项目生成的包元数据：根包和已安装的包、它们的环境要求以及它们的类映射，使应用启动时无需读取 Composer 文件或扫描源码。

## 安装

```bash
composer require super-kernel/metadata
```

本包需要一个用 Composer 安装、由其 `vendor/autoload.php` 加载本包的项目。它恢复的元数据只有在项目中运行过 `skernel build` 之后才存在。

## 提供的能力

- 加载本包的 Composer 类加载器所对应的依赖目录（vendor 目录），在项目移动之后以及在 Phar 中同样适用。
- 构建时记录的项目目录。
- 根包和已安装的包，以及它们的版本、引用、Composer 类型和环境要求。
- 拥有某个类的包，以及每个包的类映射。
- 检测缺失、损坏或比项目的 `composer.lock` 更旧的元数据。
- `skernel build` 调用来写出元数据的发布器。

它不会：

- 读取 `composer.json` 或 `composer.lock` 的内容，或扫描类文件；
- 注册自动加载器或引入 Composer 的 `files` 自动加载文件，这些仍由 `vendor/autoload.php` 完成；
- 重新构建过期的元数据，这由 `skernel build` 负责。

## 快速开始

项目中的任何类都可以用来查找它所属的包。将一个类保存为 `src/Billing/Invoice.php`：

```php
<?php

declare(strict_types=1);

namespace Src\Billing;

final class Invoice
{
}
```

恢复元数据，并查询哪个包拥有哪个类。将脚本保存为 `bin/packages.php`：

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

构建项目并运行脚本：

```bash
skernel build
php bin/packages.php
```

它会打印：

```text
super-kernel/metadata
project
bool(true)
bool(true)
```

## 公开接口

| 名称 | 类别 | 用途 |
| --- | --- | --- |
| `SuperKernel\Metadata\Provider\VendorDirProvider` | 类 | 提供 `VendorDirInterface`：加载本包的 Composer 类加载器所对应的依赖目录。 |
| `SuperKernel\Contract\VendorDirInterface` | 接口 | 依赖目录；转换为字符串即得到路径。 |
| `SuperKernel\Metadata\Provider\BasePathProvider` | 类 | 提供 `BasePathInterface`：构建时记录的项目目录。 |
| `SuperKernel\Contract\BasePathInterface` | 接口 | 项目目录；转换为字符串即得到路径。 |
| `SuperKernel\Metadata\Provider\PackageManagerProvider` | 类 | 提供从生成的元数据恢复的 `PackageManagerInterface`。 |
| `SuperKernel\Contract\PackageManagerInterface` | 接口 | 列出所有包，并按名称、Composer 类型或其中的某个类查找包。 |
| `SuperKernel\Contract\PackageMetadataInterface` | 接口 | 一个包：名称、版本、引用和所在路径。 |
| `SuperKernel\Contract\EnvironmentRequirementInterface` | 接口 | 对某个 Composer 平台包（例如 PHP 或扩展）的一项要求，以及声明它的包。 |
| `SuperKernel\Metadata\Build\MetadataPublisher` | 类 | 写出元数据；`skernel build` 在项目自己的进程中调用它。 |
| `SuperKernel\Metadata\Constants\Protocol` | 类 | `VERSION` 是本版本读写的构建协议。 |

## 需要了解的行为

- 在 `composer install` 或 `composer update` 之后，以及修改自动加载规则或带注解的类之后，请运行 `skernel build`，或者用会先构建的 `skernel run` 启动。在 `composer.lock` 不变的情况下于 `composer install` 和 `composer install --no-dev` 之间切换不会被检测到；切换之后请重新构建。
- 当元数据缺失或无效、在另一个构建协议下写出，或者与当前的 `composer.lock` 不一致时，`PackageManagerProvider` 和 `BasePathProvider` 抛出 `SuperKernel\Metadata\Exception\BuildRequiredException`。其消息说明问题所在，并以 ``Run `skernel build` to generate the project metadata.`` 结尾。
- 当没有任何 Composer 类加载器加载本包时，`VendorDirProvider` 抛出 `SuperKernel\Metadata\Exception\MetadataException`，消息为 `No registered Composer class loader serves super-kernel/metadata.`。
- 每次调用提供者都返回一个新值；SuperKernel 容器为每个接口保留一个实例。
- 依赖目录位于项目之内时，项目可以连同其依赖目录一起移动，元数据依然有效。

## 文档

开发者文档用完整示例介绍每一种查找、生成的文件及其分发方式、元数据的发布，以及各种错误及其消息：[SuperKernel Metadata](https://github.com/super-kernel/.github/blob/main/docs/components/metadata.zh-CN.md)。

## 许可证

基于 MIT 许可证发布，详见 [LICENSE](LICENSE)。
