<?php

declare(strict_types=1);

namespace SuperKernel\Metadata\Tests\Integration;

use InvalidArgumentException;
use RuntimeException;
use SuperKernel\Metadata\Build\MetadataPublisher;
use SuperKernel\Metadata\Constants\Protocol;
use SuperKernel\Metadata\Exception\BuildRequiredException;
use SuperKernel\Metadata\Exception\MetadataException;
use SuperKernel\Metadata\Manager\PackageManager;
use SuperKernel\Metadata\Model\BuildStamp;
use SuperKernel\Metadata\Model\EnvironmentRequirement;
use SuperKernel\Metadata\Model\Package;
use SuperKernel\Metadata\Path\BasePath;
use SuperKernel\Metadata\Path\VendorDir;
use SuperKernel\Metadata\Provider\BasePathProvider;
use SuperKernel\Metadata\Provider\PackageManagerProvider;
use SuperKernel\Metadata\Provider\VendorDirProvider;
use SuperKernel\Metadata\Tests\Support\TestCase;
use Throwable;

use function array_map;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function hash_file;
use function implode;
use function is_file;
use function json_decode;
use function realpath;
use function rename;
use function serialize;
use function sprintf;
use function strlen;
use function substr;
use function umask;
use function unlink;
use function unserialize;
use function var_export;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

final class MetadataLifecycleTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPublishedMetadataRestoresThroughTheProviders(): void
    {
        $this->withTemporaryDirectory(function (string $temporary): void {
            $project = $this->createProject(realpath($temporary) . '/project', 'vendor');
            $vendorDir = new VendorDir($project . '/vendor');

            $this->publish($project, 'vendor');

            $stamp = json_decode(
                (string) file_get_contents($project . '/vendor/.cache/build.json'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            self::assertSame([
                'protocol'  => Protocol::VERSION,
                'skernel'   => '1.0.0-alpha.2',
                'lock'      => hash_file('sha256', $project . '/composer.lock'),
                'base-path' => '..',
            ], $stamp);

            foreach ([
                'build.json',
                'metadata.ser',
            ] as $artifact) {
                self::assertSame(0o666 & ~umask(), fileperms($project . '/vendor/.cache/' . $artifact) & 0o777);
            }

            $basePath = new BasePathProvider()($vendorDir);
            self::assertSame($project, (string) $basePath);

            $manager = new PackageManagerProvider()($vendorDir, $basePath);
            self::assertSame([
                'fixture/application',
                'fixture/library',
                'fixture/meta',
            ], array_map(static fn($package): string => $package->name(), $manager->all()));
            self::assertSame($project, $manager->root()->locatedPath());
            self::assertSame('project', $manager->root()->composerType());
            self::assertSame($project . '/vendor/fixture/library', $manager->get('fixture/library')?->locatedPath());
            self::assertSame('abc123', $manager->get('fixture/library')?->reference());
            self::assertNull($manager->get('fixture/meta')?->locatedPath());
            self::assertSame([
                'App\\Kernel' => $project . '/src/Kernel.php',
            ], $manager->classes('fixture/application'));
            self::assertSame([
                'Fixture\\Library\\Outside' => '/opt/shared/Outside.php',
                'Fixture\\Library\\Service' => $project . '/vendor/fixture/library/src/Service.php',
            ], $manager->classes('fixture/library'));
            self::assertSame('ext-json', $manager->environmentRequirements()[0]->name());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRestorationFollowsAMovedProject(): void
    {
        $this->withTemporaryDirectory(function (string $temporary): void {
            $original = $this->createProject(realpath($temporary) . '/original', 'lib/vendor');
            $this->publish($original, 'lib/vendor');

            self::assertStringContainsString(
                '"base-path": "../.."',
                (string) file_get_contents($original . '/lib/vendor/.cache/build.json'),
            );

            $moved = realpath($temporary) . '/moved';
            rename($original, $moved);
            $vendorDir = new VendorDir($moved . '/lib/vendor');
            $basePath = new BasePathProvider()($vendorDir);
            $manager = new PackageManagerProvider()($vendorDir, $basePath);

            self::assertSame($moved, (string) $basePath);
            self::assertSame($moved, $manager->root()->locatedPath());
            self::assertSame($moved . '/src/Kernel.php', $manager->allClasses()['App\\Kernel']);
            self::assertSame(
                $moved . '/lib/vendor/fixture/library/src/Service.php',
                $manager->allClasses()['Fixture\\Library\\Service'],
            );
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testVendorDirectoryOutsideTheProjectIsRecordedAbsolutely(): void
    {
        $this->withTemporaryDirectory(function (string $temporary): void {
            $base = realpath($temporary);
            $project = $this->createProject($base . '/project', '../shared-vendor');
            $this->publish($project, '../shared-vendor');

            $vendorDir = new VendorDir($base . '/shared-vendor');
            $stamp = BuildStamp::decode((string) file_get_contents($base . '/shared-vendor/.cache/build.json'));

            self::assertSame($project, $stamp->basePath());
            self::assertSame($project, (string) new BasePathProvider()($vendorDir));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRestorationReadsFromAPhar(): void
    {
        $this->withTemporaryDirectory(function (string $temporary): void {
            $base = realpath($temporary);
            $project = $this->createProject($base . '/project', 'vendor');
            $this->publish($project, 'vendor');

            $phar = $base . '/app.phar';
            $script = sprintf(
                '$phar = new Phar(%s); $phar->buildFromDirectory(%s); $phar->setStub("<?php __HALT_COMPILER();");',
                var_export($phar, true),
                var_export($project, true),
            );
            $output = [];
            exec(implode(' ', [
                escapeshellarg(PHP_BINARY),
                '-d phar.readonly=0',
                '-r',
                escapeshellarg($script),
                '2>&1',
            ]), $output, $status);
            self::assertSame(0, $status, implode("\n", $output));

            $vendorDir = new VendorDir('phar://' . $phar . '/vendor');
            $basePath = new BasePathProvider()($vendorDir);
            $manager = new PackageManagerProvider()($vendorDir, $basePath);

            self::assertSame('phar://' . $phar, (string) $basePath);
            self::assertSame('phar://' . $phar . '/src/Kernel.php', $manager->allClasses()['App\\Kernel']);
            self::assertSame(
                'phar://' . $phar . '/vendor/fixture/library',
                $manager->get('fixture/library')?->locatedPath(),
            );
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMissingOrStaleArtifactsNameTheBuildCommand(): void
    {
        $this->withTemporaryDirectory(function (string $temporary): void {
            $project = $this->createProject(realpath($temporary) . '/project', 'vendor');
            $vendorDir = new VendorDir($project . '/vendor');
            $basePath = new BasePath($project);
            $restore = static fn() => new PackageManagerProvider()($vendorDir, $basePath);

            $this->assertThrows(BuildRequiredException::class, $restore, 'build stamp');
            $this->assertThrows(
                BuildRequiredException::class,
                static fn() => new BasePathProvider()($vendorDir),
                'Run `skernel build` to generate the project metadata.',
            );

            $this->publish($project, 'vendor');
            self::assertNotNull($restore());

            file_put_contents($project . '/composer.lock', "{\"packages\": []}\n");
            $this->assertThrows(BuildRequiredException::class, $restore, 'does not match the current composer.lock');

            $this->publish($project, 'vendor');
            $stampPath = $project . '/vendor/.cache/build.json';
            $stamp = BuildStamp::decode((string) file_get_contents($stampPath));
            file_put_contents($stampPath, new BuildStamp(
                Protocol::VERSION + 1,
                'future',
                $stamp->lock(),
                $stamp->basePath(),
            )->encode());
            $this->assertThrows(BuildRequiredException::class, $restore, 'under build protocol 2');

            $this->publish($project, 'vendor');
            $recordsPath = $project . '/vendor/.cache/metadata.ser';
            $records = (string) file_get_contents($recordsPath);
            file_put_contents($recordsPath, substr($records, 0, strlen($records) - 2));
            $this->assertThrows(BuildRequiredException::class, $restore, 'Run `skernel build`');

            $record = unserialize($records, [
                'allowed_classes' => [
                    Package::class,
                    EnvironmentRequirement::class,
                ],
            ]);
            $record['classes']['fixture/application']['App\\Injected'] = 'src/Injected.php';
            file_put_contents($recordsPath, serialize($record));
            $this->assertThrows(BuildRequiredException::class, $restore, 'package records are invalid');

            unlink($project . '/composer.lock');
            $this->assertThrows(BuildRequiredException::class, $restore, 'no readable composer.lock');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testInvalidateRemovesTheStampBeforeANewBuildCommits(): void
    {
        $this->withTemporaryDirectory(function (string $temporary): void {
            $project = $this->createProject(realpath($temporary) . '/project', 'vendor');
            $publisher = new MetadataPublisher(new VendorDir($project . '/vendor'), new BasePath($project));

            $this->publish($project, 'vendor');
            $publisher->invalidate();

            self::assertFalse(is_file($project . '/vendor/.cache/build.json'));
            self::assertFalse(is_file($project . '/vendor/.cache/metadata.ser'));
            $this->assertThrows(
                MetadataException::class,
                static fn() => $publisher->commit('dev'),
                'do not restore',
            );
            self::assertFalse(is_file($project . '/vendor/.cache/build.json'));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPublisherRejectsARootOutsideTheBasePathAndAnEmptyVersion(): void
    {
        $this->withTemporaryDirectory(function (string $temporary): void {
            $project = $this->createProject(realpath($temporary) . '/project', 'vendor');
            $publisher = new MetadataPublisher(new VendorDir($project . '/vendor'), new BasePath($project));
            $manager = new PackageManager(new Package('fixture/application', null, null, 'project', '/elsewhere'), []);

            $this->assertThrows(
                MetadataException::class,
                static fn() => $publisher->publishPackages($manager),
                'located at the base path',
            );
            $this->assertThrows(InvalidArgumentException::class, static fn() => $publisher->commit(''));
        });
    }

    public function testVendorDirectoryComesFromTheLoaderServingThisPackage(): void
    {
        self::assertSame(realpath(dirname(__DIR__, 2) . '/vendor'), (string) new VendorDirProvider()());
    }

    /**
     * Creates a project with a root class, one located dependency, and a lock file.
     *
     * @return string The project directory
     *
     * @throws RuntimeException When a fixture file cannot be written
     */
    private function createProject(string $project, string $vendor): string
    {
        $vendorDirectory = $project . '/' . $vendor;
        $this->writeFile($project . '/composer.lock', "{\"packages\": [{\"name\": \"fixture/library\"}]}\n");
        $this->writeFile($project . '/src/Kernel.php', "<?php\n");
        $this->writeFile($vendorDirectory . '/fixture/library/src/Service.php', "<?php\n");

        return $project;
    }

    /**
     * Runs the generator's publish sequence for the fixture project.
     *
     * @throws Throwable When publication fails
     */
    private function publish(string $project, string $vendor): void
    {
        $vendorDirectory = realpath($project . '/' . $vendor);
        $library = $vendorDirectory . '/fixture/library';
        $manager = new PackageManager(
            new Package('fixture/application', null, null, 'project', $project, [
                new EnvironmentRequirement('php', '>=8.4', 'fixture/application', false),
            ]),
            [
                new Package('fixture/library', '1.0.0', 'abc123', 'library', $library, [
                    new EnvironmentRequirement('ext-json', '*', 'fixture/library', false),
                ]),
                new Package('fixture/meta', '1.0.0', null, 'metapackage', null),
            ],
            [
                'fixture/application' => [
                    'App\\Kernel' => $project . '/src/Kernel.php',
                ],
                'fixture/library' => [
                    'Fixture\\Library\\Service' => $library . '/src/Service.php',
                    'Fixture\\Library\\Outside' => '/opt/shared/Outside.php',
                ],
            ],
        );
        $publisher = new MetadataPublisher(new VendorDir($vendorDirectory), new BasePath($project));

        $publisher->invalidate();
        $publisher->publishPackages($manager);
        $publisher->commit('1.0.0-alpha.2');
    }
}
