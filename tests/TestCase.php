<?php

declare(strict_types=1);

namespace Tests;

use Esoul\Cqrs\Helpers\Discovery;
use Esoul\LaravelCqrs\LaravelCqrsServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Console\Application as ArtisanApplication;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class TestCase extends BaseTestCase
{
    protected ?Application $app = null;

    /** @var list<string> */
    private array $basePaths = [];

    /** @var list<callable(string): void> */
    private array $autoloaders = [];

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->autoloaders = [];

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Discovery::setCacheDirectory(null);
        ArtisanApplication::forgetBootstrappers();

        foreach ($this->basePaths as $basePath) {
            $this->deleteDirectory($basePath);
        }

        $this->basePaths = [];
        $this->app = null;

        parent::tearDown();
    }

    protected function createApplication(array $configOverrides = []): Application
    {
        $basePath = sys_get_temp_dir() . '/laravel-cqrs-tests-' . bin2hex(random_bytes(8));

        foreach ([
            $basePath . '/app',
            $basePath . '/bootstrap',
            $basePath . '/config',
            $basePath . '/storage/framework/cache',
        ] as $directory) {
            mkdir($directory, 0777, true);
        }

        $app = new Application($basePath);
        $app->useAppPath($basePath . '/app');
        $app->useConfigPath($basePath . '/config');

        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $app->instance('config', new Repository([
            'cqrs' => array_replace_recursive(
                require dirname(__DIR__) . '/config/cqrs.php',
                $configOverrides
            ),
        ]));

        $app->singleton(Filesystem::class, Filesystem::class);
        $app->alias(Filesystem::class, 'files');
        $app->singleton(Dispatcher::class, fn (Application $app): Dispatcher => new Dispatcher($app));
        $app->alias(Dispatcher::class, 'events');

        $this->basePaths[] = $basePath;
        $this->app = $app;

        return $app;
    }

    protected function bootPackage(Application $app): void
    {
        $app->register(LaravelCqrsServiceProvider::class);
        $app->boot();
    }

    protected function makeArtisan(Application $app): ArtisanApplication
    {
        return new ArtisanApplication($app, $app->make(Dispatcher::class), 'testing');
    }

    /**
     * @param  array<string, string>  $prefixesToPaths
     */
    protected function registerPsr4Autoloader(array $prefixesToPaths): void
    {
        $autoloader = static function (string $class) use ($prefixesToPaths): void {
            foreach ($prefixesToPaths as $prefix => $path) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }

                $relativeClass = substr($class, strlen($prefix));
                $file = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                if (is_file($file)) {
                    require_once $file;
                }

                return;
            }
        };

        spl_autoload_register($autoloader);
        $this->autoloaders[] = $autoloader;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
