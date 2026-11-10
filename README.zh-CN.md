<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Metadata

`super-kernel/metadata` 还原 `skernel build` 为 SuperKernel 项目生成的包元数据：根包与已安装的包、它们的环境要求以及类映射。它只读取生成的文件，因此应用启动时既不读取 Composer 文件也不扫描源码，项目移动后以及在 Phar 内同样适用。

## 安装

```bash
composer require super-kernel/metadata
```

本包需要 8.4 系列中 8.4.27 及以上版本的 PHP，并要求项目通过 Composer 安装、由其 `vendor/autoload.php` 加载本包。它还原的元数据只有在项目中运行过 `skernel build` 后才存在。

## 提供的内容

| 声明                                           | 用途                                                                                                            |
|------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| `Provider\VendorDirProvider`                   | 提供 `SuperKernel\Contract\VendorDirInterface`：加载本包的 Composer 类加载器所属的 vendor 目录。                 |
| `Provider\BasePathProvider`                    | 提供 `SuperKernel\Contract\BasePathInterface`：构建时记录的项目目录，相对 vendor 目录解析。                      |
| `Provider\PackageManagerProvider`              | 提供从生成记录还原的 `SuperKernel\Contract\PackageManagerInterface`。                                            |
| `Exception\BuildRequiredException`             | 生成的元数据缺失、无效或过期时抛出；其消息以 ``Run `skernel build` to generate the project metadata.`` 结尾。      |
| `Build\MetadataPublisher`                      | 写入元数据；SKERNEL 在 `skernel build` 期间于项目自身的进程中调用它。                                            |
| `Constants\Protocol::VERSION`                  | 本版本读写的构建协议；SKERNEL 只构建协议与其支持版本一致的项目。                                                 |

三个提供者都带有 `FactoryProvider` Attribute，因此 SuperKernel 容器通过它们解析这些共享接口。还原出的包实现 `SuperKernel\Contract\PackageMetadataInterface`，并额外提供 `composerType()` 和 `environmentRequirements()`。

它不读取 `composer.json` 或 `composer.lock` 的内容，不扫描类文件，不注册自动加载器，也不包含 Composer 的 `files` 自动加载文件；这些仍由 Composer 的 `vendor/autoload.php` 负责。

## 示例

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

## 生成的文件

`skernel build` 在 vendor 目录下写入以下文件。请将它们与项目的 `composer.lock` 一同随应用发布；`skernel package` 会这样做。

| 文件                  | 内容                                                                                            |
|-----------------------|-------------------------------------------------------------------------------------------------|
| `.cache/build.json`   | 构建戳：构建协议、SKERNEL 版本、`composer.lock` 的 SHA-256，以及项目目录相对 vendor 目录的路径。   |
| `.cache/metadata.ser` | 每个包及其类映射，路径相对项目目录和 vendor 目录存储。                                           |

构建戳示例：

```json
{
    "protocol": 1,
    "skernel": "1.0.0-alpha.2",
    "lock": "3f9c5d0e6a1b2c4d8e7f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5",
    "base-path": ".."
}
```

## 可观察行为

- 当 `build.json` 或 `metadata.ser` 缺失或损坏、构建戳由其他构建协议写入、项目没有可读的 `composer.lock`，或 `composer.lock` 在构建后发生变化时，`PackageManagerProvider` 抛出 `BuildRequiredException`。在 `composer install`、`composer update` 之后，或自动加载规则、带注解的类发生任何变化之后，请重新运行 `skernel build`。
- 构建戳缺失、无效或属于其他协议时，`BasePathProvider` 抛出 `BuildRequiredException`。
- 没有任何已注册的 Composer 类加载器加载本包时，`VendorDirProvider` 抛出 `Exception\MetadataException`。
- 在不改变 `composer.lock` 的情况下于 `composer install` 与 `composer install --no-dev` 之间切换不会被检测到；切换后请重新构建。
- 还原过程只读。每次调用提供者都返回新的值；容器为每个接口保留一个实例。
- 当 vendor 目录位于项目内部时，项目与其 vendor 目录可以一起移动而元数据保持有效；在 Phar 内，路径以 `phar://` 开头。vendor 目录位于项目之外时，项目目录以绝对路径记录。

## 许可证

基于 MIT 许可证发布。详见 [LICENSE](LICENSE)。
