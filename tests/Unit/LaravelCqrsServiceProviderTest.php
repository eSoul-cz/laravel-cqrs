<?php

declare(strict_types=1);

namespace Tests\Unit;

use Esoul\Cqrs\Contracts\CommandBusInterface;
use Esoul\Cqrs\Contracts\QueryBusInterface;
use Esoul\LaravelCqrs\LaravelCqrsServiceProvider;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Stubs\CQRS\Example\TestCommand;
use Tests\Stubs\CQRS\Example\TestQuery;
use Tests\Stubs\CQRS\TestingCommand;
use Tests\Stubs\CQRS\TestingCommandHandler;
use Tests\Stubs\CQRS\TestingQuery;
use Tests\Stubs\CQRS\TestingQueryHandler;
use Tests\Stubs\Invalid\InvalidClass;
use Tests\TestCase;

#[Group('laravel')]
class LaravelCqrsServiceProviderTest extends TestCase
{
    public function test_provider_registers_singleton_buses_and_discovers_handlers(): void
    {
        $app = $this->createApplication($this->discoveryConfig());
        $this->bootPackage($app);

        $commandBus = $app->make(CommandBusInterface::class);
        $queryBus = $app->make(QueryBusInterface::class);

        $this->assertSame($commandBus, $app->make(CommandBusInterface::class));
        $this->assertSame($queryBus, $app->make(QueryBusInterface::class));
        $this->assertSame('Discovered command handled', $commandBus->dispatch(new TestCommand()));
        $this->assertSame('Discovered query handled', $queryBus->execute(new TestQuery()));
    }

    public function test_provider_registers_manual_handlers(): void
    {
        $app = $this->createApplication([
            'discovery' => [
                'enabled' => false,
                'cache_dir' => $this->temporaryCacheDirectory(),
                'paths' => [],
            ],
            'commands' => [
                'register' => [
                    TestingCommand::class => TestingCommandHandler::class,
                ],
            ],
            'queries' => [
                'register' => [
                    TestingQuery::class => TestingQueryHandler::class,
                ],
            ],
        ]);
        $this->bootPackage($app);

        $this->assertSame('Handled!', $app->make(CommandBusInterface::class)->dispatch(new TestingCommand()));
        $this->assertSame('Handled!', $app->make(QueryBusInterface::class)->execute(new TestingQuery()));
    }

    public function test_provider_uses_configured_discovery_cache_directory(): void
    {
        $cacheDirectory = $this->temporaryCacheDirectory();
        $app = $this->createApplication([
            ...$this->discoveryConfig(),
            'discovery' => [
                'enabled' => true,
                'cache_dir' => $cacheDirectory,
                'paths' => [
                    'base' => [
                        'base_namespace' => 'Tests\\Stubs\\CQRS\\Example',
                        'path' => dirname(__DIR__) . '/Stubs/CQRS/Example',
                    ],
                ],
            ],
        ]);
        $this->bootPackage($app);

        $app->make(CommandBusInterface::class);
        $app->make(QueryBusInterface::class);

        $cacheFiles = glob($cacheDirectory . '/discovery_*.php');

        $this->assertIsArray($cacheFiles);
        $this->assertCount(2, $cacheFiles);
    }

    public function test_provider_rejects_invalid_discovery_path_configuration(): void
    {
        $app = $this->createApplication([
            'discovery' => [
                'enabled' => true,
                'cache_dir' => $this->temporaryCacheDirectory(),
                'paths' => [
                    'broken' => [
                        'base_namespace' => 'Tests\\Stubs\\CQRS\\Example',
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid discovery configuration for path 'broken': missing 'base_namespace' or 'path'");

        $app->register(LaravelCqrsServiceProvider::class);
    }

    public function test_provider_rejects_invalid_command_registration(): void
    {
        $app = $this->createApplication([
            'commands' => [
                'register' => [
                    InvalidClass::class => TestingCommandHandler::class,
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Command class '" . InvalidClass::class . "' must implement CommandInterface");

        $app->register(LaravelCqrsServiceProvider::class);
    }

    public function test_provider_rejects_invalid_query_registration(): void
    {
        $app = $this->createApplication([
            'queries' => [
                'register' => [
                    TestingQuery::class => InvalidClass::class,
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Query handler class '" . InvalidClass::class . "' must implement QueryHandlerInterface");

        $app->register(LaravelCqrsServiceProvider::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function discoveryConfig(): array
    {
        return [
            'discovery' => [
                'enabled' => true,
                'cache_dir' => $this->temporaryCacheDirectory(),
                'paths' => [
                    'base' => [
                        'base_namespace' => 'Tests\\Stubs\\CQRS\\Example',
                        'path' => dirname(__DIR__) . '/Stubs/CQRS/Example',
                    ],
                ],
            ],
        ];
    }

    private function temporaryCacheDirectory(): string
    {
        return $this->app instanceof Application
            ? $this->app->storagePath('framework/cache/discovery')
            : sys_get_temp_dir() . '/laravel-cqrs-cache-' . bin2hex(random_bytes(8));
    }
}
