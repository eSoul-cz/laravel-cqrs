<?php

declare(strict_types=1);

namespace Esoul\LaravelCqrs;

use Esoul\Cqrs\Bus\CommandBus;
use Esoul\Cqrs\Bus\QueryBus;
use Esoul\Cqrs\Contracts\CommandBusInterface;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use Esoul\Cqrs\Contracts\QueryBusInterface;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use Esoul\Cqrs\Helpers\Discovery;
use Esoul\LaravelCqrs\Console\Commands\CommandHandlerMakeCommand;
use Esoul\LaravelCqrs\Console\Commands\CommandMakeCommand;
use Esoul\LaravelCqrs\Console\Commands\QueryHandlerMakeCommand;
use Esoul\LaravelCqrs\Console\Commands\QueryMakeCommand;
use Esoul\LaravelCqrs\Factory\LaravelInjectionHandlerFactory;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelCqrsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-cqrs')
            ->hasConfigFile('cqrs')
            ->hasCommands(
                CommandMakeCommand::class,
                CommandHandlerMakeCommand::class,
                QueryMakeCommand::class,
                QueryHandlerMakeCommand::class,
            )
            ->hasInstallCommand(static fn (InstallCommand $command) => $command
                ->publishConfigFile()
            );
    }

    public function packageRegistered(): void
    {
        [
            'discover' => $discover,
            'cacheDirectory' => $cacheDirectory,
            'paths' => $discoverPaths,
        ] = $this->getDiscoveryConfig();

        // Prepare manual registrations for command and query handlers
        $commandsRegister = $this->loadManualRegistrations('commands');
        $queriesRegister = $this->loadManualRegistrations('queries');

        if (!empty($cacheDirectory)) {
            Discovery::setCacheDirectory($cacheDirectory);
        }

        // Register the command bus and query bus as singletons in the Laravel service container
        $this->app->singleton(CommandBusInterface::class, function (Application $app) use ($discover, $discoverPaths, $commandsRegister): CommandBusInterface {
            $commandBus = new CommandBus(new LaravelInjectionHandlerFactory());

            // Register command handlers
            if ($discover) {
                foreach ($discoverPaths as ['base_namespace' => $baseNamespace, 'path' => $path]) {
                    foreach ($commandBus->discoverHandlers($path, $baseNamespace) as $handler) {
                        $app->singleton($handler, $handler);
                    }
                }
            }

            // Manual registrations
            foreach ($commandsRegister as $commandClass => $handlerClass) {
                $commandBus->registerHandler($commandClass, $handlerClass);
                $app->singleton($handlerClass, $handlerClass);
            }

            return $commandBus;
        });

        $this->app->singleton(QueryBusInterface::class, function (Application $app) use ($discover, $discoverPaths, $queriesRegister): QueryBusInterface {
            $queryBus = new QueryBus(new LaravelInjectionHandlerFactory());

            // Register query handlers
            if ($discover) {
                foreach ($discoverPaths as ['base_namespace' => $baseNamespace, 'path' => $path]) {
                    foreach ($queryBus->discoverHandlers($path, $baseNamespace) as $handler) {
                        $app->singleton($handler, $handler);
                    }
                }
            }

            // Manual registrations
            foreach ($queriesRegister as $queryClass => $handlerClass) {
                $queryBus->registerHandler($queryClass, $handlerClass);
                $app->singleton($handlerClass, $handlerClass);
            }

            return $queryBus;
        });
    }

    /**
     * @param  'commands'|'queries'  $type
     * @return ($type is 'commands' ? array<class-string<CommandInterface<mixed>>, class-string<CommandHandlerInterface>> : array<class-string<QueryInterface<mixed>>, class-string<QueryHandlerInterface>>)
     */
    private function loadManualRegistrations(string $type): array
    {
        if (!in_array($type, ['commands', 'queries'], true)) {
            throw new InvalidArgumentException("Invalid type '{$type}' for manual registrations. Expected 'commands' or 'queries'.");
        }

        $interface = $type === 'commands' ? CommandInterface::class : QueryInterface::class;
        $handlerInterface = $type === 'commands' ? CommandHandlerInterface::class : QueryHandlerInterface::class;

        $config = config('cqrs.' . $type . '.register', []);
        if (!is_array($config)) {
            throw new InvalidArgumentException('Invalid configuration: queries.register must be an array');
        }

        // Validate the array<class-string<QueryInterface>, class-string<QueryHandlerInterface>> structure
        foreach ($config as $queryClass => $handlerClass) {
            if (!is_string($queryClass) || !is_string($handlerClass)) {
                throw new InvalidArgumentException('Invalid query registration: both query and handler must be class strings');
            }
            if (!class_exists($queryClass)) {
                throw new InvalidArgumentException("Class '{$queryClass}' does not exist");
            }
            if (!class_exists($handlerClass)) {
                throw new InvalidArgumentException("Handler class '{$handlerClass}' does not exist");
            }
            if (!is_subclass_of($queryClass, $interface)) {
                throw new InvalidArgumentException("Class '{$queryClass}' must implement '{$interface}'");
            }
            if (!is_subclass_of($handlerClass, $handlerInterface)) {
                throw new InvalidArgumentException("Handler class '{$handlerClass}' must implement '{$handlerInterface}'");
            }
        }

        // Manually validated shape for phpstan
        /** @phpstan-ignore return.type */
        return $config;
    }

    /**
     * @return array{discover:bool, cacheDirectory:string, paths:array{base_namespace:non-empty-string, path:non-empty-string}[]}
     */
    private function getDiscoveryConfig(): array
    {
        /** @var array{enabled?:bool, cache_dir?:string, paths?:array<string, array{base_namespace?:string, path?:string}>|null}|null $discoveryConfig */
        $discoveryConfig = config('cqrs.discovery');

        $discover = false;
        $cacheDirectory = storage_path('framework/cache/cqrs/discovery');
        /** @var array{base_namespace:non-empty-string, path:non-empty-string}[] $discoverPaths */
        $discoverPaths = [];
        if (is_array($discoveryConfig)) {
            $discover = $discoveryConfig['enabled'] ?? true;
            $cacheDirectory = $discoveryConfig['cache_dir'] ?? $cacheDirectory;

            // Validate and prepare discovery paths
            if (array_key_exists('paths', $discoveryConfig) && is_array($discoveryConfig['paths'])) {
                foreach ($discoveryConfig['paths'] as $key => $config) {
                    if (isset($config['base_namespace'], $config['path'])) {
                        $namespace = rtrim($config['base_namespace'], '\\');
                        $path = rtrim($config['path'], DIRECTORY_SEPARATOR);
                        if (!empty($namespace) && !empty($path)) {
                            $discoverPaths[] = [
                                'base_namespace' => $namespace,
                                'path' => $path,
                            ];
                        }
                    } else {
                        throw new InvalidArgumentException("Invalid discovery configuration for path '{$key}': missing 'base_namespace' or 'path'");
                    }
                }
            }
        }

        return [
            'discover' => $discover,
            'cacheDirectory' => $cacheDirectory,
            'paths' => $discoverPaths,
        ];
    }
}
