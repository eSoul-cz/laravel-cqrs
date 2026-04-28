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
        /** @var array{enabled?:bool, cache_dir?:string, paths?:array<string, array{base_namespace?:string, path?:string}>|null}|null $discoveryConfig */
        $discoveryConfig = config('cqrs.discovery');

        $discover = false;
        $cacheDirectory = storage_path('framework/cache/cqrs/discovery');
        $discoverPaths = [];
        if (is_array($discoveryConfig)) {
            $discover = $discoveryConfig['enabled'] ?? true;
            $cacheDirectory = $discoveryConfig['cache_dir'] ?? $cacheDirectory;

            // Validate and prepare discovery paths
            if (array_key_exists('paths', $discoveryConfig) && is_array($discoveryConfig['paths'])) {
                foreach ($discoveryConfig['paths'] as $key => $config) {
                    if (isset($config['base_namespace'], $config['path'])) {
                        $discoverPaths[] = [
                            'base_namespace' => rtrim($config['base_namespace'], '\\'),
                            'path' => rtrim($config['path'], DIRECTORY_SEPARATOR),
                        ];
                    } else {
                        throw new InvalidArgumentException("Invalid discovery configuration for path '{$key}': missing 'base_namespace' or 'path'");
                    }
                }
            }
        }

        // Prepare manual registrations for command and query handlers
        $commandsRegister = config('cqrs.commands.register', []);
        if (!is_array($commandsRegister)) {
            throw new InvalidArgumentException('Invalid configuration: commands.register must be an array');
        }

        // Validate the array<class-string<CommandInterface>, class-string<CommandHandlerInterface>> structure
        foreach ($commandsRegister as $commandClass => $handlerClass) {
            if (!is_string($commandClass) || !is_string($handlerClass)) {
                throw new InvalidArgumentException('Invalid command registration: both command and handler must be class strings');
            }
            if (!class_exists($commandClass)) {
                throw new InvalidArgumentException("Command class '{$commandClass}' does not exist");
            }
            if (!class_exists($handlerClass)) {
                throw new InvalidArgumentException("Command handler class '{$handlerClass}' does not exist");
            }
            if (!is_subclass_of($commandClass, CommandInterface::class)) {
                throw new InvalidArgumentException("Command class '{$commandClass}' must implement CommandInterface");
            }
            if (!is_subclass_of($handlerClass, CommandHandlerInterface::class)) {
                throw new InvalidArgumentException("Command handler class '{$handlerClass}' must implement CommandHandlerInterface");
            }
        }

        // Set now validated shape for phpstan
        /** @var array<class-string<CommandInterface<mixed>>, class-string<CommandHandlerInterface>> $commandsRegister */
        $queriesRegister = config('cqrs.queries.register', []);
        if (!is_array($queriesRegister)) {
            throw new InvalidArgumentException('Invalid configuration: queries.register must be an array');
        }

        // Validate the array<class-string<QueryInterface>, class-string<QueryHandlerInterface>> structure
        foreach ($queriesRegister as $queryClass => $handlerClass) {
            if (!is_string($queryClass) || !is_string($handlerClass)) {
                throw new InvalidArgumentException('Invalid query registration: both query and handler must be class strings');
            }
            if (!class_exists($queryClass)) {
                throw new InvalidArgumentException("Query class '{$queryClass}' does not exist");
            }
            if (!class_exists($handlerClass)) {
                throw new InvalidArgumentException("Query handler class '{$handlerClass}' does not exist");
            }
            if (!is_subclass_of($queryClass, QueryInterface::class)) {
                throw new InvalidArgumentException("Query class '{$queryClass}' must implement QueryInterface");
            }
            if (!is_subclass_of($handlerClass, QueryHandlerInterface::class)) {
                throw new InvalidArgumentException("Query handler class '{$handlerClass}' must implement QueryHandlerInterface");
            }
        }

        // Set now validated shape for phpstan
        /** @var array<class-string<QueryInterface<mixed>>, class-string<QueryHandlerInterface>> $queriesRegister */
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
}
